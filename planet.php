<?php
date_default_timezone_set('Europe/Prague');

$json_dir = "/opt/astro_json";

$map = [
    "jup" => ["jupiter", "Jupiter"],
    "mar" => ["mars", "Mars"],
    "sat" => ["saturn", "Saturn"],
    "ven" => ["venus", "Venuše"],
    "mer" => ["mercury", "Merkur"],
    "urn" => ["uranus", "Uran"],
    "nep" => ["neptune", "Neptun"],
];

$constellationCZ = [
    "And" => "Andromeda",
    "Ant" => "Čeropas",
    "Aps" => "Rajka",
    "Aql" => "Orel",
    "Aqr" => "Vodnář",
    "Ara" => "Oltář",
    "Ari" => "Beran",
    "Aur" => "Vozka",
    "Boo" => "Pastýř",
    "CMa" => "Velký pes",
    "CMi" => "Malý pes",
    "CVn" => "Honicí psi",
    "Cae" => "Rydlo",
    "Cam" => "Žirafa",
    "Cap" => "Kozoroh",
    "Car" => "Kýl",
    "Cas" => "Kasiopeja",
    "Cen" => "Kentaur",
    "Cep" => "Cefeus",
    "Cet" => "Velryba",
    "Cha" => "Kameleón",
    "Cir" => "Kružítko",
    "Col" => "Holubice",
    "Com" => "Vlasy Bereniky",
    "CrA" => "Jižní koruna",
    "CrB" => "Severní koruna",
    "Crt" => "Pohár",
    "Cru" => "Jižní kříž",
    "Crv" => "Havran",
    "Cyg" => "Labuť",
    "Del" => "Delfín",
    "Dor" => "Mečoun",
    "Dra" => "Drak",
    "Equ" => "Hříbě",
    "Eri" => "Eridanus",
    "For" => "Pec",
    "Gem" => "Blíženci",
    "Gru" => "Jeřáb",
    "Her" => "Herkules",
    "Hor" => "Hodiny",
    "Hya" => "Hydra",
    "Hyi" => "Jižní Hydra",
    "Ind" => "Indián",
    "Lac" => "Ještěrka",
    "Leo" => "Lev",
    "Lep" => "Zajíc",
    "Lib" => "Váhy",
    "LMi" => "Malý lev",
    "Lup" => "Vlk",
    "Lyn" => "Rys",
    "Lyr" => "Lyra",
    "Men" => "Tabulová hora",
    "Mic" => "Mikroskop",
    "Mon" => "Jednorožec",
    "Mus" => "Moucha",
    "Nor" => "Kružítko",
    "Oct" => "Oktant",
    "Oph" => "Hadonoš",
    "Ori" => "Orion",
    "Pav" => "Páv",
    "Peg" => "Pegas",
    "Per" => "Perseus",
    "Phe" => "Fénix",
    "Pic" => "Malíř",
    "PsA" => "Jižní ryba",
    "Psc" => "Ryby",
    "Pup" => "Zád",
    "Pyx" => "Kompas",
    "Ret" => "Síť",
    "Scl" => "Sochař",
    "Sco" => "Štír",
    "Sct" => "Štít",
    "Ser" => "Had",
    "Sex" => "Sextant",
    "Sge" => "Šíp",
    "Sgr" => "Střelec",
    "Tau" => "Býk",
    "Tel" => "Teleskop",
    "TrA" => "Jižní trojúhelník",
    "Tri" => "Trojúhelník",
    "Tuc" => "Tukan",
    "UMa" => "Velká medvědice",
    "UMi" => "Malý medvěd",
    "Vel" => "Plachty",
    "Vir" => "Panna",
    "Vol" => "Létající ryba",
    "Vul" => "Lištička",
];

