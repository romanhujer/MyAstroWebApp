<?php

// ------------------------------------------------------------
// KONFIGURACE
// ------------------------------------------------------------
$katalogDecode = [
  "test" => "Test",  
  "messier" => "Messier",
  "caldwell" => "Caldwell",
  "herschel" => "Herschel",
  "sharpless" => "Sharpless",
  "galaxie" => "Galaxie",
  "arp" => "Arp",
  "snr" => "SNR",
  "vdb" => "vdB",
  "abell" => "Abell",
  "barnard" => "Barnard",
  "dark" => "Výber LBN/LDN",
  "select" => "Výběr NGC"
];

// ------------------------------------------------------------
// EXTRAKCE HASH Z URL NEBO TEXTU
// ------------------------------------------------------------
function extract_hash($input) {
    // čistý hash
    if (preg_match('/^[a-zA-Z0-9]{6}$/', $input)) {
        return $input;
    }

    // URL typu https://www.astrobin.com/12hhh5/
    if (preg_match('~/([a-zA-Z0-9]{6})(/|$)~', $input, $m)) {
        return $m[1];
    }

    // URL typu ...?i=12hhh5
    if (preg_match('/[?&]i=([a-zA-Z0-9]{6})/', $input, $m)) {
        return $m[1];
    }

    // fallback – najdi první hash
    if (preg_match('/([a-zA-Z0-9]{6})/', $input, $m)) {
        return $m[1];
    }

    return null;
}

// ------------------------------------------------------------
// FUNKCE – načtení preview JSON
// ------------------------------------------------------------
function load_preview_json($path) {
    if (file_exists($path . ".gz")) {
        return json_decode(gzdecode(file_get_contents($path . ".gz")), true);
    }
    if (file_exists($path)) {
        return json_decode(file_get_contents($path), true);
    }
    return null;
}

function save_preview_json_gz($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path . ".gz", gzencode($json, 9));
}

function rotate_backups($path) {
    for ($i = 9; $i >= 1; $i--) {
        if (file_exists($path . ".$i.gz")) {
            rename($path . ".$i.gz", $path . "." . ($i+1) . ".gz");
        }
    }
    if (file_exists($path . ".gz")) {
        rename($path . ".gz", $path . ".1.gz");
    }
}

// ------------------------------------------------------------
// FUNKCE – volání Python skriptu
// ------------------------------------------------------------
function find_astrobin_image($hashInput) {
    $hash = extract_hash($hashInput);
    if (!$hash) return null;

    $python = "/home/hujer/.pyenv/versions/sky310/bin/python";
    $script = "/opt/astro_json/find_hash.py";

    $cmd = "$python $script " . escapeshellarg($hash) . " "
         . "/opt/astro_json/astrobin_romanhujer.json.gz "
         . "/opt/astro_json/astrobin_toppic.json.gz "
         . "/opt/astro_json/astrobin.json.gz";

    $output = shell_exec($cmd);
    if (!$output) return null;

    $data = json_decode($output, true);
    return (is_array($data) && !empty($data)) ? $data : null;
}

// ------------------------------------------------------------
// NAČTENÍ GET/POST PROMĚNNÝCH
// ------------------------------------------------------------
$katalog = $_GET['katalog'] ?? $_POST['katalog'] ?? 'test';
$obj     = $_GET['obj']     ?? $_POST['obj']     ?? null;
$index   = $_GET['index']   ?? $_POST['index']   ?? null;
$hashIn  = $_GET['hash']    ?? $_POST['hash']    ?? null;

$cesta = '/opt/astro_json/' . $katalog . '_preview.json';
$preview = load_preview_json($cesta);

if (!$preview) {
    die("Nelze načíst preview JSON pro katalog: " . htmlspecialchars($katalog));
}

$action = $_GET['action']  ?? $_POST['action']  ?? null;

// ------------------------------------------------------------
// 1) FORMULÁŘ PRO ZADÁNÍ HASHE
// ------------------------------------------------------------
if ($action === "replace" && $obj !== null && $index !== null) {

    if (!isset($preview[$obj][$index])) {
        die("Původní snímek nenalezen.");
    }

    $old = $preview[$obj][$index];
    ?>
    <html><body style="background:#111;color:#eee;font-family:Arial">
    <h2>Výměna snímku pro objekt: <?= htmlspecialchars($obj) ?></h2>

    <h3>Původní snímek</h3><a href="<?= $old['url'] ?>">
    <img src="<?= htmlspecialchars($old['thumbnail']) ?>" width="300"></a><br>
    <?= htmlspecialchars($old['title']) ?><br>
    <?= htmlspecialchars($old['userDisplayName']) ?><br><br>

    <form method="get">
        <input type="hidden" name="katalog" value="<?= htmlspecialchars($katalog) ?>">
        <input type="hidden" name="obj" value="<?= htmlspecialchars($obj) ?>">
        <input type="hidden" name="index" value="<?= htmlspecialchars($index) ?>">
        <input type="hidden" name="action" value="search">
        <h3>Zadej hash nebo URL:</h3>
        <input type="text" name="hash" size="50" required>
        <button type="submit">Vyhledat</button>
    </form>
    </body></html>
    <?php
    exit;
}

