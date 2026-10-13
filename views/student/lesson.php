<?php
/** @var array $lesson @var array $materials @var bool $completed @var bool $measurable @var int $watchedPercent @var int $startAt */
$isVideo = $lesson['type'] === 'video';
$canManual = !$measurable;
?>
<div class="lesson-head">
  <p class="muted" style="margin:0 0 .25rem"><?= e($lesson['module_title']) ?></p>
  <h1><?= e($lesson['title']) ?></h1>
</div>

<?php if ($isVideo): ?>
  <div id="player-root"
       data-track-url="<?= url('aluno/aula/' . $lesson['id'] . '/progresso') ?>"
       data-start="<?= (int) $startAt ?>"
       data-duration="<?= (int) $lesson['duration_seconds'] ?>"
       data-completed="<?= $completed ? 1 : 0 ?>"
       data-provider="<?= e($measurable ? $lesson['video_provider'] : 'none') ?>">
    <div class="video-frame">
      <?php partial('partials/video-embed', ['lesson' => $lesson, 'src' => url('aluno/aula/' . $lesson['id'] . '/video')]) ?>
    </div>
  </div>
  <div class="video-status">
    <?php if ($measurable && !$preview): ?>
      <div class="watch">
        <div class="progress" id="watch-bar" style="--value:<?= $completed ? 100 : $watchedPercent ?>%" data-done="<?= $completed ? 1 : 0 ?>"></div>
        <span id="watch-text"><?= $completed ? 'Aula concluída' : 'Assistido: ' . $watchedPercent . '% (conclui com 85%)' ?></span>
      </div>
    <?php endif; ?>
    <span id="resume-note" hidden><?= icon('history') ?> Retomado em <?= clock_fmt((int) $startAt) ?>, onde você parou.</span>
    <span class="badge badge-ok" id="done-badge" <?= $completed ? '' : 'hidden' ?>><?= icon('check') ?> Concluída</span>
  </div>
<?php endif; ?>

<div class="<?= $isVideo ? 'prose' : 'text-lesson prose' ?>">
  <?= rich_text($lesson['content']) ?>
</div>

<?php if ($materials): ?>
  <section style="margin-top:2rem">
    <h2 style="font-size:var(--step-1)">Materiais da aula</h2>
    <ul class="materials">
      <?php foreach ($materials as $mat): $ext = strtoupper(pathinfo($mat['original_name'], PATHINFO_EXTENSION)); ?>
        <li>
          <a href="<?= url('aluno/material/' . $mat['id']) ?>">
            <span class="file-ico"><?= e($ext ?: 'ARQ') ?></span>
            <span class="grow"><strong><?= e($mat['title']) ?></strong><small><?= e($mat['original_name']) ?> · <?= bytes_fmt((int) $mat['size_bytes']) ?></small></span>
            <?= icon('download') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<nav class="lesson-nav" aria-label="Navegação entre aulas">
  <?php if ($prevUrl): ?>
    <a class="btn btn-ghost" href="<?= e($prevUrl) ?>"><?= icon('arrow-left') ?> <span>Anterior<span class="sr-only">: <?= e($prev['title']) ?></span></span></a>
  <?php else: ?><span></span><?php endif; ?>
  <div class="cluster">
    <?php if (!$preview && $canManual && !$completed): ?>
      <form method="post" action="<?= url('aluno/aula/' . $lesson['id'] . '/concluir') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ok" type="submit"><?= icon('check') ?> Marcar como concluída e seguir</button>
      </form>
    <?php endif; ?>
    <?php if ($nextUrl): ?>
      <a class="btn <?= $completed ? 'btn-marker' : 'btn-secondary' ?>" id="next-btn" href="<?= e($nextUrl) ?>">Próxima: <?= e(str_limit($next['title'], 34)) ?> <?= icon('arrow-right') ?></a>
    <?php endif; ?>
  </div>
</nav>

<?php if ($isVideo && $measurable && !$preview) {
    App\Core\View::push('scripts', '<script src="' . asset('js/player.js') . '" defer></script>');
} ?>
