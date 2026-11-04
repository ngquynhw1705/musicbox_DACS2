<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('#^/assets/[a-zA-Z0-9_.-]+\.(css|js|svg|png|jpg|webp)$#',$path) && is_file(__DIR__.$path)) return false;
if ($path==='/api') { require __DIR__.'/api.php'; return true; }
if ($path==='/' || $path==='/index.php') { require __DIR__.'/index.php'; return true; }
http_response_code(404);
echo '404 — Không tìm thấy trang';
