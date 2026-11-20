<?php
declare(strict_types=1);
// Serves the single-page app (index.html) with the SEO <title> and meta description of the requested URL
// already in the HTML, so search engines see the right pair without running JavaScript.
// The titles/descriptions live in one place: the <script id="seo-meta"> block in index.html.
// URLs not in that block (cart, checkout, ...) keep index.html's defaults (the Home page pair).
$html = file_get_contents(__DIR__ . '/index.html');
$path = '/' . trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');

if (preg_match('#<script type="application/json" id="seo-meta">(.*?)</script>#s', $html, $m)) {
    $meta = json_decode($m[1], true);
    if (is_array($meta) && isset($meta[$path][0], $meta[$path][1])) {
        $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = '<title>' . $e($meta[$path][0]) . '</title>';
        $desc  = '<meta name="description" content="' . $e($meta[$path][1]) . '" />';
        $html = preg_replace_callback('#<title>.*?</title>#s', fn() => $title, $html, 1);
        $html = preg_replace_callback('#<meta name="description" content="[^"]*" />#', fn() => $desc, $html, 1);
    }
}

header('Content-Type: text/html; charset=UTF-8');
echo $html;
