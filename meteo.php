<?php
/*  komety.php
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

header("Content-Type: text/html; charset=UTF-8");

/* ---------------------------------------------------------
   1) Připojení k databázi
--------------------------------------------------------- */

$db_pass_file = "/home/hujer/.dbpass";
$dbpass = "";
$line = @file($db_pass_file);
if ($line && isset($line[0])) {
    $parts = explode(":", trim($line[0]));
    if (count($parts) >= 2) {
        $dbpass = $parts[1];
    }
}

$mysqli = new mysqli("localhost", "master", $dbpass, "hujer_net");
if ($mysqli->connect_error) {
    die("DB ERROR: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

$sensor = "SQM-HR03";

/* ---------------------------------------------------------
   2) Zpracování vstupních parametrů
--------------------------------------------------------- */

$from_param = $_GET['from'] ?? null;
$to_param   = $_GET['to']   ?? null;
$presure    = $_GET['p']  ??  'no';
$humidity   = $_GET['h']  ??  'no';


if ($from_param && $to_param) {
    $from_ts = strtotime($from_param);
    $to_ts   = strtotime($to_param);

    if ($from_ts === false || $to_ts === false || $from_ts >= $to_ts) {
        die("Neplatný interval.");
    }
} else {
    // výchozí: posledních 36 hodin
    $stmt = $mysqli->prepare("SELECT MAX(timesec) FROM teploty WHERE sensor=?");
    $stmt->bind_param("s", $sensor);
    $stmt->execute();
    $stmt->bind_result($max_ts);
    $stmt->fetch();
    $stmt->close();

    if (!$max_ts) die("Žádná data.");

    $to_ts   = $max_ts;
    $from_ts = $max_ts - 36 * 3600;
}

$from_input_value = date('Y-m-d\TH:i', $from_ts);
$to_input_value   = date('Y-m-d\TH:i', $to_ts);

/* ---------------------------------------------------------
   3) Přepočet tlaku na hladinu moře
--------------------------------------------------------- */

function pressure_to_sea_level_simple($p_hpa, $height_m = 600) {
    return $p_hpa + ($height_m / 8.0);
}

/* ---------------------------------------------------------
   4) Načtení dat – rovnoměrný sampling max. 900 bodů
--------------------------------------------------------- */

// 1) Zjistit počet řádků
$sql_count = "
    SELECT COUNT(*) 
    FROM teploty 
    WHERE sensor=? AND timesec BETWEEN ? AND ?
";

$stmt = $mysqli->prepare($sql_count);
$stmt->bind_param("sii", $sensor, $from_ts, $to_ts);
$stmt->execute();
$stmt->bind_result($total_rows);
$stmt->fetch();
$stmt->close();

if ($total_rows == 0) die("Pro zadaný interval nejsou data.");

// 2) Spočítat krok
$max_points = 780;
$step = max(1, floor($total_rows / $max_points));

// 3) Vybrat rovnoměrně rozložené body
$sql = "
    SELECT timesec, teplota1, tlak, vlhkost
    FROM (
        SELECT 
            timesec, teplota1, tlak, vlhkost,
            ROW_NUMBER() OVER (ORDER BY timesec) AS rn
        FROM teploty
        WHERE sensor=? AND timesec BETWEEN ? AND ?
    ) AS t
    WHERE t.rn % ? = 0
    ORDER BY timesec
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("siii", $sensor, $from_ts, $to_ts, $step);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['tlak_msl'] = pressure_to_sea_level_simple((float)$row['tlak'], 600);
    $data[] = $row;
}
$stmt->close();
$mysqli->close();

if (empty($data)) die("Pro zadaný interval nejsou data.");

/* ---------------------------------------------------------
   4e) Odstranění neplatných hodnot
--------------------------------------------------------- */

function filter_invalid_points($data) {
    $out = [];

    foreach ($data as $row) {
        $t = isset($row['teplota1']) ? (float)$row['teplota1'] : null;
        $p = isset($row['tlak_msl']) ? (float)$row['tlak_msl'] : null;
        $h = isset($row['vlhkost']) ? (float)$row['vlhkost'] : null;

        $valid_temp  = ($t !== null && $t > -50 && $t < 60);
        $valid_press = ($p !== null && $p >= 800 && $p <= 1100);
        $valid_hum   = ($h !== null && $h >= 10 && $h <= 100);

        if ($valid_temp || $valid_press || $valid_hum) {
            $out[] = $row;
        }
    }

    return $out;
}

$data = filter_invalid_points($data);

/* ---------------------------------------------------------
   4f) Jemná aproximace mezi body (hladká křivka)
--------------------------------------------------------- */

function smooth_interpolation($data, $field, $segments = 4) {
    $out = [];
    $count = count($data);
    if ($count < 2) return $data;

    for ($i = 0; $i < $count - 1; $i++) {
        $a = $data[$i];
        $b = $data[$i+1];

        $out[] = $a;

        $t1 = $a['timesec'];
        $t2 = $b['timesec'];

        $v1 = (float)$a[$field];
        $v2 = (float)$b[$field];

        for ($k = 1; $k < $segments; $k++) {
            $ratio = $k / $segments;
            $ts = $t1 + ($t2 - $t1) * $ratio;
            $val = $v1 + ($v2 - $v1) * $ratio;

            $out[] = [
                'timesec' => $ts,
                $field    => $val
            ];
        }
    }

    $out[] = end($data);
    return $out;
}

/* ---------------------------------------------------------
   5) SVG generátor grafů
--------------------------------------------------------- */

function generate_svg_line_chart($data, $field, $width = 900, $height = 220, $color = "#ff0000", $label = "") {

    if (empty($data)) return "<svg width=\"$width\" height=\"$height\"></svg>";

    // jemné vyhlazení křivky
    $data = smooth_interpolation($data, $field, 4);

    $min_ts = $data[0]['timesec'];
    $max_ts = end($data)['timesec'];
    if ($max_ts == $min_ts) $max_ts = $min_ts + 1;

    $min_val = PHP_INT_MAX;
    $max_val = -PHP_INT_MAX;

    foreach ($data as $row) {
        if (!isset($row[$field])) continue;
        $v = (float)$row[$field];
        if ($v < $min_val) $min_val = $v;
        if ($v > $max_val) $max_val = $v;
    }

    // fyzikální limity
    if ($field === 'tlak_msl' && $min_val < 800) $min_val = 800;
    if ($field === 'vlhkost' && $min_val < 10)   $min_val = 10;

    if ($max_val == $min_val) $max_val = $min_val + 1;

    $padding_left   = 50;
    $padding_right  = 10;
    $padding_top    = 20;
    $padding_bottom = 30;

    $plot_width  = $width  - $padding_left - $padding_right;
    $plot_height = $height - $padding_top  - $padding_bottom;

    $svg  = "<svg width=\"$width\" height=\"$height\" xmlns=\"http://www.w3.org/2000/svg\">\n";
    $svg .= "<rect x=\"0\" y=\"0\" width=\"$width\" height=\"$height\" fill=\"white\" />\n";

    /* --- Mřížka Y --- (jen celá čísla) */
    $grid_lines = 5;
    for ($i = 0; $i <= $grid_lines; $i++) {
        $y = $padding_top + ($plot_height / $grid_lines) * $i;
        $svg .= "<line x1=\"$padding_left\" y1=\"$y\" x2=\"" . ($width - $padding_right) . "\" y2=\"$y\" stroke=\"#cccccc\" />\n";

        $val = $max_val - ($max_val - $min_val) * ($i / $grid_lines);
        $svg .= "<text x=\"5\" y=\"" . ($y + 4) . "\" font-size=\"10\">" . round($val) . "</text>\n";
    }

    /* --- Čára 0 °C --- */
    if ($field === 'teplota1' && $min_val < 0 && $max_val > 0) {
        $y0 = $padding_top + ($max_val - 0) / ($max_val - $min_val) * $plot_height;
        $svg .= "<line x1=\"$padding_left\" y1=\"$y0\" x2=\"" . ($width - $padding_right) . "\" y2=\"$y0\" stroke=\"#000000\" stroke-dasharray=\"4,4\" />\n";
        $svg .= "<text x=\"" . ($padding_left - 5) . "\" y=\"" . ($y0 + 4) . "\" font-size=\"10\" text-anchor=\"end\">0°C</text>\n";
    }

    /* ---------------------------------------------------------
       OPRAVENÁ X‑OSA — kalendářní zarovnání
    --------------------------------------------------------- */
/* ---------------------------------------------------------
   OPRAVENÁ X‑OSA — kalendářní zarovnání + správné popisky
--------------------------------------------------------- */

$interval = $max_ts - $min_ts;
$total_hours = $interval / 3600.0;
$total_days  = $interval / 86400.0;

$cz_months = ["led","úno","bře","dub","kvě","čer","čvc","srp","zář","říj","lis","pro"];

/* --- Určení režimu a počátečního času --- */
if ($total_days > 730) {                 // > 2 roky
    $mode = "year";
    $t = strtotime(date("Y-01-01 00:00:00", $min_ts));
}
elseif ($total_days > 90) {              // > 3 měsíce
    $mode = "month";
    $t = strtotime(date("Y-m-01 00:00:00", $min_ts));
}
elseif ($total_days > 14) {              // > 2 týdny
    $mode = "week";
    $dow = date("N", $min_ts); // 1 = pondělí
    $t = strtotime(date("Y-m-d 00:00:00", $min_ts)) - ($dow - 1) * 86400;
}
elseif ($total_hours > 48) {             // > 48 hodin
    $mode = "day";
    $t = strtotime(date("Y-m-d 00:00:00", $min_ts));
}
elseif ($total_hours > 24) {             // > 24 hodin
    $mode = "hour2";
    $t = strtotime(date("Y-m-d H:00:00", $min_ts));
}
else {                                   // <= 24 hodin
    $mode = "hour1";
    $t = strtotime(date("Y-m-d H:00:00", $min_ts));
}

/* --- Pokud zarovnání spadlo před interval, posuň dopředu --- */
if ($t < $min_ts) {
    if ($mode === "year")   $t = strtotime("+1 year", $t);
    if ($mode === "month")  $t = strtotime("+1 month", $t);
    if ($mode === "week")   $t = strtotime("+1 week", $t);
    if ($mode === "day")    $t = strtotime("+1 day", $t);
    if ($mode === "hour2")  $t = strtotime("+2 hours", $t);
    if ($mode === "hour1")  $t = strtotime("+1 hour", $t);
}

/* ---------------------------------------------------------
   Kreslení svislých čar a popisků — kalendářní inkrementy
--------------------------------------------------------- */

while ($t <= $max_ts) {

    $x = $padding_left + ($t - $min_ts) / ($max_ts - $min_ts) * $plot_width;

    // svislá čára
    $svg .= "<line x1=\"$x\" y1=\"$padding_top\" x2=\"$x\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"#e0e0e0\" />\n";

    // popisek
    if ($mode === "year") {
        $label_x = date("Y", $t);
    }
    elseif ($mode === "month") {
        $m = (int)date("n", $t);
        $label_x = $cz_months[$m-1];
    }
    elseif ($mode === "week") {
        $label_x = "Týden " . date("W", $t);
    }
    elseif ($mode === "day") {
        $label_x = date("j.n.", $t);
    }
    else { // hour1 / hour2
        $label_x = date("H:i", $t);
    }

    $svg .= "<text x=\"$x\" y=\"" . ($height - 10) . "\" font-size=\"10\" text-anchor=\"middle\">$label_x</text>\n";

    // kalendářní inkrement
    if ($mode === "year")   $t = strtotime("+1 year", $t);
    if ($mode === "month")  $t = strtotime("+1 month", $t);
    if ($mode === "week")   $t = strtotime("+1 week", $t);
    if ($mode === "day")    $t = strtotime("+1 day", $t);
    if ($mode === "hour2")  $t = strtotime("+2 hours", $t);
    if ($mode === "hour1")  $t = strtotime("+1 hour", $t);
}

    

    /* --- Osy --- */
    $svg .= "<line x1=\"$padding_left\" y1=\"" . ($height - $padding_bottom) . "\" x2=\"" . ($width - $padding_right) . "\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"black\" />\n";
    $svg .= "<line x1=\"$padding_left\" y1=\"$padding_top\" x2=\"$padding_left\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"black\" />\n";

    /* --- Nadpis grafu --- */
    if ($label !== "") {
        $svg .= "<text x=\"" . ($width/2) . "\" y=\"15\" text-anchor=\"middle\" font-size=\"14\">$label</text>\n";
    }

    /* --- Křivka (path) --- */
    $commands = [];
    $prev_valid = false;

    foreach ($data as $row) {
        if (!isset($row[$field])) {
            $prev_valid = false;
            continue;
        }

        $v = (float)$row[$field];

        if ($field === 'tlak_msl' && ($v < 800 || $v > 1100)) {
            $prev_valid = false;
            continue;
        }
        if ($field === 'vlhkost' && ($v < 10 || $v > 100)) {
            $prev_valid = false;
            continue;
        }
        if ($field === 'teplota1' && ($v < -50 || $v > 60)) {
            $prev_valid = false;
            continue;
        }

        $ts = $row['timesec'];
        $x = $padding_left + ($ts - $min_ts) / ($max_ts - $min_ts) * $plot_width;
        $y = $padding_top + ($max_val - $v) / ($max_val - $min_val) * $plot_height;

        if (!$prev_valid) {
            $commands[] = "M$x,$y";
        } else {
            $commands[] = "L$x,$y";
        }

        $prev_valid = true;
    }

    if (!empty($commands)) {
        $svg .= "<path d=\"" . implode(" ", $commands) . "\" fill=\"none\" stroke=\"$color\" stroke-width=\"2\" />\n";
    }

    $svg .= "</svg>\n";

    return $svg;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="/css/hujer.css">
<title>Grafy počasí</title>
<style>
body { font-family: sans-serif;  color: white;}
.graf { margin-bottom: 25px; }
form { margin-bottom: 20px; color: white; font-size: 14px;}
</style>
</head>
<body>
<!-- 
<h1>Graf počasí Vrkoslavice</h1>
<div class="main-wrapper">Interval: <?php echo date('Y-m-d H:i', $from_ts); ?> → <?php echo date('Y-m-d H:i', $to_ts); ?></div>
-->

<div class="graf" >
<?php echo generate_svg_line_chart($data, 'teplota1', $max_points, 220, "#d32f2f", "Teplota [°C]"); ?>
</div>
<?php if (  $presure === 'yes'  ): ?>
    <div class="graf">
    <?php echo generate_svg_line_chart($data, 'tlak_msl', $max_points, 220, "#1976d2", "Tlak přepočtený na hladinu moře [hPa]"); ?>
    </div>
<?php endif; ?>
<?php if (  $humidity === 'yes'  ): ?>
    <div class="graf">
    <?php echo generate_svg_line_chart($data, 'vlhkost', $max_points, 220, "#388e3c", "Vlhkost [%]"); ?>
    </div>
<?php endif; ?>
<form method="get">
    <label>Od:
        <input type="datetime-local" name="from" value="<?php echo htmlspecialchars($from_input_value, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Do:
        <input type="datetime-local" name="to" value="<?php echo htmlspecialchars($to_input_value, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Tlak:
         <input type="checkbox" id="p" name="p" value="yes" 
         <?php if (  $presure === 'yes'  ): ?> checked <?php endif; ?>  />
    </label>
    <label>Vlhkost:
         <input type="checkbox" id="h" name="h" value="yes" 
         <?php if ( $humidity === 'yes'  ): ?> checked <?php endif; ?>  />
    </label>
    <button type="submit">Zobrazit</button>
</form>
</body>
</html>
