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

from skyfield.api import load, wgs84
from datetime import datetime, timedelta, timezone
import json

# Vrkoslavice
OBS_LAT = 50.7220
OBS_LON = 15.1700
OBS_ELEV = 460

# Přesné hranice ekliptických souhvězdí (IAU)
ECLIPTIC_CONSTELLATIONS = [
    ('Ryby',       0.00,  33.25),
    ('Beran',     33.25,  51.75),
    ('Býk',       51.75,  90.50),
    ('Blíženci',  90.50, 118.25),
    ('Rak',      118.25, 138.25),
    ('Lev',      138.25, 173.25),
    ('Panna',    173.25, 217.25),
    ('Váhy',     217.25, 240.25),
    ('Štír',     240.25, 247.00),
    ('Hadonoš',  247.00, 266.25),
    ('Střelec',  266.25, 299.75),
    ('Kozoroh',  299.75, 327.00),
    ('Vodnář',   327.00, 360.00),
]

def find_sun_constellation(lambda_deg):
    for name, start, end in ECLIPTIC_CONSTELLATIONS:
        if start <= lambda_deg < end:
            return name
    return "Ryby"

def find_crossing(times, altitudes, target=0.0):
    """Najde čas, kdy altitudes protne target (0°)."""
    for i in range(len(altitudes)-1):
        if (altitudes[i] < target and altitudes[i+1] >= target) or \
           (altitudes[i] > target and altitudes[i+1] <= target):

            # lineární interpolace
            t1 = times[i].utc_datetime().replace(tzinfo=timezone.utc)
            t2 = times[i+1].utc_datetime().replace(tzinfo=timezone.utc)
            a1 = altitudes[i]
            a2 = altitudes[i+1]

            if a2 == a1:
                return t1.isoformat()

            ratio = (target - a1) / (a2 - a1)
            dt = t1 + (t2 - t1) * ratio
            return dt.isoformat()

    return None

def sun_rise_set(ts, eph, date):
    observer = eph['earth'] + wgs84.latlon(OBS_LAT, OBS_LON, OBS_ELEV)

    minutes = list(range(0, 24*60, 1))  # krok 1 minuta
    times = ts.utc(date.year, date.month, date.day,
                   [m//60 for m in minutes],
                   [m%60 for m in minutes])

    altitudes = observer.at(times).observe(eph['sun']).apparent().altaz()[0].degrees

    sunrise = find_crossing(times, altitudes, 0.0)
    sunset  = find_crossing(times[::-1], altitudes[::-1], 0.0)

    return sunrise, sunset

def sun_culmination(ts, eph, date):
    observer = eph['earth'] + wgs84.latlon(OBS_LAT, OBS_LON, OBS_ELEV)

    minutes = list(range(0, 24*60, 2))
    times = ts.utc(date.year, date.month, date.day,
                   [m//60 for m in minutes],
                   [m%60 for m in minutes])

    altitudes = observer.at(times).observe(eph['sun']).apparent().altaz()[0].degrees

    max_index = max(range(len(altitudes)), key=lambda i: altitudes[i])
    culm_time = times[max_index].utc_datetime().replace(tzinfo=timezone.utc).isoformat()
    culm_alt = float(altitudes[max_index])

    return culm_time, culm_alt

def generate_sun_ephemeris():
    ts = load.timescale()
    eph = load('de421.bsp')

    today = datetime.utcnow().date()
    end_date = today + timedelta(days=62)

    result = []
    current = today

    while current <= end_date:
        t = ts.utc(current.year, current.month, current.day)

        astrometric = eph['earth'].at(t).observe(eph['sun'])
        ecliptic = astrometric.ecliptic_latlon()
        lam = float(ecliptic[1].degrees)

        constellation = find_sun_constellation(lam)
        sunrise, sunset = sun_rise_set(ts, eph, current)
        culm_time, culm_alt = sun_culmination(ts, eph, current)

        result.append({
            "date": current.isoformat(),
            "lambda_deg": lam,
            "constellation": constellation,
            "sunrise": sunrise,
            "sunset": sunset,
            "culmination": culm_time,
            "culmination_alt_deg": culm_alt
        })

        current += timedelta(days=1)

    with open("sun_ephemeris.json", "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print("Hotovo: sun_ephemeris.json vygenerován.")

if __name__ == "__main__":
    generate_sun_ephemeris()