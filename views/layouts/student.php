<?php
partial('partials/head', get_defined_vars());
partial('partials/header');
$u = auth();
$first = explode(' ', $u['name'])[0];
$tabs = [
    'aluno' => ['Meus cursos', 'book'],
    'aluno/certificados' => ['Certificados', 'award'],
    'aluno/perfil' => ['Meu perfil', 'user'],
    'aluno/privacidade' => ['Privacidade e dados', 'shield'],
];
$path = trim(App\Core\Request::current()->path(), '/');
?>
<div class="student-head">
  <div class="container" style="display:block">
    <div class="split">
      <div>
        <h1><?= e($heading ?? 'Olá, ' . $first) ?></h1>
        <p><?= e($subheading ?? 'Tudo o que você estuda fica aqui: cursos, avaliações e certificados.') ?></p>
      </div>
      <form method="post" action="<?= url('sair') ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('logout') ?> Sair</button></form>
    </div>
    <nav class="subnav" aria-label="Área do aluno">
      <?php foreach ($tabs as $href => [$label, $ic]): ?>
        <a href="<?= url($href) ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= icon($ic) ?> <?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
<main id="conteudo">
  <div class="container">
    <?php if (App\Core\Session::get('_flash')): ?><div style="padding-top:1.25rem"><?php partial('partials/flash') ?></div><?php endif; ?>
    <?= $content ?>
  </div>
</main>
<?php partial('partials/footer') ?>
<?php partial('partials/cookie-banner') ?>
<?php if ($track = App\Core\Session::get('_track')): ?><span hidden data-track-event="<?= e($track['event']) ?>" data-track-params="<?= e(json_encode(array_diff_key($track, ['event' => 1]))) ?>"></span><?php endif; ?>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= App\Core\View::stack('scripts') ?>
</body>
</html>
