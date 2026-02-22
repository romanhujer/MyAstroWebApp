<?php
/*  planet.php
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
*/

date_default_timezone_set('Europe/Prague');
$nowTZ = new DateTimeZone("Europe/Prague");
$nowTime = date('Y-m-d H:i');


$json_dir = "/opt/astro_json";

$map = [
    "jup" => ["jupiter", "Jupiter"],
    "mar" => ["mars", "Mars"],
    "sat" => ["saturn", "Saturn"],
    "ven" => ["venus", "Venuše"],
    "mer" => ["mercury", "Merkur"],
    "urn" => ["uranus", "Uran"],
    "nep" => ["neptune", "Neptun"],
];

$constellationCZ = [
    "And" => "Andromeda",
    "Ant" => "Vývěva",
    "Aps" => "Rajka",
    "Aql" => "Orel",
    "Aqr" => "Vodnář",
    "Ara" => "Oltář",
    "Ari" => "Beran",
    "Aur" => "Vozka",
    "Boo" => "Pastýř",
    "CMa" => "Velký pes",
    "CMi" => "Malý pes",
    "CVn" => "Honicí psi",
    "Cnc" => "Rak",
    "Cae" => "Rydlo",
    "Cam" => "Žirafa",
    "Cap" => "Kozoroh",
    "Car" => "Kýl",
    "Cas" => "Kasiopeja",
    "Cen" => "Kentaur",
    "Cep" => "Cefeus",
    "Cet" => "Velryba",
    "Cha" => "Kameleón",
    "Cir" => "Kružítko",
    "Col" => "Holubice",
    "Com" => "Vlasy Bereniky",
    "CrA" => "Jižní koruna",
    "CrB" => "Severní koruna",
    "Crt" => "Pohár",
    "Cru" => "Jižní kříž",
    "Crv" => "Havran",
    "Cyg" => "Labuť",
    "Del" => "Delfín",
    "Dor" => "Mečoun",
    "Dra" => "Drak",
    "Equ" => "Hříbě",
    "Eri" => "Eridanus",
    "For" => "Pec",
    "Gem" => "Blíženci",
    "Gru" => "Jeřáb",
    "Her" => "Herkules",
    "Hor" => "Hodiny",
    "Hya" => "Hydra",
    "Hyi" => "Jižní Hydra",
    "Ind" => "Indián",
    "Lac" => "Ještěrka",
    "Leo" => "Lev",
    "Lep" => "Zajíc",
    "Lib" => "Váhy",
    "LMi" => "Malý lev",
    "Lup" => "Vlk",
    "Lyn" => "Rys",
    "Lyr" => "Lyra",
    "Men" => "Tabulová hora",
    "Mic" => "Mikroskop",
    "Mon" => "Jednorožec",
    "Mus" => "Moucha",
    "Nor" => "Kružítko",
    "Oct" => "Oktant",
    "Oph" => "Hadonoš",
    "Ori" => "Orion",
    "Pav" => "Páv",
    "Peg" => "Pegas",
    "Per" => "Perseus",
    "Phe" => "Fénix",
    "Pic" => "Malíř",
    "PsA" => "Jižní ryba",
    "Psc" => "Ryby",
    "Pup" => "Zád",
    "Pyx" => "Kompas",
    "Ret" => "Síť",
    "Scl" => "Sochař",
    "Sco" => "Štír",
    "Sct" => "Štít",
    "Ser" => "Had",
    "Sex" => "Sextant",
    "Sge" => "Šíp",
    "Sgr" => "Střelec",
    "Tau" => "Býk",
    "Tel" => "Teleskop",
    "TrA" => "Jižní trojúhelník",
    "Tri" => "Trojúhelník",
    "Tuc" => "Tukan",
    "UMa" => "Velká medvědice",
    "UMi" => "Malý medvěd",
    "Vel" => "Plachty",
    "Vir" => "Panna",
    "Vol" => "Létající ryba",
    "Vul" => "Lištička",
];


