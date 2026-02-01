<?php
date_default_timezone_set("Europe/Prague");

// Souřadnice
$latitude = 50.11;
$longitude = 15.40;
$timestamp = time();

// Slunce – standardní výška 90° (horizont)
$sunrise = date_sunrise($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 90, 1);
$sunset = date_sunset($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 90, 1);

// Astronomický rozbřesk a soumrak – výška -18°
$astro_twilight_begin = date_sunrise($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 108, 1);
$astro_twilight_end = date_sunset($timestamp, SUNFUNCS_RET_STRING, $latitude, $longitude, 108, 1);

// Výstup
echo "<div style='font-family: monospace; background:#111; color:#ccc; padding:1em; max-width:300px; border:1px solid #444'>";
echo "<h3 style='color:#fff;'>Slunce – ".date("d.m.Y")."</h3>";
echo "<p><strong>Souřadnice:</strong> 50.11° N, 15.40° E</p>";
echo "<p><strong>Východ Slunce:</strong> $sunrise</p>";
echo "<p><strong>Západ Slunce:</strong> $sunset</p>";
echo "<p><strong>Astronomická noc:</strong><br> $astro_twilight_end – $astro_twilight_begin</p>";
echo "<p style='font-size:0.8em;'>Aktualizováno: ".date("H:i")."</p>";
echo "</div>";
?>

