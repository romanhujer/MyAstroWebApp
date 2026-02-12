<?php
date_default_timezone_set("Europe/Prague");

/*  weather.php
# 
#   Copyright (c) 2003 - 2026 Roman Hujer   http://hujer.net
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


$dt = new DateTime("now",  $nowTZ);
$offsetHours = $dt->getOffset() / 3600;


// --- ENV proměnné ---
$addr = $_SERVER['REMOTE_ADDR'] ?? "";
$host = $_SERVER['REMOTE_HOST'] ?? "";
$query_string = $_SERVER['QUERY_STRING'] ?? "";
$document_uri = $_SERVER['DOCUMENT_URI'] ?? "";
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? "";
$http_referer = $_SERVER['HTTP_REFERER'] ?? "/";
$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? "";


// Souřadnice Vrkoslavice 
$latitude = 50.71;
$longitude = 15.18;

$db_pass_file = "/home/hujer/.dbpass";
$json_dir = "/opt/astro_json";
$app_dir = "/opt/www/html/app";

// Načtení JSON
$ephemeris = load_json($json_dir . '/moon_ephemeris.json');
$phases = load_json($json_dir . '/moon_phases_2000_2100.json');
$sun_ephemeris = load_json($json_dir . '/sun_ephemeris.json');

if ($ephemeris === null || $phases === null || $sun_ephemeris === null) {
  die("Chyba: nelze načíst JSON soubory.\n");
}

// ------------------------------------------------------------
// Astro FUNKCE 
// ------------------------------------------------------------
function load_json($path)
{
  $json = file_get_contents($path);
  if ($json === false) {
    return null;
  }
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return null;
  }
  return $data;
}

// ------------------------------------------------------------
// MOON
// ------------------------------------------------------------
// dnešní souhvězdí z moon_ephemeris.json
function get_today_constellation($ephemeris)
{
  $today = (new DateTime('today'))->format('Y-m-d');

  foreach ($ephemeris as $row) {
    if (!isset($row['date'], $row['constellation']))
      continue;
    if ($row['date'] === $today) {
      return $row['constellation'];
    }
  }
  return null;
}


// ------------------------------------------------------------
// najde poslední nov, další nov a další úplněk
function get_moon_phases_info($phases)
{
  $now = time();

  $lastNew = null;
  $nextNew = null;
  $nextFull = null;

  foreach ($phases as $p) {
    if (!isset($p['type'], $p['utc']))
      continue;

    $ts = strtotime($p['utc']);
    if ($ts === false)
      continue;

    if ($p['type'] === 'new') {
      if ($ts <= $now) {
        if ($lastNew === null || $ts > $lastNew) {
          $lastNew = $ts;
        }
      } elseif ($ts > $now && $nextNew === null) {
        $nextNew = $ts;
      }
    }

    if ($p['type'] === 'full' && $ts > $now && $nextFull === null) {
      $nextFull = $ts;
    }

    if ($lastNew !== null && $nextNew !== null && $nextFull !== null) {
      // máme vše podstatné
      // nepřerušujeme, kdybys chtěl později víc logiky
    }
  }

  return [$lastNew, $nextNew, $nextFull];
}

// stáří Měsíce v dnech (od posledního novu)
function get_moon_age_days($lastNewTs)
{
  if ($lastNewTs === null)
    return null;
  $now = time();
  $ageSeconds = $now - $lastNewTs;
  return $ageSeconds / 86400;
}


function get_next_moonset($ephemeris)
{
  $now = new DateTime('now');
  $best = null;

  foreach ($ephemeris as $row) {
    if (!empty($row['moonset'])) {
      $dt = new DateTime($row['moonset']);

      if ($dt > $now) {
        if ($best === null || $dt < $best) {
          $best = $dt;
        }
      }
    }
  }

  return $best ? $best->format(DateTime::ATOM) : null;
}
function get_next_moonrise($ephemeris)
{
  $now = new DateTime('now');
  $best = null;

  foreach ($ephemeris as $row) {
    if (!empty($row['moonrise'])) {
      $dt = new DateTime($row['moonrise']);

      if ($dt > $now) {
        if ($best === null || $dt < $best) {
          $best = $dt;
        }
      }
    }
  }

  return $best ? $best->format(DateTime::ATOM) : null;
}

function get_today_moonrise_moonset($ephemeris)
{
  $now = new DateTime('now');
  $today = (new DateTime('today'))->format('Y-m-d');

  $todayRise = null;
  $todaySet = null;

  foreach ($ephemeris as $row) {
    if ($row['date'] === $today) {
      $todayRise = $row['moonrise'] ?? null;
      $todaySet = $row['moonset'] ?? null;
      break;
    }
  }

  // VÝCHOD
  if ($todayRise && new DateTime($todayRise) > $now) {
    $finalRise = $todayRise;
  } else {
    $finalRise = get_next_moonrise($ephemeris);
  }

  // ZÁPAD
  if ($todaySet && new DateTime($todaySet) > $now) {
    $finalSet = $todaySet;
  } else {
    $finalSet = get_next_moonset($ephemeris);
  }

  return [$finalRise, $finalSet];
}


function get_moon_phase_percent($lastNewTs, $nextNewTs)
{
  if ($lastNewTs === null || $nextNewTs === null) {
    return null;
  }

  $now = time();
  $lunation = $nextNewTs - $lastNewTs; // délka lunace v sekundách
  $age = $now - $lastNewTs;

  if ($lunation <= 0)
    return null;

  $percent = ($age / $lunation) * 100;

  // omezíme na 0–100 %
  if ($percent < 0)
    $percent = 0;
  if ($percent > 100)
    $percent = 100;

  return $percent;
}

function get_moon_illumination_percent($ageDays)
{
  if ($ageDays === null) {
    return null;
  }

  $synodicMonthDays = 29.53058867;

  $phase = 2 * M_PI * ($ageDays / $synodicMonthDays); // fáze v radiánech
  $illum = 0.5 * (1 - cos($phase));                   // 0–1

  $percent = $illum * 100;

  // omezíme na 0–100 %
  if ($percent < 0)
    $percent = 0;
  if ($percent > 100)
    $percent = 100;

  return $percent;
}

function get_today_culmination($ephemeris)
{
  $today = (new DateTime('today'))->format('Y-m-d');

  foreach ($ephemeris as $row) {
    if ($row['date'] === $today) {
      return [
        $row['culmination'] ?? null,
        $row['culmination_alt_deg'] ?? null
      ];
    }
  }
  return [null, null];
}

// ------------------------------------------------------------
// SUN
// ------------------------------------------------------------

// ------------------------------------------------------------

function get_today_sun($ephemeris)
{
  $today = (new DateTime('today'))->format('Y-m-d');
  $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
  $now = time();

  $todayRow = null;
  $tomorrowRow = null;

  foreach ($ephemeris as $row) {
    if ($row['date'] === $today) {
      $todayRow = $row;
    }
    if ($row['date'] === $tomorrow) {
      $tomorrowRow = $row;
    }
  }

  // --- VÝCHOD ---
  if (!empty($todayRow['sunrise']) && strtotime($todayRow['sunrise']) > $now) {
    $sunrise = $todayRow['sunrise'];
  } else {
    $sunrise = $tomorrowRow['sunrise'] ?? null;
  }

  // --- ZÁPAD ---
  if (!empty($todayRow['sunset']) && strtotime($todayRow['sunset']) > $now) {
    $sunset = $todayRow['sunset'];
  } else {
    $sunset = $tomorrowRow['sunset'] ?? null;
  }

  return [
    'constellation' => $todayRow['constellation'] ?? null,
    'sunrise' => $sunrise,
    'sunset' => $sunset,
    'culmination' => $todayRow['culmination'] ?? null,
    'culmination_alt_deg' => $todayRow['culmination_alt_deg'] ?? null
  ];
}

// ------------------------------------------------------------
// FUNKCE svatek
// ------------------------------------------------------------
function svatek($datum)
{
  static $sv = [
  "1.1." => "",
  "2.1." => "Karina",
  "3.1." => "Radmila",
  "4.1." => "Diana",
  "5.1." => "Dalimil",
  "6.1." => "",
  "7.1." => "Vilma",
  "8.1." => "Čestmír",
  "9.1." => "Vladan",
  "10.1." => "Břetislav",
  "11.1." => "Bohdana",
  "12.1." => "Pravoslav",
  "13.1." => "Edita",
  "14.1." => "Radovan",
  "15.1." => "Alice",
  "16.1." => "Ctirad",
  "17.1." => "Drahoslav",
  "18.1." => "Vladislav",
  "19.1." => "Doubravka",
  "20.1." => "Ilona",
  "21.1." => "Běla",
  "22.1." => "Slavomír",
  "23.1." => "Zdeněk",
  "24.1." => "Milena",
  "25.1." => "Miloš",
  "26.1." => "Zora",
  "27.1." => "Ingrid",
  "28.1." => "Otýlie",
  "29.1." => "Zdislava",
  "30.1." => "Robin",
  "31.1." => "Marika",
  "1.2." => "Hynek",
  "2.2." => "Nela",
  "3.2." => "Blažej",
  "4.2." => "Jarmila",
  "5.2." => "Dobromila",
  "6.2." => "Vanda",
  "7.2." => "Veronika",
  "8.2." => "Milada",
  "9.2." => "Apolena",
  "10.2." => "Mojmír",
  "11.2." => "Božena",
  "12.2." => "Slavěna",
  "13.2." => "Věnceslav",
  "14.2." => "Valentýn",
  "15.2." => "Jiřina",
  "16.2." => "Ljuba",
  "17.2." => "Miloslava",
  "18.2." => "Gizela",
  "19.2." => "Patrik",
  "20.2." => "Oldřich",
  "21.2." => "Lenka",
  "22.2." => "Petr",
  "23.2." => "Svatopluk",
  "24.2." => "Matěj",
  "25.2." => "Liliana",
  "26.2." => "Dora",
  "27.2." => "Alexandr",
  "28.2." => "Lumír",
  "29.2." => "Horymír",
  "1.3." => "Bedřich",
  "2.3." => "Anežka",
  "3.3." => "Kamil",
  "4.3." => "Stela",
  "5.3." => "Kazimír",
  "6.3." => "Miroslav",
  "7.3." => "Tomáš",
  "8.3." => "Gabriela",
  "9.3." => "Františka",
  "10.3." => "Viktorie",
  "11.3." => "Anděla",
  "12.3." => "Řehoř",
  "13.3." => "Růžena",
  "14.3." => "Rút",
  "15.3." => "Ida",
  "16.3." => "Elena",
  "17.3." => "Vlastimil",
  "18.3." => "Eduard",
  "19.3." => "Josef",
  "20.3." => "Světlana",
  "21.3." => "Radek",
  "22.3." => "<font color=red>Ota</font>/Leona",
  "23.3." => "Ivona",
  "24.3." => "Gabriel",
  "25.3." => "Marián",
  "26.3." => "Emanuel",
  "27.3." => "Dita",
  "28.3." => "Soňa",
  "29.3." => "Taťána",
  "30.3." => "Arnošt",
  "31.3." => "Kvido",
  "1.4." => "Hugo",
  "2.4." => "Erika",
  "3.4." => "Richard",
  "4.4." => "<font color=red>Ivana</font>",
  "5.4." => "Miroslava",
  "6.4." => "<font color=red>Vendula</font>",
  "7.4." => "Heřman",
  "8.4." => "Ema",
  "9.4." => "Dušan",
  "10.4." => "Darja",
  "11.4." => "Izabela",
  "12.4." => "Julius",
  "13.4." => "Aleš",
  "14.4." => "Vincenc",
  "15.4." => "Anastázie",
  "16.4." => "Irena",
  "17.4." => "Rudolf",
  "18.4." => "Valérie",
  "19.4." => "Rostislav",
  "20.4." => "Marcela",
  "21.4." => "Alexandra",
  "22.4." => "Evženie",
  "23.4." => "<font color=red>Vojtěch</font>",
  "24.4." => "<font color=red>Jiří</font>",
  "25.4." => "Marek",
  "26.4." => "Oto",
  "27.4." => "Jaroslav",
  "28.4." => "Vlastislav",
  "29.4." => "Robert",
  "30.4." => "Blahoslav",
  "1.5." => "",
  "2.5." => "Zikmund",
  "3.5." => "Alexej",
  "4.5." => "Květoslav",
  "5.5." => "Klaudie",
  "6.5." => "Radoslav",
  "7.5." => "Stanislav",
  "8.5." => "",
  "9.5." => "Ctibor",
  "10.5." => "Blažena",
  "11.5." => "Svatava",
  "12.5." => "Pankrác",
  "13.5." => "Servác",
  "14.5." => "Bonifác",
  "15.5." => "Žofie",
  "16.5." => "<font color=red>Jan</font>/Přemysl",
  "17.5." => "Aneta",
  "18.5." => "Nataša",
  "19.5." => "Ivo",
  "20.5." => "Zbyšek",
  "21.5." => "Monika",
  "22.5." => "Emil",
  "23.5." => "Vladimír",
  "24.5." => "Jana",
  "25.5." => "Viola",
  "26.5." => "Filip",
  "27.5." => "Valdemar",
  "28.5." => "Vilém",
  "29.5." => "Maxim",
  "30.5." => "Ferdinand",
  "31.5." => "Kamila",
  "1.6." => "Laura",
  "2.6." => "Jarmil",
  "3.6." => "Tamara",
  "4.6." => "Dalibor",
  "5.6." => "Dobroslav",
  "6.6." => "Norbert",
  "7.6." => "Iveta",
  "8.6." => "Medard",
  "9.6." => "Stanislava",
  "10.6." => "Gita",
  "11.6." => "Bruno",
  "12.6." => "Antonie",
  "13.6." => "Antonín",
  "14.6." => "Roland",
  "15.6." => "Vít",
  "16.6." => "Zbyněk",
  "17.6." => "Adolf",
  "18.6." => "Milan",
  "19.6." => "Leoš",
  "20.6." => "Květa",
  "21.6." => "Alois",
  "22.6." => "Pavla",
  "23.6." => "Zdeňka",
  "24.6." => "Jan",
  "25.6." => "Ivan",
  "26.6." => "Adriana",
  "27.6." => "Ladislav",
  "28.6." => "Lubomír",
  "29.6." => "Petr a Pavel",
  "30.6." => "Šárka",
  "1.7." => "Jaroslava",
  "2.7." => "Patricie",
  "3.7." => "Radomír",
  "4.7." => "Prokop",
  "5.7." => "Cyril a Metoděj",
  "6.7." => "mistr Jan Hus",
  "7.7." => "Bohuslava",
  "8.7." => "Nora",
  "9.7." => "Drahoslava",
  "10.7." => "Libuše",
  "11.7." => "Olga",
  "12.7." => "Bořek",
  "13.7." => "<font color=red>Markéta</font>",
  "14.7." => "Karolína",
  "15.7." => "Jindřich",
  "16.7." => "Luboš",
  "17.7." => "Martina",
  "18.7." => "Drahomíra",
  "19.7." => "Čeněk",
  "20.7." => "Ilja",
  "21.7." => "Vítězslav",
  "22.7." => "Magdaléna",
  "23.7." => "Libor",
  "24.7." => "Kristýna",
  "25.7." => "Jakub",
  "26.7." => "Anna",
  "27.7." => "Věroslav",
  "28.7." => "Viktor",
  "29.7." => "Marta",
  "30.7." => "Bořivoj",
  "31.7." => "Ignác",
  "1.8." => "Oskar",
  "2.8." => "Gustav",
  "3.8." => "Miluše",
  "4.8." => "Dominik",
  "5.8." => "Kristián",
  "6.8." => "Oldřiška",
  "7.8." => "Lada",
  "8.8." => "Soběslav",
  "9.8." => "<font color=red>Roman</font>",
  "10.8." => "Vavřinec",
  "11.8." => "Zuzana",
  "12.8." => "Klára",
  "13.8." => "Alena",
  "14.8." => "Alan",
  "15.8." => "Hana",
  "16.8." => "Jáchym",
  "17.8." => "Petra",
  "18.8." => "Helena",
  "19.8." => "Ludvík",
  "20.8." => "Bernard",
  "21.8." => "Johana",
  "22.8." => "Bohuslav",
  "23.8." => "Sandra",
  "24.8." => "Bartoloměj",
  "25.8." => "Radim",
  "26.8." => "Luděk",
  "27.8." => "Otakar",
  "28.8." => "Augustýn",
  "29.8." => "Evelína",
  "30.8." => "Vladěna",
  "31.8." => "Pavlína",
  "1.9." => "Linda",
  "2.9." => "Adéla",
  "3.9." => "Bronislav",
  "4.9." => "Jindřiška",
  "5.9." => "Boris",
  "6.9." => "Boleslav",
  "7.9." => "Regina",
  "8.9." => "Mariana",
  "9.9." => "Daniela",
  "10.9." => "Irma",
  "11.9." => "Denisa",
  "12.9." => "Marie",
  "13.9." => "Lubor",
  "14.9." => "Radka",
  "15.9." => "Jolana",
  "16.9." => "Ludmila",
  "17.9." => "Naděžda",
  "18.9." => "Kryštof",
  "19.9." => "Zita",
  "20.9." => "Oleg",
  "21.9." => "Matouš",
  "22.9." => "Darina",
  "23.9." => "Berta",
  "24.9." => "Jaromír",
  "25.9." => "Zlata",
  "26.9." => "Andrea",
  "27.9." => "Jonáš",
  "28.9." => "Václav",
  "29.9." => "Michal",
  "30.9." => "Jeroným",
  "1.10." => "Igor",
  "2.10." => "Olivie/Oliver",
  "3.10." => "Bohumil",
  "4.10." => "František",
  "5.10." => "<font color=red>Eliška</font>",
  "6.10." => "Hanuš",
  "7.10." => "Justýna",
  "8.10." => "Věra",
  "9.10." => "Štefan/Sára",
  "10.10." => "Marina",
  "11.10." => "Andrej",
  "12.10." => "Marcel",
  "13.10." => "Renáta",
  "14.10." => "Agáta",
  "15.10." => "<font color=red>Tereza</font>",
  "16.10." => "Havel",
  "17.10." => "Hedvika",
  "18.10." => "Lukáš",
  "19.10." => "Michaela",
  "20.10." => "Vendelín",
  "21.10." => "Brigita",
  "22.10." => "Sabina",
  "23.10." => "Teodor",
  "24.10." => "Nina",
  "25.10." => "Beáta",
  "26.10." => "Erik",
  "27.10." => "Zoja",
  "28.10." => "",
  "29.10." => "Silvie",
  "30.10." => "Tadeáš",
  "31.10." => "Štěpánka",
  "1.11." => "Felix",
  "2.11." => "",
  "3.11." => "Hubert",
  "4.11." => "Karel",
  "5.11." => "Miriam",
  "6.11." => "Liběna",
  "7.11." => "Saskie",
  "8.11." => "Bohumír",
  "9.11." => "Bohdan",
  "10.11." => "Evžen",
  "11.11." => "Martin",
  "12.11." => "Benedikt",
  "13.11." => "Tibor",
  "14.11." => "Sáva",
  "15.11." => "Leopold",
  "16.11." => "Otmar",
  "17.11." => "Mahulena",
  "18.11." => "Romana",
  "19.11." => "Alžběta",
  "20.11." => "Nikola",
  "21.11." => "Albert",
  "22.11." => "Cecílie",
  "23.11." => "Klement",
  "24.11." => "Emílie",
  "25.11." => "Kateřina",
  "26.11." => "Artur",
  "27.11." => "Xenie",
  "28.11." => "René",
  "29.11." => "Zina",
  "30.11." => "Ondřej",
  "1.12." => "Iva",
  "2.12." => "Blanka",
  "3.12." => "Svatoslav",
  "4.12." => "<font color=red>Barbora</font>",
  "5.12." => "Jitka",
  "6.12." => "Mikuláš",
  "7.12." => "Ambrož",
  "8.12." => "Květoslava",
  "9.12." => "Vratislav",
  "10.12." => "Julie",
  "11.12." => "Dana",
  "12.12." => "Simona",
  "13.12." => "<font color=red>Lucie</font>",
  "14.12." => "Lýdie",
  "15.12." => "Radana",
  "16.12." => "Albína",
  "17.12." => "Daniel",
  "18.12." => "Miloslav",
  "19.12." => "Ester",
  "20.12." => "Dagmar",
  "21.12." => "Natálie",
  "22.12." => "Šimon",
  "23.12." => "Vlasta",
  "24.12." => "Adam a Eva",
  "25.12." => "",
  "26.12." => "Štěpán",
  "27.12." => "Žaneta",
  "28.12." => "Bohumila",
  "29.12." => "Judita",
  "30.12." => "David",
  "31.12." => "Silvester"
  ];
  return $sv[$datum] ?? "";
}

// ------------------------------------------------------------
// FUNKCE NAROZENINY
// ------------------------------------------------------------
function narozeniny($datum)
{
  static $nar = [
  "16.1." => "Vojtěch Hujer:2006",
  "18.1." => "Jiří Černohorký:2007",
  "29.1." => "Lucie Černohorská:1980",
  "14.2." => "Jan Hujer:1973",
  "17.2." => "Barbora Černohorská:2010",
  "13.3." => "Eliška Hujerová:2001",
  "14.3." => "Markéta Hujerová:1972",
  "22.5." => "Tereza Hujerová:1998",
  "23.7." => "Jan Drábek:1942",
  "31.7." => "Vendula Pelantová:2005",
  "7.8." => "Roman Hujer:1967",
  "20.9." => "Ota Hujer:1941"
  ];
  return $nar[$datum] ?? "";
}

// --- Čtení hesla z .dbpass ---
$dbpass = "";
$line = @file($db_pass_file);
if ($line && isset($line[0])) {
  $parts = explode(":", trim($line[0]));
  if (count($parts) >= 2) {
    $dbpass = $parts[1];   // prostřední položka
  }
}

// --- Připojení k MySQL ---
$mysqli = new mysqli("localhost", "master", $dbpass, "hujer_net");
if ($mysqli->connect_error) {
  die("DB ERROR: " . $mysqli->connect_error);
}

// --- SQL dotaz ---
$sql = "
    SELECT teplota1, tlak, vlhkost, batery, time, date, mpsas
    FROM teploty
    WHERE timesec = (
        SELECT MAX(timesec) FROM teploty WHERE sensor='SQM-HR03'
    )
";

$res = $mysqli->query($sql);
list($teplota, $tlak, $vlhkost, $batery, $cas_t, $datum_t, $sqm) = $res->fetch_row();
$mysqli->close();

// --- Výpočet tlaku ---
$tlak_m = sprintf("%.2f", $tlak + 600 / 8.3);

// ------------------------------------------------------------
// DATUMY - svátaky a narozeniny
// ------------------------------------------------------------

$mesic = ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];

$now = time();
$zitra = $now + 86400;

$den = date("j", $now);
$mes = date("n", $now);
$rok = date("Y", $now);

$den_zitra = date("j", $zitra);
$mes_zitra = date("n", $zitra);
$rok_zitra = date("Y", $zitra);

$dny = [
  1 => "pondělí",
  2 => "úterý",
  3 => "středa",
  4 => "čtvrtek",
  5 => "pátek",
  6 => "sobota",
  7 => "neděle"
];

$den_t = $dny[date("N")];
$datum_dnes = $den . "." . $mes . ".";
$datum_zitra = $den_zitra . "." . $mes_zitra . ".";

$datum_text = $den_t . " " . $den . ". " . $mesic[$mes - 1] . " " . $rok;

$svatek_dnes = svatek($datum_dnes);
$svatek_zitra = svatek($datum_zitra);

$nar_dnes = narozeniny($datum_dnes);
$nar_zitra = narozeniny($datum_zitra);

$nar_text_dnes = "";
if ($nar_dnes != "") {
  list($jmeno, $rok_nar) = explode(":", $nar_dnes);
  $vek = $rok - $rok_nar;
  $nar_text_dnes = "Dnes má <b>$vek</b> narozeniny <font color=red><b>$jmeno</b></font>.<br>";
}
$nar_text_zitra = "";
if ($nar_zitra != "") {
  list($jmeno, $rok_nar) = explode(":", $nar_zitra);
  $vek = $rok - $rok_nar;
  $nar_text_zitra = "Zítra má <b>$vek</b> narozeniny <b>$jmeno</b>.<br>";
}

//
// Astro funkce
$timestamp = time();

// VYPOCET Posunu času k UTC 
// ------------------------------------------------------------
$tz = new DateTimeZone('Europe/Prague');
$now = new DateTime('now', $tz);
// offset v sekundách vůči UTC, v létě 7200 (= +2h), v zimě 3600 (= +1h)
$offsetSeconds = $tz->getOffset($now);
// převedeme na hodiny se znaménkem (+1, +2)
$offsetHours = $offsetSeconds / 3600;
// formát se znaménkem pro widget (např. "+2")
$tzAttr = sprintf('%+d', $offsetHours);

// ------------------------------------------------------------
// VYPOCET SLUNCE
// ------------------------------------------------------------
// Slunce – standardní výška 90° (horizont)
//$sun_rise = date_sunrise($timestamp, SUNFUNCS_RET_STRING,$latitude, $longitude, 90, 1);
//$sun_set =  date_sunset($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 90, 1);


$sun = get_today_sun($sun_ephemeris);

$sun_const = "<strong>Souhvězdí: </strong>" . $sun['constellation'];
$sun_rise = "<strong>Východ: </strong>" . ($sun['sunrise'] ? date('d. M H:i', strtotime($sun['sunrise'])) : '—');
$sun_set = "<strong>Západ: </strong>" . ($sun['sunset'] ? date('d. M H:i', strtotime($sun['sunset'])) : '—');
$sun_culm = "<strong>Kulminace: </strong>" . ($sun['culmination'] ? date('H:i', strtotime($sun['culmination'])) : '—');
$sun_alt = "<strong>Max. výška: </strong>" . ($sun['culmination_alt_deg'] !== null ? number_format($sun['culmination_alt_deg'], 1, ',', ' ') . "°" : '—');

if ($sun['sunrise'] < $sun['sunset']) {
  $sun_rs1 = $sun_rise;
  $sun_rs2 = $sun_set;
  $sun_on_sky = "";
} else {
  $sun_on_sky = '<div class="on-sky"><strong>je na obloze</strong></div>';
  $sun_rs1 = $sun_set;
  $sun_rs2 = $sun_rise;
}


// ------------------------------------------------------------
// VYPOCET NOC
// ------------------------------------------------------------
/*
// Astronomický rozbřesk a soumrak – výška -18°
$astro_start = date_sunset($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 108, 1);

// $astro_end = date_sunrise($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 108, 1);
$now = time();

// Výpočet dnešního ranního konce astronomické noci
$today_end = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, 108, 1);

// Pokud už je po něm, spočítáme zítřejší
if ($now >= $today_end) {
  $target_day = strtotime("+1 day", $now);
  $astro_end = date_sunrise($target_day, SUNFUNCS_RET_STRING, $latitude, $longitude, 108, 1);
  $label = "Konec příští astronomické noci";
} else {
  $astro_end = date("H:i", $today_end);
  $label = "Konec aktuální astronomické noci";
}
*/
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
// VYPOCET pro MESIC
// ------------------------------------------------------------
$constellation = get_today_constellation($ephemeris);
list($lastNew, $nextNew, $nextFull) = get_moon_phases_info($phases);
$phasePercent = get_moon_phase_percent($lastNew, $nextNew);
$ageDays = get_moon_age_days($lastNew);
list($moonrise, $moonset) = get_today_moonrise_moonset($ephemeris);
list($culmTime, $culmAlt) = get_today_culmination($ephemeris);
$ageDays = get_moon_age_days($lastNew);
$illumPercent = get_moon_illumination_percent($ageDays);


