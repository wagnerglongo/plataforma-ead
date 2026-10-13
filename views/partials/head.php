<?php
/** @var string|null $title @var string|null $description */
$pageTitle = isset($title) && $title ? $title . ' · ' . site_name() : site_name() . ' · ' . setting('site_tagline', 'Cursos online');
$pageDescription = $description ?? setting('site_tagline', 'Cursos online com certificado verificável.');
$canonical = absolute_url(App\Core\Request::current()?->path() ?? '/');
$favicon = "data:image/svg+xml," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#1F3A93"/><rect x="12" y="40" width="40" height="9" rx="3" fill="#F5E04B" transform="rotate(-4 32 44)"/><text x="32" y="38" font-family="Arial" font-weight="bold" font-size="30" text-anchor="middle" fill="#fff">' . e(mb_substr(site_name(), 0, 1)) . '</text></svg>');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(str_limit($pageDescription, 160)) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#1F3A93">
<?php if (!empty($noindex) || config('app.demo') && str_starts_with(App\Core\Request::current()?->path() ?? '', '/aluno')): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e(str_limit($pageDescription, 200)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="pt_BR">
<link rel="icon" href="<?= $favicon ?>">
<link rel="preload" href="<?= url('assets/fonts/bricolage-grotesque-latin-800-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= url('assets/fonts/atkinson-hyperlegible-latin-400-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json" nonce="<?= csp_nonce() ?>"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? 'app-body') ?>"
      data-gtm="<?= e(setting('gtm_id', '')) ?>"
      data-ga="<?= e(setting('ga_id', '')) ?>"
      data-consent-url="<?= url('cookies/consentimento') ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
