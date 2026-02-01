#!/usr/bin/python
# -*- coding: utf-8 -*-

from skyfield.api import load, wgs84, load_constellation_map
from skyfield import almanac
from datetime import datetime, timedelta
import json

# -------------------------------------------------
# Geografická poloha (Vrkoslavice / Jablonec n. N.)
# -------------------------------------------------
LAT = 50.71
LON = 15.18

# -------------------------------------------------
# ČÍSELNÉ KÓDY PRO de440s.bsp (barycentra planet)
# -------------------------------------------------
PLANETS = {
    "mercury": 1,
    "venus":   2,
    "mars":    4,
    "jupiter": 5,
    "saturn":  6,
    "uranus":  7,
    "neptune": 8,
}

# -------------------------------------------------
# Průměrné průměry planet (km)
# -------------------------------------------------
PLANET_DIAMETERS = {
    "mercury": 4879,
    "venus":   12104,
    "mars":    6779,
    "jupiter": 139820,
    "saturn":  116460,
    "uranus":  50724,
    "neptune": 49244,
}

# -------------------------------------------------
# Úhlová velikost v arcsec
# -------------------------------------------------
def angular_size_arcsec(diameter_km, distance_km):
    return (diameter_km / distance_km) * 206265.0


def generate_planet(planet_key, planet_code):

    ts = load.timescale()
    eph = load('de440s.bsp')

    earth = eph['earth']
    body = eph[planet_code]
    sun = eph['sun']

    topos = wgs84.latlon(LAT, LON)
    observer = earth + topos

    today = datetime.utcnow().date()
    end_date = today + timedelta(days=62)

    constellation_map = load_constellation_map()

    # -------------------------------------------------
    # Nejbližší opozice (Mars → Neptun)
    # -------------------------------------------------
    nearest_opposition = None

    if planet_key not in ("mercury", "venus"):

        opp_start = ts.utc(today.year, 1, 1)
        opp_end   = ts.utc(today.year + 1, 12, 31)

        f_opp = almanac.oppositions_conjunctions(eph, body)
        times_opp, events_opp = almanac.find_discrete(opp_start, opp_end, f_opp)

        for t, e in zip(times_opp, events_opp):
            if e == 1:
                d = t.utc_datetime().date()
                if d >= today:
                    nearest_opposition = d.isoformat()
                    break

    # -------------------------------------------------
    # Perihelium (jen Merkur + Venuše)
    # -------------------------------------------------
    nearest_perihelion = None

    if planet_key in ("mercury", "venus"):

        peri_dates = []
        peri_distances = []

        for year in (today.year, today.year + 1):
            for month in range(1, 13):
                for day in (1, 8, 15, 22):
                    try:
                        t = ts.utc(year, month, day)
                    except:
                        continue

                    sun_pos = eph['sun'].at(t)
                    body_pos = eph[planet_code].at(t)
                    dist = (body_pos - sun_pos).distance().au

                    peri_dates.append(t.utc_datetime().date())
                    peri_distances.append(dist)

        best_date = None
        best_dist = 999

        for d, dist in zip(peri_dates, peri_distances):
            if d >= today and dist < best_dist:
                best_dist = dist
                best_date = d

        if best_date:
            nearest_perihelion = best_date.isoformat()

    # -------------------------------------------------
    # Hlavní smyčka přes dny
    # -------------------------------------------------
    result = []
    current = today

    while current <= end_date:

        t0 = ts.utc(current.year, current.month, current.day, 0, 0)
        t1 = ts.utc(current.year, current.month, current.day, 23, 59)

        # Východ / západ
        f_rs = almanac.risings_and_settings(eph, body, topos)
        times_rs, events_rs = almanac.find_discrete(t0, t1, f_rs)

        rise = None
        set_ = None

        for t, e in zip(times_rs, events_rs):
            dt = t.utc_datetime().isoformat()
            if e == 1:
                rise = dt
            elif e == 0:
                set_ = dt

        # Kulminace
        f_tr = almanac.meridian_transits(eph, body, topos)
        times_tr, events_tr = almanac.find_discrete(t0, t1, f_tr)

        transit = None
        for t, e in zip(times_tr, events_tr):
            if e == 1:
                transit = t.utc_datetime().isoformat()

        # -------------------------------------------------
        # 48h altitude graph
        # -------------------------------------------------
        graph = []

        for m in range(0, 48 * 60 + 1, 30):

            day_offset = m // (24 * 60)
            minute_of_day = m % (24 * 60)

            hour = minute_of_day // 60
            minute = minute_of_day % 60

            t = ts.utc(
                current.year,
                current.month,
                current.day + day_offset,
                hour,
                minute
            )

            astrometric = observer.at(t).observe(body)
            apparent = astrometric.apparent()

            alt, az, distance = apparent.altaz()
            alt_deg = max(0.0, alt.degrees)

            dist_km = distance.km
            dist_au = distance.au

            diameter = PLANET_DIAMETERS[planet_key]
            ang = angular_size_arcsec(diameter, dist_km)

            const_name = constellation_map(apparent)

            sun_app = observer.at(t).observe(sun).apparent()
            elong_deg = apparent.separation_from(sun_app).degrees

            graph.append({
                "time": f"{hour:02d}:{minute:02d}",
                "day_offset": day_offset,
                "alt": round(alt_deg, 2),
                "distance_au": round(dist_au, 6),
                "distance_km": round(dist_km),
                "angular_size_arcsec": round(ang, 3),
                "constellation": const_name,
                "elongation_deg": round(elong_deg, 2)
            })

        # -------------------------------------------------
        # Záznam dne
        # -------------------------------------------------
        result.append({
            "date": current.isoformat(),
            "rise_utc": rise,
            "set_utc": set_,
            "transit_utc": transit,
            "nearest_opposition": nearest_opposition,
            "nearest_perihelion": nearest_perihelion,
            "altitude_graph": graph
        })

        current += timedelta(days=1)

    # -------------------------------------------------
    # Uložení JSON
    # -------------------------------------------------
    filename = f"{planet_key}_ephemeris.json"
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print(f"Hotovo: {filename}")


def generate_all():
    for key, code in PLANETS.items():
        generate_planet(key, code)


if __name__ == "__main__":
    generate_all()