$moon_ilum = "<strong>Osvětlení: </strong>" . ($illumPercent !== null ? number_format($illumPercent, 1, ',', ' ') . " %" : "neznámé");
$moon_phase = "<strong>Fáze: </strong>" . ($phasePercent !== null ? number_format($phasePercent, 1, ',', ' ') . " %" : "neznámá");
$moon_old = "<strong>Stáří: </strong>" . ($ageDays !== null ? number_format($ageDays, 1, ',', ' ') : 'neznámé') . " dne";
$moon_const = "<strong>Souhvězdí: </strong>" . ($constellation ?: 'neznámé');
$moon_new = "<strong>Nov: </strong>" . ($nextNew ? date('d. M Y', $nextNew) : 'neznámý');
$moon_full = "<strong>Úplněk: </strong>" . ($nextFull ? date('d. M Y', $nextFull) : 'neznámý');
$moon_rise = "<strong>Východ: </strong>" . ($moonrise ? date('d. M H:i', strtotime($moonrise)) : '—');
$moon_set = "<strong>Západ: </strong>" . ($moonset ? date('d. M H:i', strtotime($moonset)) : '—');
$moon_culm = "<strong>Kulminace: </strong>" . ($culmTime ? date('H:i', strtotime($culmTime)) : '—');
$moon_alt = "<strong>Max. výška: </strong>" . ($culmAlt !== null ? number_format($culmAlt, 1, ',', ' ') . "°" : '—');

