<?php /** @var array $result @var array $filters @var array $courses */ ?>
<div class="admin-head">
  <div><h1>Alunos</h1><p><?= plural($result['total'], 'aluno encontrado', 'alunos encontrados') ?></p></div>
  <div class="cluster">
    <?php if (can('reports.export')): ?><a class="btn btn-secondary" href="<?= url('admin/relatorios/exportar', ['tipo' => 'alunos'] + array_filter($filters)) ?>"><?= icon('download') ?> Exportar CSV</a><?php endif; ?>
    <?php if (can('students.manage')): ?><a class="btn" href="<?= url('admin/alunos/novo') ?>"><?= icon('plus') ?> Novo aluno</a><?php endif; ?>
  </div>
</div>
<section class="card">
  <form class="toolbar" method="get">
    <div class="field grow"><label class="sr-only" for="q">Buscar</label><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Nome, e-mail ou CPF"></div>
    <div class="field"><label class="sr-only" for="curso">Curso</label>
      <select class="select" id="curso" name="curso" data-autosubmit>
        <option value="">Todos os cursos</option>
        <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $filters['curso'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="sr-only" for="status">Situação</label>
      <select class="select" id="status" name="status" data-autosubmit>
        <option value="">Qualquer situação</option>
        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativos</option>
        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativos</option>
      </select>
    </div>
    <div class="field"><label class="sr-only" for="ordem">Ordenar</label>
      <select class="select" id="ordem" name="ordem" data-autosubmit>
        <option value="recentes" <?= $filters['ordem'] === 'recentes' ? 'selected' : '' ?>>Cadastro mais recente</option>
        <option value="acesso" <?= $filters['ordem'] === 'acesso' ? 'selected' : '' ?>>Último acesso</option>
        <option value="nome" <?= $filters['ordem'] === 'nome' ? 'selected' : '' ?>>Nome (A–Z)</option>
      </select>
    </div>
    <button class="btn btn-secondary" type="submit"><?= icon('search') ?> Buscar</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Aluno</th><th>Cidade</th><th class="num">Cursos</th><th class="num">Concluídos</th><th>Progresso médio</th><th>Último acesso</th><th>Situação</th></tr></thead>
      <tbody>
        <?php foreach ($result['items'] as $s): ?>
          <tr>
            <td>
              <div class="person">
                <span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
                <span><a class="row-link" href="<?= url('admin/alunos/' . $s['id']) ?>"><?= e($s['name']) ?></a><span class="sub"><?= e($s['email']) ?></span></span>
              </div>
            </td>
            <td><?= e($s['city'] ?? '—') ?></td>
            <td class="num"><?= (int) $s['enrollments'] ?></td>
            <td class="num"><?= (int) $s['completed'] ?></td>
            <td style="min-width:130px"><?php if ($s['enrollments']): ?><div class="progress-label" style="margin-bottom:3px"><span></span><strong><?= (int) $s['avg_progress'] ?>%</strong></div><div class="progress" style="--value:<?= (int) $s['avg_progress'] ?>%;height:6px"></div><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td class="muted"><?= time_ago($s['last_login_at']) ?></td>
            <td><?= $s['anonymized_at'] ? '<span class="badge badge-muted">Anonimizado</span>' : ($s['status'] === 'active' ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-muted">Inativo</span>') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$result['items']): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">Nenhum aluno com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php partial('partials/pagination', ['p' => $result, 'path' => 'admin/alunos']) ?>
