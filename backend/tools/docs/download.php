<?php
$path = $_SERVER['DOCUMENT_ROOT'].'/tools/docs/output/docs_bundle.zip';
if (!is_file($path)) { http_response_code(404); exit('ZIP no encontrado'); }
header('Content-Type: application/zip');
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="docs_bundle.zip"');
readfile($path);
