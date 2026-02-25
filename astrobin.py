#!/usr/bin/env python3
import requests
import json
import gzip
import time
import os
import glob

BASE_URL = "https://www.astrobin.com/api/v2/images/image-search/?format=json"
HEADERS = {"User-Agent": "Mozilla/5.0"}

BLOCK_SIZE = 50                 # počet stránek v jednom bloku
SLEEP_BETWEEN_BLOCKS = 66      # pauza mezi bloky (sekundy)
OUTPUT_DIR = "/opt/astro_json"
FINAL_JSON = f"{OUTPUT_DIR}/astrobin.json.gz"


# ------------------------------------------------------------
# Stáhne jeden blok stránek
# ------------------------------------------------------------
def download_block(start_page, block_id):
    url = f"{BASE_URL}&page={start_page}"
    out = []
    page = start_page
    end_page = start_page + BLOCK_SIZE - 1

    while url and page <= end_page:
        print(f"Stahuji stránku {page}: {url}")
        r = requests.get(url, headers=HEADERS)

        try:
            data = r.json()
        except:
            print("❌ API přestalo odpovídat – ukládám blok a končím.")
            break

        # uložíme jen potřebné klíče
        for img in data.get("results", []):
            out.append({
                "hash": img["hash"],
                "title": img.get("title", ""),
                "username": img.get("username"),
                "userDisplayName": img.get("userDisplayName"),
                "thumbnail": img.get("regularThumbnail"),
                "published": img.get("published"),
                "integration": img.get("integration"),
                "isIotd": img.get("isIotd", False),
                "isTopPick": img.get("isTopPick", False),
                "isTopPickNomination": img.get("isTopPickNomination", False)
            })

        url = data.get("next")
        page += 1
        time.sleep(0.2)

    # uložit blok
    filename = f"{OUTPUT_DIR}/astrobin_block_{block_id}.json.gz"
    with gzip.open(filename, "wt", encoding="utf-8") as f:
        json.dump(out, f)

    print(f"✓ Uloženo: {filename}")
    return url


# ------------------------------------------------------------
# Spojí všechny bloky do jednoho JSON pole
# ------------------------------------------------------------
def merge_blocks():
    print("\n=== Spojuji bloky do jednoho JSON ===")

    all_items = []

    for block_file in sorted(glob.glob(f"{OUTPUT_DIR}/astrobin_block_*.json.gz")):
        print(f"Načítám {block_file}")
        with gzip.open(block_file, "rt", encoding="utf-8") as f:
            data = json.load(f)
            all_items.extend(data)

    print(f"Celkem položek: {len(all_items)}")

    # uložit finální JSON
    with gzip.open(FINAL_JSON, "wt", encoding="utf-8") as f:
        json.dump(all_items, f)

    print(f"✓ Hotovo → {FINAL_JSON}")

    # smazat bloky
    for block_file in glob.glob(f"{OUTPUT_DIR}/astrobin_block_*.json.gz"):
        os.remove(block_file)

    print("✓ Dočasné bloky smazány.")


# ------------------------------------------------------------
# MAIN
# ------------------------------------------------------------
def main():
    block_id = 1
    next_url = f"{BASE_URL}&page=1"

    while next_url:
        print(f"\n=== BLOK {block_id} ===")
        next_url = download_block((block_id - 1) * BLOCK_SIZE + 1, block_id)
        block_id += 1

        if next_url:
            print(f"⏳ Pauza {SLEEP_BETWEEN_BLOCKS} sekund…")
            time.sleep(SLEEP_BETWEEN_BLOCKS)

    merge_blocks()
    print("\nVšechny data staženy a sloučeny.")


if __name__ == "__main__":
    main()
