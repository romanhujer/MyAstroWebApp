#!/usr/bin/env python3
# -*- coding: utf-8 -*-
#
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
# HTML 
# http://www.aerith.net/comet/weekly/current.html"  example format:
'''
<H2><IMG SRC="../../icon/pr_star.gif" ALT="*" WIDTH=21 HEIGHT=18>
<A HREF="../catalog/2025R3/2025R3.html">C/2025 R3 ( PanSTARRS )</A></H2>
<TABLE BORDER=0 CELLPADDING=4>
<TR><TD>
  <A HREF="../catalog/2025R3/2025R3.html"><IMG SRC="../../pictures/jager/s/2025R320260413ut340sm.jpg" WIDTH=120 HEIGHT=120></A>
</TD><TD>
<P>
Now it is 4.5 mag (Apr. 19, Marco Goiato). It will fade out rapidly after this. In the Northern Hemisphere, it is not observable now. It locates somewhat low in the Southern Hemisphere. But it will become high in autumn. 
</P>
<PRE>
Date(TT)  R.A. (2000) Decl.   Delta     r    Elong.  m1   Best Time(A, h)  
Apr. 25   1 58.82   10 16.9   0.494   0.514     3    4.1   3:45 (243,-17)  
May   2   4  8.10   -0 54.9   0.559   0.577    28    4.9  20:19 ( 95,-10)  
</PRE>
</TD></TR>
</TABLE>
'''


import re
import json
import requests
from datetime import datetime, timedelta
from bs4 import BeautifulSoup
from skyfield.api import load, wgs84, Star, load_constellation_map, utc
# -------------------------------------------------
# Konfigurace
# -------------------------------------------------
LAT = 50.71
LON = 15.18
ALT = 600
AERITH_URL = "http://www.aerith.net/comet/weekly/current.html"
SPAN_HOURS = 72
STEP_MIN = 10

def get_constellation(observer, t, ra, dec):
    constellation_map = load_constellation_map()
    star = Star(ra_hours=ra, dec_degrees=dec)
    apparent = observer.at(t).observe(star).apparent()
    return constellation_map(apparent)

def interpolate(a, b, f):
    if a is None or b is None: return None
    return a + (b - a) * f

def interpolate_ra(ra0, ra1, f):
    if ra0 is None or ra1 is None: return None
    d = ra1 - ra0
    if d > 12.0: d -= 24.0
    elif d < -12.0: d += 24.0
    return (ra0 + d * f) % 24.0

def find_events(times, alts):
    rise = None; set_ = None; max_alt = -90.0; transit = None
    for i in range(1, len(times)):
        a0, a1 = alts[i - 1], alts[i]
        t0, t1 = times[i - 1], times[i]
        if a0 is not None and a1 is not None:
            if a0 <= 0 < a1 and rise is None: rise = t0.utc_iso()
            if a0 > 0 >= a1 and set_ is None: set_ = t0.utc_iso()
            if a1 > max_alt:
                max_alt = a1
                transit = t1.utc_iso()
    return rise, transit, set_, max_alt

# -------------------------------------------------
# OPRAVENÝ PARSER S DEBUGEM
# -------------------------------------------------
def parse_ephem_line(line):
    # Najdeme všechna čísla na řádku
    #parts = re.findall(r"[-+]?\d+\.\d+|[-+]?\d+", line)
    
    # Skutečný datový řádek musí mít hodně čísel (RA h, m, Dec d, m, Delta, r, Elong, Mag...)
    # Pokud jich je méně než 8, je to pravděpodobně hlavička nebo smetí
    #if len(parts) < 8:
    #    return None


    # Odstraníme název měsíce na začátku řádku. Měsíc + mezery zabírají cca 
    # prvních 8-10 znaků (např. "June  6   " nebo "Apr. 25   ").
    # Pro jistotu regulárním výrazem odřízneme slovní začátek, abychom začínali číslem dne.
    clean_line = re.sub(r'^\s*[a-zA-Z.]+\s+', '', line)
    
    # Najdeme všechna čísla na zbytku řádku
    parts = re.findall(r"[-+]?\d+\.\d+|[-+]?\d+", clean_line)
    
    # Skutečný datový řádek musí mít aspoň 8 astronomických hodnot + den v měsíci = 9 čísel
    if len(parts) < 9:
        return None


    try:
        # Na Aerithu je formát: [Měsíc] [Den] [RA_h] [RA_m] [Dec_d] [Dec_m] ...
        # parts[0] je Den (pokud tam není rok). 
        # Pokud je první číslo rok (např. 2026), posuneme indexy.
        
        idx = 1
        #if int(parts[0]) > 100: # Pravděpodobně rok 2026
        #    idx = 2
        
        # Ověříme, že máme dostatek prvků od startovního indexu
        #if len(parts) < idx + 8:
        #   return None


        ra_h     = float(parts[idx])
        ra_m     = float(parts[idx+1])
        dec_d    = float(parts[idx+2])
        dec_m    = float(parts[idx+3])
        delta_au = float(parts[idx+4])
        r_au     = float(parts[idx+5])
        elong    = float(parts[idx+6])
        mag      = float(parts[idx+7])

        sign = -1.0 if "-" in parts[idx+2] else 1.0
        
        return {
            "ra_hours": ra_h + (ra_m / 60.0),
            "dec_deg": dec_d + (sign * dec_m / 60.0),
            "delta_au": delta_au,
            "r_au": r_au,
            "elong": elong,
            "mag": mag
        }
    except:
        return None