$constellationIcon = [
    "Beran" => "♈︎",
    "Býk" => "♉︎",
    "Blíženci" => "♊︎",
    "Rak" => "♋︎",
    "Lev" => "♌︎",
    "Panna" => "♍︎",
    "Váhy" => "♎︎",
    "Štír" => "♏︎",
    "Střelec" => "♐︎",
    "Kozoroh" => "♑︎",
    "Vodnář" => "♒︎",
    "Ryby" => "♓︎",
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
<?php
        // základní údaje
        $rise    = $planet['rise_utc']    ? date('H:i', strtotime($planet['rise_utc']))    : '—';
        $set     = $planet['set_utc']     ? date('H:i', strtotime($planet['set_utc']))     : '—';
        $transit = $planet['transit_utc'] ? date('H:i', strtotime($planet['transit_utc'])) : '—';
        // -----------------------------
        // Určení typu viditelnosti
        // -----------------------------
        $visibleWord = ($_GET['id'] ?? '') === 'ven' ? 'viditelná' : 'viditelný';
        
        $visibility = "na obloze";

        if (!empty($planet['transit_utc'])) {
            $t = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));
            $transitH = (int)$t->format('H');

            if ($transitH >= 7 && $transitH < 15) {
                $visibility = "na denní obloze";
            } elseif ($transitH >= 15 && $transitH < 21) {
                $visibility = "na večerní obloze";
            } elseif ($transitH >= 21 && $transitH < 24) {
                $visibility = "na noční obloze";
            } elseif ($transitH >= 0 && $transitH < 2) {
                $visibility = "na noční obloze";
            } elseif ($transitH >= 2 && $transitH < 7) {
                $visibility = "na ranní obloze";
            }
        }

        // -----------------------------
        // Načtení doplňkových údajů z prvního bodu grafu
        // (všechny body mají stejné hodnoty distance/constellation)
        // -----------------------------
        $first = $planet['altitude_graph'][0] ?? null;
        $distAU  = $first ? $first['distance_au'] : null;
        $distKM  = $first ? $first['distance_km'] : null;
        $angSize = $first ? $first['angular_size_arcsec'] : null;
        $elong = $first ? $first['elongation_deg'] : null;
        $opposition = $planet['nearest_opposition'] ?? null;
        $constCode = $first ? $first['constellation'] : null;  
        $perihelion = $planet['nearest_perihelion'] ?? null;
        $const = $constCode && isset($constellationCZ[$constCode]) ? $constellationCZ[$constCode] : $constCode;
        $icon = isset($constellationIcon[$const]) ? $constellationIcon[$const] : "";
        
        // původní 48h graf z JSONu
        $graph   = $planet['altitude_graph'] ?? [];

        // ---------------------------------------------
        // 1) Dynamický začátek grafu podle kulminace
        // ---------------------------------------------
        $startMinutes = 0;

        if (!empty($planet['transit_utc'])) {
            $t = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));

            // kulminace v minutách
            $transitMinutes = $t->format('H') * 60 + $t->format('i');

            // zaokrouhlení na celé hodiny
            $roundedTransit = round($transitMinutes / 60) * 60;

            // začátek grafu = kulminace - 12 hodin
            $startMinutes = $roundedTransit - 12 * 60;

            // přetočení do rozsahu 0–1440
            while ($startMinutes < 0) $startMinutes += 1440;
            while ($startMinutes >= 1440) $startMinutes -= 1440;
        } 

        // -----------------------------
        // 2) Vybrat správné 24h okno z 48h dat
        // -----------------------------
        $filtered = [];
        foreach ($graph as $p) {
            // day_offset z JSONu (0 = dnešek, 1 = zítřek); fallback 0 pro jistotu
            $dayOffset = isset($p['day_offset']) ? (int)$p['day_offset'] : 0;

            [$hStr, $mStr] = explode(':', $p['time']);
            $ptMinutes = (int)$hStr * 60 + (int)$mStr + $dayOffset * 1440; // 48h osa

            // rozdíl vůči začátku grafu
            $diff = $ptMinutes - $startMinutes;
            if ($diff < 0) {
                $diff += 2880; // přetočení v rámci 48h okna
            }

            // bereme jen 24h interval
            if ($diff < 1440) {
                $filtered[] = $p;
            }
        }
        $graph = $filtered;
        $n = count($graph);

        // -----------------------------
        // 3) Zjištění max. výšky po filtrování
        // -----------------------------
        $maxAlt  = 0.0;
        foreach ($graph as $p) {
            if ($p['alt'] > $maxAlt) $maxAlt = $p['alt'];
        }
        if ($maxAlt < 10) $maxAlt = 10;
    ?>

    <?php if (!$planet): ?>
            <p>Data pro planet dnes nejsou k dispozici.</p>
    <?php else: ?>
    
    <h1><?= $name ?> <?= htmlspecialchars($planet['date']); ?> <?= $visibleWord ?> <?= $visibility ?></h1>

    <table>
        <tr><td class="label">Východ</td><td class="value"><?= $rise; ?></td></tr>
        <tr><td class="label">Kulminace</td><td class="value"><?= $transit; ?></td></tr>
        <tr><td class="label">Západ</td><td class="value"><?= $set; ?></td></tr>
        <tr><td class="label">Souhvězdí</td><td class="value"> <?= $const ?> </td></tr>
        <tr><td class="label">Vzdálenost</td><td class="value"><?= $distAU ?> AU (<?= number_format($distKM, 0, ',', ' ') ?> km)</td></tr>
        <tr><td class="label">Úhlová velikost</td><td class="value"><?= $angSize ?>"</td></tr>
        <tr><td class="label">Elongace</td><td class="value"><?= $elong ?>°</td></tr> 
        <?php if (!empty($opposition)): ?>
            <tr> <td class="label">Nejbližší opozice</td><td class="value"><?= $opposition ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($perihelion)): ?>
            <tr> <td class="label">Nejbližší perihelium</td><td class="value"><?= $perihelion ?></td></tr>
        <?php endif; ?>
    </table>

    <?php
        // SVG parametry
        $width  = 600;
        $height = 200;
        $paddingLeft  = 30;
        $paddingRight = 10;
        $paddingTop   = 10;
        $paddingBottom= 20;

        $innerW = $width  - $paddingLeft - $paddingRight;
        $innerH = $height - $paddingTop  - $paddingBottom;

        // -----------------------------
        // 4) Výpočet X/Y souřadnic bodů
        // -----------------------------
        $points = [];
        if ($n > 0) {
            foreach ($graph as $p) {
                [$hStr, $mStr] = explode(':', $p['time']);
                $ptMinutes = (int)$hStr * 60 + (int)$mStr;

                // rozdíl vůči začátku grafu (24h okno)
                $diff = $ptMinutes - $startMinutes;
                if ($diff < 0) {
                    $diff += 1440;
                }

                $ratio = $diff / 1440.0;
                $x = $paddingLeft + $innerW * $ratio;

                $y = $paddingTop + $innerH * (1 - ($p['alt'] / $maxAlt));

                $points[] = [$x, $y, $p['time'], $p['alt']];
            }
        }

        // -----------------------------
        // 5) Transit X
        // -----------------------------
        $transitX = null;
        if (!empty($planet['transit_utc'])) {
            $dt = new DateTime($planet['transit_utc'], new DateTimeZone('UTC'));
            $transitMinutes = $dt->format('H') * 60 + $dt->format('i');

            $diff = $transitMinutes - $startMinutes;
            if ($diff < 0) $diff += 1440;

            $ratio = $diff / 1440;
            $transitX = $paddingLeft + $innerW * $ratio;
        }

        // -----------------------------
        // 6) Osa Y
        // -----------------------------
        if ($maxAlt >= 40)      $yStep = 20;
        elseif ($maxAlt >= 20)  $yStep = 10;
        else                    $yStep = 5;

        $yLines = floor($maxAlt / $yStep);

        // -----------------------------
        // 7) Osa X – popisky (převod do CET)
        // -----------------------------
        $xStepMinutes = 30;
        $xSteps = (24 * 60) / $xStepMinutes;
    ?>

<svg viewBox="0 0 <?= $width ?> <?= $height ?>">

<?php for ($i = 1; $i <= $yLines; $i++): // nezačínáme od 0°, aby nebyla falešná čára ?>
    <?php
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
    // polygon jen když má smysl (aby se minimalizovaly artefakty u nízkých výšek)
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

<?php endif; ?>
</div>
</body>
</html>