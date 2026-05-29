<?php

declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "Usage: php extract-docx.php <file.docx>\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Could not open docx.\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml === false) {
    fwrite(STDERR, "No document.xml found.\n");
    exit(1);
}

$text = preg_replace('/<w:tab\/>/', "\t", $xml);
$text = preg_replace('/<\/w:p>/', PHP_EOL, (string) $text);
$text = strip_tags((string) $text);
$text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
$text = preg_replace("/\n{3,}/", "\n\n", $text);

echo trim($text), PHP_EOL;
