#!/usr/bin/env python3
import sys, json, gzip, ijson

if len(sys.argv) < 3:
    print("{}", end="")
    sys.exit(0)

hash_to_find = sys.argv[1]
files = sys.argv[2:]

def find_in_file(hash_value, filename):
    try:
        with gzip.open(filename, "rb") as f:
            # JSON je pole → iterujeme přes položky
            for obj in ijson.items(f, "item"):
                obj_hash = obj.get("hash") or obj.get("id")
                if obj_hash == hash_value:
                    return {
                        "hash": obj.get("hash"),
                        "title": obj.get("title", ""),
                        "userDisplayName": obj.get("userDisplayName"),
                        "username": obj.get("username"),
                        "thumbnail": obj.get("thumbnail", ""),
                        "published": obj.get("published"),
                        "integration": float(obj.get("integration") or 0.0),
                        "isIotd": bool(obj.get("isIotd", False)),
                        "isTopPick": bool(obj.get("isTopPick", False)),
                        "isTopPickNomination": bool(obj.get("isTopPickNomination", False)),
                    }  

    except Exception as e:
        pass

    return None

for f in files:
    result = find_in_file(hash_to_find, f)
    if result:
        print(json.dumps(result))
        sys.exit(0)

print("{}", end="")
