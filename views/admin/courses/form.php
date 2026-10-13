<?php
/** @var array $course @var array $categories @var array $instructors */
$isNew = empty($course['id']);
$v = fn (string $k, $d = '') => old($k, $course[$k] ?? $d);
$canManage = can('courses.manage');
?>
<div class="admin-head">
  <div>
    <nav class="breadcrumb"><a href="<?= url('admin/cursos') ?>">Cursos</a> <?= icon('chevron-right') ?> <span><?= $isNew ? 'Novo curso' : e($course['title']) ?></span></nav>
    <h1><?= $isNew ? 'Novo curso' : 'Editar curso' ?></h1>
  </div>
  <?php if (!$isNew): ?>
    <div class="cluster">
      <a class="btn btn-secondary" href="<?= url('admin/cursos/' . $course['id'] . '/conteudo') ?>"><?= icon('layers') ?> Módulos e aulas</a>
      <?php if (can('reports.view')): ?><a class="btn btn-ghost" href="<?= url('admin/relatorios/curso/' . $course['id']) ?>"><?= icon('chart') ?> Relatório</a><?php endif; ?>
      <a class="btn btn-ghost" href="<?= url('curso/' . $course['slug']) ?>" target="_blank"><?= icon('eye') ?> Ver no site</a>
    </div>
  <?php endif; ?>
</div>

