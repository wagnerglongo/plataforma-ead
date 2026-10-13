<?php
/** @var array $student @var array $enrollments @var array $attempts @var array $available @var array $activity @var array $consents */
$v = fn (string $k) => old($k, $student[$k] ?? '');
$statusBadge = ['active' => '<span class="badge">Em andamento</span>', 'completed' => '<span class="badge badge-ok">Concluído</span>', 'cancelled' => '<span class="badge badge-muted">Cancelada</span>'];
$anon = (bool) $student['anonymized_at'];
?>
<div class="admin-head">
  <div class="person">
    <span class="avatar avatar-lg"><?php if ($student['avatar']): ?><img src="<?= e(upload_url($student['avatar'])) ?>" alt=""><?php else: ?><?= e(initials($student['name'])) ?><?php endif; ?></span>
    <div>
      <nav class="breadcrumb"><a href="<?= url('admin/alunos') ?>">Alunos</a></nav>
      <h1><?= e($student['name']) ?></h1>
      <p><?= e($student['email']) ?> · aluno desde <?= date_br($student['created_at']) ?> · último acesso <?= time_ago($student['last_login_at']) ?></p>
    </div>
  </div>
  <?= $anon ? '<span class="badge badge-muted">Dados anonimizados em ' . date_br($student['anonymized_at']) . '</span>' : '' ?>
</div>

