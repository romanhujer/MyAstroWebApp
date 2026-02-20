<?php
ini_set('memory_limit', '512M');
header("Access-Control-Allow-Origin: https://astro.hujer.net");
header("Content-Type: text/html; charset=utf-8");

function getUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $data = curl_exec($ch);

    if ($data === false) {
        echo "cURL error: " . curl_error($ch);
        exit;
    }

    curl_close($ch);
    return $data;
}

$url = "https://udalosti.astro.cz/wp-json/wp/v2/posts?per_page=10";

$response = getUrl($url);
$posts = json_decode($response);

if ($posts === null) {
    echo "json_decode selhalo<br>";
    echo json_last_error_msg();
    exit;
}
?>
<html>
<head>
<style>

.excerpt, 
.excerpt * {
    color: #bbb !important;
}

h2 a {
    color: #0066cc !important;
}

body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
    background: black;
    font-size: 20px;
}

article {
    margin-bottom: 40px;
    padding-bottom: 25px;
    border-bottom: 1px solid #444;
}
img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 10px auto 15px auto; /* ← auto = centrování */
    display: block;              /* ← nutné pro margin:auto */
}


article img {
    display: block;
    margin-left: auto;
    margin-right: auto;
}
article figure {
    margin-left: auto !important;
    margin-right: auto !important;
    text-align: center;
}

article figure img {
    display: inline-block !important;
    margin: 10px auto 15px auto !important;
}
/* Centrovat WordPress caption box */
.wp-caption {
    margin-left: auto !important;
    margin-right: auto !important;
    text-align: center !important;
}

/* Obrázek uvnitř caption */
.wp-caption img {
    display: block !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

/* Popisek */
.wp-caption-text {
    text-align: center !important;
    color: #bbb;
    font-size: 18px;
}

article iframe {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.readmore {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 12px;
    background: #0066cc;
    color: white !important;
    text-decoration: none;
    border-radius: 4px;
}

.readmore:hover {
    background: #004c99;
}

.pubdate {
    color: #888;
    font-size: 18px;
    margin-bottom: 10px;
}

</style>
</head>

<body>
<?php

foreach ($posts as $post) {

    $title = $post->title->rendered;
    $link = $post->link;

    // obsah – WordPress vrací čistý text
    $excerpt = $post->content->rendered;
   
   // $excerpt = nl2br($excerpt); // převede nové řádky na <br>

    $author = "";
    if (!empty($post->yoast_head_json->author)) {
        $author = $post->yoast_head_json->author;
    }
    // datum
    $date = date("d.m.Y H:i", strtotime($post->date));

    // obrázek z Yoast SEO
    $image = "";
    if (!empty($post->yoast_head_json->og_image[0]->url)) {
        $image = $post->yoast_head_json->og_image[0]->url;
    }

    echo "<article>";
    echo "<h2><a target='_blank'>$title</a></h2>";
    echo "<div class='pubdate'>$date $author</div>";

    if ($image) {
        echo "<img src='$image'>";
    }
    echo "<div class='excerpt'>$excerpt</div>";
    echo "<a class='readmore' href='$link' target='_blank'>Číst originál</a>";
    echo "</article>";
}

?>
</body>
</html>