<form method="post" action="<?= url($isNew ? 'admin/cursos' : 'admin/cursos/' . $course['id']) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <fieldset <?= $canManage ? '' : 'disabled' ?>>
  <div class="cols">
    <div>
      <section class="card"><div class="card-body">
        <div class="field">
          <label for="title">Título</label>
          <input class="input" id="title" name="title" value="<?= e($v('title')) ?>" required<?= invalid_attr('title') ?>>
          <?= field_error('title') ?>
        </div>
        <div class="field">
          <label for="slug">Endereço da página</label>
          <div class="input-group"><span class="btn btn-secondary" style="pointer-events:none;border-radius:var(--radius) 0 0 var(--radius)">/curso/</span><input class="input" id="slug" name="slug" value="<?= e($v('slug')) ?>" data-slug-from="title" style="border-radius:0 var(--radius) var(--radius) 0"<?= invalid_attr('slug') ?>></div>
          <p class="hint">Gerado a partir do título. Só letras minúsculas, números e hífens.</p>
          <?= field_error('slug') ?>
        </div>
        <div class="field">
          <label for="summary">Resumo</label>
          <textarea class="textarea" id="summary" name="summary" rows="2" maxlength="300" style="min-height:80px" required<?= invalid_attr('summary') ?>><?= e($v('summary')) ?></textarea>
          <p class="hint">Aparece no catálogo e no Google. Até 300 caracteres.</p>
          <?= field_error('summary') ?>
        </div>
        <div class="field">
          <label for="description">Descrição completa</label>
          <textarea class="textarea" id="description" name="description" rows="7"><?= e($v('description')) ?></textarea>
          <p class="hint">Separe parágrafos com uma linha em branco. Use **texto** para negrito e "- " no início da linha para listas.</p>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="learning_outcomes">O que o aluno vai aprender</label>
            <textarea class="textarea" id="learning_outcomes" name="learning_outcomes" rows="5" placeholder="Um item por linha"><?= e($v('learning_outcomes')) ?></textarea>
          </div>
          <div class="field">
            <label for="requirements">Requisitos</label>
            <textarea class="textarea" id="requirements" name="requirements" rows="5" placeholder="Um item por linha"><?= e($v('requirements')) ?></textarea>
          </div>
        </div>
        <div class="field">
          <label for="target_audience">Para quem é</label>
          <textarea class="textarea" id="target_audience" name="target_audience" rows="2" style="min-height:70px"><?= e($v('target_audience')) ?></textarea>
        </div>
      </div></section>
    </div>

    <div>
      <section class="card">
        <header class="card-head"><h2>Publicação</h2></header>
        <div class="card-body">
          <div class="field">
            <label for="status">Situação</label>
            <select class="select" id="status" name="status">
              <option value="draft" <?= $v('status') === 'draft' ? 'selected' : '' ?>>Rascunho (só a equipe vê)</option>
              <option value="published" <?= $v('status') === 'published' ? 'selected' : '' ?>>Publicado no catálogo</option>
            </select>
          </div>
          <label class="check" style="margin-bottom:.75rem"><input type="checkbox" name="featured" value="1" <?= $v('featured') ? 'checked' : '' ?>> Destacar na página inicial</label>
          <label class="check"><input type="checkbox" name="certificate_enabled" value="1" <?= $v('certificate_enabled') ? 'checked' : '' ?>> Emitir certificado na conclusão</label>
        </div>
      </section>
      <section class="card">
        <header class="card-head"><h2>Detalhes</h2></header>
        <div class="card-body">
          <div class="field">
            <label for="category_id">Categoria</label>
            <select class="select" id="category_id" name="category_id">
              <option value="">Sem categoria</option>
              <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= (string) $v('category_id') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="instructor_id">Instrutor</label>
            <select class="select" id="instructor_id" name="instructor_id">
              <option value="">Não exibir</option>
              <?php foreach ($instructors as $i): ?><option value="<?= $i['id'] ?>" <?= (string) $v('instructor_id') === (string) $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <div class="field">
              <label for="workload_hours">Carga horária (h)</label>
              <input class="input" type="number" min="1" id="workload_hours" name="workload_hours" value="<?= e($v('workload_hours')) ?>"<?= invalid_attr('workload_hours') ?>>
              <?= field_error('workload_hours') ?>
            </div>
            <div class="field">
              <label for="pass_score">Nota mínima (%)</label>
              <input class="input" type="number" min="0" max="100" id="pass_score" name="pass_score" value="<?= e($v('pass_score')) ?>">
            </div>
          </div>
          <div class="field">
            <label for="level">Nível</label>
            <select class="select" id="level" name="level">
              <?php foreach (App\Models\Course::LEVELS as $k => $label): ?><option value="<?= $k ?>" <?= $v('level') === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>
      <section class="card">
        <header class="card-head"><h2>Capa</h2></header>
        <div class="card-body">
          <?php if (!$isNew): ?><div style="margin-bottom:1rem"><?php partial('partials/cover', ['course' => $course + ['category_name' => $course['category_name'] ?? null]]) ?></div><?php endif; ?>
          <div class="field">
            <label for="cover_color">Cor da capa</label>
            <input class="input" type="color" id="cover_color" name="cover_color" value="<?= e($v('cover_color', '#1F3A93')) ?>" style="height:46px;padding:4px">
            <p class="hint">Sem imagem, a capa é gerada com esta cor, a categoria e a carga horária.</p>
          </div>
          <div class="field">
            <label for="thumbnail">Imagem (opcional)</label>
            <input class="input" type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp"<?= invalid_attr('thumbnail') ?>>
            <p class="hint">JPG, PNG ou WebP até 4 MB. Recortada em 16:9 e otimizada automaticamente.</p>
            <?= field_error('thumbnail') ?>
          </div>
          <?php if (!empty($course['thumbnail'])): ?><label class="check"><input type="checkbox" name="remove_thumbnail" value="1"> Remover imagem e usar a capa gerada</label><?php endif; ?>
        </div>
      </section>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-lg" type="submit"><?= $isNew ? 'Criar curso' : 'Salvar alterações' ?></button>
    <a class="btn btn-ghost" href="<?= url('admin/cursos') ?>">Cancelar</a>
  </div>
  </fieldset>
</form>
<?php if (!$isNew && $canManage): ?>
  <form method="post" action="<?= url('admin/cursos/' . $course['id'] . '/excluir') ?>" data-confirm="Excluir este curso de vez? Módulos, aulas e avaliações também serão apagados." style="margin-top:1rem;text-align:right">
    <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Excluir curso</button>
  </form>
<?php endif; ?>
