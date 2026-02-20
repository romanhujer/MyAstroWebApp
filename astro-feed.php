<?php
header("Access-Control-Allow-Origin: https://astro.hujer.net");
header("Content-Type: text/html; charset=utf-8");

$posts = json_decode(file_get_contents("https://hujer.net/wp-json/wp/v2/posts?_embed&per_page=3"));
?>
<html>
<head>
<style>

/* tady vložíš css */
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

<body style="background: black; font-size: 24px;">
<?php
foreach ($posts as $post) {

    $title = $post->title->rendered;
    $link = $post->link;
    $excerpt = $post->excerpt->rendered;

    // datum publikace
    $date = date("d.m.Y H:i", strtotime($post->date));

    // autor
    $author = "";
    if (!empty($post->yoast_head_json->author)) {
        $author = $post->yoast_head_json->author;
    }

    // obrázek
    $image = "";
    if (!empty($post->_embedded["wp:featuredmedia"][0]->source_url)) {
        $image = $post->_embedded["wp:featuredmedia"][0]->source_url;
    }

    echo "<article>";
    echo "<h2><a href='$link' target='_blank'>$title</a></h2>";

    // vložení data
    echo "<div class='pubdate'>$date - $author</div>";

    if ($image) {
        echo "<img src='$image'>";
    }

    echo "<div class='excerpt'>$excerpt</div>";
    echo "<a class='readmore' href='$link' target='_blank'>Číst dál</a>";
    echo "</article>";
}
?>
</body>
</html>

