#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import re
import json
import requests
from datetime import datetime, timedelta

from bs4 import BeautifulSoup
from skyfield.api import load, wgs84, Star

# -------------------------------------------------
# Geografická poloha (Vrkoslavice / Jablonec n. N.)
# -------------------------------------------------
LAT = 50.71
LON = 15.18
ALT = 600

AERITH_URL = "http://www.aerith.net/comet/weekly/current.html"

SPAN_HOURS = 48
STEP_MIN = 30

# -------------------------------------------------
# Pomocné funkce
# -------------------------------------------------
def interpolate(a, b, f):
    if a is None or b is None:
        return None
    return a + (b - a) * f

def interpolate_ra(ra0, ra1, f):
    if ra0 is None or ra1 is None:
        return None
    d = ra1 - ra0
    if d > 12.0:
        d -= 24.0
    elif d < -12.0:
        d += 24.0
    return (ra0 + d * f) % 24.0

def find_events(times, alts):
    rise = None
    set_ = None
    max_alt = -90.0
    transit = None

    for i in range(1, len(times)):
        a0, a1 = alts[i - 1], alts[i]
        t0, t1 = times[i - 1], times[i]

        if a0 is not None and a1 is not None:
            if a0 <= 0 < a1 and rise is None:
                rise = t0.utc_iso()
            if a0 > 0 >= a1 and set_ is None:
                set_ = t0.utc_iso()

            if a1 > max_alt:
                max_alt = a1
                transit = t1.utc_iso()

    return rise, transit, set_, max_alt

# -------------------------------------------------
# Parser efemeridního řádku Aerith
# -------------------------------------------------
def parse_ephem_line(line):
    parts = line.split()
    # očekáváme:
    # [0] Mon.
    # [1] day
    # [2] RA_h
    # [3] RA_m
    # [4] Dec_d
    # [5] Dec_m
    # [6] Delta
    # [7] r
    # [8] Elong
    # [9] m1
    if len(parts) < 10:
        return None

    ra_h = float(parts[2])
    ra_m = float(parts[3])
    ra_hours = ra_h + ra_m / 60.0

    dec_d = float(parts[4])
    dec_m = float(parts[5])
    sign = -1 if dec_d < 0 else 1
    dec_deg = dec_d + sign * dec_m / 60.0

    delta = float(parts[6])
    r = float(parts[7])
    elong = float(parts[8])
    m1 = float(parts[9])

    return {
        "ra_hours": ra_hours,
        "dec_deg": dec_deg,
        "delta_au": delta,
        "r_au": r,
        "elong": elong,
        "mag": m1,
    }

# -------------------------------------------------
# 1) Načíst a rozparsovat Aerith (Now + +7d pro každou kometu)
# -------------------------------------------------
def fetch_aerith_ephemeris():
    r = requests.get(AERITH_URL, timeout=20)
    r.raise_for_status()
    soup = BeautifulSoup(r.text, "html.parser")

    tables = soup.find_all("table")
    target_table = None
    max_cells = 0

    for tbl in tables:
        cells = tbl.find_all("td")
        if len(cells) > max_cells:
            max_cells = len(cells)
            target_table = tbl

    if not target_table:
        print("[ERROR] Nenašel jsem tabulku s kometami.")
        return {}

    cells = target_table.find_all("td")
    #print(f"[DEBUG] Počet buněk s kometami: {len(cells)}")

    re_desig = re.compile(r"^([CP]\/\d{4}\s?[A-Z0-9]+|\d+P)", re.IGNORECASE)
    re_date = re.compile(r"^(Jan\.|Feb\.|Mar\.|Apr\.|May\.|Jun\.|Jul\.|Aug\.|Sep\.|Oct\.|Nov\.|Dec\.)")

    comets = {}

    for cell in cells:
        text = cell.get_text("\n", strip=True)
        lines = text.split("\n")

        # indexy začátků bloků komet
        indices = [i for i, ln in enumerate(lines) if re_desig.match(ln)]

        for idx in range(len(indices)):
            start = indices[idx]
            end = indices[idx + 1] if idx + 1 < len(indices) else len(lines)
            block = lines[start:end]

            m = re_desig.match(block[0])
            if not m:
                continue
            desig = m.group(1).strip()

            ephem_lines = [ln for ln in block if re_date.match(ln)]
            if len(ephem_lines) < 2:
                continue

            now_line = parse_ephem_line(ephem_lines[0])
            plus7_line = parse_ephem_line(ephem_lines[1])

            if now_line and plus7_line:
                comets[desig] = {
                    "now": now_line,
                    "plus7": plus7_line,
                }

    #print(f"[DEBUG] Nalezeno {len(comets)} komet s párem Now/+7d")
    return comets

