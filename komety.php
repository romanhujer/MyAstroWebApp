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

// limit komet
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;

// volba osy X: transit (B1) nebo noon
$xaxis = $_GET['xaxis'] ?? 'transit';
if (!in_array($xaxis, ['transit', 'noon'], true)) {
    $xaxis = 'transit';
}

// zaokrouhlení času na 30 minut (UTC) – jen pro info v hlavičce
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
<p>Čas: <?= htmlspecialchars($rounded) ?> UTC</p>

<table class="main-table">
<?php
$shown = 0;
foreach ($comets as $c):
    if ($shown >= $limit) break;

    $graph48 = $c['graph_48h'] ?? [];
    if (count($graph48) < 2) continue;

    // najdeme nejbližší časový bod k zaokrouhlenému času – pro aktuální údaje
    $roundedUTC = new DateTime($rounded, new DateTimeZone('UTC'));
    $roundedTs  = $roundedUTC->getTimestamp();

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

    if (!$current) continue;

    // základní hodnoty v aktuálním čase
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
    // 24h okno – vždy přesně 24 hodin
    // -----------------------------
    $firstDT = new DateTime($graph48[0]['time_utc'], new DateTimeZone('UTC'));

    if ($xaxis === 'noon') {
        // 12:00–12:00 podle dne kulminace, nebo prvního bodu
        if (!empty($c['transit_utc'])) {
            $t = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
        } else {
            $t = clone $firstDT;
        }
        $dayStr = $t->format('Y-m-d');
        $hourT  = (int)$t->format('H');

        $startDT = new DateTime($dayStr . ' 12:00:00', new DateTimeZone('UTC'));
        if ($hourT < 12) {
            $startDT->modify('-1 day');
        }
        $endDT = clone $startDT;
        $endDT->modify('+24 hours');
    } else {
        // transit (B1) – kulminace -12h → +12h
        if (!empty($c['transit_utc'])) {
            $t = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
            $startDT = clone $t;
            $startDT->modify('-12 hours');
            $endDT = clone $t;
            $endDT->modify('+12 hours');
        } else {
            $startDT = clone $firstDT;
            $endDT   = clone $firstDT;
            $endDT->modify('+24 hours');
        }
    }

    $startTs = $startDT->getTimestamp();
    $endTs   = $endDT->getTimestamp();
    if ($endTs <= $startTs) continue;

    $spanSec = max(1, $endTs - $startTs);

    // vybereme body v tomto 24h okně (body mimo okno ignorujeme)
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

    if (count($graph) < 2) continue;

    // pokud je kometa v celém 24h okně nad obzorem → vůbec nezobrazit
    $allAbove = true;
    foreach ($graph as $p) {
        if ($p['alt_deg'] <= 0) {
            $allAbove = false;
            break;
        }
    }
    if ($allAbove) continue;

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

    // body grafu – absolutní čas, alt oříznutý na ≥ 0
    $points = [];
    foreach ($graph as $p) {
        $ts = $p['_ts'];
        $ratio = ($ts - $startTs) / $spanSec;
        if ($ratio < 0) $ratio = 0;
        if ($ratio > 1) $ratio = 1;

        $x = $paddingLeft + $innerW * $ratio;

        $altPlot = max(0, $p['alt_deg']);
        $y = $paddingTop + $innerH * (1 - ($altPlot / $maxAlt));

        $points[] = [$x, $y];
    }

    // osa Y
    if ($maxAlt >= 40)      $yStep = 20;
    elseif ($maxAlt >= 20)  $yStep = 10;
    else                    $yStep = 5;

    $yLines = floor($maxAlt / $yStep);

    // osa X – přesně 24h, 30min krok, popisky po 2h
    $totalMinutes = 24 * 60;
    $xStepMinutes = 30;
    $xSteps = (int)($totalMinutes / $xStepMinutes);

    // transit X – jen pokud spadá do okna
    $transitX = null;
    if (!empty($c['transit_utc'])) {
        $dtT = new DateTime($c['transit_utc'], new DateTimeZone('UTC'));
        $tsT = $dtT->getTimestamp();
        if ($tsT >= $startTs && $tsT <= $endTs) {
            $ratioT = ($tsT - $startTs) / $spanSec;
            $transitX = $paddingLeft + $innerW * $ratioT;
        }
    }

    $shown++;
?>

<tr>

    <!-- LEVÁ BUŇKA: POPIS KOMETY -->
    <td style="width:40%;">
        <h2><?= htmlspecialchars($c['designation']) ?></h2>
        <table class="inner-table">
            <tr><td class="label">Jasnost</td><td class="value"><?= htmlspecialchars($mag) ?></td></tr>
            <tr><td class="label">RA</td><td class="value"><?= htmlspecialchars($ra) ?></td></tr>
            <tr><td class="label">Dec</td><td class="value"><?= htmlspecialchars($dec) ?></td></tr>
            <tr><td class="label">Výška</td><td class="value"><?= htmlspecialchars($alt) ?></td></tr>
            <tr><td class="label">Azimut</td><td class="value"><?= htmlspecialchars($az) ?></td></tr>
            <tr><td class="label">r</td><td class="value"><?= htmlspecialchars($r) ?> AU</td></tr>
            <tr><td class="label">Δ</td><td class="value"><?= htmlspecialchars($delta) ?> AU</td></tr>
            <tr><td class="label">Elongace</td><td class="value"><?= htmlspecialchars($elong) ?>°</td></tr>
            <tr><td class="label">Východ</td><td class="value"><?= htmlspecialchars($rise) ?></td></tr>
            <tr><td class="label">Kulminace</td><td class="value"><?= htmlspecialchars($transit) ?></td></tr>
            <tr><td class="label">Západ</td><td class="value"><?= htmlspecialchars($set) ?></td></tr>
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

        <?php
        $hasTimeLabels = false;
        for ($j = 0; $j <= $xSteps; $j++):
            $minutesFromStart = $j * $xStepMinutes;
            $tsLabel = $startTs + $minutesFromStart * 60;

            $ratioGX = ($tsLabel - $startTs) / $spanSec;
            if ($ratioGX < 0) $ratioGX = 0;
            if ($ratioGX > 1) $ratioGX = 1;

            $vx = $paddingLeft + $innerW * $ratioGX;

            $labelDT = new DateTime('@' . $tsLabel);
            $labelDT->setTimezone(new DateTimeZone('Europe/Prague'));
            $hour = (int)$labelDT->format('H');
            $minute = (int)$labelDT->format('i');
            $isLabel = ($minute === 0) && ($hour % 2 === 0);
            if ($isLabel) $hasTimeLabels = true;
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

            <?php if ($hasTimeLabels): ?>
            <text x="<?= ($paddingLeft + $width - $paddingRight) / 2 ?>"
                  y="<?= $height - 4 ?>"
                  class="text-small" text-anchor="middle">
                Čas (SEČ)
            </text>
            <?php endif; ?>

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
           