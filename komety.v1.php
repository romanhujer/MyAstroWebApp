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

$limit = 10;

// zaokrouhlení času na 30 minut
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
.box { max-width: 900px; margin: 20px auto; padding: 16px; background:#222; border:1px solid #444; }
h1 { font-size: 22px; margin: 0 0 15px; }
table { width:100%; border-collapse: collapse; margin-bottom: 16px; }
td, th { padding: 6px 4px; }
th { text-align:left; color:#ccc; border-bottom:1px solid #444; }
.value { font-weight: 600; }
a { color:#6cf; }
</style>
</head>
<body>

<div class="box">
<h1>Komety – aktuální viditelnost</h1>
<p>Čas: <?php echo $rounded; ?> UTC</p>

<table>
<tr>
    <th>Kometa</th>
    <th>Mag</th>
    <th>Alt</th>
    <th>Az</th>
    <th>RA</th>
    <th>Dec</th>
    <th>Východ</th>
    <th>Kulminace</th>
    <th>Západ</th>
    <th>Detail</th>
    
</tr>

<?php
$i = 0;
foreach ($comets as $c):
    if ($i >= $limit) break;
    $i++;

    // najdeme nejbližší časový bod
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

    $ra  = sprintf("%.2f h", $current['ra_hours_j2000']);
    $dec = sprintf("%.2f°", $current['dec_deg_j2000']);
    $alt = sprintf("%.1f°", $current['alt_deg']);
    $az  = sprintf("%.1f°", $current['az_deg']);
    $rise = $c['rise_utc'] ? date('H:i', strtotime($c['rise_utc'])) : '—';
    $transit = $c['transit_utc'] ? date('H:i', strtotime($c['transit_utc'])) : '—';
    $set = $c['set_utc'] ? date('H:i', strtotime($c['set_utc'])) : '—';


?>
<tr>
    <td><?php echo htmlspecialchars($c['designation']); ?></td>
    <td><?php echo $c['mag_est']; ?></td>
    <td><?php echo $alt; ?></td>
    <td><?php echo $az; ?></td>
    <td><?php echo $ra; ?></td>
    <td><?php echo $dec; ?></td>
    <td><?php echo $rise; ?></td>
    <td><?php echo $transit; ?></td>
    <td><?php echo $set; ?></td>
    <td><a href="#<?php echo $c['designation']; ?>">Detail</a></td>
</tr>
<?php endforeach; ?>
</table>

</div>

</body>
</html>
