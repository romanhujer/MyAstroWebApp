#!/usr/bin/python3

from skyfield.api import load
from datetime import datetime, timedelta
import json

# české názvy souhvězdí
CONSTELLATION_CZ = {
    'Ari': 'Beran', 'Tau': 'Býk', 'Gem': 'Blíženci', 'Cnc': 'Rak',
    'Leo': 'Lev', 'Vir': 'Panna', 'Lib': 'Váhy', 'Sco': 'Štír',
    'Sgr': 'Střelec', 'Cap': 'Kozoroh', 'Aqr': 'Vodnář', 'Psc': 'Ryby'
}

# zjednodušené hranice souhvězdí podél ekliptiky (IAU)
# Měsíc se pohybuje jen ±5° od ekliptiky → tohle stačí
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
    """Najde souhvězdí podle ekliptické délky."""
    for abbr, start, end in ECLIPTIC_CONSTELLATIONS:
        if start <= lambda_deg < end:
            return abbr
    return 'Psc'

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

        lam = ecliptic[1].degrees  # ekliptická délka
        beta = ecliptic[0].degrees # ekliptická šířka

        abbr = find_constellation_ecliptic(lam)
        cz_name = CONSTELLATION_CZ.get(abbr, abbr)

        result.append({
            "date": current.isoformat(),
            "lambda_deg": lam,
            "beta_deg": beta,
            "constellation": cz_name
        })

        current += timedelta(days=1)

    with open("moon_ephemeris.json", "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print("Hotovo: moon_ephemeris.json vygenerován.")

if __name__ == "__main__":
    generate_ephemeris()
