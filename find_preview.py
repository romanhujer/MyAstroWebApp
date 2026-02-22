#!/usr/bin/env python3
import requests
import csv
import json
import time
import re
from datetime import datetime

USER = "romanhujer"
SEARCH_URL = "https://www.astrobin.com/api/v2/images/image-search/"
OUTPUT = "/opt/astro_json/messier_preview.json"
CSV_FILE = "/opt/astro_json/messier.csv"

HEADERS = {"User-Agent": "Mozilla/5.0"}

# Messier v TITLE (M1..M110), povolíme i "M 110"
# TITLE_REGEX = r"\bM\s*([1-9][0-9]?)\b"
TITLE_REGEX = r"\bM\s*(\d{1,3})\b"


# komety
COMET_REGEX = r"(C/\d+|P/\d+|\d+P|COMET|KOMETA)"

def load_csv(path):
    objs = []
    with open(path) as f:
        reader = csv.reader(f)
        next(reader)  # přeskočit hlavičku
        for row in reader:
            if row and row[0].strip():
                objs.append(row[0].strip().upper())
    return objs

def extract_messier_from_title(title: str):
    found = set()
    for m in re.findall(TITLE_REGEX, title, flags=re.IGNORECASE):
        num = int(m)
        if 1 <= num <= 110:
            found.add(f"M{num}")
    return list(found)


def is_comet_title(title: str):
    return bool(re.search(COMET_REGEX, title, flags=re.IGNORECASE))

def get_all_images():
    images = []
    url = f"{SEARCH_URL}?format=json&username={USER}"

    while url:
        r = requests.get(url, headers=HEADERS)
        if r.status_code != 200:
            print("Chyba API:", r.status_code)
            break

        data = r.json()
        results = data.get("results", [])
        images.extend(results)

        url = data.get("next")
        time.sleep(0.2)

    print(f"Nalezeno {len(images)} snímků.")
    return images

def parse_date(s: str):
    try:
        return datetime.fromisoformat(s.replace("Z", ""))
    except:
        return datetime.min

def choose_better(current, new):
    if current is None:
        return new
    if new["isTopPick"] and not current["isTopPick"]:
        return new
    if current["isTopPick"] and not new["isTopPick"]:
        return current
    if new["isTopPickNomination"] and not current["isTopPickNomination"]:
        return new
    if current["isTopPickNomination"] and not new["isTopPickNomination"]:
        return current
    if new["integration"] > current["integration"]:
        return new
    if current["integration"] > new["integration"]:
        return current
    if new["uploaded"] > current["uploaded"]:
        return new
    return current

def main():
    objects = load_csv(CSV_FILE)  # M1..M110
    images = get_all_images()

    best = {obj: None for obj in objects}

    for img in images:
        if img.get("username") != USER:
            continue

        title = img.get("title", "") or ""
        title_upper = title.upper()

        # 1) vyřadit komety
        if is_comet_title(title_upper):
            continue

        # 2) extrahovat Messier objekty z TITLE
        found = extract_messier_from_title(title_upper)

        # žádný Messier → pryč
        if not found:
            continue

        # více Messier objektů → pryč (M81+M82 apod.)
        if len(found) != 1:
            continue

        obj = found[0]

        # není v CSV → pryč
        if obj not in objects:
            continue

        data = {
            "id": img["hash"],
            "url": f"https://www.astrobin.com/{img['hash']}/?force-classic-view",
            "thumbnail": img.get("regularThumbnail"),
            "title": title,
            "author": img.get("username"),
            "userDisplayName": img.get("userDisplayName"),
            "objects": [obj],
            "isIotd": img.get("isIotd", False),
            "isTopPick": img.get("isTopPick", False),
            "isTopPickNomination": img.get("isTopPickNomination", False),
            "uploaded": parse_date(img.get("published", "")),
            "integration": float(img.get("integration") or 0.0),
            "foceno": "Yes"
        }

        best[obj] = choose_better(best[obj], data)

    output = {obj: ([best[obj]] if best[obj] else []) for obj in objects}

    with open(OUTPUT, "w") as f:
        json.dump(output, f, indent=2, default=str)

    print("Hotovo →", OUTPUT)

if __name__ == "__main__":
    main()
