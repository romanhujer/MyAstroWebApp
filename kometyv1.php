<?php
date_default_timezone_set('Europe/Prague');

$json_dir = "/opt/astro_json";

function load_comets($path) {
    $json = file_get_contents($path);
    if ($json === false) return null;
    $data = json_decode($json, true);
    if (!is_array($data)) return null;
    return $data;
}

$data = load_comets($json_dir . '/comets_current_aerith_ra_alt.json');
$comets = $data['comets'] ?? [];

// LIMIT
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 5;

// zaokrouhlení času na 30 minut (UTC)
function roundTo30($dt) {
    $m = (int)$dt->format('i');
    $rounded = ($m < 15) ? 0 : (($m < 45) ? 30 : 60);
    if ($rounded === 60) {
        $dt->modify('+1 hour');
        $rounded = 0;
    }
    return $dt->format('Y-m-d H:' . sprintf('%02d', $rounded));
}

$nowUTC = new DateTime('now', new DateTimeZone('UTC'));
$rounded = roundTo30(clone $nowUTC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>Komety – aktuální viditelnost</title>
<style>
body { font-family: system-ui, -apple-system, sans-serif; background:#111; color:#eee; }
.box { max-width: 1100px; margin: 20px auto; padding: 16px; background:#222; border:1px solid #444; }
h1 { font-size: 22px; margin: 0 0 15px; }
h2 { font-size: 18px; margin: 0 0 6px; }
table { width:100%; border-collapse: collapse; }
.main-table tr + tr { border-top:1px solid #444; }
.main-table td { padding: 10px 6px; vertical-align: top; }
.inner-table { width:100%; border-collapse: collapse; }
.inner-table td { padding: 2px 0; }
.label { color:#aaa; width: 40%; }
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
<h1>Komety – aktuální viditelnost</h1>
<p>Čas: <?= $rounded ?> UTC</p>

<table class="main-table">
<?php
$i = 0;
foreach ($comets as $c):
    if ($i >= $limit) break;
    $i++;

    // najdeme nejbližší časový bod k zaokrouhlenému času
    $roundedUTC = new DateTime($rounded, new DateTimeZone('UTC'));
    $roundedStr = $roundedUTC->format('Y-m-d H:i');

    $current = null;
    $bestDiff = 999999999;

    foreach ($c['graph_48h'] as $p) {
        $pt = new DateTime($p['time_utc'], new DateTimeZone('UTC'));
        $ptStr = $pt->format('Y-m-d H:i');
        $diff = abs(strtotime($ptStr) - strtotime($roundedStr));
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $current = $p;
        }
    }

    if (!$current) continue;

    // základní hodnoty
    $ra   = sprintf("%.2f h", $current['ra_hours_j2000']);
    $dec  = sprintf("%.2f°", $current['dec_deg_j2000']);
    $alt  = sprintf("%.1f°", $current['alt_deg']);
    $az   = sprintf("%.1f°", $current['az_deg']);
    $r    = $current['r_au'];
    $delta= $current['delta_au'];
    $elong= $current['elong_deg'];
    $mag  = $current['mag_est'];

    $rise    = !empty($c['rise_utc'])    ? date('H:i', strtotime($c['rise_utc']))    : '—';
    $transit = !empty($c['transit_utc']) ? date('H:i', strtotime($c['transit_utc'])) : '—';
    $set     = !empty($c['set_utc'])     ? date('H:i', strtotime($c['set_utc']))     : '—';

    // -----------------------------
    // 24h OKNO JAKO PLANETY (B1)
    // -----------------------------
    $graph = $c['graph_48h'];
    $n = count($graph);
    if ($n < 2) continue;

    // kulminace → start = -12h
    if (!empty($c['transit_utc'])) {
        $t = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
        $transitMinutes = $t->format('H') * 60 + $t->format('i');
        $roundedTransit = round($transitMinutes / 60) * 60;
        $startMinutes = $roundedTransit - 12 * 60;
        while ($startMinutes < 0) $startMinutes += 1440;
        while ($startMinutes >= 1440) $startMinutes -= 1440;
    } else {
        $startMinutes = 0;
    }

    // vybereme 24h okno z 48h dat
    $filtered = [];
    foreach ($graph as $p) {
        $dt = new DateTime($p['time_utc'], new DateTimeZone('UTC'));
        $h = (int)$dt->format('H');
        $m = (int)$dt->format('i');

        // spočítáme rozdíl vůči startu
        $ptMinutes = $h * 60 + $m;
        $diff = $ptMinutes - $startMinutes;
        if ($diff < 0) $diff += 1440;

        if ($diff < 1440) {
            $filtered[] = $p;
        }
    }

    $graph = $filtered;
    $n = count($graph);
    if ($n < 2) continue;

    // max výška
    $maxAlt = 0.0;
    foreach ($graph as $p) {
        if ($p['alt_deg'] > $maxAlt) $maxAlt = $p['alt_deg'];
    }
    if ($maxAlt < 10) $maxAlt = 10;

    // SVG parametry
    $width  = 600;
    $height = 200;
    $paddingLeft   = 30;
    $paddingRight  = 10;
    $paddingTop    = 10;
    $paddingBottom = 20;

    $innerW = $width  - $paddingLeft - $paddingRight;
    $innerH = $height - $paddingTop  - $paddingBottom;

    // výpočet X/Y souřadnic
    $points = [];
    foreach ($graph as $p) {
        $dt = new DateTime($p['time_utc'], new DateTimeZone('UTC'));
        $h = (int)$dt->format('H');
        $m = (int)$dt->format('i');

        $ptMinutes = $h * 60 + $m;
        $diff = $ptMinutes - $startMinutes;
        if ($diff < 0) $diff += 1440;

        $ratio = $diff / 1440.0;
        $x = $paddingLeft + $innerW * $ratio;

        $y = $paddingTop + $innerH * (1 - ($p['alt_deg'] / $maxAlt));

        $points[] = [$x, $y];
    }

    // osa Y
    if ($maxAlt >= 40)      $yStep = 20;
    elseif ($maxAlt >= 20)  $yStep = 10;
    else                    $yStep = 5;

    $yLines = floor($maxAlt / $yStep);

    // osa X – popisky
    $xStepMinutes = 30;
    $xSteps = (24 * 60) / $xStepMinutes;

    // transit X
    $transitX = null;
    if (!empty($c['transit_utc'])) {
        $dtT = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
        $transitMinutes = $dtT->format('H') * 60 + $dtT->format('i');
        $diff = $transitMinutes - $startMinutes;
        if ($diff < 0) $diff += 1440;
        $ratio = $diff / 1440;
        $transitX = $paddingLeft + $innerW * $ratio;
    }
?>
<tr>

    <!-- LEVÁ BUŇKA -->
    <td style="width:40%;">
        <h2><?= htmlspecialchars($c['designation']) ?></h2>
        <table class="inner-table">
            <tr><td class="label">Jasnost</td><td class="value"><?= $mag ?></td></tr>
            <tr><td class="label">RA</td><td class="value"><?= $ra ?></td></tr>
            <tr><td class="label">Dec</td><td class="value"><?= $dec ?></td></tr>
            <tr><td class="label">Výška</td><td class="value"><?= $alt ?></td></tr>
            <tr><td class="label">Azimut</td><td class="value"><?= $az ?></td></tr>
            <tr><td class="label">r</td><td class="value"><?= $r ?> AU</td></tr>
            <tr><td class="label">Δ</td><td class="value"><?= $delta ?> AU</td></tr>
            <tr><td class="label">Elongace</td><td class="value"><?= $elong ?>°</td></tr>
            <tr><td class="label">Východ</td><td class="value"><?= $rise ?></td></tr>
            <tr><td class="label">Kulminace</td><td class="value"><?= $transit ?></td></tr>
            <tr><td class="label">Západ</td><td class="value"><?= $set ?></td></tr>
        </table>
    </td>

    <!-- PRAVÁ BUŇKA: GRAF -->
    <td style="width:60%;">
        <svg viewBox="0 0 <?= $width ?> <?= $height ?>">

        <?php for ($j = 1; $j <= $yLines; $j++): ?>
            <?php
                $altVal = $j * $yStep;
                $gy = $paddingTop + $innerH * (1 - ($altVal / $maxAlt));
            ?>
            <line x1="<?= $paddingLeft ?>" y1="<?= $gy ?>"
                  x2="<?= $width - $paddingRight ?>" y2="<?= $gy ?>"
                  stroke="#333" stroke-width="1" />

            <text x="<?= $paddingLeft - 6 ?>" y="<?= $gy + 4 ?>"
                  class="text-small" text-anchor="end">
                <?= $altVal ?>°
            </text>
        <?php endfor; ?>

        <?php for ($j = 0; $j <= $xSteps; $j++):
            $vx = $paddingLeft + $innerW * ($j / $xSteps);

            $totalMinutes = $startMinutes + $j * $xStepMinutes;
            $utcHour = floor(($totalMinutes / 60)) % 24;
            $utcMinute = $totalMinutes % 60;

            $dt = new DateTime(sprintf('%02d:%02d', $utcHour, $utcMinute), new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Europe/Prague'));

            $hour = (int)$dt->format('H');
            $minute = (int)$dt->format('i');

            $isLabel = ($minute === 0) && ($hour % 2 === 0);
        ?>
            <line x1="<?= $vx ?>" y1="<?= $paddingTop ?>"
                  x2="<?= $vx ?>" y2="<?= $height - $paddingBottom ?>"
                  stroke="#333" stroke-width="1" />

            <?php if ($isLabel): ?>
                <text x="<?= $vx ?>" y="<?= $height - $paddingBottom + 12 ?>"
                      class="text-small" text-anchor="middle">
                    <?= sprintf("%02d:00", $hour) ?>
                </text>
            <?php endif; ?>

        <?php endfor; ?>

            <line x1="<?= $paddingLeft ?>" y1="<?= $height - $paddingBottom ?>"
                  x2="<?= $width - $paddingRight ?>" y2="<?= $height - $paddingBottom ?>"
                  class="axis" />

            <line x1="<?= $paddingLeft ?>" y1="<?= $paddingTop ?>"
                  x2="<?= $paddingLeft ?>" y2="<?= $height - $paddingBottom ?>"
                  class="axis" />

            <text x="<?= $paddingLeft - 35 ?>" y="<?= ($height / 2) ?>"
                  class="text-small" text-anchor="middle"
                  transform="rotate(-90 <?= $paddingLeft - 35 ?>,<?= ($height / 2) ?>)">
                Výška (°)
            </text>

        <?php if (!empty($points)):
            $poly = [];
            foreach ($points as $pt) $poly[] = $pt[0] . ',' . $pt[1];
            $poly[] = end($points)[0] . ',' . ($height - $paddingBottom);
            $poly[] = $points[0][0] . ',' . ($height - $paddingBottom);
            $polyPoints = implode(' ', $poly);

            $linePoints = implode(' ', array_map(fn($pt) => $pt[0].','.$pt[1], $points));
        ?>
            <?php if ($maxAlt > 3): ?>
                <polygon points="<?= $polyPoints ?>" class="graph-fill" />
            <?php endif; ?>

            <polyline points="<?= $linePoints ?>" class="graph-line" />
        <?php endif; ?>

        <?php if ($transitX !== null): ?>
            <line x1="<?= $transitX ?>" y1="<?= $paddingTop ?>"
                  x2="<?= $transitX ?>" y2="<?= $height - $paddingBottom ?>"
                  stroke="red" stroke-width="1.5" />
        <?php endif; ?>

        </svg>
    </td>

</tr>
<?php endforeach; ?>
</table>

</div>

</body>
</html>
