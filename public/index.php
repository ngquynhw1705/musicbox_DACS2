<?php
// Allow embedding only when explicitly enabled for a development preview.
// Production always keeps clickjacking protection.
$framePolicy = getenv('APP_ENV') !== 'production' && getenv('APP_PREVIEW') === '1'
    ? '' : "; frame-ancestors 'none'";
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'" . $framePolicy);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#171529">
  <title>Music Box — Không gian của bạn, âm nhạc của bạn</title>
  <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/app.js" defer></script>
</head>
<body>
  <a class="skip" href="#content">Đến nội dung chính</a>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="#rooms"><img src="/assets/logo.svg" alt="" width="38" height="38"><span>music<span class="brand-light">box</span><small>YOUR PRIVATE MUSIC SPACE</small></span></a>
    <div class="side-label">KHÔNG GIAN CỦA BẠN</div>
    <nav id="navigation" aria-label="Điều hướng chính"></nav>
    <div class="side-bottom"><div class="side-note"><span class="mini-wave">▂ ▅ ▃ ▇ ▄</span><strong>Chạm vào cảm xúc.</strong><p>Để âm nhạc kể câu chuyện<br>của riêng bạn.</p></div><div id="side-user"></div></div>
  </aside>
  <div class="workspace">
    <header class="topbar"><div class="breadcrumb"><button id="menu-toggle" class="icon-btn mobile-menu" aria-label="Mở điều hướng" aria-expanded="false">☰</button><span>Music Box</span><span class="slash">/</span><strong id="page-name">Khám phá phòng</strong></div><div class="top-actions"><span class="open-indicator"><i></i> Mỗi ngày · 09:00 – 23:00</span><div id="top-user"></div></div></header>
    <main id="content" tabindex="-1"><div class="loading">Đang chuẩn bị không gian âm nhạc…</div></main>
    <footer><span>© <?= date('Y') ?> Music Box Studio</span><span>Âm nhạc kết nối những khoảnh khắc.</span><span>PHP · MySQL</span></footer>
  </div>
  <div id="modal-root"></div>
  <div id="toast-root" role="status" aria-live="polite"></div>
  <noscript>Vui lòng bật JavaScript để xem lịch trống và đặt phòng.</noscript>
</body>
</html>
