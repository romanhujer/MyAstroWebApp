<?php


$katalogDecode = [
  "test" => "Test",  
  "messier" => "Messier",
  "caldwell" => "Caldwell",
  "herschel" => "Herschel",
  "sharpless" => "Sharples",
  "galaxie" => "Galaxie",
  "arp" => "Arp",
  "snr" => "SNR",
  "vdb" => "vdB",
  "abell" => "Abell",
  "barnard" => "Barnard",
  "dark" => "Výber LBN/LDN",
  "select" => "Výběr NGC"
];

$katalog = $_GET['katalog'] ?? 'messier';

$cesta = '/opt/astro_json/' . $katalog . '_preview.json';


function load_data($path)
{
  // Pokud existuje .gz varianta, použij ji
  if (file_exists($path . '.gz')) {
    $path .= '.gz';
  }
  // Načtení souboru
  $raw = file_get_contents($path);
  if ($raw === false)
    return null;
  // Pokud je gzip, dekomprimuj
  if (substr($path, -3) === '.gz') {
    $json = gzdecode($raw);
    if ($json === false)
      return null;
  } else {
    $json = $raw;
  }
  // Dekódování JSONu
  $data = json_decode($json, true);
  if (!is_array($data))
    return null;

  return $data;
}


$preview = load_data($cesta);


$selected = $_GET['obj'] ?? null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>DSO Preview</title>
<style>
body { font-family: Arial; background:#111; color:#eee; }
a { color:#6cf; text-decoration:none; }
.thumb { margin:10px; display:inline-block; text-align:center; }
.thumb img { width:200px; border-radius:6px; }
.badge { padding:3px 6px; border-radius:4px; font-size:12px; margin-left:5px; }
.iotd { background:#ff4444; }
.tp { background:#44aa44; }
.tpn { background:#ffaa00; }
</style>
</head>
<body>

<h1>DSO Preview katalog: <?= $katalogDecode[$katalog] ?? $katalog ?></h1>
<form method="get">
    <label for="katalog">Vyber katalog:</label>
    <select name="katalog" id="katalog" onchange="this.form.submit()">
        <?php foreach ($katalogDecode as $key => $name): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($key === $katalog) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<br><br>

<?php
echo "<table><tr>"; 

$col = 0;

foreach ($preview as $obj => $items) : ?>
<?php
    
    if ($col > 4){
       echo "</tr><tr>";
        $col = 0;
    }
    echo "<td width='250px' valign='top'>"; 
    $col++;
?>


<?php $selected = $obj; ?> 
<strong><?php echo $selected; ?></strong><br>
<?php foreach ($preview[$selected] as $img): ?>

 <div class="thumb">
    <a href="<?php echo $img['url']; ?>" >
        <img src="<?php echo $img['thumbnail']; ?>" height="150">      
    </a>    
    <div><?php echo htmlspecialchars($img['title']); ?></div><br>
    <span><?php  echo $img['userDisplayName']; ?>  </span><br>
    <?php if ($img['isIotd']): ?>
        <span class="badge iotd">IOTD</span>
    <?php endif; ?>

    <?php if ($img['isTopPick']): ?>
        <span class="badge tp">TP</span>
    <?php endif; ?>

    <?php if ($img['isTopPickNomination']): ?>
        <span class="badge tpn">TPN</span>
    <?php endif; ?>
</div></td>
<?php endforeach; ?>


<?php endforeach; ?>
</tr></table>   
</body>
</html>
