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
import re
import requests
from bs4 import BeautifulSoup

URL = "https://www.astropixels.com/messier/messiercat.html"
OUT_CSV = "messier.csv"

def sexagesimal_ra_to_hours(ra_str: str) -> float:
    # "5h 34.5m" → 5 + 34.5/60
    m = re.match(r"\s*(\d+)\s*h\s*(\d+(?:\.\d*)?)\s*m", ra_str)
    if not m:
        raise ValueError(f"RA parse error: {ra_str}")
    h = float(m.group(1))
    m_ = float(m.group(2))
    return h + m_ / 60.0

def sexagesimal_dec_to_deg(dec_str: str) -> float:
    # "+22° 01′" / "-26° 32′"
    m = re.match(r"\s*([+\-]?\d+)\s*°\s*(\d+(?:\.\d*)?)\s*′", dec_str)
    if not m:
        raise ValueError(f"Dec parse error: {dec_str}")
    d = float(m.group(1))
    m_ = float(m.group(2))
    sign = 1 if d >= 0 else -1
    return d + sign * m_ / 60.0

def main():
    headers = {"User-Agent": "Mozilla/5.0"}
    resp = requests.get(URL, headers=headers)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")

    # najdeme první tabulku s Messier katalogem
    table = soup.find("table")
    rows = table.find_all("tr")

    with open(OUT_CSV, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["id", "name", "type", "ra_hours", "dec_deg", "mag", "size_arcmin", "constellation"])

        # přeskočíme hlavičku
        for tr in rows[1:]:
            tds = [td.get_text(strip=True) for td in tr.find_all("td")]
            if len(tds) < 10:
                continue

            m_id = tds[0]          # "M1"
            m_type = tds[2]        # "Sn", "Gc", "Oc", ...
            mag = tds[3]           # "8.4"
            size = tds[4]          # "6x4"
            ra_str = tds[6]        # "5h 34.5m"
            dec_str = tds[7]       # "+22° 01′"
            const = tds[8]         # "Tau"
            name = tds[10]         # "Crab Nebula" (může být prázdné)

            ra_hours = sexagesimal_ra_to_hours(ra_str)
            dec_deg = sexagesimal_dec_to_deg(dec_str)

            writer.writerow([
                m_id,
                name,
                m_type,
                f"{ra_hours:.5f}",
                f"{dec_deg:.5f}",
                mag,
                size,
                const,
            ])

if __name__ == "__main__":
    main()
