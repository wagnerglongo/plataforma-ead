<?php
/** @var array $course @var array $curriculum @var array $lessonProgress @var array $quizProgress @var array $locks @var array $current */
$base = 'aluno/curso/' . $course['slug'];
$isCurrent = fn (string $type, int $id) => $current['type'] === $type && $current['id'] === $id;
$quizRow = function (array $quiz) use ($base, $quizProgress, $locks, $isCurrent) {
    $qp = $quizProgress[$quiz['id']] ?? null;
    $locked = isset($locks[$quiz['id']]);
    $cls = 'is-quiz' . (!empty($qp['passed']) ? ' is-done' : '') . ($locked ? ' locked' : '');
    $status = !empty($qp['passed']) ? 'Aprovado com ' . $qp['best'] . '%' : ($qp ? 'Melhor nota: ' . $qp['best'] . '%' : ($locked ? $locks[$quiz['id']] : plural((int) $quiz['questions_count'], 'questão', 'questões')));
    ob_start(); ?>
    <li class="<?= $cls ?>">
      <a href="<?= url($base . '/avaliacao/' . $quiz['id']) ?>"<?= $isCurrent('quiz', (int) $quiz['id']) ? ' aria-current="page"' : '' ?>>
        <?= icon(!empty($qp['passed']) ? 'check-circle' : ($locked ? 'lock' : 'quiz')) ?>
        <span><?= e($quiz['title']) ?><small><?= e($status) ?></small></span>
      </a>
    </li>
    <?php return ob_get_clean();
};
?>
<aside class="player-side" id="curso-conteudo" aria-label="Conteúdo do curso">
  <div class="side-head">
    <h2>Conteúdo do curso</h2>
    <?php if ($enrollment): ?>
      <div class="progress-label"><span><?= $enrollment['status'] === 'completed' ? 'Curso concluído' : 'Seu progresso' ?></span><strong><?= (int) $percent ?>%</strong></div>
      <div class="progress" style="--value:<?= (int) $percent ?>%" data-done="<?= $percent >= 100 ? 1 : 0 ?>"></div>
    <?php endif; ?>
  </div>
  <?php foreach ($curriculum['modules'] as $i => $m):
      $done = count(array_filter($m['lessons'], fn ($l) => !empty($lessonProgress[$l['id']]['completed_at'])));
      $hasCurrent = (bool) array_filter($m['lessons'], fn ($l) => $isCurrent('lesson', (int) $l['id'])) || ($m['quiz'] && $isCurrent('quiz', (int) $m['quiz']['id']));
  ?>
    <details class="side-module" <?= $hasCurrent ? 'open' : '' ?>>
      <summary>
        <span>Módulo <?= $i + 1 ?>: <?= e($m['title']) ?><small><?= $done ?> de <?= count($m['lessons']) ?> aulas concluídas</small></span>
        <?= icon('chevron-down') ?>
      </summary>
      <ul class="side-items">
        <?php foreach ($m['lessons'] as $l):
            $lp = $lessonProgress[$l['id']] ?? null;
            $ok = !empty($lp['completed_at']);
        ?>
          <li class="<?= $ok ? 'is-done' : '' ?>">
            <a href="<?= url($base . '/aula/' . $l['id']) ?>"<?= $isCurrent('lesson', (int) $l['id']) ? ' aria-current="page"' : '' ?>>
              <?= icon($ok ? 'check-circle' : 'circle') ?>
              <span><?= e($l['title']) ?><small><?= $l['type'] === 'video' ? 'Vídeo' : 'Leitura' ?> · <?= duration_fmt((int) $l['duration_seconds']) ?><?= (!$ok && !empty($lp['position_seconds'])) ? ' · parou em ' . clock_fmt((int) $lp['position_seconds']) : '' ?></small></span>
            </a>
          </li>
        <?php endforeach; ?>
        <?php if ($m['quiz']): ?><?= $quizRow($m['quiz']) ?><?php endif; ?>
      </ul>
    </details>
  <?php endforeach; ?>
  <?php if ($curriculum['final']): ?>
    <details class="side-module" open>
      <summary><span>Encerramento<small>Aprovação libera o certificado</small></span><?= icon('chevron-down') ?></summary>
      <ul class="side-items"><?= $quizRow($curriculum['final']) ?></ul>
    </details>
  <?php endif; ?>
</aside>
