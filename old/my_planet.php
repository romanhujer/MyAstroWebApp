<?php
date_default_timezone_set('Europe/Prague');

$json_dir = __DIR__; 

$map = [
    "jup" => ["jupiter", "Jupiter"],
    "mar" => ["mars", "Mars"],
    "sat" => ["saturn", "Saturn"],
    "ven" => ["venus", "Venuše"],
];

$planeta = "jupiter";
$name = "Jupiter";

if (isset($_GET['id']) && isset($map[$_GET['id']])) {
    [$planeta, $name] = $map[$_GET['id']];
}

function load_today_planet($path) {
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

$planet = load_today_planet($json_dir . "/" . $planeta . '_ephemeris.json');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title><?= $name ?> – dnešní viditelnost</title>
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
<?php if (!$planet): ?>
    <p>Data pro planet dnes nejsou k dispozici.</p>
<?php else: ?>
    <h1><?= $name ?> – dnešní viditelnost</h1>
    <?php
        $rise    = $planet['rise_utc']    ? date('H:i', strtotime($planet['rise_utc']))    : '—';
        $set     = $planet['set_utc']     ? date('H:i', strtotime($planet['set_utc']))     : '—';
        $graph   = $planet['altitude_graph'] ?? [];
        $transit = $planet['transit_utc'] ? date('H:i', strtotime($planet['transit_utc'])) : '—';

        $maxAlt  = 0.0;
        foreach ($graph as $p) {
            if ($p['alt'] > $maxAlt) $maxAlt = $p['alt'];
        }
        if ($maxAlt < 10) $maxAlt = 10;
    ?>
    <table>
        <tr><td class="label">Datum</td><td class="value"><?= htmlspecialchars($planet['date']); ?></td></tr>
        <tr><td class="label">Východ</td><td class="value"><?= $rise; ?></td></tr>
        <tr><td class="label">Kulminace</td><td class="value"><?= $transit; ?></td></tr>
        <tr><td class="label">Západ</td><td class="value"><?= $set; ?></td></tr>
    </table>

    <?php
        // SVG body
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


if ( date('H', strtotime($planet['rise_utc'])) < 12 ) {

if ($n > 0) {
    foreach ($graph as $p) {
        // čas bodu v minutách od půlnoci (UTC)
        [$hStr, $mStr] = explode(':', $p['time']);
        $ptMinutes = (int)$hStr * 60 + (int)$mStr;

        // rozdíl vůči začátku grafu
        $diff = $ptMinutes - $startMinutes;
        if ($diff < 0) {
            $diff += 1440; // přetočení přes půlnoc
        }

        $ratio = $diff / 1440.0; // 24h okno
        $x = $paddingLeft + $innerW * $ratio;

        $y = $paddingTop + $innerH * (1 - ($p['alt'] / $maxAlt));
        $points[] = [$x, $y, $p['time'], $p['alt']];
    }
}
} else {
if ($n > 1) {
            foreach ($graph as $i => $p) {
                $x = $paddingLeft + $innerW * ($i / ($n - 1));
                $y = $paddingTop + $innerH * (1 - ($p['alt'] / $maxAlt));
                $points[] = [$x, $y, $p['time'], $p['alt']];
            }
        }
}
        // Dynamický začátek grafu podle času východu
        $riseRaw = $planet['rise_utc'] ?? null;
        $riseDt = new DateTime($riseRaw, new DateTimeZone('UTC'));
        $riseDt->setTimezone(new DateTimeZone('Europe/Prague'));

        $riseMinutes = $riseDt->format('H') * 60 + $riseDt->format('i');

        if ($riseMinutes < 12 * 60) {
            $startMinutes = 0;          // 00:00
        } else {
            $startMinutes = 12 * 60;    // 12:00
        }

        // Osa X – každých 30 minut
        $xStepMinutes = 30;
        $xSteps = (24 * 60) / $xStepMinutes;

        // Výpočet pozice kulminace
        $transitRaw = $planet['transit_utc'] ?? null;
        $dt = new DateTime($transitRaw, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('UTC'));

        $transitMinutes = $dt->format('H') * 60 + $dt->format('i');

        $diff = $transitMinutes - $startMinutes;
        if ($diff < 0) $diff += 1440;

        $ratio = $diff / 1440;
        $transitX = $paddingLeft + $innerW * $ratio;

        // Dynamický krok Y
        if ($maxAlt >= 40)      $yStep = 20;
        elseif ($maxAlt >= 20)  $yStep = 10;
        else                    $yStep = 5;

        $yLines = floor($maxAlt / $yStep);
    ?>

<svg viewBox="0 0 <?= $width ?> <?= $height ?>">

<?php for ($i = 0; $i <= $yLines; $i++):
    $altVal = $i * $yStep;
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

<?php for ($i = 0; $i <= $xSteps; $i++):
    $vx = $paddingLeft + $innerW * ($i / $xSteps);

    $totalMinutes = $startMinutes + $i * $xStepMinutes;
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
    <polygon points="<?= $polyPoints ?>" class="graph-fill" />
    <polyline points="<?= $linePoints ?>" class="graph-line" />
<?php endif; ?>

<?php if ($transitX !== null): ?>
    <line x1="<?= $transitX ?>" y1="<?= $paddingTop ?>"
          x2="<?= $transitX ?>" y2="<?= $height - $paddingBottom ?>"
          stroke="red" stroke-width="1.5" />
<?php endif; ?>

</svg>

<?php endif; ?>
</div>
</body>
</html>