#!/usr/bin/env python3
import json
import gzip

INPUT = "/opt/astro_json/astrobin.json.gz"
OUTPUT = "/opt/astro_json/astrobin_toppic.json.gz"

def main():
    print("Načítám celý astrobin.json.gz…")
    with gzip.open(INPUT, "rt", encoding="utf-8") as f:
        data = json.load(f)

    print(f"Celkem snímků v databázi: {len(data)}")

    result = []

    print("Filtruji IOTD / TopPick / TopPickNomination…")
    for img in data:
        if img.get("isIotd") or img.get("isTopPick") or img.get("isTopPickNomination"):
            result.append({
                "hash": img["hash"],
                "title": img.get("title", ""),
                "username": img.get("username"),
                "userDisplayName": img.get("userDisplayName"),
                "thumbnail": img.get("thumbnail"),
                "published": img.get("published"),
                "integration": img.get("integration"),
                "isIotd": img.get("isIotd", False),
                "isTopPick": img.get("isTopPick", False),
                "isTopPickNomination": img.get("isTopPickNomination", False)
            })

    print(f"Nalezeno TOP snímků: {len(result)}")

    print("Ukládám do astrobin_toppic.json.gz…")
    with gzip.open(OUTPUT, "wt", encoding="utf-8") as f:
        json.dump(result, f)

    print("✓ Hotovo!")

if __name__ == "__main__":
    main()
