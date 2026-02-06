<?php
// graf_pocasi.php
header("Content-Type: text/html; charset=UTF-8");

// 1) Připojení k DB
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

// 2) Zpracování vstupních parametrů
$sensor = "SQM-HR03"; // případně parametrizovat

$from_param = isset($_GET['from']) ? $_GET['from'] : null;
$to_param   = isset($_GET['to'])   ? $_GET['to']   : null;

if ($from_param && $to_param) {
    // očekáváme formát "YYYY-MM-DD HH:MM"
    $from_ts = strtotime($from_param);
    $to_ts   = strtotime($to_param);
    if ($from_ts === false || $to_ts === false || $from_ts >= $to_ts) {
        die("Neplatný interval (from/to).");
    }
} else {
    // default: posledních 36 hodin
    $sql_max = "SELECT MAX(timesec) AS max_ts FROM teploty WHERE sensor = ?";
    $stmt = $mysqli->prepare($sql_max);
    $stmt->bind_param("s", $sensor);
    $stmt->execute();
    $stmt->bind_result($max_ts);
    $stmt->fetch();
    $stmt->close();

    if (!$max_ts) {
        die("V databázi nejsou žádná data pro sensor $sensor.");
    }

    $to_ts   = $max_ts;
    $from_ts = $max_ts - 36 * 3600; // 36 hodin zpět
}

// 3) Načtení dat z DB
$sql = "
    SELECT timesec, teplota1, tlak, vlhkost
    FROM teploty
    WHERE sensor = ?
      AND timesec BETWEEN ? AND ?
    ORDER BY timesec ASC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("sii", $sensor, $from_ts, $to_ts);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();
$mysqli->close();

if (empty($data)) {
    die("Pro zadaný interval nejsou žádná data.");
}

function generate_svg_line_chart($data, $field, $width = 900, $height = 220, $color = "#ff0000", $label = "") {

    if (empty($data)) {
        return "<svg width=\"$width\" height=\"$height\"></svg>";
    }

    // časový rozsah
    $min_ts = $data[0]['timesec'];
    $max_ts = $data[count($data)-1]['timesec'];
    if ($max_ts == $min_ts) $max_ts = $min_ts + 1;

    // min/max hodnoty
    $min_val = PHP_INT_MAX;
    $max_val = -PHP_INT_MAX;
    foreach ($data as $row) {
        $v = (float)$row[$field];
        if ($v < $min_val) $min_val = $v;
        if ($v > $max_val) $max_val = $v;
    }
    if ($max_val == $min_val) $max_val = $min_val + 1;

    // rozměry
    $padding_left   = 50;
    $padding_right  = 10;
    $padding_top    = 20;
    $padding_bottom = 30;

    $plot_width  = $width  - $padding_left - $padding_right;
    $plot_height = $height - $padding_top  - $padding_bottom;

    // SVG start
    $svg  = "<svg width=\"$width\" height=\"$height\" xmlns=\"http://www.w3.org/2000/svg\">\n";
    $svg .= "<rect x=\"0\" y=\"0\" width=\"$width\" height=\"$height\" fill=\"white\" />\n";

    // === MŘÍŽKA Y (horizontální) ===
    $grid_lines = 5;
    for ($i = 0; $i <= $grid_lines; $i++) {
        $y = $padding_top + ($plot_height / $grid_lines) * $i;
        $svg .= "<line x1=\"$padding_left\" y1=\"$y\" x2=\"" . ($width - $padding_right) . "\" y2=\"$y\" stroke=\"#cccccc\" stroke-width=\"1\" />\n";

        // popisek hodnoty
        $val = $max_val - ($max_val - $min_val) * ($i / $grid_lines);
        $svg .= "<text x=\"5\" y=\"" . ($y + 4) . "\" font-size=\"10\" fill=\"#000\">" . round($val, 1) . "</text>\n";
    }

    // === MŘÍŽKA X (vertikální) ===
    // spočítáme vhodný krok (např. každé 2–3 hodiny)
    $total_hours = ($max_ts - $min_ts) / 3600;
    $step_hours = ($total_hours > 30) ? 3 : 2;

    for ($h = ceil($min_ts / 3600) * 3600; $h <= $max_ts; $h += $step_hours * 3600) {
        $x = $padding_left + ($h - $min_ts) / ($max_ts - $min_ts) * $plot_width;
        $svg .= "<line x1=\"$x\" y1=\"$padding_top\" x2=\"$x\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"#e0e0e0\" stroke-width=\"1\" />\n";

        // popisek času
        $svg .= "<text x=\"$x\" y=\"" . ($height - 10) . "\" font-size=\"10\" text-anchor=\"middle\" fill=\"#000\">" .
                date("H:i", $h) . "</text>\n";
    }

    // === Osy ===
    $axis_color = "#000000";
    // X osa
    $svg .= "<line x1=\"$padding_left\" y1=\"" . ($height - $padding_bottom) . "\" x2=\"" . ($width - $padding_right) . "\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"$axis_color\" stroke-width=\"1.2\" />\n";
    // Y osa
    $svg .= "<line x1=\"$padding_left\" y1=\"$padding_top\" x2=\"$padding_left\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"$axis_color\" stroke-width=\"1.2\" />\n";

    // === Popisek grafu ===
    if ($label !== "") {
        $svg .= "<text x=\"" . ($width/2) . "\" y=\"15\" text-anchor=\"middle\" font-size=\"14\" fill=\"#000\">$label</text>\n";
    }

    // === Křivka ===
    $points = [];
    foreach ($data as $row) {
        $ts = $row['timesec'];
        $v  = (float)$row[$field];

        $x = $padding_left + ($ts - $min_ts) / ($max_ts - $min_ts) * $plot_width;
        $y = $padding_top + ($max_val - $v) / ($max_val - $min_val) * $plot_height;

        $points[] = "$x,$y";
    }

    $svg .= "<polyline fill=\"none\" stroke=\"$color\" stroke-width=\"2\" points=\"" . implode(" ", $points) . "\" />\n";

    $svg .= "</svg>\n";
    return $svg;
}



