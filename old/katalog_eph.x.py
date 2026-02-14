#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# 
#   Copyright (c) 2026 Roman Hujer   http://hujer.net
#
#   This program is free software: you can redistribute it and/or modify
#   the Free Software Foundation, either version 3 of the License, or
#   (at your option) any later version.
#
#   This program is distributed in the hope that it will be useful,ss
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.   
#
#!/usr/bin/env python3
import csv
import json
import os
from datetime import datetime, timedelta, timezone

from skyfield.api import load, wgs84, Star, Angle
from skyfield.almanac import (
    find_discrete,
    dark_twilight_day,
    risings_and_settings,
    meridian_transits,
)

# ---------------------------------------------------------
# KONSTANTY
# ---------------------------------------------------------
LAT = 50.71
LON = 15.18
ALT = 600

MIN_ALT = 5          # minimální výška pro výběr objektu (ve stupních)
SPAN_HOURS = 72      # délka grafu od 00:00 UTC (v hodinách)
STEP_MIN = 10        # krok grafu (v minutách)

JSON_DIR = "/opt/astro_json"


# ---------------------------------------------------------
# NAČTENÍ KATALOGU
# ---------------------------------------------------------
def load_catalog(csv_path):
    objects = []
    with open(csv_path, encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            objects.append({
                "id": row["id"],
                "name": row["name"],
                "type": row["type"],
                "ra_hours": float(row["ra_hours"]),
                "dec_deg": float(row["dec_deg"]),
                "mag": float(row["mag"]) if row["mag"] else None,
                "size_arcmin": row.get("size_arcmin"),
                "constellation": row["constellation"],
            })
    return objects


# ---------------------------------------------------------
# VÝPOČET DNEŠNÍ NAUTICKÉ NOCI
# ---------------------------------------------------------
def compute_nautical_night(ts, eph):
    now = datetime.now(timezone.utc)
    today = now.date()

    # čistý Topos pro almanac funkce
    topos = wgs84.latlon(LAT, LON, ALT)

    dt0 = datetime(today.year, today.month, today.day, 0, 0, 0, tzinfo=timezone.utc)
    dt1 = dt0 + timedelta(days=1, hours=12)

    t0 = ts.from_datetime(dt0)
    t1 = ts.from_datetime(dt1)

    f = dark_twilight_day(eph, topos)
    times, events = find_discrete(t0, t1, f)

    night_start = None
    night_end = None

    for t, e in zip(times, events):
        # 1 = start nautical night, 4 = end nautical night
        if e == 1:
            night_start = t.utc_datetime()
        if e == 4:
            night_end = t.utc_datetime()

    return night_start, night_end


# ---------------------------------------------------------
# GRAF ALT/AZ
# ---------------------------------------------------------
def compute_graph(ts, observer, obj, t0, t1, clip_below_horizon):
    ra = Angle(hours=obj["ra_hours"])
    dec = Angle(degrees=obj["dec_deg"])
    sky_obj = Star(ra=ra, dec=dec)

    times = []
    t = t0
    while t < t1:
        times.append(t)
        t += timedelta(minutes=STEP_MIN)

    graph = []
    for t in times:
        sf_t = ts.from_datetime(t)
        topo = observer.at(sf_t)
        apparent = topo.observe(sky_obj).apparent()
        alt, az, _ = apparent.altaz()

        alt_deg = alt.degrees
        if clip_below_horizon and alt_deg < 0:
            alt_deg = 0

        graph.append({
            "time_utc": t.isoformat().replace("+00:00", "Z"),
            "alt_deg": alt_deg,
            "az_deg": az.degrees,
            "ra_hours_j2000": obj["ra_hours"],
            "dec_deg_j2000": obj["dec_deg"],
            "mag": obj["mag"],
            "constellation": obj["constellation"],
        })

    return graph


# ---------------------------------------------------------
# RISE / TRANSIT / SET
# ---------------------------------------------------------
def compute_events(ts, eph, obj, t0, t1):
    ra = Angle(hours=obj["ra_hours"])
    dec = Angle(degrees=obj["dec_deg"])
    sky_obj = Star(ra=ra, dec=dec)

    # čistý Topos pro almanac funkce
    topos = wgs84.latlon(LAT, LON, ALT)

    # Rise / Set
    f = risings_and_settings(eph, sky_obj, topos)
    t_rs, events_rs = find_discrete(ts.from_datetime(t0), ts.from_datetime(t1), f)

    rise = None
    set_ = None
    for ti, ev in zip(t_rs, events_rs):
        if ev == 1:
            rise = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")
        elif ev == 0:
            set_ = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")

    # Transit
    f_tr = meridian_transits(eph, sky_obj, topos)
    t_tr, events_tr = find_discrete(ts.from_datetime(t0), ts.from_datetime(t1), f_tr)

    transit = None
    for ti, ev in zip(t_tr, events_tr):
        if ev == 1:
            transit = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")

    return rise, transit, set_


# ---------------------------------------------------------
# HLAVNÍ PROGRAM
# ---------------------------------------------------------
def main(csv_path):
    ts = load.timescale()
    eph = load("de421.bsp")
    earth = eph["earth"]
    observer = earth + wgs84.latlon(LAT, LON, ALT)

    catalog = load_catalog(csv_path)

    # --- FÁZE 1: výběr objektů podle dnešní NAUTICKÉ noci ---
    night_start, night_end = compute_nautical_night(ts, eph)
    print(f"Nautická noc: {night_start} → {night_end}")

    if night_start is None or night_end is None:
        print("Nautická noc dnes nenastává – žádné objekty nebudou vybrány.")
        selected = []
    else:
        selected = []
        for obj in catalog:
            # pro výběr používáme skutečnou výšku (neklipujeme pod horizont)
            night_graph = compute_graph(ts, observer, obj, night_start, night_end, clip_below_horizon=False)
            max_alt = max(p["alt_deg"] for p in night_graph)

            if max_alt >= MIN_ALT:
                print(f"Vybrán objekt: {obj['id']} {obj['name']}")
                selected.append(obj)

    print(f"Počet vybraných objektů: {len(selected)}")

    # --- FÁZE 2: graf 00:00 UTC → +SPAN_HOURS h ---
    now = datetime.now(timezone.utc)
    t0 = now.replace(hour=0, minute=0, second=0, microsecond=0)
    t1 = t0 + timedelta(hours=SPAN_HOURS)

    # půlnoc mezi dnem 0 a dnem 1 (pro vzdálenost kulminace od půlnoci)
    midnight = t0 + timedelta(days=1)

    results = []

    for obj in selected:
        full_graph = compute_graph(ts, observer, obj, t0, t1, clip_below_horizon=True)
        rise, transit, set_ = compute_events(ts, eph, obj, t0, t1)

        # vzdálenost kulminace od půlnoci (v sekundách)
        if transit:
            t_transit = datetime.fromisoformat(transit.replace("Z", "+00:00"))
            dist = abs((t_transit - midnight).total_seconds())
        else:
            dist = 999999999

        results.append({
            "id": obj["id"],
            "name": obj["name"],
            "type": obj["type"],
            "mag": obj["mag"],
            "rise_utc": rise,
            "transit_utc": transit,
            "set_utc": set_,
            "graph": full_graph,
            "sort_key": dist,
        })

    # seřadit podle vzdálenosti kulminace od půlnoci
    results.sort(key=lambda x: x["sort_key"])

    out = {
        "timestamp_utc": now.isoformat().replace("+00:00", "Z"),
        "location": {"lat": LAT, "lon": LON, "alt_m": ALT},
        "span_hours": SPAN_HOURS,
        "step_min": STEP_MIN,
        "objects": results,
    }

    base = os.path.basename(csv_path)
    name_no_ext = os.path.splitext(base)[0]
    out_path = os.path.join(JSON_DIR, f"{name_no_ext}_ephemeris.json")

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(out, f, indent=2)

    print("OK:", out_path)


if __name__ == "__main__":
    import sys
    main(sys.argv[1])
