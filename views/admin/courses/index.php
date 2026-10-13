<?php /** @var array $courses @var string $status @var string $q */ ?>
<div class="admin-head">
  <div><h1>Cursos</h1><p><?= plural(count($courses), 'curso', 'cursos') ?><?= $status ? ' com o filtro aplicado' : '' ?></p></div>
  <?php if (can('courses.manage')): ?><a class="btn" href="<?= url('admin/cursos/novo') ?>"><?= icon('plus') ?> Novo curso</a><?php endif; ?>
</div>
<section class="card">
  <form class="toolbar" method="get">
    <div class="field grow"><label class="sr-only" for="q">Buscar</label><input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar pelo título"></div>
    <div class="field"><label class="sr-only" for="status">Situação</label>
      <select class="select" id="status" name="status" data-autosubmit>
        <option value="">Todas as situações</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publicados</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Rascunhos</option>
      </select>
    </div>
    <button class="btn btn-secondary" type="submit"><?= icon('search') ?> Buscar</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Curso</th><th>Situação</th><th class="num">Conteúdo</th><th class="num">Alunos</th><th class="num">Conclusão</th><th class="actions"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td>
              <div class="person">
                <span style="width:12px;height:36px;border-radius:3px;background:<?= e($c['cover_color']) ?>;flex:none"></span>
                <span><a class="row-link" href="<?= url('admin/cursos/' . $c['id']) ?>"><?= e($c['title']) ?></a><span class="sub"><?= e($c['category_name'] ?? 'Sem categoria') ?> · <?= (int) $c['workload_hours'] ?> h · <?= e(level_label($c['level'])) ?></span></span>
              </div>
            </td>
            <td><?= $c['status'] === 'published' ? '<span class="badge badge-ok">Publicado</span>' : '<span class="badge badge-warn">Rascunho</span>' ?><?= $c['featured'] ? ' <span class="badge badge-marker">Destaque</span>' : '' ?></td>
            <td class="num"><?= (int) $c['modules_count'] ?> mód. · <?= (int) $c['lessons_count'] ?> aulas</td>
            <td class="num"><?= (int) $c['students'] ?></td>
            <td class="num"><?= $c['students'] ? round($c['completed'] / $c['students'] * 100) . '%' : '—' ?></td>
            <td class="actions">
              <a class="btn btn-secondary btn-sm" href="<?= url('admin/cursos/' . $c['id'] . '/conteudo') ?>"><?= icon('layers') ?> Conteúdo</a>
              <a class="btn btn-ghost btn-sm" href="<?= url('curso/' . $c['slug']) ?>" target="_blank" title="Ver no site"><?= icon('eye') ?><span class="sr-only">Ver no site</span></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$courses): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:2rem">Nenhum curso encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
