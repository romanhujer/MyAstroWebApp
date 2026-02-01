<?php
date_default_timezone_set("Europe/Prague");

// --- ENV proměnné ---
$addr          = $_SERVER['REMOTE_ADDR'] ?? "";
$host          = $_SERVER['REMOTE_HOST'] ?? "";
$query_string  = $_SERVER['QUERY_STRING'] ?? "";
$document_uri  = $_SERVER['DOCUMENT_URI'] ?? "";
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? "";
$http_referer  = $_SERVER['HTTP_REFERER'] ?? "/";
$forwarded     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? "";

// Souřadnice Vrkoslavice 
$latitude = 50.71;
$longitude = 15.18;

$db_pass_file = "/home/hujer/.dbpass"; 
$json_dir = "/opt/astro_json";

// Načtení JSON
$ephemeris = load_json($json_dir . '/moon_ephemeris.json');
$phases    = load_json($json_dir . '/moon_phases_2000_2100.json');
$sun_ephemeris = load_json($json_dir . '/sun_ephemeris.json');

if ($ephemeris === null || $phases === null  || $sun_ephemeris === null ) {
    die("Chyba: nelze načíst JSON soubory.\n");
}

// ------------------------------------------------------------
// Astro FUNKCE 
// ------------------------------------------------------------
function load_json($path) {
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

// ------------------------------------------------------------
// MĚSÍC – je na obloze?
// ------------------------------------------------------------
function moon_on_sky($ephemeris) {
    $today = (new DateTime('today'))->format('Y-m-d');
    $now = time();

    $rise = null;
    $set  = null;

    foreach ($ephemeris as $row) {
        if ($row['date'] === $today) {

            if (!empty($row['moonrise'])) {
                $rise = strtotime($row['moonrise']);
            }
            if (!empty($row['moonset'])) {
                $set = strtotime($row['moonset']);
            }

            break;
        }
    }

    // Pokud chybí data → Měsíc nepovažujeme za viditelný
    if ($rise === null || $set === null) {
        return false;
    }

    // Pokud moonset < moonrise → západ je až následující den
    if ($set < $rise) {
        // přičteme 24 hodin
        $set += 24 * 3600;
    }

    return ($now >= $rise && $now < $set);
}


// dnešní souhvězdí z moon_ephemeris.json
function get_today_constellation($ephemeris) {
    $today = (new DateTime('today'))->format('Y-m-d');

    foreach ($ephemeris as $row) {
        if (!isset($row['date'], $row['constellation'])) continue;
        if ($row['date'] === $today) {
            return $row['constellation'];
        }
    }
    return null;
}

// najde poslední nov, další nov a další úplněk
function get_moon_phases_info($phases) {
    $now = time();

    $lastNew = null;
    $nextNew = null;
    $nextFull = null;

    foreach ($phases as $p) {
        if (!isset($p['type'], $p['utc'])) continue;

        $ts = strtotime($p['utc']);
        if ($ts === false) continue;

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
function get_moon_age_days($lastNewTs) {
    if ($lastNewTs === null) return null;
    $now = time();
    $ageSeconds = $now - $lastNewTs;
    return $ageSeconds / 86400;
}


function get_next_moonset($ephemeris) {
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
function get_next_moonrise($ephemeris) {
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

function get_today_moonrise_moonset($ephemeris) {
    $now = new DateTime('now');
    $today = (new DateTime('today'))->format('Y-m-d');

    $todayRise = null;
    $todaySet  = null;

    foreach ($ephemeris as $row) {
        if ($row['date'] === $today) {
            $todayRise = $row['moonrise'] ?? null;
            $todaySet  = $row['moonset'] ?? null;
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


function get_moon_phase_percent($lastNewTs, $nextNewTs) {
    if ($lastNewTs === null || $nextNewTs === null) {
        return null;
    }

    $now = time();
    $lunation = $nextNewTs - $lastNewTs; // délka lunace v sekundách
    $age = $now - $lastNewTs;

    if ($lunation <= 0) return null;

    $percent = ($age / $lunation) * 100;

    // omezíme na 0–100 %
    if ($percent < 0) $percent = 0;
    if ($percent > 100) $percent = 100;

    return $percent;
}

function get_moon_illumination_percent($ageDays) {
    if ($ageDays === null) {
        return null;
    }

    $synodicMonthDays = 29.53058867;

    $phase = 2 * M_PI * ($ageDays / $synodicMonthDays); // fáze v radiánech
    $illum = 0.5 * (1 - cos($phase));                   // 0–1

    $percent = $illum * 100;

    // omezíme na 0–100 %
    if ($percent < 0) $percent = 0;
    if ($percent > 100) $percent = 100;

    return $percent;
}

function get_today_culmination($ephemeris) {
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

function get_today_sun($ephemeris) {
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


//
// Astro funkce
$timestamp = time();

// VYPOCET Posunu času k UTC 
// ------------------------------------------------------------
$tz   = new DateTimeZone('Europe/Prague');
$now  = new DateTime('now', $tz);
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
$sun_alt = "<strong>Max. výška: </strong>" . ($sun['culmination_alt_deg'] !== null ? number_format($sun['culmination_alt_deg'], 1, ',', ' ') . "°" : '—'  );

if ($sun['sunrise'] < $sun['sunset'] ) {
   $sun_rs1 = $sun_rise;
   $sun_rs2 = $sun_set;
} else {
   $sun_rs1 = $sun_set;
   $sun_rs2 = $sun_rise;
}

// ------------------------------------------------------------
// VYPOCET NOC
// ------------------------------------------------------------
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
$moon_is_on_sky = moon_on_sky($ephemeris);


$moon_ilum = "<strong>Osvětlení: </strong>" . ($illumPercent !== null ? number_format($illumPercent, 1, ',', ' ') . " %" : "neznámé");
$moon_phase = "<strong>Fáze: </strong>" . ($phasePercent !== null  ? number_format($phasePercent, 1, ',', ' ') . " %"  : "neznámá");
$moon_old = "<strong>Stáří: </strong>" . ($ageDays !== null ? number_format($ageDays, 1, ',', ' ') : 'neznámé') . " dne";
$moon_const = "<strong>Souhvězdí: </strong>" . ($constellation ?: 'neznámé');
$moon_new = "<strong>Nov: </strong>" . ($nextNew  ? date('d. M Y', $nextNew)  : 'neznámý');
$moon_full = "<strong>Úplněk: </strong>" . ($nextFull ? date('d. M Y', $nextFull) : 'neznámý');
$moon_rise = "<strong>Východ: </strong>" . ($moonrise ? date('d. M H:i', strtotime($moonrise)) : '—');
$moon_set = "<strong>Západ: </strong>" . ($moonset ? date('d. M H:i', strtotime($moonset)) : '—');
$moon_culm = "<strong>Kulminace: </strong>" . ($culmTime ? date('H:i', strtotime($culmTime)) : '—');
$moon_alt = "<strong>Max. výška: </strong>" . ($culmAlt !== null ? number_format($culmAlt, 1, ',', ' ') . "°" : '—') ;

if ($nextNew < $nextFull) {
  $moon_nf1 = $moon_new; 
  $moon_nf2 = $moon_full; 
} else {
  $moon_nf1 = $moon_full; 
  $moon_nf2 = $moon_new; 
}

if ($moonrise < $moonset ) {
   $moon_rs1 = $moon_rise;
   $moon_rs2 = $moon_set;
} else {
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
   <link rel="stylesheet" href="/css/hujer.css">
</head>
<body>
<table class="layout-table">
     <tr>
        
        <td class="sidebar-box">
          <div class="links-title">I N F O R M A C E</div>
          <div class="links-box">
          <!-- Odkazy -->
	 <div><b>Slunce</b> <br>
	  <div  class="sun-box">
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
     <div><b>Měsíc</b><br> tady
     <?php if ($moon_is_on_sky): ?>
    <div class="on-sky">na obloze</div>  <?php endif; ?> Tady
	 <div  class="sun-box">
        <?= $moon_const ?><br> 
        <?= $moon_old ?><br>
        <?= $moon_phase ?><br>
        <?= $moon_ilum ?><br> 
        <?= $moon_culm ?><br> 
        <?= $moon_alt ?> 
     </div>
	 <div class="moon-widget">
       <script 
        type="text/javascript"
		src="https://moonphases.co.uk/js/widget.js"
		id="moonphase_widget"
		lat="<?= $latitude ?>" lng="<?= $longitude ?>"
		tz="<?= $tzAttr ?>"  
       	widget="tiny-nodate">
        </script>
	  </div>
	 <div  class="sun-box"><br>
        <?= $moon_rs1 ?><br>
        <?= $moon_rs2 ?><br>
        <?= $moon_nf1 ?><br>
        <?= $moon_nf2 ?><br>
     </div>
      <br>
      </div>
    </td>
   </tr>
</table>    
