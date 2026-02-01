#!/usr/bin/python

from skyfield.api import load, wgs84
from skyfield import almanac
from datetime import datetime, timedelta
import json

LAT = 50.71
LON = 15.18

def generate_saturn():
    ts = load.timescale()
    eph = load('de440s.bsp')

    earth = eph['earth']
    jupiter = eph['saturn barycenter']   # tohle v de440s.bsp JE
    topos = wgs84.latlon(LAT, LON)        # Topos, ne earth+topos

    today = datetime.utcnow().date()
    end_date = today + timedelta(days=62)

    result = []

    current = today
    while current <= end_date:
        t0 = ts.utc(current.year, current.month, current.day, 0, 0)
        t1 = ts.utc(current.year, current.month, current.day, 23, 59)

        # východ / západ – tady MUSÍ být Topos, ne observer
        f = almanac.risings_and_settings(eph, jupiter, topos)
        times, events = almanac.find_discrete(t0, t1, f)

        rise = None
        set_ = None
        for t, e in zip(times, events):
            dt = t.utc_datetime().isoformat()
            if e == 1:
                rise = dt
            elif e == 0:
                set_ = dt

        # kulminace – opět Topos
        f2 = almanac.meridian_transits(eph, jupiter, topos)
        times2, events2 = almanac.find_discrete(t0, t1, f2)

        transit = None
        for t, e in zip(times2, events2):
            if e == 1:  # upper transit
                transit = t.utc_datetime().isoformat()

        # 24h graf výšky – tady už potřebujeme observer = earth + topos
        observer = earth + topos
        graph = []

        # 10min krok = 144 bodů
        for m in range(0, 24*60 + 1, 30):
            total_hours = 12 + m/60
            t = ts.utc(current.year, current.month, current.day, total_hours)

            astrometric = observer.at(t).observe(jupiter)
            alt, az, distance = astrometric.apparent().altaz()

            alt_deg = alt.degrees
            if alt_deg < 0:
                alt_deg = 0

            graph.append({
                "time": f"{int(total_hours) % 24:02d}:{m % 60:02d}",
                "alt": round(alt_deg, 2)


            })

        result.append({
                "date": current.isoformat(),
                "rise_utc": rise,
                "set_utc": set_,
                "transit_utc": transit,
                "altitude_graph": graph
        })

        current += timedelta(days=1)

    with open("saturn_ephemeris.json", "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print("Hotovo: saturn_ephemeris.json vygenerován.")

if __name__ == "__main__":
    generate_saturn()