#!/usr/bin/env python3

import json
import gzip
import csv
import re
from datetime import datetime

# Cesty k JSONům
MY_JSON = "/opt/astro_json/astrobin_romanhujer.json.gz"
TOP_JSON = "/opt/astro_json/astrobin_toppic.json.gz"
ALL_JSON = "/opt/astro_json/astrobin.json.gz"

CSV_FILE = "/opt/astro_json/herschel.csv"
OUTPUT = "/opt/astro_json/test_preview.json.gz"

PLANET_REGEX = r"(JUPITER|SATURN|MARS|VENUS|MERCURY|URANUS|NEPTUNE|SUN|MOON|LUNA)"
COMET_REGEX = r"(C/\d+|P/\d+|\d+P|COMET|KOMETA)"
MIN_INTEGRATION = 1800.0


# ------------------------------------------------------------
# Pomocná normalizace textu
# ------------------------------------------------------------
def _clean(t: str) -> str:
    t = t.upper()
    t = re.sub(r"[^A-Z0-9 ]", " ", t)
    t = re.sub(r"\s+", " ", t)
    return t.strip()


# ------------------------------------------------------------
# Hledání v ID (plná priorita katalogů)
# ------------------------------------------------------------
def find_in_id(id_text: str):
    t = _clean(id_text)

    patterns = [
        (r"\bM\s*(\d+)\b", "M"),
        (r"NGC\s*(\d+)", "NGC"),
        (r"IC\s*(\d+)", "IC"),
        (r"UGC\s*(\d+)", "UGC"),

        # SH2 – všechny formáty
        (r"SH\s*2\s*(\d+)", "SH2"),
        (r"SH2\s*(\d+)", "SH2"),
        (r"SHARPLESS\s*2\s*(\d+)", "SH2"),
        (r"SHARPLESS\s*(\d+)", "SH2"),

        # Caldwell – všechny formáty
        (r"\bC\s*(\d+)\b", "C"),
        (r"CALDWELL\s*(\d+)", "C"),

        (r"\bH\s*(\d+)\b", "H"),
        (r"HERSCHEL\s*(\d+)", "H"),

        (r"ARP\s*(\d+)", "ARP"),
        (r"VDB\s*(\d+)", "VDB"),
        (r"LDN\s*(\d+)", "LDN"),
        (r"LBN\s*(\d+)", "LBN"),
        (r"ABELL\s*(\d+)", "ABELL"),
        (r"\bB\s*(\d+)\b", "B"),
    ]

    for regex, prefix in patterns:
        m = re.search(regex, t)
        if m:
            return f"{prefix}{m.group(1)}"

    return None


# ------------------------------------------------------------
# Hledání v NAME (jen M / NGC / IC)
# ------------------------------------------------------------
def find_in_name(name_text: str):
    t = _clean(name_text)

    patterns = [
        (r"\bM\s*(\d+)\b", "M"),
        (r"NGC\s*(\d+)", "NGC"),
        (r"UGC\s*(\d+)", "UGC"),
        (r"LBN\s*(\d+)", "LBN"),
        (r"LDN\s*(\d+)", "LDN"),
        (r"IC\s*(\d+)", "IC"),
    ]

    for regex, prefix in patterns:
        m = re.search(regex, t)
        if m:
            return f"{prefix}{m.group(1)}"

    return None


# ------------------------------------------------------------
# Normalizace CSV name → extrahuje jen hlavní identifikátor
# ------------------------------------------------------------
def normalize_name(name: str) -> str:
    s = name.upper()
    s = re.sub(r"[^A-Z0-9]", "", s)

    # SH2
    m = re.match(r"SH2(\d+)", s)
    if m: return f"SH2{m.group(1)}"

    # ARP
    m = re.match(r"ARP(\d+)", s)
    if m: return f"ARP{m.group(1)}"

    # ABELL
    m = re.match(r"ABELL(\d+)", s)
    if m: return f"ABELL{m.group(1)}"

    # VDB
    m = re.match(r"VDB(\d+)", s)
    if m: return f"VDB{m.group(1)}"

    # Barnard B
    m = re.match(r"B(\d+)", s)
    if m: return f"B{m.group(1)}"

    # NGC / IC / M / C
    m = re.match(r"(LDN|LBN|UGC|NGC|IC|M|C)(\d+)", s)
    if m: return f"{m.group(1)}{m.group(2)}"

    return s


# ------------------------------------------------------------
# Normalizace TITLE (jen pro filtrování planet/komet)
# ------------------------------------------------------------
def normalize_title(title: str) -> str:
    t = title.upper()
    t = re.sub(r"[^A-Z0-9 ]", " ", t)
    t = re.sub(r"\s+", " ", t)
    return t.strip()


