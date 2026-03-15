<?php
ini_set('memory_limit', '512M');
/*  preview.php
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
$edit_key_file = "/home/hujer/.ekey";
$EDIKEY = "";
//
// Získání klíče pro editace    
//
$line = @file($edit_key_file);
if ($line && isset($line[0])) {
    $parts = explode(":", trim($line[0]));
    if (count($parts) >= 2) {
        $EDIKEY = $parts[1];   // prostřední položka
    }
}
// ------------------------------------------------------------
// KONFIGURACE
// ------------------------------------------------------------
$katalogDecode = [
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
function extract_hash($input)
{
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

function hms($sec)
{

    $h = floor($sec / 3600);
    $m_float = ($sec / 3600 - $h) * 60;
    $m = floor($m_float);
    $s = floor(($m_float - $m) * 60);
    return sprintf("%4dh %02dm %02s", $h, $m, $s);
}

// ------------------------------------------------------------
// FUNKCE – načtení preview JSON
// ------------------------------------------------------------
function load_preview_json($path)
{
    if (file_exists($path . ".gz")) {
        return json_decode(gzdecode(file_get_contents($path . ".gz")), true);
    }
    if (file_exists($path)) {
        return json_decode(file_get_contents($path), true);
    }
    return null;
}

function save_preview_json_gz($path, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path . ".gz", gzencode($json, 9));
}

function rotate_backups($path)
{
    for ($i = 9; $i >= 1; $i--) {
        if (file_exists($path . ".$i.gz")) {
            rename($path . ".$i.gz", $path . "." . ($i + 1) . ".gz");
        }
    }
    if (file_exists($path . ".gz")) {
        rename($path . ".gz", $path . ".1.gz");
    }
}

// ------------------------------------------------------------
// FUNKCE – volání Python skriptu
// ------------------------------------------------------------
function find_astrobin_image($hashInput)
{
    $hash = extract_hash($hashInput);
    if (!$hash)
        return null;

    $python = "/home/hujer/.pyenv/versions/sky310/bin/python";
    $script = "/opt/astro_json/find_hash.py";

    $cmd = "$python $script " . escapeshellarg($hash) . " "
        . "/opt/astro_json/astrobin_romanhujer.json.gz "
        . "/opt/astro_json/astrobin_toppic.json.gz "
        . "/opt/astro_json/astrobin.json.gz";

    $output = shell_exec($cmd);
    if (!$output)
        return null;

    $data = json_decode($output, true);
    return (is_array($data) && !empty($data)) ? $data : null;
}

function find_name($id, $csv)
{
    if (($handle = fopen($csv, 'r')) === false) {
        echo $csv;
        die("Nelze otevřít CSV soubor.\n");
    }

    // přeskočíme hlavičku
    $header = fgetcsv($handle, 0, ',');

    $result = null;

    while (($row = fgetcsv($handle, 0, ',')) !== false) {

        // $row je číselné pole: 0=id, 1=name, 2=type, 3=ra_hours, ...
        if ($row[0] === $id) {
            $result = [
                'name' => $row[1],
                'description' => $row[8]
            ];
            break;
        }
    }

    fclose($handle);

    return $result; // vrací pole nebo null
}

// ------------------------------------------------------------
// NAČTENÍ GET/POST PROMĚNNÝCH
// ------------------------------------------------------------
$ekey = $_GET['ekey'] ?? $_POST['ekey'] ?? "no";
$katalog = $_GET['katalog'] ?? $_POST['katalog'] ?? 'messier';
$obj = $_GET['obj'] ?? $_POST['obj'] ?? null;
$index = $_GET['index'] ?? $_POST['index'] ?? null;
$hashIn = $_GET['hash'] ?? $_POST['hash'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$myfoto = $_GET['myfoto'] ?? $_POST['myfoto'] ?? 'all';
$edit = $_GET['e'] ?? $_POST['e'] ?? 'n';

$edit_mode = ($ekey === $EDIKEY);

if (($edit === 'y') && ($ekey !== $EDIKEY)) {
    $action = "enterkey";
} elseif ($edit !== 'y' ) {
    $edit_mode = false;
}

$json_file = '/opt/astro_json/' . $katalog . '_preview.json';
$csv_file = '/opt/astro_json/' . $katalog . '.csv';
$preview = load_preview_json($json_file);


?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="utf-8">
    <title>DSO visibility</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #111;
            color: #eee;
        }

        .box {
            max-width: 1100px;
            margin: 20px auto;
            padding: 16px;
            background: #222;
            border: 1px solid #444;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 15px;
        }

        h2 {
            font-size: 18px;
            margin: 0 0 6px;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        .main-table tr+tr {
            border-top: 1px solid #444;
        }

        .main-table td {
            padding: 10px 6px;
            vertical-align: top;
        }

        .inner-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inner-table td {
            padding: 2px 0;
        }

        .thumb {
            margin: 10px;
            display: inline-block;
            text-align: center;
        }

        .thumb img {
            width: 200px;
            border-radius: 6px;
        }

        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }

        .iotd {
            background: #ff4444;
        }

        .tp {
            background: #44aa44;
        }

        .tpn {
            background: #ffaa00;
        }



        .label {
            color: #aaa;
            width: 40%;
        }

        .value {
            font-weight: 600;
        }

        .desc {
            color: #aaa;
            font-size: 18px;

        }

        svg {
            width: 100%;
            height: 220px;
            background: #111;
            border: 1px solid #444
        }

        .axis {
            stroke: #555;
            stroke-width: 1;
        }

        .graph-line {
            fill: none;
            stroke: #4caf50;
            stroke-width: 2;
        }

        .graph-fill {
            fill: rgba(76, 175, 80, 0.25);
            stroke: none;
        }

        .graph-night {
            fill: rgba(1, 3, 36, 0.25);
            stroke: none;
        }

        .graph-day {
            fill: rgba(86, 86, 90, 0.25);
            stroke: none;
        }

        .graph-tw {
            fill: rgba(50, 50, 69, 0.25);
            stroke: none;
        }

        .text-small {
            font-size: 11px;
            fill: #aaa;
        }

        .body a {
            color: blue;
            text-decoration: none;
        }

        .body a:hover {
            color: white;
            text-decoration: none;
        }

        h2 {
            text-align: center;
        }

        h2 a {
            color: grey;
            text-decoration: none;
        }

        h2 a:hover {
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<script>
    let autoSubmitTimer = null;
    function autoSubmitDebounced() {
        clearTimeout(autoSubmitTimer);
        autoSubmitTimer = setTimeout(() => {
            document.getElementById("filterForm").submit();
        }, 300); // 300 ms pauza po poslední změně
    }
</script>
<?php
if (!$preview) {
    die("Nelze načíst preview JSON pro katalog: " . htmlspecialchars($katalog));
}


// ------------------------------------------------------------
// 1) FORMULÁŘ PRO ZADÁNÍ HASHE
// ------------------------------------------------------------
if ($action === "replace" && $obj !== null && $index !== null) {

    $old_not_found = false;
    if (!isset($preview[$obj][$index])) {
        $old_not_found = true;
    }

    $old = $preview[$obj][$index];
    ?>

    <body style="background:#111;color:#eee;font-family:Arial">
        <h2>Výměna snímku pro objekt: <?= htmlspecialchars($obj) ?></h2>
        <h3>Původní snímek</h3>
        <?php if ($old_not_found): ?>
            <br><br><br>
            <h3><a href="https://www.astrobin.com" target="_\">Nenalezen!</a></h3>
            <br><br><br>
        <?php else: ?>
            <a href="<?= $old['url'] ?>" target="_blank">
                <img src="<?= htmlspecialchars($old['thumbnail']) ?>" width="400"></a><br>
            <?= htmlspecialchars($old['title']) ?><br>
            Integrace: <?= hms((float) $old['integration']) ?><br>
            Author: <?= htmlspecialchars($old['userDisplayName']) ?><br>


        <?php endif; ?>
        <form method="get">
            <input type="hidden" name="katalog" value="<?= htmlspecialchars($katalog) ?>">
            <input type="hidden" name="obj" value="<?= htmlspecialchars($obj) ?>">
            <input type="hidden" name="index" value="<?= htmlspecialchars($index) ?>">
            <input type="hidden" name="action" value="search">
            <input type="hidden" name="ekey" value="<?= $ekey ?>">
            <h3>Zadej hash nebo URL:</h3>
            <input type="text" name="hash" size="50" required>
            <button type="submit">Vyhledat</button>
        </form>
    </body>

    </html>
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

    $old_not_found = false;
    if (!isset($preview[$obj][$index])) {
        $old_not_found = true;
    }

    $old = $preview[$obj][$index];


    ?>

    <body style="background:#111;color:#eee;font-family:Arial">
        <h2>Porovnání snímků pro objekt: <?= htmlspecialchars($obj) ?></h2>
        <table>
            <tr>
                <td width="300">
                    <h3>Původní</h3>
                    <?php if ($old_not_found): ?>
                        <br><br><br>
                        <dev class="text-align:center;">
                            <h3>Nenalezen!</h3>
                        </dev>
                        <br><br><br>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($old['thumbnail']) ?>" width="400"><br>
                        <?= htmlspecialchars($old['title']) ?><br>
                        Integrace: <?= hms((float) $old['integration']) ?><br>
                        Author: <?= htmlspecialchars($old['userDisplayName']) ?><br>

                    <?php endif; ?>
                </td>
                <td>
                    <h3>Nový</h3>
                    <img src="<?= htmlspecialchars($found['thumbnail']) ?>" width="400"><br>
                    <?= htmlspecialchars($found['title']) ?><br>
                    Integrace: <?= hms((float) $found['integration']) ?><br>
                    Author: <?= htmlspecialchars($found['userDisplayName']) ?><br>
                </td>
            </tr>
        </table>

        <form method="get">
            <input type="hidden" name="mode" value="confirm">
            <input type="hidden" name="katalog" value="<?= htmlspecialchars($katalog) ?>">
            <input type="hidden" name="obj" value="<?= htmlspecialchars($obj) ?>">
            <input type="hidden" name="index" value="<?= htmlspecialchars($index) ?>">
            <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">
            <input type="hidden" name="ekey" value="<?= $ekey ?>">
            <button type="submit">Provést</button>&nbsp;
            Změnu: <input type="radio" id="action" name="action" value="confirm"> &nbsp;
            Zrušit: <input type="radio" id="action" name="action" value="cnacel" checked>

        </form>
    </body>

    </html>
    <?php
    exit;
}

// ------------------------------------------------------------
// 3) POTVRZENÍ ZMĚNY
// ------------------------------------------------------------
if ($action === "confirm") {

    $hash = extract_hash($_GET['hash'] ?? $_POST['hash']);
    $new = find_astrobin_image($hash);

    if (!$new) {
        die("Hash " . htmlspecialchars($hash) . " nebyl nalezen při potvrzení.");
    }

    rotate_backups($json_file);

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
        "foceno" => ($new['username'] === "romanhujer") ? "Yes" : "No"
    ];

    save_preview_json_gz($json_file, $preview);

    ?>
    <h2 style="color:#6f6;background:#111;font-family:Arial">✔ Snímek byl úspěšně vyměněn.</h2>
    <br><a href="?e=y&ekey=<?= $ekey ?>&katalog=<?= $katalog ?>"><button type="button">Zpět do katalogu</button></a>
    <?php
    exit;
}

if ($action === "confirm") {
    // if (($_POST['confirm'] ?? '') === 'no') {
    header("Location: ?katalog=" . urlencode($katalog));
    exit;
}

if ($action === "enterkey") {
    ?>

    <body style="background:#111;color:#eee;font-family:Arial">
        <form method="get">

            <label> Zadej ekey :
               <input type="hidden" name="e" value="y" />
               <input type="hidden" name="katalog" value="<?= $katalog ?>" />
               <input type="text" name="ekey" id="ekey" onkeydown="if(event.key === 'Enter') autoSubmitDebounced()" />
            </label>
        </form>
    </body>

    </html>
    <?php
    exit;
}
// ------------------------------------------------------------
// 4) VÝPIS KATALOGU
// ------------------------------------------------------------
?>

<body style="background:#111;color:#eee;font-family:Arial">

    <h1>DSO Preview <?php echo $edit_mode ? "(edit mode)" : "" ?> –
        <?= htmlspecialchars($katalogDecode[$katalog] ?? $katalog) ?>
    </h1>
    
    <form method="get" id="filterForm">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="ekey" value="<?= $ekey ?>">
        <?php endif; ?>
        <select name="katalog" onchange="this.form.submit()">
            <?php foreach ($katalogDecode as $key => $name): ?>
                <option value="<?= $key ?>" <?= ($key === $katalog) ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
        </select>
        &nbsp; &nbsp;
        <label>Foceno:
            ANO <input type="radio" name="myfoto" value="yes" <?php if ($myfoto === 'yes'): ?> checked <?php endif; ?>
                onchange="autoSubmitDebounced()" />
            NE <input type="radio" name="myfoto" value="no" <?php if ($myfoto === 'no'): ?> checked <?php endif; ?>
                onchange="autoSubmitDebounced()" />
            Vše <input type="radio" name="myfoto" value="all" <?php if ($myfoto === 'all'): ?> checked <?php endif; ?>
                onchange="autoSubmitDebounced()" />
        </label>
        <label>Edit mode:
            <input type="hidden" name="e" value="n" />
            <input type="checkbox" id="e" name="e" value="y" <?php if ($edit === 'y'): ?> checked <?php endif; ?>
                onchange="autoSubmitDebounced()" />
        </label>&nbsp;&nbsp;
    </form>
    <table>
        <tr>
            <?php
            $col = 0;
            foreach ($preview as $obj => $items):
                $foceno = "No";
                foreach ($items as $i => $img) {
                    $foceno = $img['foceno'];
                }

                if ($myfoto !== "all") {

                    if ($foceno === "Yes" && $myfoto === "no")
                        continue;
                    if ($foceno !== "Yes" && $myfoto === "yes")
                        continue;

                }


                if ($col > 3) {
                    echo "</tr><tr>";
                    $col = 0;
                }

                echo "<td width='270px' style='padding:10px;'>";
                echo "<div style='display:flex; flex-direction:column; height:100%;'>";


                $col++;
                ?>
                <h2><strong><?= $obj ?></strong> - <a href="/app/dso.php?katalog=<?= $katalog ?>&id=<?= $obj ?>&f=no">Graf
                        viditelnosti</a>
                </h2>
                <div style="margin:10px; text-align:center; min-height:220px;">
                    <?= mb_substr(htmlspecialchars(find_name($obj, $csv_file)['name']), 0, 28, "UTF-8") ?><br>

                    <?php $img_not_foud = true;
                    foreach ($items as $i => $img): ?>
                        <a href="<?= strtok($img['url'],'?') ?>" target="_blank">
                            <img src="<?= htmlspecialchars($img['thumbnail']) ?>" width="270" height="180"></a><br>
                        <?= htmlspecialchars(mb_substr($img['title'], 0, 28, "UTF-8")) ?><br>
                        Integrace: <?= hms((float) $img['integration']) ?><br>
                        Author: <?= htmlspecialchars($img['userDisplayName']) ?><br>
                        Foceno : <?= $foceno ?><br>
                        <?php $img_not_foud = false; endforeach; ?>

                    <?php if ($img_not_foud): ?>

                        <br>
                        <h3>Nenalezeno!</h3>
                        <br>

                    <?php endif; ?>
                </div>
                <br>
                <?php if ($edit_mode): ?>
                    <div style="text-align:center; margin-top:auto;">
                        <a
                            href="?ekey=<?= $ekey ?>&katalog=<?= urlencode($katalog) ?>&obj=<?= urlencode($obj) ?>&index=<?= urlencode($i) ?>&action=replace">
                            <button type="button">Vyměnit snímek</button>
                        </a>
                    </div>
                <?php endif ?>
                <br>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

</body>

</html>