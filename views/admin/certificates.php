<?php /** @var array $result @var array $filters @var array $courses */ ?>
<div class="admin-head">
  <div><h1>Certificados</h1><p><?= plural($result['total'], 'certificado', 'certificados') ?> · cada um tem assinatura HMAC conferida na validação pública</p></div>
  <?php if (can('reports.export')): ?><a class="btn btn-secondary" href="<?= url('admin/relatorios/exportar', ['tipo' => 'certificados']) ?>"><?= icon('download') ?> Exportar CSV</a><?php endif; ?>
</div>
<section class="card">
  <form class="toolbar" method="get">
    <div class="field grow"><label class="sr-only" for="q">Buscar</label><input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Código ou nome do aluno"></div>
    <div class="field"><label class="sr-only" for="curso">Curso</label>
      <select class="select" id="curso" name="curso" data-autosubmit><option value="">Todos os cursos</option><?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $filters['course'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field"><label class="sr-only" for="status">Situação</label>
      <select class="select" id="status" name="status" data-autosubmit><option value="">Válidos e revogados</option><option value="valid" <?= $filters['status'] === 'valid' ? 'selected' : '' ?>>Válidos</option><option value="revoked" <?= $filters['status'] === 'revoked' ? 'selected' : '' ?>>Revogados</option></select>
    </div>
    <button class="btn btn-secondary" type="submit"><?= icon('search') ?> Buscar</button>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Código</th><th>Aluno</th><th>Curso</th><th class="num">Nota</th><th>Emissão</th><th>Situação</th><th class="actions"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
        <?php foreach ($result['items'] as $c): $intact = App\Services\CertificateService::isIntact($c); ?>
          <tr>
            <td class="code"><?= e($c['code']) ?></td>
            <td><?php if ($c['user_id']): ?><a class="row-link" href="<?= url('admin/alunos/' . $c['user_id']) ?>"><?= e($c['student_name']) ?></a><?php else: ?><?= e($c['student_name']) ?><?php endif; ?></td>
            <td><?= e(str_limit($c['course_title'], 40)) ?><span class="sub"><?= (int) $c['workload_hours'] ?> horas</span></td>
            <td class="num"><?= $c['final_score'] !== null ? (int) $c['final_score'] . '%' : '—' ?></td>
            <td class="muted"><?= date_br($c['issued_at']) ?></td>
            <td>
              <?= $c['status'] === 'valid' ? '<span class="badge badge-ok">Válido</span>' : '<span class="badge badge-danger" title="' . e($c['revoke_reason']) . '">Revogado</span>' ?>
              <?= $intact ? '' : ' <span class="badge badge-danger" title="Os dados não conferem com a assinatura">Adulterado</span>' ?>
            </td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= url('certificado/' . $c['code']) ?>" target="_blank" title="Página de validação"><?= icon('shield-check') ?><span class="sr-only">Validação</span></a>
              <a class="btn btn-ghost btn-sm" href="<?= url('admin/certificados/' . $c['id'] . '/pdf') ?>" target="_blank" title="PDF"><?= icon('file') ?><span class="sr-only">PDF</span></a>
              <?php if (can('certificates.manage')): ?>
                <?php if ($c['status'] === 'valid'): ?>
                  <details class="inline-form" style="display:inline-block;position:relative">
                    <summary class="btn btn-ghost btn-sm" style="list-style:none">Revogar</summary>
                    <form method="post" action="<?= url('admin/certificados/' . $c['id'] . '/revogar') ?>" class="card card-body" style="position:absolute;right:0;z-index:5;width:300px;text-align:left;white-space:normal">
                      <?= csrf_field() ?>
                      <label class="label" for="reason-<?= $c['id'] ?>">Motivo da revogação</label>
                      <input class="input" id="reason-<?= $c['id'] ?>" name="reason" required minlength="5" placeholder="Ex.: emitido com nome incorreto">
                      <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.6rem">Confirmar revogação</button>
                    </form>
                  </details>
                <?php else: ?>
                  <form class="inline-form" method="post" action="<?= url('admin/certificados/' . $c['id'] . '/reativar') ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Reativar</button></form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$result['items']): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">Nenhum certificado encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php partial('partials/pagination', ['p' => $result, 'path' => 'admin/certificados']) ?>
