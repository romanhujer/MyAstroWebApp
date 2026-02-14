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


import csv
import json
from datetime import datetime, timedelta, timezone

from skyfield.api import load, wgs84, Star, Angle
from skyfield.almanac import find_discrete, risings_and_settings, meridian_transits

# ---------------------------------------------------------
# KONFIGURACE
# ---------------------------------------------------------
LAT = 50.71
LON = 15.18
ALT = 600

SPAN_HOURS = 72
STEP_MIN = 10

json_dir = "/opt/astro_json"

# ---------------------------------------------------------
# FUNKCE: načtení katalogu CSV
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
                "size": row["size_arcmin"],
                "constellation": row["constellation"],
            })
    return objects

# ---------------------------------------------------------
# FUNKCE: výpočet alt/az v časech
# ---------------------------------------------------------
def compute_graph(ts, observer, obj, t0, t1):
    from skyfield.api import Star, Angle

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
        # TOP LEVEL FIX
        topo = observer.at(sf_t)
        apparent = topo.observe(sky_obj).apparent()
        alt, az, _ = apparent.altaz()

        graph.append({
            "time_utc": t.isoformat().replace("+00:00", "Z"),
            "ra_hours_j2000": obj["ra_hours"],
            "dec_deg_j2000": obj["dec_deg"],
            "alt_deg": alt.degrees,
            "az_deg": az.degrees,
            "constellation": obj["constellation"],
            "mag": obj["mag"],
        })

    return graph

# ---------------------------------------------------------
# FUNKCE: výpočet rise / transit / set
# ---------------------------------------------------------
def compute_events(ts, observer, obj, t0, t1):
    from skyfield.api import Star, Angle
    from skyfield.api import load as sf_load

    ra = Angle(hours=obj["ra_hours"])
    dec = Angle(degrees=obj["dec_deg"])
    sky_obj = Star(ra=ra, dec=dec)

    eph = sf_load("de421.bsp")

    # Rise / Set
    #f = risings_and_settings(eph, sky_obj, observer)
    topos = wgs84.latlon(LAT, LON, ALT)
    f = risings_and_settings(eph, sky_obj, topos)

    
    t, events = find_discrete(ts.from_datetime(t0), ts.from_datetime(t1), f)

    rise = None
    set_ = None
    for ti, ev in zip(t, events):
        if ev == 1:
            rise = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")
        elif ev == 0:
            set_ = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")

    # Transit
    f2 = meridian_transits(eph, sky_obj, topos)
    t2, events2 = find_discrete(ts.from_datetime(t0), ts.from_datetime(t1), f2)

    transit = None
    for ti, ev in zip(t2, events2):
        if ev == 1:
            transit = ti.utc_strftime("%Y-%m-%dT%H:%M:%SZ")

    return rise, transit, set_


# ---------------------------------------------------------
# FUNKCE: max výška během noci
# ---------------------------------------------------------
def max_alt_during_night(graph):
    return max(p["alt_deg"] for p in graph)

# ---------------------------------------------------------
# FUNKCE: vzdálenost kulminace od půlnoci
# ---------------------------------------------------------
def transit_distance_from_midnight(transit_str, midnight):
    if not transit_str:
        return 999999
    t = datetime.fromisoformat(transit_str.replace("Z", "+00:00"))
    return abs((t - midnight).total_seconds())

# ---------------------------------------------------------
# HLAVNÍ FUNKCE
# ---------------------------------------------------------
def main(csv_path):
    ts = load.timescale()
    eph = load("de421.bsp")
    earth = eph["earth"]
    observer = earth + wgs84.latlon(LAT, LON, ALT)

   
    now = datetime.now(timezone.utc)

    # Začátek grafu = dnešní 00:00 UTC
    t0 = now.replace(hour=0, minute=0, second=0, microsecond=0)

    # Konec grafu = +72 hodin od půlnoci
    t1 = t0 + timedelta(hours=SPAN_HOURS)


    midnight = (now + timedelta(days=1)).replace(hour=0, minute=0, second=0, microsecond=0)

    catalog = load_catalog(csv_path)

    results = []

    for obj in catalog:
        graph = compute_graph(ts, observer, obj, t0, t1)
        max_alt = max_alt_during_night(graph)

        if max_alt < 5.0:
            continue

        rise, transit, set_ = compute_events(ts, observer, obj, t0, t1)

        dist = transit_distance_from_midnight(transit, midnight)

        results.append({
            "id": obj["id"],
            "name": obj["name"],
            "type": obj["type"],
            "mag": obj["mag"],
            "max_alt_deg": max_alt,
            "rise_utc": rise,
            "transit_utc": transit,
            "set_utc": set_,
            "graph": graph,
            "sort_key": dist,
        })

    results.sort(key=lambda x: x["sort_key"])
    # Statistika po dokončení výpočtu
    print(f"Vybráno objektů splňujících podmínku: {len(results)}")

    out = {
        "timestamp_utc": now.isoformat().replace("+00:00", "Z"),
        "location": {"lat": LAT, "lon": LON, "alt_m": ALT},
        "span_hours": SPAN_HOURS,
        "step_min": STEP_MIN,
         "objects": results,
    }

    out_path = f"{json_dir}/{csv_path.split('/')[-1].replace('.csv', '')}_ephemeris.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(out, f, indent=2)

    print("OK:", out_path)

# ---------------------------------------------------------
if __name__ == "__main__":
    import sys
    main(sys.argv[1])