if ($nextNew < $nextFull) {
  $moon_nf1 = $moon_new;
  $moon_nf2 = $moon_full;
} else {
  $moon_nf1 = $moon_full;
  $moon_nf2 = $moon_new;
}

if ($moonrise < $moonset) {
  $moon_rs1 = $moon_rise;
  $moon_rs2 = $moon_set;
  $moon_on_sky = "";
} else {
  $moon_on_sky = '<div class="on-sky"><strong>je na obloze</strong></div>';
  $moon_rs1 = $moon_set;
  $moon_rs2 = $moon_rise;
}
// ------------------------------------------------------------
// ZAČÁTEK HTML
// ------------------------------------------------------------
?>
<!doctype HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="refresh" content="1800">
  <title>Počasi</title>
  <link rel="stylesheet" href="/css/my.css">
  <style>
    .graf {
      margin-bottom: 25px;
    }

    form {
      margin-bottom: 20px;
      color: white;
      font-size: 14px;
    }
  </style>
</head>
<script>
class LiveClock extends HTMLElement {
  static get observedAttributes(){ return ['locale','hour12','seconds','timezone','blink']; }
  constructor(){
    super();
    const root = this.attachShadow({mode:'open'});
    root.innerHTML = `
      <style>
        :host { font: 700 1rem/1.2 system-ui, Segoe UI, Roboto, Arial, sans-serif; }
        .off { opacity: .25; transition: opacity .15s; }
      </style>
      <span id="h">--</span><span id="c1">:</span><span id="m">--</span><span id="c2">:</span><span id="s">--</span>
    `;
    this._els = {
      h: root.getElementById('h'),
      m: root.getElementById('m'),
      s: root.getElementById('s'),
      c1: root.getElementById('c1'),
      c2: root.getElementById('c2'),
    };
    this._tick = this._tick.bind(this);
  }
  connectedCallback(){ this._setup(); this._start(); }
  disconnectedCallback(){ clearInterval(this._int); clearTimeout(this._to); }
  attributeChangedCallback(){ this._setup(); }
  _setup(){
    this.locale = this.getAttribute('locale') || 'cs-CZ';
    this.hour12 = this.getAttribute('hour12') === 'true' ? true : false;
    this.seconds = this.getAttribute('seconds') !== 'false';
    this.timeZone = this.getAttribute('timezone') || undefined;
    this.blink = this.getAttribute('blink') !== 'false';
    this._df = new Intl.DateTimeFormat(this.locale, {
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hour12: this.hour12, timeZone: this.timeZone
    });
  }
  _start(){
    this._tick();
    this._to = setTimeout(()=>{
      this._tick();
      this._int = setInterval(this._tick, 1000);
    }, 1000 - (Date.now() % 1000));
  }
  _tick(){
    const {h,m,s,c1,c2} = this._els;
    const now = new Date();
    const parts = this._df.formatToParts(now);
    const hh = parts.find(p=>p.type==='hour')?.value ?? String(now.getHours()).padStart(2,'0');
    const mm = parts.find(p=>p.type==='minute')?.value ?? String(now.getMinutes()).padStart(2,'0');
    const ss = parts.find(p=>p.type==='second')?.value ?? String(now.getSeconds()).padStart(2,'0');
    h.textContent = hh; m.textContent = mm; s.textContent = ss;

    if (this.seconds) { s.style.display='inline'; c2.style.display='inline'; }
    else { s.style.display='none'; c2.style.display='none'; }

    if (this.blink) {
      const on = now.getSeconds() % 2 === 0;
      c1.classList.toggle('off', !on);
      c2.classList.toggle('off', !on);
    } else { c1.classList.remove('off'); c2.classList.remove('off'); }
  }
}
customElements.define('live-clock', LiveClock);
</script>

