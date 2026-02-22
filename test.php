<?php
$json = file_get_contents('/opt/astro_json/messier_preview.json');
$preview = json_decode($json, true);

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

<h1>DSO Preview</h1>

<h2>Messier seznam</h2>
<?php
foreach ($preview as $obj => $items) {
    echo "<a href='?obj=$obj'>$obj</a> (" . count($items) . ") &nbsp; ";
}

foreach ($preview as $obj => $items) :
?>
<?php $selected = $obj; ?> 
<hr>

<?php if ($selected): ?>
<h2><?php echo $selected; ?></h2>

<?php foreach ($preview[$selected] as $img): ?>
<div class="thumb">
    <a href="<?php echo $img['url']; ?>" target="_blank">
        <img src="<?php echo $img['thumbnail']; ?>">
    </a>
    <div><?php echo htmlspecialchars($img['title']); ?></div>

    <?php if ($img['isIotd']): ?>
        <span class="badge iotd">IOTD</span>
    <?php endif; ?>

    <?php if ($img['isTopPick']): ?>
        <span class="badge tp">TP</span>
    <?php endif; ?>

    <?php if ($img['isTopPickNomination']): ?>
        <span class="badge tpn">TPN</span>
    <?php endif; ?>

</div>
<?php endforeach; ?>

<?php endif; ?>

<?php endforeach; ?>
</body>
</html>
