<?php
date_default_timezone_set('Europe/Prague');

function load_today_saturn($path) {
    $json = file_get_contents($path);
    if ($json === false) return null;
    $data = json_decode($json, true);
    if (!is_array($data)) return null;

    $today = (new DateTime('today'))->format('Y-m-d');

    foreach ($data as $row) {
        if (isset($row['date']) && $row['date'] === $today) {
            return $row;
        }
    }
    return null;
}

$saturn = load_today_saturn(__DIR__ . '/saturn_ephemeris.json');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>Jupiter – dnešní viditelnost</title>
<style>
body { font-family: system-ui, -apple-system, sans-serif; background:#111; color:#eee; }
.box { max-width: 700px; margin: 20px auto; padding: 16px; background:#222; border:1px solid #444; }
h1 { font-size: 20px; margin: 0 0 10px; }
table { width:100%; border-collapse: collapse; margin-bottom: 16px; }
td { padding: 4px 0; }
.label { color:#aaa; width: 35%; }
.value { font-weight: 600; }
svg { width:100%; height:220px; background:#000; border:1px solid #444; }
.axis { stroke:#555; stroke-width:1; }
.graph-line { fill:none; stroke:#4caf50; stroke-width:2; }
.graph-fill { fill:rgba(76,175,80,0.25); stroke:none; }
.text-small { font-size: 11px; fill:#aaa; }
</style>
</head>
<body>
<div class="box">
<?php if (!$saturn): ?>
    <p>Data pro Saturn dnes nejsou k dispozici.</p>
<?php else: ?>
    <h1>Saturn – dnešní viditelnost</h1>
    <?php
        $rise    = $saturn['rise_utc']    ? date('H:i', strtotime($saturn['rise_utc']))    : '—';
        $set     = $saturn['set_utc']     ? date('H:i', strtotime($saturn['set_utc']))     : '—';
        $graph   = $saturn['altitude_graph'] ?? [];
        $transit = $saturn['transit_utc'] ? date('H:i', strtotime($saturn['transit_utc'])) : '—';
        $maxAlt  = 0.0;
        foreach ($graph as $p) {
            if ($p['alt'] > $maxAlt) $maxAlt = $p['alt'];
        }
        if ($maxAlt < 10) $maxAlt = 10; // aby graf nebyl placatý
    ?>
    <table>
        <tr>
            <td class="label">Datum</td>
            <td class="value"><?php echo htmlspecialchars($saturn['date']); ?></td>
        </tr>
        <tr>
            <td class="label">Východ</td>
            <td class="value"><?php echo $rise; ?></td>
        </tr>
        <tr>
            <td class="label">Kulminace</td>
            <td class="value"><?php echo $transit; ?> 
        </td>
         
        </tr>
        <tr>
            <td class="label">Západ</td>
            <td class="value"><?php echo $set; ?></td>
        </tr>
    </table>

    <?php
        // připravíme body pro SVG
        $width  = 600;
        $height = 200;
        $paddingLeft  = 30;
        $paddingRight = 10;
        $paddingTop   = 10;
        $paddingBottom= 20;

        $innerW = $width  - $paddingLeft - $paddingRight;
        $innerH = $height - $paddingTop  - $paddingBottom;

        $n = count($graph);
        $points = [];
        if ($n > 1) {
            foreach ($graph as $i => $p) {
                $x = $paddingLeft + $innerW * ($i / ($n - 1));
                $y = $paddingTop + $innerH * (1 - ($p['alt'] / $maxAlt));
                $points[] = [$x, $y, $p['time'], $p['alt']];
            }
        }
    ?>


<?php
// dynamický krok osy Y
if ($maxAlt >= 40)      $yStep = 20;
elseif ($maxAlt >= 20)  $yStep = 10;
else                    $yStep = 5;

$yLines = floor($maxAlt / $yStep);

// osa X – každé 2 hodiny
// $xStepHours = 2;
// $xSteps = 24 / $xStepHours;
$xStepMinutes = 30;
$xSteps = (24 * 60) / $xStepMinutes; // 48 půlhodin
// výpočet X pozice kulminace


$transitRaw = $saturn['transit_utc'] ?? null;
// 1) Převod UTC → CET/CEST
$dt = new DateTime($transitRaw, new DateTimeZone('UTC'));
//$dt->setTimezone(new DateTimeZone('Europe/Prague'));
$dt->setTimezone(new DateTimeZone('UTC'));

// 2) Juliánské minuty tranzitu
$transitMinutes = $dt->format('H') * 60 + $dt->format('i');

// 3) Juliánské minuty začátku grafu (poledne CET)
$startMinutes = 12 * 60; // 12:00 CET

// 4) Rozdíl od začátku grafu
$diff = $transitMinutes - $startMinutes;
if ($diff < 0) {
    $diff += 1440; // přetočení přes půlnoc
}

// 5) Přepočet na pixely
$ratio = $diff / 1440; // 24 hodin = 1440 minut
$transitX = $paddingLeft + $innerW * $ratio;

?>
<svg viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>">

    

  <!-- horizontální mřížka + popisky Y -->
   <?php for ($i = 0; $i <= $yLines; $i++):
        $altVal = $i * $yStep;
        $gy = $paddingTop + $innerH * (1 - ($altVal / $maxAlt));
    ?>
        <line x1="<?php echo $paddingLeft; ?>"
              y1="<?php echo $gy; ?>"
              x2="<?php echo $width - $paddingRight; ?>"
              y2="<?php echo $gy; ?>"
              stroke="#333" stroke-width="1" />

        <text x="<?php echo $paddingLeft - 6; ?>"
              y="<?php echo $gy + 4; ?>"
              class="text-small"
              text-anchor="end">
            <?php echo $altVal; ?>°
        </text>
    <?php endfor; ?>


    <!-- vertikální mřížka po 2 hodinách -->

 <?php for ($i = 0; $i <= $xSteps; $i++):
    $vx = $paddingLeft + $innerW * ($i / $xSteps);

    // čas v UTC = 12:00 + i * 30 min
    $totalMinutes = 12*60 + $i * $xStepMinutes;
    $utcHour = floor(($totalMinutes / 60)) % 24;
    $utcMinute = $totalMinutes % 60;

    // vytvořit UTC DateTime
    $dt = new DateTime(sprintf('%02d:%02d', $utcHour, $utcMinute), new DateTimeZone('UTC'));

    // převod na CET/CEST (automaticky podle data)
    $dt->setTimezone(new DateTimeZone('Europe/Prague'));

    // výsledný čas
    $hour = (int)$dt->format('H');
    $minute = (int)$dt->format('i');

    // popisek každé 2 hodiny
    $isLabel = ($minute === 0) && ($hour % 2 === 0);
?>
    <line x1="<?php echo $vx; ?>"
          y1="<?php echo $paddingTop; ?>"
          x2="<?php echo $vx; ?>"
          y2="<?php echo $height - $paddingBottom; ?>"
          stroke="#333" stroke-width="1" />

    <?php if ($isLabel): ?>
        <text x="<?php echo $vx; ?>"
              y="<?php echo $height - $paddingBottom + 12; ?>"
              class="text-small"
              text-anchor="middle">
            <?php printf("%02d:00", $hour); ?>
        </text>
    <?php endif; ?>

<?php endfor; ?>
 
 
    <!-- osa X -->
    <line x1="<?php echo $paddingLeft; ?>"
          y1="<?php echo $height - $paddingBottom; ?>"
          x2="<?php echo $width - $paddingRight; ?>"
          y2="<?php echo $height - $paddingBottom; ?>"
          class="axis" />

    <!-- osa Y -->
    <line x1="<?php echo $paddingLeft; ?>"
          y1="<?php echo $paddingTop; ?>"
          x2="<?php echo $paddingLeft; ?>"
          y2="<?php echo $height - $paddingBottom; ?>"
          class="axis" />

    <!-- otočený popis osy Y -->
    <text x="<?php echo $paddingLeft - 35; ?>"
          y="<?php echo ($height / 2); ?>"
          class="text-small"
          text-anchor="middle"
          transform="rotate(-90 <?php echo $paddingLeft - 35; ?>,<?php echo ($height / 2); ?>)">
        Výška (°)
    </text>

    <!-- graf -->
    <?php if (!empty($points)): ?>
        <?php
            $poly = [];
            foreach ($points as $pt) $poly[] = $pt[0] . ',' . $pt[1];
            $poly[] = end($points)[0] . ',' . ($height - $paddingBottom);
            $poly[] = $points[0][0] . ',' . ($height - $paddingBottom);
            $polyPoints = implode(' ', $poly);

            $linePoints = implode(' ', array_map(fn($pt) => $pt[0].','.$pt[1], $points));
        ?>
        <polygon points="<?php echo $polyPoints; ?>" class="graph-fill" />
        <polyline points="<?php echo $linePoints; ?>" class="graph-line" />
    <?php endif; ?>

<?php if ($transitX !== null): ?>
    <line x1="<?php echo $transitX; ?>"
          y1="<?php echo $paddingTop; ?>"
          x2="<?php echo $transitX; ?>"
          y2="<?php echo $height - $paddingBottom; ?>"
          stroke="red" stroke-width="1.5" />
<?php endif; ?>

</svg>

<?php endif; ?>
</div>
</body>
</html>