<body>
<div class="main-wrapper">

    <div class="header">
      <div class="header-text">
        Dnes je <b><?= $datum_text ?></b>,
        svátek má <b><?= $svatek_dnes ?></b>,
        zítra má svátek <b><?= $svatek_zitra ?></b>.<br>
        <?= $nar_text_dnes ?>
        <?= $nar_text_zitra ?>
      </div>
     </div>
     <div class="header-text">
      
 <div class="header-text">
    <live-clock
      style="font-size: 24px; font-weight: 700;"  
      locale="cs-CZ"
      hour12="false"
      seconds="true"
      timezone="Europe/Prague"
      blink="false">
    </live-clock>
  </div>

    </div>

    <div class="big-title">AKTUÁLNÍ POČASÍ</div>
    <div class="header-text">
      <b> Vrkoslavice - Jablonec nad Nisou</b><br>
    </div>
    <table class="layout-table">
      <tr>

        <td class="sidebar-box">
          <div class="links-title">I N F O R M A C E</div>
          <div class="links-box">
            <!-- Odkazy -->
            <div><b>Slunce</b> <br>
              <?= $sun_on_sky ?>
              <div class="sun-box">
                <?= $sun_const ?><br>
                <?= $sun_rs1 ?><br>
                <?= $sun_rs2 ?>
                
              <!--      
                <br>
                <?= $sun_culm ?><br> 
                <?= $sun_alt ?> 
              -->
              </div>
            </div>
            <div><b>Noc</b><br>
              <?= $astro_start ?> - <?= $astro_end ?><br>
            </div>
            <div><b>Měsíc</b><br>
              <?php if ($moon_is_on_sky): ?>
                <div class="on-sky"><strong>je na obloze</strong></div> <?php endif; ?>
              <?= $moon_on_sky ?>
              <div class="sun-box">
                <?= $moon_const ?><br>
                <?= $moon_old ?><br>
                <?= $moon_phase ?><br>
                <?= $moon_ilum ?><br>
                <?= $moon_culm ?><br>
                <?= $moon_alt ?>
              </div>
              <div class="moon-widget">
                <script type="text/javascript" src="https://moonphases.co.uk/js/widget.js" id="moonphase_widget"
                  lat="<?= $latitude ?>" lng="<?= $longitude ?>" tz="<?= $tzAttr ?>" widget="tiny-nodate">
                  </script>
              </div>
              <div class="sun-box"><br>
                <?= $moon_rs1 ?><br>
                <?= $moon_rs2 ?><br>
                <?= $moon_nf1 ?><br>
                <?= $moon_nf2 ?><br>
              </div>
              <br>
              <div><b>Počasí</b></div>
              <a href="https://www.ventusky.com/">Ventusky</a>
              <a href="https://mapy.meteo.pl/">Poláci</a>
              <a href="https://www.windy.com/">Windy</a>
              <a
                href="https://www.meteoblue.com/cs/po%C4%8Das%C3%AD/outdoorsports/seeing/jablonec-nad-nisou_%c4%8cesko_3074603">Meteoblue</a>
              <br>

              <div><b>Kamery</b></div>
              <a href="https://pocasi-frydlant.cz/webcam/webcam-foto-5.jpg">Horní Řasnice</a>
              <br>
              <div><b>Stránky</b></div>
              <a href="https://cesty.hujer.net">Cesty</a>
              <a href="https://astro.hujer.net">Astro</a>
              <a href="/">Domů</a>
              <!-- <a href="weather.php">Obnovit</a>  -->
              <br>
              <!-- Odakzy Konec-->
            </div>
        </td>
        <td class="content">

          <!--  Zobrazení grafu počasí z modulu api/meteo.php -->
          <?php
          $header = "no";
          $presure = "yes";
          $humidity = "no";
          include $app_dir . '/meteo.php';
          ?>
          <!--  Konec modulu api/meteo.php -->
          <div class="back-link">
            <a href="weather.php">Obnovit</a>
          </div>

          <br>
          <div class="header-text">
            <strong>Vizualiazace družicových dat</strong><br>
          </div>
          <iframe src="https://meteo.hvbo.cz/msg.htm" style="width:800; height: 800;  border:0;"></iframe>
          <br>
          <!-- Konec-->
          <!--  
         <div class="back-link">
           <a href="weather.php">Obnovit</a>
         </div>  
        -->
        </td>
        <td class="sidebar-box">
          <div class="weather-box">
            <div class="sidebar-title">A K T U Á L N Ě</div>
            <div class="weather-box">
              <img src="/teplomer.png"><br><br>
              <b class="nowrap">Teplota:</b> <?= $teplota ?>°C<br>
              <b class="nowrap">Vlhkost:</b> <?= $vlhkost ?>%<br>
              <b class="nowrap">Tlak:</b> <?= $tlak_m ?> hPa<br>
              <b class="nowrap">SQM:</b> <?= $sqm ?> m/as²<br>
              <b class="nowrap">Odečet:</b> <br><?= $cas_t ?> <?= $datum_t ?><br>
            </div>
            <div class="weather-box">
              <b>Předpověď</b><br>
              <img src="https://www.meteopress.cz/pictures/pp_cr_0den.png"><br>
              <img src="https://www.meteopress.cz/pictures/pp_cr_1den.png"><br>
              <img src="https://www.meteopress.cz/pictures/pp_cr_2den.png"><br>
              <img src="https://www.meteopress.cz/pictures/pp_cr_3den.png"><br>
              <br>&nbsp;<br>
            </div>
          </div>
        </td>
      </tr>
    </table>
  </div>
</body>

</html>