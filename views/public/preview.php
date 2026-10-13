<?php /** @var array $course @var array $lesson @var bool $enrolled */ ?>
<div class="container" style="padding-top:2rem;padding-bottom:4rem;max-width:980px">
  <nav class="breadcrumb" aria-label="Você está em">
    <a href="<?= url('cursos') ?>">Cursos</a> <?= icon('chevron-right') ?>
    <a href="<?= url('curso/' . $course['slug']) ?>"><?= e($course['title']) ?></a> <?= icon('chevron-right') ?> <span>Aula gratuita</span>
  </nav>
  <div class="lesson-head">
    <h1><?= e($lesson['title']) ?></h1>
    <p>Aula gratuita do curso <?= e($course['title']) ?>. Matriculado, seu progresso fica salvo.</p>
  </div>
  <?php if ($lesson['type'] === 'video'): ?>
    <div class="video-frame">
      <?php partial('partials/video-embed', ['lesson' => $lesson, 'src' => url('curso/' . $course['slug'] . '/aula-gratuita/' . $lesson['id'] . '/video')]) ?>
    </div>
    <div class="prose" style="margin-top:1.5rem"><?= rich_text($lesson['content']) ?></div>
  <?php else: ?>
    <div class="text-lesson prose"><?= rich_text($lesson['content']) ?></div>
  <?php endif; ?>
  <div class="panel" style="margin-top:2rem">
    <div class="split">
      <div>
        <h2 style="margin:0 0 .25rem">Gostou da aula?</h2>
        <p class="muted" style="margin:0">São <?= (int) $course['lessons_count'] ?> aulas, avaliações e certificado de <?= (int) $course['workload_hours'] ?> horas.</p>
      </div>
      <?php if ($enrolled): ?>
        <a class="btn" href="<?= url('aluno/curso/' . $course['slug']) ?>">Ir para o curso</a>
      <?php elseif (auth()): ?>
        <form method="post" action="<?= url('curso/' . $course['slug'] . '/matricular') ?>"><?= csrf_field() ?><button class="btn" type="submit">Matricular-se gratuitamente</button></form>
      <?php else: ?>
        <a class="btn" href="<?= url('cadastro', ['curso' => $course['slug']]) ?>">Criar conta e continuar</a>
      <?php endif; ?>
    </div>
  </div>
</div>
