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
from skyfield import almanac
from datetime import datetime, timedelta, timezone
import json

# české názvy souhvězdí
CONSTELLATION_CZ = {
    'Ari': 'Beran', 'Tau': 'Býk', 'Gem': 'Blíženci', 'Cnc': 'Rak',
    'Leo': 'Lev', 'Vir': 'Panna', 'Lib': 'Váhy', 'Sco': 'Štír',
    'Sgr': 'Střelec', 'Cap': 'Kozoroh', 'Aqr': 'Vodnář', 'Psc': 'Ryby'
}

ECLIPTIC_CONSTELLATIONS = [
    ('Psc',   0,  30),
    ('Ari',  30,  60),
    ('Tau',  60,  90),
    ('Gem',  90, 120),
    ('Cnc', 120, 150),
    ('Leo', 150, 180),
    ('Vir', 180, 210),
    ('Lib', 210, 240),
    ('Sco', 240, 270),
    ('Sgr', 270, 300),
    ('Cap', 300, 330),
    ('Aqr', 330, 360)
]

def find_constellation_ecliptic(lambda_deg):
    for abbr, start, end in ECLIPTIC_CONSTELLATIONS:
        if start <= lambda_deg < end:
            return abbr
    return 'Psc'

# Vrkoslavice
OBS_LAT = 50.7220
OBS_LON = 15.1700
OBS_ELEV = 460


def moon_rise_set(ts, eph, date):
    observer = eph['earth'] + wgs84.latlon(OBS_LAT, OBS_LON, OBS_ELEV)

    # sampling po 1 minutě
    minutes = list(range(0, 24*60, 1))
    times = ts.utc(date.year, date.month, date.day,
                   [m//60 for m in minutes],
                   [m%60 for m in minutes])

    altitudes = observer.at(times).observe(eph['moon']).apparent().altaz()[0].degrees

    def find_crossing(times, altitudes, target=0.0):
        for i in range(len(altitudes)-1):
            if (altitudes[i] < target and altitudes[i+1] >= target) or \
               (altitudes[i] > target and altitudes[i+1] <= target):

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

    moonrise = find_crossing(times, altitudes, 0.0)
    moonset  = find_crossing(times[::-1], altitudes[::-1], 0.0)

    return moonrise, moonset

def moon_culmination(ts, eph, date):
    """Najde kulminaci Měsíce (max výšku) během daného dne."""

    observer = eph['earth'] + wgs84.latlon(OBS_LAT, OBS_LON, OBS_ELEV)

    # časový interval dne
    t0 = ts.utc(date.year, date.month, date.day, 0, 0)
    t1 = ts.utc(date.year, date.month, date.day, 23, 59)

    # krok 2 minuty
    minutes = list(range(0, 24*60, 2))
    times = ts.utc(date.year, date.month, date.day, [m//60 for m in minutes], [m%60 for m in minutes])

    altitudes = observer.at(times).observe(eph['moon']).apparent().altaz()[0].degrees

    # najít maximum
    max_index = max(range(len(altitudes)), key=lambda i: altitudes[i])
    culm_time = times[max_index].utc_datetime().replace(tzinfo=timezone.utc).isoformat()
    culm_alt = altitudes[max_index]

    return culm_time, culm_alt

def generate_ephemeris():
    ts = load.timescale()
    eph = load('de421.bsp')

    earth = eph['earth']
    moon = eph['moon']

    today = datetime.utcnow().date()
    end_date = today + timedelta(days=62)

    result = []

    current = today
    while current <= end_date:
        t = ts.utc(current.year, current.month, current.day)

        astrometric = earth.at(t).observe(moon)
        ecliptic = astrometric.ecliptic_latlon()

        lam = ecliptic[1].degrees
        beta = ecliptic[0].degrees

        abbr = find_constellation_ecliptic(lam)
        cz_name = CONSTELLATION_CZ.get(abbr, abbr)

        moonrise, moonset = moon_rise_set(ts, eph, current)
        culm_time, culm_alt = moon_culmination(ts, eph, current)

        result.append({
            "date": current.isoformat(),
            "lambda_deg": lam,
            "beta_deg": beta,
            "constellation": cz_name,
            "moonrise": moonrise,
            "moonset": moonset,
            "culmination": culm_time,
            "culmination_alt_deg": culm_alt
        })

        current += timedelta(days=1)

    with open("moon_ephemeris.json", "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print("Hotovo: moon_ephemeris.json vygenerován.")


if __name__ == "__main__":
    generate_ephemeris()