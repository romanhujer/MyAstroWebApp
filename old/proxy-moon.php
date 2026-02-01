<?php
header("Content-Type: text/html; charset=UTF-8");

$url = "https://moonphases.co.uk/moonphases_widget.php?widget=tall";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Nastavíme User-Agent, aby server neblokoval požadavek
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200 || !$response) {
    echo "<p>Nelze načíst měsíční widget. Zkuste to později.</p>";
    exit;
}

// Překlad do češtiny (volitelně)
$translations = [
    "Moon Phase" => "Fáze Měsíce",
    "Illumination" => "Osvětlení",
    "Age" => "Stáří",
    "Distance" => "Vzdálenost",
    "Current Time" => "Aktuální čas",
    "Next Full Moon" => "Další úplněk",
    "Next New Moon" => "Další nov",
    "Waxing" => "Dorůstající",
    "Waning" => "Couvající",
    "Gibbous" => "Vypouklý",
    "Crescent" => "Srpek",
    "Full Moon" => "Úplněk",
    "New Moon" => "Nov",
    "First Quarter" => "První čtvrť",
    "Last Quarter" => "Poslední čtvrť"
];

foreach ($translations as $en => $cz) {
    $response = str_replace($en, $cz, $response);
}

echo $response;
?>