# ------------------------------------------------------------
# Filtry
# ------------------------------------------------------------
def is_planet_or_comet(title_norm):
    return re.search(PLANET_REGEX, title_norm) or re.search(COMET_REGEX, title_norm)


# ------------------------------------------------------------
# Matching logika
# ------------------------------------------------------------
def matches_object(img, cid, name_norm, min_integration=None):
    title_norm = normalize_title(img.get("title", "") or "")

    if is_planet_or_comet(title_norm):
        return False

    if min_integration:
        if float(img.get("integration") or 0.0) < min_integration:
            return False

    # 1) Normalizovat CSV ID
    cid_norm = normalize_name(cid)

    # 2) Normalizovat CSV NAME (jen M/NGC/IC)
    name_norm2 = find_in_name(name_norm) or name_norm

    # 3) Najít objekt v TITLE
    obj = find_in_id(title_norm)
    if obj:
        return obj == cid_norm or obj == name_norm2

    obj = find_in_name(title_norm)
    if obj:
        return obj == cid_norm or obj == name_norm2

    return False



# ------------------------------------------------------------
# Načtení CSV
# ------------------------------------------------------------
def load_csv(path):
    objs = {}
    with open(path) as f:
        reader = csv.reader(f)
        next(reader)
        for row in reader:
            if not row or not row[0].strip():
                continue
            cid = row[0].strip().upper()
            name = normalize_name(row[1].strip())
            objs[cid] = name
    return objs


# ------------------------------------------------------------
# Načtení JSON
# ------------------------------------------------------------
def load_json(path):
    with gzip.open(path, "rt", encoding="utf-8") as f:
        return json.load(f)


# ------------------------------------------------------------
# Příprava dat
# ------------------------------------------------------------
def prepare_data(img, cid):
    is_mine = (img.get("username") == "romanhujer")

    data = {
        "id": img["hash"],
        "url": f"https://www.astrobin.com/{img['hash']}/?force-classic-view",
        "thumbnail": img.get("thumbnail"),
        "title": img.get("title", ""),
        "author": img.get("username"),
        "userDisplayName": img.get("userDisplayName"),
        "objects": [cid],
        "isIotd": img.get("isIotd", False),
        "isTopPick": img.get("isTopPick", False),
        "isTopPickNomination": img.get("isTopPickNomination", False),
        "integration": float(img.get("integration") or 0.0),
        "foceno": "Yes" if is_mine else "No"
    }

    try:
        data["_uploaded_dt"] = datetime.fromisoformat(img.get("published", "").replace("Z", ""))
    except:
        data["_uploaded_dt"] = datetime.min

    return data


# ------------------------------------------------------------
# Výběr nejlepšího snímku
# ------------------------------------------------------------
def choose_best(current, new):
    if current is None:
        return new

    if new["isTopPick"] and not current["isTopPick"]:
        return new

    if new["isTopPickNomination"] and not current["isTopPickNomination"]:
        return new

    if new["integration"] > current["integration"]:
        return new

    if new["_uploaded_dt"] > current["_uploaded_dt"]:
        return new

    return current


# ------------------------------------------------------------
# MAIN
# ------------------------------------------------------------
def main():
    objects = load_csv(CSV_FILE)
    my_images = load_json(MY_JSON)
    top_images = load_json(TOP_JSON)
    all_images = load_json(ALL_JSON)

    result = {}

    for cid, name_norm in objects.items():
        print(f"\n=== Hledám {cid} ({name_norm}) ===")

        best = None

        # 1) Moje snímky
        for img in my_images:
            if matches_object(img, cid, name_norm):
                data = prepare_data(img, cid)
                best = choose_best(best, data)

        if best:
            print(f"✓ Moje: {best['id']}  {best['title']}")
            best.pop("_uploaded_dt", None)
            result[cid] = [best]
            continue

        # 2) TOP snímky
        for img in top_images:
            if matches_object(img, cid, name_norm, MIN_INTEGRATION):
                data = prepare_data(img, cid)
                best = choose_best(best, data)

        if best:
            print(f"✓ TOP: {best['id']}  {best['title']}")
            best.pop("_uploaded_dt", None)
            result[cid] = [best]
            continue

        # 3) Velký AstroBin
        for img in all_images:
            if matches_object(img, cid, name_norm, MIN_INTEGRATION):
                data = prepare_data(img, cid)
                best = choose_best(best, data)

        if best:
            print(f"✓ Cizí: {best['id']}  {best['title']}")
            best.pop("_uploaded_dt", None)
            result[cid] = [best]
        else:
            print("× Nenalezeno.")
            result[cid] = []

    with gzip.open(OUTPUT, "wt", encoding="utf-8") as f:
        json.dump(result, f, indent=2)

    print("\nHotovo →", OUTPUT)


if __name__ == "__main__":
    main()