<div class="cols">
  <div>
    <section class="card">
      <header class="card-head"><h2>Matrículas</h2></header>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Curso</th><th>Progresso</th><th>Situação</th><th>Matrícula</th><th class="actions"><span class="sr-only">Ações</span></th></tr></thead>
          <tbody>
            <?php foreach ($enrollments as $e): ?>
              <tr>
                <td><strong><?= e($e['title']) ?></strong><span class="sub"><?= $e['last_access_at'] ? 'Último acesso ' . time_ago($e['last_access_at']) : 'Ainda não acessou' ?></span></td>
                <td style="min-width:140px"><div class="progress-label" style="margin-bottom:3px"><span></span><strong><?= (int) $e['progress_percent'] ?>%</strong></div><div class="progress" style="--value:<?= (int) $e['progress_percent'] ?>%;height:6px" data-done="<?= $e['status'] === 'completed' ? 1 : 0 ?>"></div></td>
                <td><?= $statusBadge[$e['status']] ?></td>
                <td class="muted"><?= date_br($e['enrolled_at']) ?></td>
                <td class="actions">
                  <?php if ($e['certificate_code']): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url('certificado/' . $e['certificate_code']) ?>" target="_blank" title="Ver certificado"><?= icon('award') ?><span class="sr-only">Certificado</span></a>
                  <?php elseif ($e['status'] !== 'cancelled' && can('certificates.manage')): ?>
                    <form class="inline-form" method="post" action="<?= url('admin/matriculas/' . $e['id'] . '/emitir-certificado') ?>" data-confirm="Emitir o certificado manualmente, mesmo sem a conclusão registrada na plataforma?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" title="Emitir certificado manualmente"><?= icon('award') ?> Emitir</button></form>
                  <?php endif; ?>
                  <?php if (can('enrollments.manage')): ?>
                    <?php if ($e['status'] === 'cancelled'): ?>
                      <form class="inline-form" method="post" action="<?= url('admin/matriculas/' . $e['id'] . '/reativar') ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Reativar</button></form>
                    <?php else: ?>
                      <form class="inline-form" method="post" action="<?= url('admin/matriculas/' . $e['id'] . '/cancelar') ?>" data-confirm="Cancelar a matrícula? O aluno perde o acesso, mas o progresso fica guardado."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Cancelar</button></form>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$enrollments): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:1.5rem">Sem matrículas.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (can('enrollments.manage') && $available && !$anon): ?>
        <form class="toolbar" method="post" action="<?= url('admin/alunos/' . $student['id'] . '/matricular') ?>" style="border-top:1px solid var(--rule);border-bottom:0">
          <?= csrf_field() ?>
          <div class="field grow"><label class="sr-only" for="course_id">Curso</label>
            <select class="select" id="course_id" name="course_id"<?= invalid_attr('course_id') ?>><?php foreach ($available as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select>
            <?= field_error('course_id') ?>
          </div>
          <button class="btn" type="submit"><?= icon('plus') ?> Matricular</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <header class="card-head"><h2>Últimas avaliações</h2></header>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Avaliação</th><th class="num">Nota</th><th>Resultado</th><th>Data</th></tr></thead>
          <tbody>
            <?php foreach ($attempts as $a): ?>
              <tr><td><?= e($a['title']) ?><span class="sub"><?= e($a['course_title']) ?></span></td><td class="num"><?= (int) $a['score'] ?>%</td><td><?= $a['passed'] ? '<span class="badge badge-ok">Aprovado</span>' : '<span class="badge badge-warn">Reprovado</span>' ?></td><td class="muted"><?= date_br($a['finished_at'], true) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$attempts): ?><tr><td colspan="4" class="muted" style="text-align:center;padding:1.5rem">Nenhuma avaliação feita.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div>
    <form class="card" method="post" action="<?= url('admin/alunos/' . $student['id']) ?>" novalidate>
      <?= csrf_field() ?>
      <header class="card-head"><h2>Dados do aluno</h2></header>
      <fieldset class="card-body" <?= can('students.manage') && !$anon ? '' : 'disabled' ?> style="margin:0">
        <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" value="<?= e($v('name')) ?>"<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
        <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" value="<?= e($v('email')) ?>"<?= invalid_attr('email') ?>><?= field_error('email') ?></div>
        <div class="form-row">
          <div class="field"><label for="cpf">CPF</label><input class="input" id="cpf" name="cpf" value="<?= e($v('cpf')) ?>" data-mask="cpf"<?= invalid_attr('cpf') ?>><?= field_error('cpf') ?></div>
          <div class="field"><label for="phone">Telefone</label><input class="input" id="phone" name="phone" value="<?= e($v('phone')) ?>" data-mask="phone"></div>
        </div>
        <div class="field"><label for="status">Situação</label>
          <select class="select" id="status" name="status"><option value="active" <?= $v('status') === 'active' ? 'selected' : '' ?>>Ativo: pode entrar</option><option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inativo: acesso bloqueado</option></select>
        </div>
        <button class="btn" type="submit">Salvar</button>
      </fieldset>
    </form>

    <section class="card">
      <header class="card-head"><h2>Consentimentos (LGPD)</h2></header>
      <ul class="feed">
        <?php foreach ($consents as $c): ?>
          <li><span><?= e(['terms' => 'Termos de uso', 'privacy' => 'Política de privacidade', 'marketing' => 'E-mails de novidades', 'cookies' => 'Cookies de medição'][$c['type']] ?? $c['type']) ?>: <strong><?= $c['granted'] ? 'aceito' : 'recusado' ?></strong> <small class="muted">v<?= e($c['version']) ?></small></span><span class="when"><?= date_br($c['created_at']) ?></span></li>
        <?php endforeach; ?>
        <?php if (!$consents): ?><li class="muted">Nenhum registro.</li><?php endif; ?>
      </ul>
    </section>

    <section class="card">
      <header class="card-head"><h2>Atividade</h2></header>
      <ul class="feed">
        <?php foreach ($activity as $a): ?><li><span><?= e(App\Core\Audit::label($a['action'])) ?></span><span class="when"><?= time_ago($a['created_at']) ?></span></li><?php endforeach; ?>
        <?php if (!$activity): ?><li class="muted">Sem atividade registrada.</li><?php endif; ?>
      </ul>
    </section>

    <?php if (can('students.manage') && !$anon): ?>
      <form method="post" action="<?= url('admin/alunos/' . $student['id'] . '/anonimizar') ?>" class="card card-body" data-confirm="Anonimizar os dados pessoais deste aluno? Isso não pode ser desfeito." style="border-color:#F4B7B0">
        <?= csrf_field() ?>
        <h2 style="font-size:1rem">Eliminar dados pessoais</h2>
        <p class="muted" style="font-size:var(--step--1)">Atende ao pedido de exclusão (LGPD art. 18, VI). Nome, e-mail, CPF e contatos são apagados; estatísticas e certificados emitidos continuam válidos.</p>
        <button class="btn btn-danger btn-sm" type="submit"><?= icon('shield') ?> Anonimizar aluno</button>
      </form>
    <?php endif; ?>
  </div>
</div>