function xgenerate_svg_line_chart($data, $field, $width = 800, $height = 200, $color = "#ff0000", $label = "") {
    if (empty($data)) {
        return "<svg width=\"$width\" height=\"$height\"></svg>";
    }

    // časový rozsah
    $min_ts = $data[0]['timesec'];
    $max_ts = $data[count($data)-1]['timesec'];
    if ($max_ts == $min_ts) {
        $max_ts = $min_ts + 1; // ochrana proti dělení nulou
    }

    // min/max hodnoty
    $min_val = PHP_INT_MAX;
    $max_val = -PHP_INT_MAX;
    foreach ($data as $row) {
        $v = (float)$row[$field];
        if ($v < $min_val) $min_val = $v;
        if ($v > $max_val) $max_val = $v;
    }
    if ($max_val == $min_val) {
        $max_val = $min_val + 1; // ochrana
    }

    $padding_left   = 40;
    $padding_right  = 10;
    $padding_top    = 10;
    $padding_bottom = 20;

    $plot_width  = $width  - $padding_left - $padding_right;
    $plot_height = $height - $padding_top  - $padding_bottom;

    $points = [];
    foreach ($data as $row) {
        $ts = $row['timesec'];
        $v  = (float)$row[$field];

        $x = $padding_left + ($ts - $min_ts) / ($max_ts - $min_ts) * $plot_width;
        // invert Y (vyšší hodnota výš)
        $y = $padding_top + ($max_val - $v) / ($max_val - $min_val) * $plot_height;

        $points[] = $x . "," . $y;
    }

    $points_str = implode(" ", $points);

    // jednoduché osy
    $axis_color = "#000000";
    $svg  = "<svg width=\"$width\" height=\"$height\" xmlns=\"http://www.w3.org/2000/svg\">\n";
    $svg .= "  <rect x=\"0\" y=\"0\" width=\"$width\" height=\"$height\" fill=\"white\" stroke=\"none\" />\n";
    // osa X
    $svg .= "  <line x1=\"$padding_left\" y1=\"" . ($height - $padding_bottom) . "\" x2=\"" . ($width - $padding_right) . "\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"$axis_color\" stroke-width=\"1\" />\n";
    // osa Y
    $svg .= "  <line x1=\"$padding_left\" y1=\"$padding_top\" x2=\"$padding_left\" y2=\"" . ($height - $padding_bottom) . "\" stroke=\"$axis_color\" stroke-width=\"1\" />\n";

    // popisek grafu
    if ($label !== "") {
        $svg .= "  <text x=\"" . ($width/2) . "\" y=\"15\" text-anchor=\"middle\" font-size=\"14\" fill=\"#000\">$label</text>\n";
    }

    // min/max popisky na ose Y
    $svg .= "  <text x=\"5\" y=\"" . ($padding_top + 10) . "\" font-size=\"10\" fill=\"#000\">" . round($max_val, 1) . "</text>\n";
    $svg .= "  <text x=\"5\" y=\"" . ($height - $padding_bottom) . "\" font-size=\"10\" fill=\"#000\">" . round($min_val, 1) . "</text>\n";

    // samotná křivka
    $svg .= "  <polyline fill=\"none\" stroke=\"$color\" stroke-width=\"1.5\" points=\"$points_str\" />\n";

    $svg .= "</svg>\n";
    return $svg;
}

// 5) HTML výstup
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Grafy počasí</title>
    <style>
        body { font-family: sans-serif; }
        .graf { margin-bottom: 20px; }
        form { margin-bottom: 20px; }
        label { margin-right: 10px; }
        input[type="text"] { width: 200px; }
    </style>
</head>
<body>
<h1>Grafy počasí (<?php echo htmlspecialchars($sensor); ?>)</h1>

<form method="get">
    <label>Od (YYYY-MM-DD HH:MM):
        <input type="text" name="from" value="<?php echo $from_param ? htmlspecialchars($from_param) : date('Y-m-d H:i', $from_ts); ?>">
    </label>
    <label>Do (YYYY-MM-DD HH:MM):
        <input type="text" name="to" value="<?php echo $to_param ? htmlspecialchars($to_param) : date('Y-m-d H:i', $to_ts); ?>">
    </label>
    <button type="submit">Zobrazit</button>
</form>

<p>Aktuální interval: <?php echo date('Y-m-d H:i', $from_ts); ?> &rarr; <?php echo date('Y-m-d H:i', $to_ts); ?></p>

<div class="graf">
    <?php echo generate_svg_line_chart($data, 'teplota1', 900, 220, "#d32f2f", "Teplota [°C]"); ?>
</div>

<div class="graf">
    <?php echo generate_svg_line_chart($data, 'tlak', 900, 220, "#1976d2", "Tlak [hPa]"); ?>
</div>

<div class="graf">
    <?php echo generate_svg_line_chart($data, 'vlhkost', 900, 220, "#388e3c", "Vlhkost [%]"); ?>
</div>

</body>
</html>