def fetch_aerith_ephemeris():
    print(f"--- Debug: Start stahování z {AERITH_URL} ---")
    try:
        r = requests.get(AERITH_URL, timeout=20)
        r.encoding = 'utf-8'
        r.raise_for_status()
    except Exception as e:
        print(f"Kritická chyba při stahování: {e}")
        return {}

    soup = BeautifulSoup(r.text, "html.parser")
    
    comets = {}
    h2_tags = soup.find_all("h2")
    
    # Regex pro měsíce - přidáme \b (hranice slova), aby to nechytalo rok 2000
    re_date = re.compile(r"\b(Jan|Feb|Mar|Apr|May|Jun|June|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\b\.?", re.IGNORECASE)

    for h2 in h2_tags:
        name = h2.get_text(strip=True).replace("*", "").strip()
        pre = h2.find_next("pre")
        if not pre: continue
            
        lines = [ln.strip() for ln in pre.get_text().split("\n") if ln.strip()]
        
        valid_data_rows = []
        for ln in lines:
            # Řádek musí obsahovat měsíc
            if re_date.search(ln):
                data = parse_ephem_line(ln)
                if data:
                    valid_data_rows.append(data)
        
        if len(valid_data_rows) >= 2:
            comets[name] = {
                "now": valid_data_rows[0],
                "plus7": valid_data_rows[1]
            }
            print(f"  [OK] Načtena: {name} (Mag: {valid_data_rows[0]['mag']})")
        else:
            # Malý debug výpis, kdyby některá kometa měla problém
            if len(valid_data_rows) == 1:
                print(f"  [!] U komety {name} nalezen pouze 1 platný řádek.")
            
    print(f"--- Debug: Celkem úspěšně zpracováno {len(comets)} komet ---\n")
    return comets


# -------------------------------------------------
# Hlavní proces
# -------------------------------------------------
def main():
    ts = load.timescale()
    comets = fetch_aerith_ephemeris()
    
    if not comets:
        print("Kritická chyba: Žádné komety nebyly načteny z webu!")
        return

    eph = load("de440s.bsp")
    earth = eph["earth"]
    observer = earth + wgs84.latlon(LAT, LON, ALT)

    # --- OPRAVA ČASOVÉ ZÓNY ---
    now_utc = datetime.now(utc) # Použije Skyfield UTC zónu
    day0 = now_utc.date()
    # Při vytváření start_dt musíme přidat tzinfo
    start_dt = datetime(day0.year, day0.month, day0.day, 0, 0, 0, tzinfo=utc)
    # --------------------------

    # Teď už range proběhne v pořádku
    times = [ts.from_datetime(start_dt + timedelta(minutes=m)) for m in range(0, SPAN_HOURS * 60 + 1, STEP_MIN)]
    
    # times = [ts.utc(start_dt + timedelta(minutes=m)) for m in range(0, SPAN_HOURS * 60 + 1, STEP_MIN)]
    results = []

    for des, ephem in comets.items():
        ra0, dec0 = ephem["now"]["ra_hours"], ephem["now"]["dec_deg"]
        ra1, dec1 = ephem["plus7"]["ra_hours"], ephem["plus7"]["dec_deg"]

        graph = []; alts = []; mags = []

        for t in times:
            dt_days = (t.utc_datetime() - times[0].utc_datetime()).total_seconds() / 86400.0
            f = min(max(dt_days / 7.0, 0.0), 1.0)

            ra = interpolate_ra(ra0, ra1, f)
            dec = interpolate(dec0, dec1, f)
            mag = interpolate(ephem["now"]["mag"], ephem["plus7"]["mag"], f)
            # --- PŘIDÁNO: Interpolace elongace ---
            elong = interpolate(ephem["now"]["elong"], ephem["plus7"]["elong"], f)
            # -------------------------------------
            # --- PŘIDÁNO: Získání názvu souhvězdí ---
            const_name = get_constellation(observer, t, ra, dec)
            # ----------------------------------------
            star = Star(ra_hours=ra, dec_degrees=dec)
            alt, az, _ = observer.at(t).observe(star).apparent().altaz()
            
            alts.append(alt.degrees)
            mags.append(mag if mag is not None else 99.0)

            graph.append({
                "time_utc": t.utc_iso(),
                "ra_hours_j2000": round(ra, 5),
                "dec_deg_j2000": round(dec, 5),
                "alt_deg": round(alt.degrees, 2),
                "az_deg": round(az.degrees, 2),
                "mag_est": round(mag, 2) if mag is not None else None,
                "elong_deg": round(elong, 1) if elong is not None else None,
                # --- PŘIDÁNO: Souhvězdí do JSONu ---
                "constellation": const_name,
            })

        rise, transit, set_, max_alt = find_events(times, alts)
        if max_alt <= 0: continue

        results.append({
            "designation": des,
            "mag_est": round(min(mags), 2) if mags else None,
            "max_alt_deg": round(max_alt, 2),
            "rise_utc": rise, "transit_utc": transit, "set_utc": set_,
            "graph_48h": graph,
        })

    results.sort(key=lambda x: x["mag_est"] if x["mag_est"] is not None else 99.0)
    
    with open("comets_current_aerith_ra_alt.json", "w", encoding="utf-8") as f:
        json.dump({"timestamp_utc": datetime.utcnow().isoformat(), "comets": results}, f, indent=2, ensure_ascii=False)

    print(f"\nHotovo! Uloženo {len(results)} komet do JSON.")

if __name__ == "__main__":
    main()