$constellationIcon = [
    "Beran" => "♈︎",
    "Býk" => "♉︎",
    "Blíženci" => "♊︎",
    "Rak" => "♋︎",
    "Lev" => "♌︎",
    "Panna" => "♍︎",
    "Váhy" => "♎︎",
    "Štír" => "♏︎",
    "Střelec" => "♐︎",
    "Kozoroh" => "♑︎",
    "Vodnář" => "♒︎",
    "Ryby" => "♓︎",
];


$id = isset($_GET['id']) ? $_GET['id'] : 'all';
$filtr = isset($_GET['f']) ? $_GET['f'] : 'yes';
$xmode = isset($_GET['m']) ? $_GET['m'] : 'N';

if ($id === 'all') {
    $planety = ["mer", "ven", "mar", "jup", "sat", "urn", "nep"];

    $mer = isset($_GET['mer']) ? $_GET['mer'] : 'yes';
    $ven = isset($_GET['ven']) ? $_GET['ven'] : 'yes';
    $mar = isset($_GET['mar']) ? $_GET['mar'] : 'yes';
    $jup = isset($_GET['jup']) ? $_GET['jup'] : 'yes';
    $sat = isset($_GET['sat']) ? $_GET['sat'] : 'yes';
    $urn = isset($_GET['urn']) ? $_GET['urn'] : 'yes';
    $nep = isset($_GET['nep']) ? $_GET['nep'] : 'yes';

} else {
    $planety = [$id];

}
function load_today_planet($path)
{
    if (file_exists($path . '.gz')) {
        $path .= '.gz';
    }
    // Načtení souboru
    $raw = file_get_contents($path);
    if ($raw === false)
        return null;
    // Pokud je gzip, dekomprimuj
    if (substr($path, -3) === '.gz') {
        $json = gzdecode($raw);
 
        if ($json === false)
      return null;
    } else {
        $json = $raw;
    }
 
    $data = json_decode($json, true);
    if (!is_array($data))
        return null;



    $today = (new DateTime('today'))->format('Y-m-d');

    foreach ($data as $row) {
        if (isset($row['date']) && $row['date'] === $today) {
            return $row;
        }
    }
    return null;
}

// ------------------------------------------------------------
// VYPOCET NOC
// Astronomický soumrak (výška = -10°),  noc (výška = -18°)

$dt = new DateTime("now",  $nowTZ);
$offsetHours = $dt->getOffset() / 3600;

// Souřadnice Vrkoslavice 

$latitude = 50.71;
$longitude = 15.18;

// VYPOCET NOC

//  "dnes" a "zítra" o půlnoci v UTC
$utcTZ = new DateTimeZone('UTC');
$day0  = new DateTimeImmutable('today', $utcTZ);      
$day1  = $day0->modify('+1 day');           


// data pro aktuální den
$info_today = date_sun_info($day0->getTimestamp(), $latitude, $longitude);

// data pro následující den
$info_next  = date_sun_info($day1->getTimestamp(), $latitude, $longitude);
// Astronomický soumrak (výška = -18°) → date_sun_info: astronomical_twilight_*
// - začátek noci (večer): konec astronomického soumraku
// - konec noci (ráno): začátek astronomického soumraku

$astro_start_ts = $info_today['astronomical_twilight_end'];   // večer
$astro_end_ts   = $info_next['astronomical_twilight_begin'];  // ráno

$astro_start = date('H:i', $astro_start_ts); 
$astro_end   = date('H:i', $astro_end_ts );

// večerní
$twilightC_start_ts = $info_today['civil_twilight_end'];    // -6°
$twilight_start_ts = $info_today['nautical_twilight_end']; //  -12° 

// ranní (následující den)
$twilight_end_ts = $info_next['nautical_twilight_begin'];   // -12°
$twilightC_end_ts = $info_next['civil_twilight_begin'];      // -6°

// Večer: mezi civil_twilight_end (–6°) a nautical_twilight_end (–12°)

$twilight_start = date('H:i', $twilight_start_ts -($twilight_start_ts - $twilightC_start_ts)/2 ); 
$twilight_end   = date('H:i', $twilight_end_ts - ($twilight_end_ts - $twilightC_end_ts)/2); 

