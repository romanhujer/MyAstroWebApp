#!/usr/bin/python

from skyfield.api import load
from skyfield import almanac
import json
from datetime import timezone

START_YEAR = 2000
END_YEAR   = 2100

def generate_phases(start_year, end_year):
    ts = load.timescale()

    # NOVÉ EFEMERIDY – FUNGUJÍ DO ROKU 2650
    eph = load('de440s.bsp')

    t0 = ts.utc(start_year, 1, 1)
    t1 = ts.utc(end_year, 12, 31)

    f = almanac.moon_phases(eph)
    times, phases = almanac.find_discrete(t0, t1, f)

    result = []

    for t, phase in zip(times, phases):
        dt = t.utc_datetime().replace(tzinfo=timezone.utc)
        iso = dt.isoformat()

        # Skyfield 1.45+ vrací fáze jako čísla 0–3
        if phase == 0:
            p = "new"
        elif phase == 1:
            p = "first_quarter"
        elif phase == 2:
            p = "full"
        elif phase == 3:
            p = "last_quarter"
        else:
            continue

        result.append({
            "type": p,
            "utc": iso
        })

    return result


if __name__ == "__main__":
    phases = generate_phases(START_YEAR, END_YEAR)

    with open("moon_phases_2000_2100.json", "w", encoding="utf-8") as f:
        json.dump(phases, f, indent=2)

    print("Hotovo: moon_phases_2000_2100.json vygenerován.")
    