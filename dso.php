<?php
date_default_timezone_set('Europe/Prague');
/*  astrovsb.php
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

$nowTZ = new DateTimeZone("Europe/Prague");
$nowTime = date('Y-m-d H:i');

// Souřadnice Vrkoslavice 
$latitude = 50.71;
$longitude = 15.18;

$dt = new DateTime("now", $nowTZ);
$offsetHours = $dt->getOffset() / 3600;
$json_dir = "/opt/astro_json";


// ------------------------------------------------------------
// VYPOCET NOC

//  "dnes" a "zítra" o půlnoci v UTC
$utcTZ = new DateTimeZone('UTC');
$day0 = new DateTimeImmutable('today', $utcTZ);
$day1 = $day0->modify('+1 day');

// data pro aktuální den
$info_today = date_sun_info($day0->getTimestamp(), $latitude, $longitude);

// data pro následující den
$info_next = date_sun_info($day1->getTimestamp(), $latitude, $longitude);

// Astronomický soumrak (výška = -18°) → date_sun_info: astronomical_twilight_*
// - začátek noci (večer): konec astronomického soumraku
// - konec noci (ráno): začátek astronomického soumraku

$astro_start_ts = $info_today['astronomical_twilight_end'];   // večer
$astro_end_ts = $info_next['astronomical_twilight_begin'];  // ráno

$astro_start = date('H:i', $astro_start_ts);
$astro_end = date('H:i', $astro_end_ts);

// večerní soumrak
$twilightC_start_ts = $info_today['civil_twilight_end'];    // -6°
$twilight_start_ts = $info_today['nautical_twilight_end']; //  -12° 

// ranní svítání
$twilight_end_ts = $info_next['nautical_twilight_begin'];   // -12°
$twilightC_end_ts = $info_next['civil_twilight_begin'];      // -6°

// Soumrak a svítaní mezi civil_twilight_end (–6°) a nautical_twilight_end (–12°)

$twilight_start = date('H:i', $twilight_start_ts - ($twilight_start_ts - $twilightC_start_ts) / 2);
$twilight_end = date('H:i', $twilight_end_ts - ($twilight_end_ts - $twilightC_end_ts) / 2);

// ------------------------------------------------------------

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

$objectTypeCZ = [
    "GAL"   => "Galaxie",
    "NEB"   => "Mlhovina",
    "OC"    => "Otevřená hvězdokupa",
    "GC"    => "Kulová hvězdokupa",
    "PN"    => "Planetární mlhovina",
    "SNR"   => "Pozůstatek po supernově",
    "DN"    => "Temná mlhovina",
    "QSO"   => "Kvasar",
    "STAR"  => "Hvězda",
    "DOUBLE"=> "Dvojhvězda",
];


function load_katalog($path)
{
  $json = file_get_contents($path);
  if ($json === false)
    return null;
  $data = json_decode($json, true);
  if (!is_array($data))
    return null;
  return $data;
}

// GET Parmaetry
$filtr = isset($_GET['f']) ? $_GET['f'] : 'yes';
$id = isset($_GET['id']) ? $_GET['id'] : 'all';
$katalog = isset($_GET['katalog']) ?  $_GET['katalog'] : "messier";

$vmag = isset($_GET['vmag']) ? max(1, (int) $_GET['vmag']) : 24;

$gal  = isset($_GET['gal']) ? $_GET['gal'] : 'yes';
$neb  = isset($_GET['neb']) ? $_GET['neb'] : 'yes';
$oc  = isset($_GET['oc']) ? $_GET['oc'] : 'yes';
$gc  = isset($_GET['gc']) ? $_GET['gc'] : 'yes';
$pn  = isset($_GET['pn']) ? $_GET['pn'] : 'yes';
$snr  = isset($_GET['snr']) ? $_GET['snr'] : 'yes';
$dn  = isset($_GET['dn']) ? $_GET['dn'] : 'yes';
$qso  = isset($_GET['qso']) ? $_GET['qso'] : 'yes';
$star  = isset($_GET['star']) ? $_GET['star'] : 'yes';
$double  = isset($_GET['double']) ? $_GET['double'] : 'yes';

// Reset když není nic vybráno 
if ( $gal === "no" && $neb === "no" && $oc === "no" &&  $gc === "no" &&  $pn === "no" && 
     $snr === "no" && $dn === "no" && $qso === "no" && $star === "no" &&  $double === "no" ) {
  $gal = "yes";
  $neb = "yes";  
  $oc = "yes";
  $gc = "yes";
  $pn = "yes"; 
  $snr = "yes"; 
  $dn = "yes"; 
  $qso = "yes"; 
  $star ="yes";
  $double = "yes";
}


$tam = isset($_GET['am']) ? $_GET['am'] : 'yes';
$tpm = isset($_GET['pm']) ? $_GET['pm'] : 'yes';
// Reset když není nic vybráno 
if ($tam === 'no' && $tpm === 'no') {
  $tpm = "yes";
  $tam = "yes";
}


$data = load_katalog($json_dir . '/' . $katalog . '_ephemeris.json');
$objects = $data['objects'] ?? [];



// funkce RA → H M S
function ra_to_hms($ra_hours)
{
  $h = floor($ra_hours);
  $m_float = ($ra_hours - $h) * 60;
  $m = floor($m_float);
  $s = ($m_float - $m) * 60;
  return sprintf("%02dh %02dm %04.1fs", $h, $m, $s);
}

// funkce Dec → ° ′ ″
function dec_to_dms($dec_deg)
{
  $sign = ($dec_deg < 0) ? '-' : '+';
  $dec_deg = abs($dec_deg);

  $d = floor($dec_deg);
  $m_float = ($dec_deg - $d) * 60;
  $m = floor($m_float);
  $s = ($m_float - $m) * 60;

  return sprintf("%s%02d° %02d′ %04.1f″", $sign, $d, $m, $s);
}

// zaokrouhlení času na 30 minut (UTC) – jen pro info v hlavičce
function roundTo30($dt)
{
  $m = (int) $dt->format('i');
  $rounded = ($m < 15) ? 0 : (($m < 45) ? 30 : 60);
  if ($rounded === 60) {
    $dt->modify('+1 hour');
    $rounded = 0;
  }
  return $dt->format('Y-m-d H:' . sprintf('%02d', $rounded));
}


// $nowTime = date('Y-m-d H:i');

$nowCET = new DateTime('now', new DateTimeZone('Europe/Prague'));
$nowTimeR = roundTo30(clone $nowCET);

$nowUTC = new DateTime('now', new DateTimeZone('UTC'));
$rounded = roundTo30(clone $nowUTC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Aktuální viditelnost</title>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #111;
      color: #eee;
    }

    .box {
      max-width: 1100px;
      margin: 20px auto;
      padding: 16px;
      background: #222;
      border: 1px solid #444;
    }

    h1 {
      font-size: 22px;
      margin: 0 0 15px;
    }

    h2 {
      font-size: 18px;
      margin: 0 0 6px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    .main-table tr+tr {
      border-top: 1px solid #444;
    }

    .main-table td {
      padding: 10px 6px;
      vertical-align: top;
    }

    .inner-table {
      width: 100%;
      border-collapse: collapse;
    }

    .inner-table td {
      padding: 2px 0; 
    }

    .label {
      color: #aaa;
      width: 40%;
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
</head>
<body>
  <div class="box">
    <?php if ($filtr === 'yes'): ?>
      <h1>Aktuální viditelnost</h1>
      <br>
      <form method="get">
        <label>
          <input type="hidden" id="f" name="f" value="yes" />
        </label>
        <label>Katalog:
          <select id="katalog" name="katalog" >
              <option <?php if ($katalog === 'messier'): ?> selected <?php endif; ?> value="messier">Messier</option>  
              <option <?php if ($katalog === 'caldwell'): ?> selected <?php endif; ?> value="caldwell">Cadwell</option>  
           </select>   
        </label> &nbsp; &nbsp;
        
        <label>Jasnost:
          <input type="number" min="-10" max="25" id="vmag" name="vmag" value="<?= $vmag ?>" />mag &nbsp;
        </label>&nbsp;
        <label>Kulminace PM:
          <input type="hidden" id="pm" name="pm" value="no" />
          <input type="checkbox" id="pm" name="pm" value="yes" <?php if ($tpm === 'yes'): ?> checked <?php endif; ?> /> 
        </label>&nbsp;
        <label>AM:
          <input type="hidden" id="am" name="am" value="no" />
          <input type="checkbox" id="am" name="am" value="yes" <?php if ($tam === 'yes'): ?> checked <?php endif; ?> />  &nbsp; &nbsp;
        </label>

        <br> <br>
              
        <label>Galaxie:
          <input type="hidden" id="gal" name="gal" value="no" />
          <input type="checkbox" id="gal" name="gal" value="yes" <?php if ($gal === 'yes'): ?> checked <?php endif; ?> />  &nbsp; &nbsp;
        </label>
        <label>Mlhoviny:
          <input type="hidden" id="neb" name="neb" value="no" />
          <input type="checkbox" id="neb" name="neb" value="yes" <?php if ($neb === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>Plantární:
          <input type="hidden" id="pn" name="pn" value="no" />
          <input type="checkbox" id="pn" name="pn" value="yes" <?php if ($pn === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>Temné:
          <input type="hidden" id="dn" name="dn" value="no" />
          <input type="checkbox" id="dn" name="dn" value="yes" <?php if ($dn === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>SNR:
          <input type="hidden" id="snr" name="snr" value="no" />
          <input type="checkbox" id="snr" name="snr" value="yes" <?php if ($snr === 'yes'): ?> checked <?php endif; ?> /> &nbsp; &nbsp;
        </label>
        <label>Hvězdy:
          <input type="hidden" id="star" name="star" value="no" />
          <input type="checkbox" id="star" name="star" value="yes" <?php if ($star === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>Otevřené:
          <input type="hidden" id="oc" name="oc" value="no" />
          <input type="checkbox" id="oc" name="oc" value="yes" <?php if ($oc === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>Kulové:
          <input type="hidden" id="gc" name="gc" value="no" />
          <input type="checkbox" id="gc" name="gc" value="yes" <?php if ($gc === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label>
        <label>Dvojité:
          <input type="hidden" id="double" name="double" value="no" />
          <input type="checkbox" id="double" name="double" value="yes" <?php if ($double === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label> &nbsp;  
        <label>Kvasar:
          <input type="hidden" id="qso" name="qso" value="no" />
          <input type="checkbox" id="qso" name="qso" value="yes" <?php if ($qso === 'yes'): ?> checked <?php endif; ?> /> &nbsp;
        </label> <br><br>

        <button type="submit">Zobrazit</button>
      </form>
    <?php else: ?>

     <h1>Aktuální viditelnost</h1>

    <?php endif; ?>

    <p>Data jsou plantá pro čas: <stron><?= htmlspecialchars($nowTimeR) ?></strong></p>

    <table class="main-table">
      <?php
      $shown = 0;
      foreach ($objects as $c):
        // výběr pouze daného objektu  
        if ($id !== "all" &&  $id  !== $c['id'])
          continue;

        


        $graph48 = $c['graph'] ?? [];
        
        if (count($graph48) < 2)
          continue;

        // najdeme nejbližší časový bod k zaokrouhlenému času – pro aktuální údaje
        $roundedUTC = new DateTime($rounded, new DateTimeZone('UTC'));
        $roundedTs = $roundedUTC->getTimestamp();

        $current = null;
        $bestDiff = PHP_INT_MAX;

        foreach ($graph48 as $p) {
          $pt = new DateTime($p['time_utc'], new DateTimeZone('UTC'));
          $diff = abs($pt->getTimestamp() - $roundedTs);
          if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $current = $p;
          }
        }

        if (!$current)
          continue;

        // RA/Dec v HMS/DMS
        $ra_hms = ra_to_hms($current['ra_hours_j2000']);
        $dec_dms = dec_to_dms($current['dec_deg_j2000']);

        // základní hodnoty v aktuálním čase
        $alt = sprintf("%.1f°", $current['alt_deg']);
        $az = sprintf("%.1f°", $current['az_deg']);
        $mag = $current['mag'];
      
        //  výběr dle jasnosti        
        if ( (float)$vmag < (float)$mag ) 
            continue;



        $constCode = $current['constellation'];
        $constName = $constellationCZ[$constCode] ?? $constCode;
        // východ / kulminace / západ
        $rise = !empty($c['rise_utc']) ? date('H:i', strtotime($c['rise_utc'])) : '—';
        $transit = !empty($c['transit_utc']) ? date('H:i', strtotime($c['transit_utc'])) : '—';
        $set = !empty($c['set_utc']) ? date('H:i', strtotime($c['set_utc'])) : '—';


        // ------------------------------------------------------------
        // OSA X: pevně 12:00 UTC → 12:00 UTC následující den (24 h)
        // ------------------------------------------------------------
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $dayStr = $nowUTC->format('Y-m-d');

        // začátek grafu = dnešní 12:00 UTC
        $startDT = new DateTime($dayStr . ' 12:00:00', new DateTimeZone('UTC'));

        // konec grafu = +24 hodin
        $endDT = clone $startDT;
        $endDT->modify('+24 hours');

        $startTs = $startDT->getTimestamp();
        $endTs = $endDT->getTimestamp();
        $spanSec = 24 * 3600;

        // ------------------------------------------------------------
        // VÝBĚR BODŮ Z JSONU — jen ty, které spadají do 12→12 UTC
        // ------------------------------------------------------------
        $graph = [];
        foreach ($graph48 as $p) {
          $dt = new DateTime($p['time_utc'], new DateTimeZone('UTC'));
          $ts = $dt->getTimestamp();
          if ($ts >= $startTs && $ts <= $endTs) {
            $p['_dt'] = $dt;
            $p['_ts'] = $ts;
            $graph[] = $p;
          }
        }

        // ------------------------------------------------------------
        // JSON nezačíná přesně v 12:00 UTC → doplníme umělý bod
        // ------------------------------------------------------------
        $firstAlt = $graph[0]['alt_deg'];

        array_unshift($graph, [
          'time_utc' => $startDT->format('Y-m-d H:i:s'),
          'alt_deg' => $firstAlt,
          '_dt' => clone $startDT,
          '_ts' => $startTs
        ]);


        // ------------------------------------------------------------
        // MAX výška pro Y osu
        // ------------------------------------------------------------
        $maxAlt = 0.0;
        foreach ($graph as $p) {
          if ($p['alt_deg'] > $maxAlt)
            $maxAlt = $p['alt_deg'];
        }
        $mAlt = $maxAlt;
        if ($maxAlt < 10)
          $maxAlt = 10;
        // ------------------------------------------------------------
        // SVG parametry
        // ------------------------------------------------------------
        $width = 600;
        $height = 200;
        $paddingLeft = 30;
        $paddingRight = 10;
        $paddingTop = 10;
        $paddingBottom = 20;

        $innerW = $width - $paddingLeft - $paddingRight;
        $innerH = $height - $paddingTop - $paddingBottom;

        // Noční barvy
        $nightStart = $paddingLeft + ($innerW * ((date('H', strtotime($astro_start)) * 60
          + date('i', strtotime($astro_start)) - (12 + $offsetHours) * 60) / (24 * 60)));
        $nightEnd = $paddingLeft + ($innerW * ((date('H', strtotime($astro_end)) * 60
          + date('i', strtotime($astro_end)) + (12 - $offsetHours) * 60) / (24 * 60)));
        $twStart = $paddingLeft + ($innerW * ((date('H', strtotime($twilight_start)) * 60
          + date('i', strtotime($twilight_start)) - (12 + $offsetHours) * 60)) / (24 * 60));
        $twEnd = $paddingLeft + ($innerW * ((date('H', strtotime($twilight_end)) * 60
          + date('i', strtotime($twilight_end)) + (12 - $offsetHours) * 60)) / (24 * 60));

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

        // ------------------------------------------------------------
        // BODY GRAFU (x = UTC ratio, y = alt)
        // ------------------------------------------------------------
        $points = [];
        foreach ($graph as $p) {
          $ts = $p['_ts'];
          $ratio = ($ts - $startTs) / $spanSec;
          if ($ratio < 0)
            $ratio = 0;
          if ($ratio > 1)
            $ratio = 1;

          $x = $paddingLeft + $innerW * $ratio;

          $altPlot = max(0, $p['alt_deg']);
          $y = $paddingTop + $innerH * (1 - ($altPlot / $maxAlt));

          $points[] = [$x, $y];
        }


        // ------------------------------------------------------------
        // OSA Y — krok podle max výšky
        // ------------------------------------------------------------
        if ($maxAlt >= 40)
          $yStep = 20;
        elseif ($maxAlt >= 20)
          $yStep = 10;
        else
          $yStep = 5;
        $yLines = floor($maxAlt / $yStep);

        // ------------------------------------------------------------
        // KULMINACE – výpočet nezávislý na mřížce i bodech grafu
        // ------------------------------------------------------------
        $transitX = null;

        if (!empty($c['transit_utc'])) {

          // 1) Z JSON vezmeme jen čas (HH:MM)
          $dtT = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
          $hT = (int) $dtT->format('H');
          $mT = (int) $dtT->format('i');

          $utcTm = $hT * 60 + $mT;

          // 3) Pokud je kulminace < 12:00 → posuneme na další den
          if ($hT < 12) {
            $hT += 24;
          }

          // 2) Převod na desetinné hodiny
          $hT = $hT + $mT / 60.0;

          // 4) Odpočítáme 12:00 → tím dostaneme pozici v grafu (0–24 h)
          $hFromStart = $hT - 12.0;   // 12:00 = 0h, 36:00 = 24h
      
          // 5) Přepočet na ratio 0–1
          $ratioT = $hFromStart / 24.0;

          // 6) Přepočet na pixely
          $transitX = $paddingLeft + $innerW * $ratioT;
        } else {
          //když neni nemělo by nastat
          $utcTm = 12*60;
        }
        // Posun Astro půlnoc pro 15°E na 23:00 UTC
        $DAY_MINUTE = 24 * 60; 
        $utcTm = $utcTm + 60;
        if ($utcTm >= $DAY_MINUTE) {
             $utcTm = $utcTm - $DAY_MINUTE;
        }
        
        if ($tpm === 'no' && $utcTm > (12*60) )
                    continue;
        if ($tam === 'no' && $utcTm <= (12*60) )
                    continue;

        $shown++;
        $type = $c['type'];
        $typeCZ = $objectTypeCZ[$type] ?? $type;

        if ($type === 'GAL' && $gal === 'no')
                    continue;
        if ($type === 'NEB' && $neb === 'no')
                    continue;       
        if ($type === 'OC' && $oc === 'no')
                    continue;
        if ($type === 'GC' && $gc === 'no')
                    continue;
        if ($type === 'PN' && $pn === 'no')
                    continue;
        if ($type === 'SNR' && $snr === 'no')
                    continue;
        if ($type === 'DN' && $dn === 'no')
                    continue;
        if ($type === 'QSO' && $qso === 'no')
                    continue;
        if ($type === 'STAR' && $star === 'no')
                    continue;
        if ($type === 'DOUBLE' && $double === 'no')
                    continue;

        ?>
        <tr>
          <!-- LEVÁ BUŇKA: POPIS KOMETY -->
          <td style="width:40%;">
            <h2><?= htmlspecialchars($c['id']) ?> (<?= htmlspecialchars($c['name']) ?>)</h2>
          
            <table class="inner-table">
              <tr>
                <td class="label">Jasnost</td>
                <td class="value"><?= htmlspecialchars($mag) ?></td>
              </tr>
              <tr>
                <td class="label">RA</td>
                <td class="value"><?= htmlspecialchars($ra_hms) ?></td>
              </tr>
              <tr>
                <td class="label">Dec</td>
                <td class="value"><?= htmlspecialchars($dec_dms) ?></td>
              </tr>
              <tr>
                <td class="label">Druh</td>
                <td class="value"><?= htmlspecialchars($typeCZ) ?></td>
              </tr>
              <tr>
                <td class="label">Úhlová velikost</td>
                <td class="value"><?= htmlspecialchars($c['size']) ?>&apos;</td>
              </tr>
              <tr>
                <td class="label">Souhvězdí</td>
                <td class="value"><?= htmlspecialchars($constName) ?></td>
              </tr>
              <tr>
                <td class="label">Výška</td>
                <td class="value"><?= htmlspecialchars($alt) ?></td>
              </tr>
              <tr>
                <td class="label">Azimut</td>
                <td class="value"><?= htmlspecialchars($az) ?></td>
              </tr>
              <tr>
                <td class="label">Východ</td>
                <td class="value"><?= htmlspecialchars($rise) ?></td>
              </tr>
              <tr>
                <td class="label">Kulminace</td>
                <td class="value"><?= htmlspecialchars($transit) ?></td>
              </tr>
              <tr>
                <td class="label">Západ</td>
                <td class="value"><?= htmlspecialchars($set) ?></td>
              </tr>
            </table>
          </td>
          <!-- PRAVÁ BUŇKA: GRAF -->
          <td style="width:60%; text-align:center; vertical-align:bottom;">

            <!-- SVG začátek + mřížka X/Y + popisky -->
            <!-- SVG GRAF -->
            <svg viewBox="0 0 <?= $width ?> <?= $height ?>">
              <!-- Stmívání -->
              <polygon points="<?= $afternoon ?>" class="graph-day" /> ;
              <polygon points="<?= $tw_start ?>" class="graph-tw" /> ;
              <polygon points="<?= $night ?>" class="graph-night" /> ;
              <polygon points="<?= $tw_end ?>" class="graph-tw" /> ;
              <polygon points="<?= $morning ?>" class="graph-day" /> ;

              <!-- Vodorovné čáry (výška) -->
              <?php for ($j = 1; $j <= $yLines; $j++): ?>
                <?php
                $altVal = $j * $yStep;
                $gy = $paddingTop + $innerH * (1 - ($altVal / $maxAlt));
                ?>
                <line x1="<?= $paddingLeft ?>" y1="<?= $gy ?>" x2="<?= $width - $paddingRight ?>" y2="<?= $gy ?>"
                  stroke="#333" stroke-width="1" />

                <text x="<?= $paddingLeft - 6 ?>" y="<?= $gy + 4 ?>" class="text-small" text-anchor="end">
                  <?= $altVal ?>°
                </text>
              <?php endfor; ?>

              <!-- Svislé čáry (čas) – 12:00 UTC → 12:00 UTC -->
              <?php
              $totalMinutes = 24 * 60;
              $xStepMinutes = 30;
              $xSteps = $totalMinutes / $xStepMinutes;

              for ($j = 0; $j <= $xSteps; $j++):
                $minutesFromStart = $j * $xStepMinutes;

                // pozice na ose X (UTC)
                $ratioGX = $minutesFromStart / $totalMinutes;
                $vx = $paddingLeft + $innerW * $ratioGX;

                // čas popisku = startUTC + minutesFromStart
                $labelUTC = clone $startDT;
                $labelUTC->modify("+{$minutesFromStart} minutes");

                // převod na místní čas
                $labelLocal = clone $labelUTC;

                $hour = (int) $labelLocal->format('H');
                $minute = (int) $labelLocal->format('i');

                $isLabel = ($minute === 0) && ($hour % 2 === 0);
                ?>

                <line x1="<?= $vx ?>" y1="<?= $paddingTop ?>" x2="<?= $vx ?>" y2="<?= $height - $paddingBottom ?>"
                  stroke="#333" stroke-width="1" />

                <?php if ($isLabel): ?>
                  <text x="<?= $vx ?>" y="<?= $height - $paddingBottom + 12 ?>" class="text-small" text-anchor="middle">
                    <?= sprintf("%02d:00", $hour + $offsetHours) ?>
                  </text>
                <?php endif; ?>

              <?php endfor; ?>

              <!-- Osy -->
              <line x1="<?= $paddingLeft ?>" y1="<?= $height - $paddingBottom ?>" x2="<?= $width - $paddingRight ?>"
                y2="<?= $height - $paddingBottom ?>" class="axis" />

              <line x1="<?= $paddingLeft ?>" y1="<?= $paddingTop ?>" x2="<?= $paddingLeft ?>"
                y2="<?= $height - $paddingBottom ?>" class="axis" />

              <!-- Křivka, výplň, kulminace, konec SVG  -->
              <?php if (!empty($points)):
                $poly = [];
                foreach ($points as $pt) {
                  $poly[] = $pt[0] . ',' . $pt[1];
                }
                // uzavření polygonu dolů k horizontu
                $poly[] = end($points)[0] . ',' . ($height - $paddingBottom);
                $poly[] = $points[0][0] . ',' . ($height - $paddingBottom);
                $polyPoints = implode(' ', $poly);

                // polyline pro samotnou křivku
                $linePoints = implode(' ', array_map(
                  fn($pt) => $pt[0] . ',' . $pt[1],
                  $points
                ));
                ?>

                <?php if ($maxAlt > 3): ?>
                  <polygon points="<?= $polyPoints ?>" class="graph-fill" />
                <?php endif; ?>

                <polyline points="<?= $linePoints ?>" class="graph-line" />

              <?php endif; ?>

              <!-- Červená čára kulminace -->

              <!-- Úprva pro altMax < 10° -->
              <?php
              if ($mAlt > 10) {
                $m_y1 = $paddingTop;
              } else {
                $m_y1 = $paddingTop + $innerH - $innerH * $mAlt / 10;
              }
              ?>
              <?php if ($transitX !== null): ?>
                <line x1="<?= $transitX ?>" y1=" <?= $m_y1 ?>" x2="<?= $transitX ?>" y2="<?= $height - $paddingBottom ?>"
                  stroke="red" stroke-width="1.5" />
              <?php endif; ?>

            </svg>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  </div> <!-- /box -->

</body>

</html>