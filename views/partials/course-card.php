<?php /** @var array $course */ ?>
<article class="course-card">
  <?php partial('partials/cover', ['course' => $course, 'showTitle' => false]) ?>
  <div class="body">
    <h3><a href="<?= url('curso/' . $course['slug']) ?>"><?= e($course['title']) ?></a></h3>
    <p class="summary"><?= e(str_limit($course['summary'], 130)) ?></p>
    <div class="course-meta">
      <span><?= icon('clock') ?> <?= (int) $course['workload_hours'] ?> horas</span>
      <span><?= icon('sliders') ?> <?= e(level_label($course['level'])) ?></span>
      <span><?= icon('play-circle') ?> <?= plural((int) $course['lessons_count'], 'aula', 'aulas') ?></span>
      <?php if ($course['certificate_enabled']): ?><span><?= icon('award') ?> Certificado</span><?php endif; ?>
    </div>
  </div>
</article>