?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="utf-8">
    <title><?= $name ?> – dnešní viditelnost</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #111;
            color: #eee;
        }

        .box {
            max-width:
                <?php if ($id === 'all'): ?>
                    1100px;
            <?php else: ?>
                700px;
            <?php endif; ?>
            margin: 20px auto;
            padding: 16px;
            background: #222;
            border: 1px solid #444;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        td {
            padding: 4px 0;
        }

        .label {
            color: #aaa;
            width: 35%;
        }

        .popis {
            color: #aaa;
            width: 35%;
        }

        .value {
            font-weight: 600;
        }

        svg {
            width: 100%;
            height: 220px;
            background: #111;
            border: 1px solid #444
        }

        .axis {
            stroke: #555;
            stroke-width: 1;
        }

        .graph-line {
            fill: none;
            stroke: #4caf50;
            stroke-width: 2;
        }

        .graph-fill {
            fill: rgba(76, 175, 80, 0.25);
            stroke: none;
        }

        .graph-night {
            fill: rgba(1, 3, 36, 0.25);
            stroke: none;
        }

        .graph-day {
            fill: rgba(86, 86, 90, 0.25);
            stroke: none;
        }

        .graph-tw {
            fill: rgba(50, 50, 69, 0.25);
            stroke: none;
        }

        .text-small {
            font-size: 11px;
            fill: #aaa;
        }

        .body a {
            color: blue;
            text-decoration: none;
        }

        .body a:hover {
            color: white;
            text-decoration: none;
        }
    </style>
    </style>
