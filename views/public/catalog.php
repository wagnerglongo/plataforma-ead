<?php
/** @var array $result @var array $filters @var array $categories @var ?array $activeCategory @var bool $hasFilters */
use App\Models\Course;
?>
<div class="container">
  <header class="page-head">
    <nav class="breadcrumb" aria-label="Você está em"><a href="<?= url('/') ?>">Início</a> <?= icon('chevron-right', 'icon') ?> <span>Cursos</span></nav>
    <h1><?= $activeCategory ? 'Cursos de ' . e($activeCategory['name']) : 'Todos os cursos' ?></h1>
    <p>Todos com aulas em vídeo, materiais para baixar, avaliações e certificado verificável.</p>
  </header>

  <form class="catalog" action="<?= url('cursos') ?>" method="get">
    <aside class="filters" id="filters" aria-label="Filtros">
      <h2>Filtrar cursos</h2>
      <div class="field">
        <label for="q">Buscar</label>
        <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Nome ou assunto">
      </div>
      <fieldset class="filter-group">
        <legend>Área</legend>
        <label class="check"><input type="radio" name="categoria" value="" <?= $filters['categoria'] === '' ? 'checked' : '' ?> data-autosubmit> Todas</label>
        <?php foreach ($categories as $c): ?>
          <label class="check"><input type="radio" name="categoria" value="<?= e($c['slug']) ?>" <?= $filters['categoria'] === $c['slug'] ? 'checked' : '' ?> data-autosubmit> <?= e($c['name']) ?> <span class="muted">(<?= (int) $c['courses_count'] ?>)</span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset class="filter-group">
        <legend>Nível</legend>
        <label class="check"><input type="radio" name="nivel" value="" <?= $filters['nivel'] === '' ? 'checked' : '' ?> data-autosubmit> Qualquer nível</label>
        <?php foreach (Course::LEVELS as $k => $label): ?>
          <label class="check"><input type="radio" name="nivel" value="<?= $k ?>" <?= $filters['nivel'] === $k ? 'checked' : '' ?> data-autosubmit> <?= $label ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset class="filter-group">
        <legend>Carga horária</legend>
        <label class="check"><input type="radio" name="duracao" value="" <?= $filters['duracao'] === '' ? 'checked' : '' ?> data-autosubmit> Qualquer duração</label>
        <?php foreach (Course::DURATIONS as $k => $label): ?>
          <label class="check"><input type="radio" name="duracao" value="<?= $k ?>" <?= $filters['duracao'] === $k ? 'checked' : '' ?> data-autosubmit> <?= $label ?></label>
        <?php endforeach; ?>
      </fieldset>
      <button class="btn btn-block" type="submit">Aplicar filtros</button>
      <?php if ($hasFilters): ?><p style="text-align:center;margin:.75rem 0 0"><a href="<?= url('cursos') ?>">Limpar filtros</a></p><?php endif; ?>
    </aside>

    <section aria-live="polite">
      <div class="catalog-bar">
        <p class="muted" style="margin:0"><?= plural($result['total'], 'curso encontrado', 'cursos encontrados') ?><?= $filters['q'] !== '' ? ' para “' . e($filters['q']) . '”' : '' ?></p>
        <div class="cluster">
          <button class="btn btn-secondary btn-sm filter-toggle" type="button" data-toggle="filters" aria-controls="filters" aria-expanded="false"><?= icon('filter') ?> Filtros</button>
          <label for="ordem" class="sr-only">Ordenar por</label>
          <select class="select" id="ordem" name="ordem" data-autosubmit>
            <?php foreach (Course::SORTS as $k => $label): ?>
              <option value="<?= $k ?>" <?= $filters['ordem'] === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if (!$result['items']): ?>
        <div class="empty">
          <?= icon('search') ?>
          <h3>Nenhum curso com esses filtros</h3>
          <p>Tente outra palavra ou remova algum filtro para ver mais opções.</p>
          <a class="btn btn-secondary" href="<?= url('cursos') ?>">Ver todos os cursos</a>
        </div>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($result['items'] as $course): ?>
            <?php partial('partials/course-card', ['course' => $course]) ?>
          <?php endforeach; ?>
        </div>
        <?php partial('partials/pagination', ['p' => $result, 'path' => 'cursos']) ?>
      <?php endif; ?>
    </section>
  </form>
</div>
