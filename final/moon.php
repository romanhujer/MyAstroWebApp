<?php
function moon_phase($timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    $synodic_month = 29.53058867;
    $new_moon = strtotime("2000-01-06 18:14:00"); // známý nov
    $days_since = ($timestamp - $new_moon) / 86400;
    $phase = fmod($days_since, $synodic_month);
    return $phase;
}

function moon_phase_name($phase) {
    if ($phase < 1.84566) return "Nov";
    elseif ($phase < 5.53699) return "Dorůstající srpek";
    elseif ($phase < 9.22831) return "První čtvrť";
    elseif ($phase < 12.91963) return "Dorůstající Měsíc";
    elseif ($phase < 16.61096) return "Úplněk";
    elseif ($phase < 20.30228) return "Couvající Měsíc";
    elseif ($phase < 23.99361) return "Poslední čtvrť";
    elseif ($phase < 27.68493) return "Couvající srpek";
    else return "Nov";
}

$phase = moon_phase();
$name = moon_phase_name($phase);
$illumination = round(($phase / 29.53058867) * 100);
$age = round($phase, 1);

echo "<div style='font-family: monospace; text-align: center; padding: 1em; background: #111; color: #ccc; border: 1px solid #444; max-width: 200px;'>";
echo "<h3 style='color: #fff;'>Fáze Měsíce</h3>";
echo "<p><strong>$name</strong></p>";
echo "<p>Stáří: $age dní</p>";
echo "<p>Osvětlení: $illumination&nbsp;%</p>";
echo "<p style='font-size: 0.8em;'>".date("d.m.Y H:i")."</p>";
echo "</div>";
?>
