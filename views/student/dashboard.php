<?php /** @var array $list @var string $filter @var array $counts @var ?array $resume @var array $suggestions */ ?>
<?php if ($resume):
    $e = $resume['enrollment'];
    $t = $resume['target'];
    $href = url('aluno/curso/' . $e['slug'] . '/' . ($t['type'] === 'lesson' ? 'aula' : 'avaliacao') . '/' . $t['id']);
?>
  <section class="resume" aria-labelledby="resume-title">
    <?php partial('partials/cover', ['course' => $e + ['category_name' => $e['category_name']], 'showTitle' => false]) ?>
    <div class="resume-body">
      <p class="k">Continuar de onde parou</p>
      <h2 id="resume-title"><?= e($e['title']) ?></h2>
      <p class="next"><?= $t['type'] === 'lesson' ? icon('play-circle') : icon('quiz') ?>
        <?= e($t['module']) ?>: <strong><?= e($t['title']) ?></strong>
        <?php if (!empty($t['position'])): ?><span class="muted">· parou em <?= clock_fmt((int) $t['position']) ?></span><?php endif; ?>
      </p>
      <div class="progress-wrap">
        <div class="progress-label"><span><?= (int) $e['lessons_done'] ?> de <?= (int) $e['lessons_count'] ?> aulas</span><strong><?= (int) $e['progress_percent'] ?>%</strong></div>
        <div class="progress" style="--value:<?= (int) $e['progress_percent'] ?>%"></div>
      </div>
      <div><a class="btn btn-lg" href="<?= $href ?>"><?= icon('play') ?> Continuar</a></div>
    </div>
  </section>
<?php endif; ?>

<nav class="tabs" aria-label="Filtrar cursos">
  <a href="<?= url('aluno') ?>"<?= $filter === 'andamento' ? ' aria-current="page"' : '' ?>>Em andamento <span>(<?= $counts['andamento'] ?>)</span></a>
  <a href="<?= url('aluno', ['filtro' => 'concluidos']) ?>"<?= $filter === 'concluidos' ? ' aria-current="page"' : '' ?>>Concluídos <span>(<?= $counts['concluidos'] ?>)</span></a>
  <a href="<?= url('aluno', ['filtro' => 'todos']) ?>"<?= $filter === 'todos' ? ' aria-current="page"' : '' ?>>Todos <span>(<?= $counts['todos'] ?>)</span></a>
</nav>

<?php if (!$list): ?>
  <div class="empty" style="margin-bottom:3rem">
    <?= icon('book') ?>
    <?php if ($counts['todos'] === 0): ?>
      <h3>Você ainda não está em nenhum curso</h3>
      <p>Escolha um curso no catálogo. A matrícula é imediata e você pode começar agora.</p>
      <a class="btn" href="<?= url('cursos') ?>">Ver cursos</a>
    <?php else: ?>
      <h3><?= $filter === 'concluidos' ? 'Nenhum curso concluído ainda' : 'Nenhum curso em andamento' ?></h3>
      <p><?= $filter === 'concluidos' ? 'Quando terminar um curso, ele aparece aqui junto com o certificado.' : 'Todos os seus cursos foram concluídos. Que tal começar outro?' ?></p>
      <a class="btn btn-secondary" href="<?= url('cursos') ?>">Explorar cursos</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="enroll-list">
    <?php foreach ($list as $e): $done = $e['status'] === 'completed'; ?>
      <article class="enroll-item">
        <?php partial('partials/cover', ['course' => $e, 'showTitle' => false]) ?>
        <div>
          <h3><a href="<?= url('aluno/curso/' . $e['slug']) ?>"><?= e($e['title']) ?></a></h3>
          <p class="meta"><?= $done ? 'Concluído em ' . date_br($e['completed_at']) : 'Último acesso ' . time_ago($e['last_access_at']) ?> · <?= (int) $e['workload_hours'] ?> horas</p>
          <div class="progress-wrap">
            <div class="progress-label"><span><?= (int) $e['lessons_done'] ?> de <?= (int) $e['lessons_count'] ?> aulas</span><strong><?= (int) $e['progress_percent'] ?>%</strong></div>
            <div class="progress" style="--value:<?= (int) $e['progress_percent'] ?>%" data-done="<?= $done ? 1 : 0 ?>"></div>
          </div>
        </div>
        <div class="actions cluster">
          <?php if ($done && $e['certificate_code']): ?>
            <a class="btn btn-marker" href="<?= url('aluno/certificados/' . $e['certificate_code'] . '/pdf') ?>"><?= icon('award') ?> Certificado</a>
            <a class="btn btn-ghost" href="<?= url('aluno/curso/' . $e['slug']) ?>">Rever</a>
          <?php else: ?>
            <a class="btn" href="<?= url('aluno/curso/' . $e['slug']) ?>"><?= (int) $e['progress_percent'] > 0 ? 'Continuar' : 'Começar' ?></a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($suggestions): ?>
  <section style="padding-bottom:3rem">
    <div class="section-head"><h2 style="font-size:var(--step-2)">Próximos cursos para você</h2></div>
    <div class="course-grid">
      <?php foreach ($suggestions as $c): ?><?php partial('partials/course-card', ['course' => $c]) ?><?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