// ------------------------------------------------------------
// 2) VYHLEDÁNÍ SNÍMKU
// ------------------------------------------------------------
if ($action === "search") {

    $hash = extract_hash($hashIn);
    $found = find_astrobin_image($hash);

    if (!$found) {
        die("<h2 style='color:#f44'>Hash " . htmlspecialchars($hashIn) . " nebyl nalezen.</h2>");
    }

    $old = $preview[$obj][$index];
    ?>
    <html><body style="background:#111;color:#eee;font-family:Arial">
    <h2>Porovnání snímků pro objekt: <?= htmlspecialchars($obj) ?></h2>

    <table><tr>
        <td>
            <h3>Původní</h3>
            <img src="<?= htmlspecialchars($old['thumbnail']) ?>" width="300"><br>
            <?= htmlspecialchars($old['title']) ?><br>
            <?= htmlspecialchars($old['userDisplayName']) ?><br>
        </td>
        <td>
            <h3>Nový</h3>
            <img src="<?= htmlspecialchars($found['thumbnail']) ?>" width="300"><br>
            <?= htmlspecialchars($found['title']) ?><br>
            <?= htmlspecialchars($found['userDisplayName']) ?><br>
        </td>
    </tr></table>

    <form method="get">
        <input type="hidden" name="mode" value="confirm">
        <input type="hidden" name="katalog" value="<?= htmlspecialchars($katalog) ?>">
        <input type="hidden" name="obj" value="<?= htmlspecialchars($obj) ?>">
        <input type="hidden" name="index" value="<?= htmlspecialchars($index) ?>">
        <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">
        <button type="submit">Provést</button>&nbsp;
        Změnu: <input type="radio" id="action" name="action" value="confirm" > &nbsp; 
        Zrušit: <input type="radio" id="action" name="action" value="cnacel" checked>
        
    </form>
    </body></html>
    <?php
    exit;
}

// ------------------------------------------------------------
// 3) POTVRZENÍ ZMĚNY
// ------------------------------------------------------------
if ($action === "confirm") {
//if (($_POST['confirm'] ?? '') === 'yes') {

    $hash = extract_hash($_GET['hash'] ?? $_POST['hash'] );
    $new = find_astrobin_image($hash);

    if (!$new) {
        die("Hash " . htmlspecialchars($hash) . " nebyl nalezen při potvrzení.");
    }

    rotate_backups($cesta);

    $preview[$obj][$index] = [
        "id" => $new['hash'],
        "url" => "https://www.astrobin.com/" . $new['hash'] . "/?force-classic-view",
        "thumbnail" => $new['thumbnail'],
        "title" => $new['title'],
        "author" => $new['username'],
        "userDisplayName" => $new['userDisplayName'],
        "objects" => $preview[$obj][$index]['objects'] ?? [$obj],
        "isIotd" => $new['isIotd'],
        "isTopPick" => $new['isTopPick'],
        "isTopPickNomination" => $new['isTopPickNomination'],
        "integration" => $new['integration'],
        "foceno" => ($new['username'] === "romanhujer" ) ? "Yes" : "No"
    ];

    save_preview_json_gz($cesta, $preview);

    echo "<h2 style='color:#6f6;background:#111;font-family:Arial'>✔ Snímek byl úspěšně vyměněn.</h2>";
    echo "<a href='?katalog=" . htmlspecialchars($katalog) . "' style='color:#6cf'>Zpět do katalogu</a>";
    exit;
}

if ($action === "confirm") {
// if (($_POST['confirm'] ?? '') === 'no') {
    header("Location: ?katalog=" . urlencode($katalog));
    exit;
}

// ------------------------------------------------------------
// 4) VÝPIS KATALOGU
// ------------------------------------------------------------
?>
<html>
<body style="background:#111;color:#eee;font-family:Arial">

<h1>DSO Preview Editor – <?= htmlspecialchars($katalogDecode[$katalog] ?? $katalog) ?></h1>

<form method="get">
    <input type="hidden" name="mode" value="katakog">
    <select name="katalog" onchange="this.form.submit()">
        <?php foreach ($katalogDecode as $key => $name): ?>
            <option value="<?= $key ?>" <?= ($key === $katalog) ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table><tr>
<?php
$col = 0;
foreach ($preview as $obj => $items):
    if ($col > 4) { echo "</tr><tr>"; $col = 0; }
    echo "<td width='250px' valign='top'>";
    $col++;

    echo "<strong>$obj</strong><br>";

    foreach ($items as $i => $img):
?>
        <div style="margin:10px;text-align:center">
            <a href="<?= $img['url'] ?>" >
            <img src="<?= htmlspecialchars($img['thumbnail']) ?>" width="200"></a><br>
            <?= htmlspecialchars($img['title']) ?><br>
            <?= htmlspecialchars($img['userDisplayName']) ?><br>
            <a href="?katalog=<?= urlencode($katalog) ?>&obj=<?= urlencode($obj) ?>&index=<?= urlencode($i) ?>&action=replace">
            <button type="button">Vyměnit snímek</button>
            </a>
        </div>
<?php
    endforeach;
    echo "</td>";
endforeach;
?>
</tr></table>

</body>
</html>
