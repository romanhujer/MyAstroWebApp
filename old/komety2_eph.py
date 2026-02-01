#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import requests
from skyfield.api import load, wgs84, Star
from skyfield.data import mpc
from datetime import datetime, timedelta
import math
import json

LAT = 50.71
LON = 15.18
ALT = 600

MPC_COMETS_URL = 'https://www.minorplanetcenter.net/iau/MPCORB/CometEls.txt'
HORIZONS_URL = "https://ssd.jpl.nasa.gov/api/horizons.api"


def horizons_ephem(name, t):
    """Dotaz na JPL Horizons – vrací RA, Dec, r, delta, mag."""
    params = {
        "format": "json",
        "COMMAND": f"'{name}'",
        "EPHEM_TYPE": "OBSERVER",
        "CENTER": "'500'",
        "SITE_COORD": f"'{LON},{LAT},{ALT}'",
        "START_TIME": f"'{t.utc_strftime('%Y-%m-%d %H:%M')}'",
        "STOP_TIME": f"'{t.utc_strftime('%Y-%m-%d %H:%M')}'",
        "STEP_SIZE": "'1 m'",
        "QUANTITIES": "'1,3,4,20'",
    }

    r = requests.get(HORIZONS_URL, params=params, timeout=10)
    data = r.json()

    try:
        row = data["result"].split("$$SOE")[1].split("$$EOE")[0].strip().split("\n")[0]
        fields = row.split(",")
        ra = float(fields[3])
        dec = float(fields[4])
        r_au = float(fields[8])
        delta_au = float(fields[9])
        mag = float(fields[10])
        return ra, dec, r_au, delta_au, mag
    except:
        return None


def main():
    ts = load.timescale()
    t0 = ts.now()

    print("Načítám MPC CometEls.txt…")
    with load.open(MPC_COMETS_URL) as f:
        df = mpc.load_comets_dataframe(f)

    print(f"Načteno {len(df)} komet")

    eph = load('de440s.bsp')
    earth = eph['earth']
    observer = earth + wgs84.latlon(LAT, LON, ALT)

    # časové body pro 48 h po 30 minutách
    times = []
    for minutes in range(0, 48 * 60 + 1, 30):
        dt = t0.utc_datetime() + timedelta(minutes=minutes)
        times.append(ts.utc(dt.year, dt.month, dt.day, dt.hour, dt.minute))

    results = []

    for idx, row in df.iterrows():
        name = row['designation'].strip() or row['name'].strip()

        graph = []
        max_alt = -90.0
        mag_ref = None

        for t in times:
            ephem = horizons_ephem(name, t)
            if not ephem:
                continue

            ra, dec, r_au, delta_au, mag = ephem

            star = Star(ra_hours=ra, dec_degrees=dec)
            alt, az, _ = observer.at(t).observe(star).apparent().altaz()

            alt_deg = alt.degrees
            max_alt = max(max_alt, alt_deg)
            mag_ref = mag

            graph.append({
                "time_utc": t.utc_iso(),
                "alt_deg": round(alt_deg, 2),
                "az_deg": round(az.degrees, 2),
                "r_au": r_au,
                "delta_au": delta_au,
                "mag_est": mag,
            })

        if max_alt <= 0 or mag_ref is None:
            continue

        results.append({
            "name": name,
            "mag_est": round(mag_ref, 2),
            "max_alt_deg": round(max_alt, 2),
            "graph_48h": graph,
        })

    if not results:
        print("Žádné komety viditelné během 48 hodin.")
        return

    results.sort(key=lambda x: x["mag_est"])
    top10 = results[:10]

    output = {
        "timestamp_utc": datetime.utcnow().isoformat(),
        "location": {"lat": LAT, "lon": LON, "alt_m": ALT},
        "top10_comets": top10,
    }

    with open("comets_top10.json", "w", encoding="utf-8") as f:
        json.dump(output, f, indent=2, ensure_ascii=False)

    print("Hotovo → comets_top10.json")
    for c in top10:
        print(f"{c['name']}: mag {c['mag_est']}, max alt {c['max_alt_deg']}°")


if __name__ == "__main__":
    main()