# -------------------------------------------------
# 2) Hlavní výpočet
# -------------------------------------------------
def main():
    ts = load.timescale()
    t0 = ts.now()

    print("Stahuji Aerith current weekly…")
    comets = fetch_aerith_ephemeris()
    print(f"Nalezeno {len(comets)} komet s párem Now/+7d:")
    for des in comets.keys():
        print("  -", des)

    eph = load("de440s.bsp")
    earth = eph["earth"]
    observer = earth + wgs84.latlon(LAT, LON, ALT)

    # časová osa
    times = []
    for minutes in range(0, SPAN_HOURS * 60 + 1, STEP_MIN):
        dt = t0.utc_datetime() + timedelta(minutes=minutes)
        times.append(ts.utc(dt.year, dt.month, dt.day, dt.hour, dt.minute))

    results = []

    for des, ephem in comets.items():
        print(f"\n→ Zpracovávám {des}")

        now = ephem["now"]
        plus7 = ephem["plus7"]

        ra0 = now["ra_hours"]
        dec0 = now["dec_deg"]
        ra1 = plus7["ra_hours"]
        dec1 = plus7["dec_deg"]

        graph = []
        alts = []
        mags = []

        for t in times:
            dt_days = (t.utc_datetime() - t0.utc_datetime()).total_seconds() / 86400.0
            f = dt_days / 7.0
            if f < 0:
                f = 0.0
            if f > 1:
                f = 1.0

            ra = interpolate_ra(ra0, ra1, f)
            dec = interpolate(dec0, dec1, f)
            r_au = interpolate(now["r_au"], plus7["r_au"], f)
            delta_au = interpolate(now["delta_au"], plus7["delta_au"], f)
            elong = interpolate(now["elong"], plus7["elong"], f)
            mag = interpolate(now["mag"], plus7["mag"], f)

            star = Star(ra_hours=ra, dec_degrees=dec)
            alt, az, _ = observer.at(t).observe(star).apparent().altaz()
            alt_deg = alt.degrees
            az_deg = az.degrees

            alts.append(alt_deg)
            mags.append(mag if mag is not None else 99.0)

            graph.append({
                "time_utc": t.utc_iso(),
                "ra_hours_j2000": round(ra, 5),
                "dec_deg_j2000": round(dec, 5),
                "alt_deg": round(alt_deg, 2),
                "az_deg": round(az_deg, 2),
                "r_au": r_au,
                "delta_au": delta_au,
                "elong_deg": round(elong, 2) if elong is not None else None,
                "mag_est": round(mag, 2) if mag is not None else None,
            })

        rise, transit, set_, max_alt = find_events(times, alts)
        if max_alt <= 0:
            print(f"  Kometa je po celou dobu pod obzorem (max_alt={max_alt:.2f})")
            continue

        mag_ref = min(mags) if mags else None

        results.append({
            "designation": des,
            "mag_est": round(mag_ref, 2) if mag_ref is not None else None,
            "max_alt_deg": round(max_alt, 2),
            "rise_utc": rise,
            "transit_utc": transit,
            "set_utc": set_,
            "graph_48h": graph,
        })

    if not results:
        print("\nŽádné komety viditelné během 48 hodin.")
        return

    results.sort(key=lambda x: x["mag_est"] if x["mag_est"] is not None else 99.0)

    output = {
        "timestamp_utc": datetime.utcnow().isoformat(),
        "location": {"lat": LAT, "lon": LON, "alt_m": ALT},
        "span_hours": SPAN_HOURS,
        "step_min": STEP_MIN,
        "comets": results,
    }

    with open("comets_current_aerith_ra_alt.json", "w", encoding="utf-8") as f:
        json.dump(output, f, indent=2, ensure_ascii=False)

    print("\nHotovo → comets_current_aerith_ra_alt.json")
    print("Viditelné komety:")
#    for c in results:
#        print(
#            f"  {c['designation']}: mag {c['mag_est']}, "
#            f"max alt {c['max_alt_deg']}°, "
#            f"rise {c['rise_utc']}, transit {c['transit_utc']}, set {c['set_utc']}"
#        )


if __name__ == "__main__":
    main()
# -------------------------------------------------