</head>
<body>
    <div class="box">
        <?php if ($filtr === 'yes' && $id === 'all'): ?>
            <h2>Planety</h2>
            <h3><strong>Dnes je tma:</strong> <?=  date('H:i', $twilight_start_ts) ?>  - <?=  date('H:i', $twilight_end_ts) ?> &nbsp; (Astro: <?= $astro_start ?>  - <?= $astro_end ?>)</h3>
            <br>
            <form method="get">
                <label>
                    <input type="hidden" id="f" name="f" value="yes" />
                </label>
                <label>Režím noc:
                    <input type="radio" id="m" name="m" value="N" <?php if ($xmode === 'N'): ?> checked <?php endif; ?> />
                    &nbsp;
                    Transit:
                    <input type="radio" id="m" name="m" value="T" <?php if ($xmode === 'T'): ?> checked <?php endif; ?> />
                    &nbsp;
                </label>

                <label>Merkur:
                    <input type="hidden" id="mer" name="mer" value="no" />
                    <input type="checkbox" id="mer" name="mer" value="yes" <?php if ($mer === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label>Venuše:
                    <input type="hidden" id="ven" name="ven" value="no" />
                    <input type="checkbox" id="ven" name="ven" value="yes" <?php if ($ven === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label> Mars:
                    <input type="hidden" id="mar" name="mar" value="no" />
                    <input type="checkbox" id="mar" name="mar" value="yes" <?php if ($mar === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label> Jupiter:
                    <input type="hidden" id="jup" name="jup" value="no" />
                    <input type="checkbox" id="jup" name="jup" value="yes" <?php if ($jup === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label> Saturn:
                    <input type="hidden" id="sat" name="sat" value="no" />
                    <input type="checkbox" id="sat" name="sat" value="yes" <?php if ($sat === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label> Uran:
                    <input type="hidden" id="urn" name="urn" value="no" />
                    <input type="checkbox" id="urn" name="urn" value="yes" <?php if ($urn === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <label> Neptun:
                    <input type="hidden" id="nep" name="nep" value="no" />
                    <input type="checkbox" id="nep" name="nep" value="yes" <?php if ($nep === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
                </label>
                <button type="submit">Zobrazit</button>
            </form>
            <br>
        <?php endif; ?>
        <?php
        foreach ($planety as $myid):
            if ($id === 'all') {
                if ($myid === 'mer' && $mer !== 'yes')
                    continue;
                if ($myid === 'ven' && $ven !== 'yes')
                    continue;
                if ($myid === 'mar' && $mar !== 'yes')
                    continue;
                if ($myid === 'jup' && $jup !== 'yes')
                    continue;
                if ($myid === 'sat' && $sat !== 'yes')
                    continue;
                if ($myid === 'urn' && $urn !== 'yes')
                    continue;
                if ($myid === 'nep' && $nep !== 'yes')
                    continue;
            }
            [$planeta, $name] = $map[$myid];
            $planet = load_today_planet($json_dir . "/" . $planeta . '_ephemeris.json');

            // základní údaje
            $rise = $planet['rise_utc'] ? date('H:i', strtotime($planet['rise_utc'])) : '—';
            $set = $planet['set_utc'] ? date('H:i', strtotime($planet['set_utc'])) : '—';
            $transit = $planet['transit_utc'] ? date('H:i', strtotime($planet['transit_utc'])) : '—';
            // -----------------------------
            // Určení typu viditelnosti
            // -----------------------------
            $visibleWord = $myid === 'ven' ? 'viditelná' : 'viditelný';

            $visibility = "na obloze";

            if (!empty($planet['transit_utc'])) {
                $t = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));
                $transitH = (int) $t->format('H');

                if ($transitH >= 7 && $transitH < 15) {
                    $visibility = "na denní obloze";
                } elseif ($transitH >= 15 && $transitH < 21) {
                    $visibility = "na večerní obloze";
                } elseif ($transitH >= 21 && $transitH < 24) {
                    $visibility = "na noční obloze";
                } elseif ($transitH >= 0 && $transitH < 2) {
                    $visibility = "na noční obloze";
                } elseif ($transitH >= 2 && $transitH < 7) {
                    $visibility = "na ranní obloze";
                }
            }

            // -----------------------------
            // Načtení doplňkových údajů z prvního bodu grafu
            // (všechny body mají stejné hodnoty distance/constellation)
            // -----------------------------
            $first = $planet['altitude_graph'][0] ?? null;
            $distAU = $first ? $first['distance_au'] : null;
            $distKM = $first ? $first['distance_km'] : null;
            $angSize = $first ? $first['angular_size_arcsec'] : null;
            $elong = $first ? $first['elongation_deg'] : null;
            $opposition = $planet['nearest_opposition'] ?? null;
            $constCode = $first ? $first['constellation'] : null;
            $perihelion = $planet['nearest_perihelion'] ?? null;
            $const = $constCode && isset($constellationCZ[$constCode]) ? $constellationCZ[$constCode] : $constCode;
            $icon = isset($constellationIcon[$const]) ? $constellationIcon[$const] : "";

            // původní 48h graf z JSONu
            $graph = $planet['altitude_graph'] ?? [];

            // ---------------------------------------------
            // 1) Dynamický začátek grafu podle kulminace
            // ---------------------------------------------
        
            if ($xmode !== "N") {
                $startMinutes = 0;
                if (!empty($planet['transit_utc'])) {
                    $t = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));

                    // kulminace v minutách
                    $transitMinutes = $t->format('H') * 60 + $t->format('i');

                    // zaokrouhlení na celé hodiny
                    $roundedTransit = round($transitMinutes / 60) * 60;

                    // začátek grafu = kulminace - 12 hodin
                    $startMinutes = $roundedTransit - 12 * 60;

                    // přetočení do rozsahu 0–1440
                    while ($startMinutes < 0)
                        $startMinutes += 1440;
                    while ($startMinutes >= 1440)
                        $startMinutes -= 1440;
                }
            } else {
                $startMinutes = 720;  //začneme v poledne
            }

            // -----------------------------
            // 2) Vybrat správné 24h okno z 48h dat
            // -----------------------------
            $filtered = [];
            foreach ($graph as $p) {
                // day_offset z JSONu (0 = dnešek, 1 = zítřek); fallback 0 pro jistotu
                $dayOffset = isset($p['day_offset']) ? (int) $p['day_offset'] : 0;

                [$hStr, $mStr] = explode(':', $p['time']);
                $ptMinutes = (int) $hStr * 60 + (int) $mStr + $dayOffset * 1440; // 48h osa
        
                // rozdíl vůči začátku grafu
                $diff = $ptMinutes - $startMinutes;
                if ($diff < 0) {
                    $diff += 2880; // přetočení v rámci 48h okna
                }

                // bereme jen 24h interval
                if ($diff < 1440) {
                    $filtered[] = $p;
                }
            }
            $graph = $filtered;
            $n = count($graph);

            // -----------------------------
            // 3) Zjištění max. výšky po filtrování
            // -----------------------------
            $maxAlt = 0.0;
            foreach ($graph as $p) {
                if ($p['alt'] > $maxAlt)
                    $maxAlt = $p['alt'];
            }
            if ($maxAlt < 10)
                $maxAlt = 10;
            ?>
            <?php if (!$planet): ?>
                <p>Data pro planet dnes nejsou k dispozici.</p>
            <?php else: ?>
                <?php if ($filtr === 'yes' && $id !== 'all'): ?>
                    <h1><?= $name ?> <?= htmlspecialchars($planet['date']); ?>             <?= $visibleWord ?>             <?= $visibility ?></h1>
                    <br>
                    <form method="get">
                        <label>
                            <input type="hidden" id="f" name="f" value="yes" />
                        </label>
                        <label>Režím noc:
                            <input type="radio" id="m" name="m" value="N" <?php if ($xmode === 'N'): ?> checked <?php endif; ?> />
                            &nbsp;
                            Transit:
                            <input type="radio" id="m" name="m" value="T" <?php if ($xmode === 'T'): ?> checked <?php endif; ?> />
                            &nbsp;
                        </label>
                        <button type="submit">Zobrazit</button>
                    </form>
                    <br>
                <?php else: ?>
                    <h1><?= $name ?>    <?= htmlspecialchars($planet['date']); ?>             <?= $visibleWord ?>             <?= $visibility ?></h1>
                <?php endif; ?>
                <?php if ($id === 'all'): ?>
                    <table>
                        <tr>
                            <td width="450">
                            <?php endif; ?>
                            <table>
                                <tr>
                                    <td class="label">Východ</td>
                                    <td class="value"><?= $rise; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Kulminace</td>
                                    <td class="value"><?= $transit; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Západ</td>
                                    <td class="value"><?= $set; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Souhvězdí</td>
                                    <td class="value"> <?= $const ?> </td>
                                </tr>
                                <tr>
                                    <td class="label">Vzdálenost</td>
                                    <td class="value"><?= $distAU ?> AU (<?= number_format($distKM, 0, ',', ' ') ?> km)</td>
                                </tr>
                                <tr>
                                    <td class="label">Úhlová velikost</td>
                                    <td class="value"><?= $angSize ?>"</td>
                                </tr>
                                <tr>
                                    <td class="label">Elongace</td>
                                    <td class="value"><?= $elong ?>°</td>
                                </tr>
                                <?php if (!empty($opposition)): ?>
                                    <tr>
                                        <td class="label">Nejbližší opozice</td>
                                        <td class="value"><?= $opposition ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($perihelion)): ?>
                                    <tr>
                                        <td class="label">Nejbližší perihelium</td>
                                        <td class="value"><?= $perihelion ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                            <?php if ($id === 'all'): ?>
                            <td widtd="700">
                            <?php endif; ?>
                            <?php
                            // SVG parametry
                            $width = 600;
                            $height = 200;
                            $paddingLeft = 30;
                            $paddingRight = 10;
                            $paddingTop = 10;
                            $paddingBottom = 20;

                            $innerW = $width - $paddingLeft - $paddingRight;
                            $innerH = $height - $paddingTop - $paddingBottom;

                            // Noční barvy
                            $nightStart = $paddingLeft + ($innerW * ((date('H', strtotime($astro_start)) * 60 +
                                                         date('i', strtotime($astro_start)) - (12 + $offsetHours) * 60) / (25 * 60)));
                            $nightEnd = $paddingLeft + ($innerW * ((date('H', strtotime($astro_end)) * 60 + 
                                                        date('i', strtotime($astro_end)) + (12 - $offsetHours)  * 60) / (25 * 60)));
                            $twStart = $paddingLeft + ($innerW * ((date('H', strtotime($twilight_start)) * 60 + 
                                                    date('i', strtotime($twilight_start)) - (12 + $offsetHours) * 60) / (25 * 60)));
                            $twEnd = $paddingLeft + ($innerW * ((date('H', strtotime($twilight_end)) * 60 + 
                                                    date('i', strtotime($twilight_end)) + (12-$offsetHours) * 60) / (25 * 60)));

                            //  Noc
                            $night = $nightStart . ',' . $paddingTop . ',' .
                                $nightStart . ',' . ($height - $paddingBottom) . ',' .
                                $nightEnd . ',' . ($height - $paddingBottom) . ',' .
                                $nightEnd . ',' . $paddingTop;

                            // stmívání a úsvit
                            $afternoon = $paddingLeft . ',' . $paddingTop . ',' .
                                $paddingLeft . ',' . ($height - $paddingBottom) . ',' .
                                $twStart . ',' . ($height - $paddingBottom) . ',' .
                                $twStart . ',' . $paddingTop;

                            $morning = $twEnd . ',' . $paddingTop . ',' .
                                $twEnd . ',' . ($height - $paddingBottom) . ',' .
                                ($width - $paddingRight) . ',' . ($height - $paddingBottom) . ',' .
                                ($width - $paddingRight) . ',' . $paddingTop;

                            $tw_start = $twStart . ',' . $paddingTop . ',' .
                                $twStart . ',' . ($height - $paddingBottom) . ',' .
                                $nightStart . ',' . ($height - $paddingBottom) . ',' .
                                $nightStart . ',' . $paddingTop;

                            $tw_end = $nightEnd . ',' . $paddingTop . ',' .
                                $nightEnd . ',' . ($height - $paddingBottom) . ',' .
                                $twEnd . ',' . ($height - $paddingBottom) . ',' .
                                $twEnd . ',' . $paddingTop;

                            // -----------------------------
                            // 4) Výpočet X/Y souřadnic bodů
                            // -----------------------------
                            $points = [];
                            if ($n > 0) {
                                foreach ($graph as $p) {
                                    [$hStr, $mStr] = explode(':', $p['time']);
                                    $ptMinutes = (int) $hStr * 60 + (int) $mStr;

                                    // rozdíl vůči začátku grafu (24h okno)
                                    $diff = $ptMinutes - $startMinutes;
                                    if ($diff < 0) {
                                        $diff += 1440;
                                    }

                                    $ratio = $diff / 1440.0;
                                    $x = $paddingLeft + $innerW * $ratio;

                                    $y = $paddingTop + $innerH * (1 - ($p['alt'] / $maxAlt));

                                    $points[] = [$x, $y, $p['time'], $p['alt']];
                                }
                            }

                            // -----------------------------
                            // 5) Transit X
                            // -----------------------------
                            $transitX = null;
                            if (!empty($planet['transit_utc'])) {
                                $dt = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));
                                $transitMinutes = $dt->format('H') * 60 + $dt->format('i');

                                $diff = $transitMinutes - $startMinutes;
                                if ($diff < 0)
                                    $diff += 1440;

                                $ratio = $diff / 1440;
                                $transitX = $paddingLeft + $innerW * $ratio;
                            }

                            // -----------------------------
                            // 6) Osa Y
                            // -----------------------------
                            if ($maxAlt >= 40)
                                $yStep = 20;
                            elseif ($maxAlt >= 20)
                                $yStep = 10;
                            else
                                $yStep = 5;

                            $yLines = floor($maxAlt / $yStep);

                            // -----------------------------
                            // 7) Osa X – popisky (převod do CET)
                            // -----------------------------
                            $xStepMinutes = 30;
                            $xSteps = (24 * 60) / $xStepMinutes;
                            ?>

                            <svg viewBox="0 0 <?= $width ?> <?= $height ?>">

                                <?php for ($i = 1; $i <= $yLines; $i++): // nezačínáme od 0°, aby nebyla falešná čára ?>
                                    <?php
                                    $altVal = $i * $yStep;
                                    $gy = $paddingTop + $innerH * (1 - ($altVal / $maxAlt));
                                    ?>
                                    <line x1="<?= $paddingLeft ?>" y1="<?= $gy ?>" x2="<?= $width - $paddingRight ?>"
                                        y2="<?= $gy ?>" stroke="#333" stroke-width="1" />

                                    <text x="<?= $paddingLeft - 6 ?>" y="<?= $gy + 4 ?>" class="text-small" text-anchor="end">
                                        <?= $altVal ?>°
                                    </text>
                                <?php endfor; ?>

                                <?php if ($xmode === "N"): ?>
                                    <!-- Stmívání -->
                                    <polygon points="<?= $afternoon ?>" class="graph-day" /> ;

                                    <polygon points="<?= $tw_start ?>" class="graph-tw" /> ;

                                    <polygon points="<?= $night ?>" class="graph-night" /> ;

                                    <polygon points="<?= $tw_end ?>" class="graph-tw" /> ;

                                    <polygon points="<?= $morning ?>" class="graph-day" /> ;
                                <?php endif; ?>

                                <?php for ($i = 0; $i <= $xSteps; $i++):
                                    $vx = $paddingLeft + $innerW * ($i / $xSteps);

                                    $totalMinutes = $startMinutes + $i * $xStepMinutes;
                                    $utcHour = floor(($totalMinutes / 60)) % 24;
                                    $utcMinute = $totalMinutes % 60;

                                    $dt = new DateTime(sprintf('%02d:%02d', $utcHour, $utcMinute), new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('Europe/Prague'));

                                    $hour = (int) $dt->format('H');
                                    $minute = (int) $dt->format('i');

                                    $isLabel = ($minute === 0) && ($hour % 2 === 0);
                                    ?>
                                    <line x1="<?= $vx ?>" y1="<?= $paddingTop ?>" x2="<?= $vx ?>"
                                        y2="<?= $height - $paddingBottom ?>" stroke="#333" stroke-width="1" />

                                    <?php if ($isLabel): ?>
                                        <text x="<?= $vx ?>" y="<?= $height - $paddingBottom + 12 ?>" class="text-small"
                                            text-anchor="middle">
                                            <?= sprintf("%02d:00", $hour) ?>
                                        </text>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <line x1="<?= $paddingLeft ?>" y1="<?= $height - $paddingBottom ?>"
                                    x2="<?= $width - $paddingRight ?>" y2="<?= $height - $paddingBottom ?>" class="axis" />
                                <line x1="<?= $paddingLeft ?>" y1="<?= $paddingTop ?>" x2="<?= $paddingLeft ?>"
                                    y2="<?= $height - $paddingBottom ?>" class="axis" />
                                <text x="<?= $paddingLeft - 35 ?>" y="<?= ($height / 2) ?>" class="text-small"
                                    text-anchor="middle" transform="rotate(-90 <?= $paddingLeft - 35 ?>,<?= ($height / 2) ?>)">
                                    Výška (°)
                                </text>

                                <?php if (!empty($points)):
                                    // polygon jen když má smysl (aby se minimalizovaly artefakty u nízkých výšek)
                                    $poly = [];
                                    foreach ($points as $pt)
                                        $poly[] = $pt[0] . ',' . $pt[1];
                                    $poly[] = end($points)[0] . ',' . ($height - $paddingBottom);
                                    $poly[] = $points[0][0] . ',' . ($height - $paddingBottom);
                                    $polyPoints = implode(' ', $poly);

                                    $linePoints = implode(' ', array_map(fn($pt) => $pt[0] . ',' . $pt[1], $points));
                                    ?>
                                    <?php if ($maxAlt > 3): ?>
                                        <polygon points="<?= $polyPoints ?>" class="graph-fill" />
                                    <?php endif; ?>
                                    <polyline points="<?= $linePoints ?>" class="graph-line" />
                                <?php endif; ?>

                                <?php if ($transitX !== null): ?>
                                    <line x1="<?= $transitX ?>" y1="<?= $paddingTop ?>" x2="<?= $transitX ?>"
                                        y2="<?= $height - $paddingBottom ?>" stroke="red" stroke-width="1.5" />
                                <?php endif; ?>
                            </svg>
                        <?php endif; ?>

                        <?php if ($id === 'all'): ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</body>
</html>
