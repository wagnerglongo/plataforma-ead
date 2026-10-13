<?php partial('partials/head', get_defined_vars()) ?>
<header class="player-top">
  <div class="bar">
    <a class="btn btn-ghost btn-sm" href="<?= url('aluno') ?>"><?= icon('arrow-left') ?><span class="sr-only">Voltar para</span> Meus cursos</a>
    <span class="title"><?= e($course['title']) ?></span>
    <?php if ($enrollment): ?>
      <div class="prog" title="Progresso no curso">
        <div class="progress" id="course-bar" style="--value:<?= (int) $percent ?>%" data-done="<?= $percent >= 100 ? 1 : 0 ?>"></div>
        <span id="course-text"><?= (int) $percent ?>%</span>
      </div>
    <?php else: ?>
      <span class="badge badge-warn">Visualização da equipe</span>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="#curso-conteudo"><?= icon('list') ?><span class="sr-only">Conteúdo do curso</span></a>
  </div>
</header>
<div class="player">
  <main class="player-main" id="conteudo">
    <?php partial('partials/flash') ?>
    <?= $content ?>
  </main>
  <?php partial('student/_sidebar', get_defined_vars()) ?>
</div>
<template id="icon-done"><?= icon('check-circle') ?></template>
<?php if ($track = App\Core\Session::get('_track')): ?><span hidden data-track-event="<?= e($track['event']) ?>" data-track-params="<?= e(json_encode(array_diff_key($track, ['event' => 1]))) ?>"></span><?php endif; ?>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= App\Core\View::stack('scripts') ?>
</body>
</html>
