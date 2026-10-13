<?php
/** @var array $course @var array $curriculum */
$canManage = can('courses.manage');
$totalLessons = array_sum(array_map(fn ($m) => count($m['lessons']), $curriculum['modules']));
?>
<div class="admin-head">
  <div>
    <nav class="breadcrumb"><a href="<?= url('admin/cursos') ?>">Cursos</a> <?= icon('chevron-right') ?> <a href="<?= url('admin/cursos/' . $course['id']) ?>"><?= e($course['title']) ?></a> <?= icon('chevron-right') ?> <span>Conteúdo</span></nav>
    <h1>Módulos e aulas</h1>
    <p><?= plural(count($curriculum['modules']), 'módulo', 'módulos') ?> · <?= plural($totalLessons, 'aula', 'aulas') ?> · <?= duration_fmt((int) $course['total_seconds']) ?><?= $canManage ? ' · arraste ' . icon('move') . ' para reordenar' : '' ?></p>
  </div>
  <div class="cluster">
    <a class="btn btn-secondary" href="<?= url('admin/cursos/' . $course['id']) ?>"><?= icon('edit') ?> Dados do curso</a>
    <a class="btn btn-ghost" href="<?= url('aluno/curso/' . $course['slug']) ?>" target="_blank"><?= icon('eye') ?> Ver como aluno</a>
  </div>
</div>

<?php if (!$curriculum['modules']): ?>
  <div class="empty" style="margin-bottom:1.5rem"><?= icon('layers') ?><h3>Comece criando o primeiro módulo</h3><p>Módulos agrupam as aulas por assunto. Depois de criar, adicione aulas em vídeo ou texto, materiais e avaliações.</p></div>
<?php endif; ?>

<div data-sortable="<?= url('admin/cursos/' . $course['id'] . '/ordem') ?>" data-type="modules">
<?php foreach ($curriculum['modules'] as $i => $m): ?>
  <section class="builder-module" id="modulo-<?= $m['id'] ?>" data-id="<?= $m['id'] ?>">
    <header>
      <?php if ($canManage): ?><button type="button" class="handle" aria-label="Mover módulo (use as setas)"><?= icon('move') ?></button><?php endif; ?>
      <h3>Módulo <?= $i + 1 ?>: <?= e($m['title']) ?></h3>
      <span class="muted" style="font-size:var(--step--1)"><?= plural(count($m['lessons']), 'aula', 'aulas') ?> · <?= duration_fmt((int) $m['seconds']) ?></span>
      <?php if ($canManage): ?>
        <details style="position:relative">
          <summary class="btn btn-ghost btn-sm" style="list-style:none"><?= icon('edit') ?> Renomear</summary>
          <form method="post" action="<?= url('admin/modulos/' . $m['id']) ?>" class="tag-input-row" style="margin-top:.5rem">
            <?= csrf_field() ?><input class="input" name="title" value="<?= e($m['title']) ?>" style="min-height:38px;width:260px" required><button class="btn btn-sm" type="submit">Salvar</button>
          </form>
        </details>
        <form method="post" action="<?= url('admin/modulos/' . $m['id'] . '/excluir') ?>" data-confirm="Excluir o módulo “<?= e($m['title']) ?>” e todas as aulas dele?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" title="Excluir módulo"><?= icon('trash') ?><span class="sr-only">Excluir módulo</span></button></form>
      <?php endif; ?>
    </header>
    <ul class="builder-items" data-sortable="<?= url('admin/cursos/' . $course['id'] . '/ordem') ?>" data-type="lessons">
      <?php foreach ($m['lessons'] as $l): ?>
        <li data-id="<?= $l['id'] ?>">
          <?php if ($canManage): ?><button type="button" class="handle" aria-label="Mover aula"><?= icon('move') ?></button><?php endif; ?>
          <?= icon($l['type'] === 'video' ? 'video' : 'file-text') ?>
          <span class="grow">
            <a href="<?= url('admin/aulas/' . $l['id']) ?>"><?= e($l['title']) ?></a><br>
            <small><?= $l['type'] === 'video' ? e(App\Services\VideoService::PROVIDERS[$l['video_provider']] ?? 'Vídeo') : 'Texto' ?> · <?= duration_fmt((int) $l['duration_seconds']) ?><?= $l['materials_count'] ? ' · ' . plural((int) $l['materials_count'], 'material', 'materiais') : '' ?></small>
          </span>
          <?php if ($l['is_preview']): ?><span class="badge badge-marker">Aula gratuita</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$m['lessons']): ?><li class="muted">Nenhuma aula neste módulo ainda.</li><?php endif; ?>
    </ul>
    <?php if ($m['quiz']): ?>
      <ul class="builder-items" style="border-top:1px solid var(--rule)">
        <li><?= icon('quiz') ?><span class="grow"><a href="<?= url('admin/avaliacoes/' . $m['quiz']['id']) ?>"><?= e($m['quiz']['title']) ?></a><br><small><?= plural((int) $m['quiz']['questions_count'], 'questão', 'questões') ?> · nota mínima <?= (int) $m['quiz']['pass_score'] ?>%</small></span><span class="badge">Teste do módulo</span></li>
      </ul>
    <?php endif; ?>
    <?php if ($canManage): ?>
      <div class="builder-foot">
        <a class="btn btn-secondary btn-sm" href="<?= url('admin/modulos/' . $m['id'] . '/aulas/nova') ?>"><?= icon('plus') ?> Aula</a>
        <?php if (!$m['quiz']): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/cursos/' . $course['id'] . '/avaliacoes/nova', ['modulo' => $m['id']]) ?>"><?= icon('quiz') ?> Teste do módulo</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<section class="builder-module">
  <header><?= icon('award') ?><h3>Avaliação final</h3></header>
  <?php if ($curriculum['final']): $f = $curriculum['final']; ?>
    <ul class="builder-items"><li><?= icon('quiz') ?><span class="grow"><a href="<?= url('admin/avaliacoes/' . $f['id']) ?>"><?= e($f['title']) ?></a><br><small><?= plural((int) $f['questions_count'], 'questão', 'questões') ?> · nota mínima <?= (int) $f['pass_score'] ?>% · <?= $f['max_attempts'] ? plural((int) $f['max_attempts'], 'tentativa', 'tentativas') : 'tentativas ilimitadas' ?></small></span></li></ul>
  <?php else: ?>
    <p class="muted" style="padding:.75rem 1.1rem;margin:0">Sem avaliação final: o certificado sai quando todas as aulas e testes de módulo forem concluídos.</p>
    <?php if ($canManage): ?><div class="builder-foot"><a class="btn btn-secondary btn-sm" href="<?= url('admin/cursos/' . $course['id'] . '/avaliacoes/nova') ?>"><?= icon('plus') ?> Criar avaliação final</a></div><?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
<form class="card card-body" method="post" action="<?= url('admin/cursos/' . $course['id'] . '/modulos') ?>" style="margin-top:1.5rem">
  <?= csrf_field() ?>
  <label for="new-module" class="label">Novo módulo</label>
  <div class="input-group" style="max-width:560px">
    <input class="input" id="new-module" name="title" placeholder="Ex.: Fórmulas avançadas" required<?= invalid_attr('title') ?>>
    <button class="btn" type="submit"><?= icon('plus') ?> Adicionar módulo</button>
  </div>
  <?= field_error('title') ?>
</form>
<?php endif; ?>
