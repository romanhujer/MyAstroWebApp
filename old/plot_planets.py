#!/usr/bin/env python3
import json
import sys
from pathlib import Path
from datetime import datetime, timedelta
import argparse
import statistics

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.dates as mdates

ROOT = Path('/Volumes/hujer/webtmp')
PLANET_FILES = {
    'mercury': 'mercury_ephemeris.json',
    'venus': 'venus_ephemeris.json',
    'mars': 'mars_ephemeris.json',
    'jupiter': 'jupiter_ephemeris.json',
    'saturn': 'saturn_ephemeris.json',
    'uranus': 'uranus_ephemeris.json',
    'neptune': 'neptune_ephemeris.json',
    'moon': 'moon_ephemeris.json',
    'sun': 'sun_ephemeris.json'
}


def load_planet_data(filename, start_date, end_date):
    path = ROOT / filename
    if not path.exists():
        raise FileNotFoundError(path)
    with open(path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    dates = []
    distances = []
    for day in data:
        try:
            d = datetime.fromisoformat(day['date']).date()
        except Exception:
            # some files may include full datetime in other fields
            d = datetime.strptime(day['date'][:10], '%Y-%m-%d').date()
        if d < start_date or d > end_date:
            continue
        # compute average distance_au across altitude_graph if available
        vals = []
        if 'altitude_graph' in day and isinstance(day['altitude_graph'], list) and day['altitude_graph']:
            for p in day['altitude_graph']:
                if 'distance_au' in p and p['distance_au'] is not None:
                    vals.append(p['distance_au'])
        elif 'distance_au' in day:
            vals.append(day['distance_au'])
        if not vals:
            continue
        avg = statistics.mean(vals)
        dates.append(d)
        distances.append(avg)
    return dates, distances


def main():
    parser = argparse.ArgumentParser(description='Plot planetary distances (AU) from ephemeris JSON files')
    parser.add_argument('--planets', '-p', nargs='+', help='Planet names', default=['mercury','venus','mars','jupiter','saturn'])
    parser.add_argument('--days', '-d', type=int, default=30, help='Number of days ending today')
    parser.add_argument('--out', '-o', default=str(ROOT / 'planets.png'), help='Output PNG file')
    args = parser.parse_args()

    end_date = datetime.utcnow().date()
    start_date = end_date - timedelta(days=args.days-1)

    plt.figure(figsize=(10,6))
    ax = plt.gca()

    plotted = 0
    for name in args.planets:
        key = name.lower()
        if key not in PLANET_FILES:
            print(f"Skipping unknown planet: {name}")
            continue
        try:
            dates, distances = load_planet_data(PLANET_FILES[key], start_date, end_date)
        except FileNotFoundError:
            print(f"File not found for {name}: {PLANET_FILES[key]}")
            continue
        if not dates:
            print(f"No data in range for {name}")
            continue
        ax.plot(dates, distances, marker='o', label=name.capitalize())
        plotted += 1

    if plotted == 0:
        print('No planets plotted; check planet names and available data files.')
        sys.exit(1)

    ax.set_xlabel('Date')
    ax.set_ylabel('Distance (AU)')
    ax.set_title(f'Planet distances (AU) from {start_date} to {end_date}')
    ax.legend()
    ax.grid(True, alpha=0.3)

    ax.xaxis.set_major_locator(mdates.AutoDateLocator())
    ax.xaxis.set_major_formatter(mdates.DateFormatter('%Y-%m-%d'))
    plt.xticks(rotation=45)
    plt.tight_layout()
    out_path = Path(args.out)
    plt.savefig(out_path)
    print(f'Saved plot to {out_path}')


if __name__ == '__main__':
    main()
