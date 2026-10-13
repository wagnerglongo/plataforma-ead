<?php /** @var array $requests @var array $consentStats */
$types = ['terms' => 'Termos de uso', 'privacy' => 'Política de privacidade', 'marketing' => 'E-mails de novidades', 'cookies' => 'Cookies de medição'];
?>
<div class="admin-head"><div><h1>Pedidos LGPD</h1><p>Pedidos de titulares e registro de consentimentos. Prazo legal de resposta: 15 dias.</p></div></div>
<div class="cols">
  <section class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Titular</th><th>Pedido</th><th>Recebido</th><th>Situação</th><th class="actions"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
          <?php foreach ($requests as $r):
              $days = (int) floor((time() - strtotime($r['created_at'])) / 86400); ?>
            <tr>
              <td><?php if ($r['user_id']): ?><a class="row-link" href="<?= url('admin/alunos/' . $r['user_id']) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['requester']) ?><?php endif; ?><span class="sub"><?= e($r['requester']) ?></span></td>
              <td><?= $r['type'] === 'export' ? 'Cópia dos dados' : 'Exclusão' ?><?php if ($r['notes']): ?><span class="sub"><?= e(str_limit($r['notes'], 60)) ?></span><?php endif; ?></td>
              <td class="muted"><?= date_br($r['created_at']) ?><?php if ($r['status'] === 'pending'): ?><span class="sub" style="color:<?= $days >= 10 ? 'var(--danger)' : 'inherit' ?>">há <?= plural($days, 'dia', 'dias') ?> · prazo <?= date_br(date('Y-m-d', strtotime($r['created_at'] . ' +15 days'))) ?></span><?php endif; ?></td>
              <td><?= ['pending' => '<span class="badge badge-warn">Pendente</span>', 'done' => '<span class="badge badge-ok">Atendido</span>', 'rejected' => '<span class="badge badge-danger">Recusado</span>'][$r['status']] ?></td>
              <td class="actions">
                <?php if ($r['status'] === 'pending'): ?>
                  <?php if ($r['type'] === 'deletion'): ?>
                    <form class="inline-form" method="post" action="<?= url('admin/lgpd/' . $r['id']) ?>" data-confirm="Anonimizar os dados deste titular agora?"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn btn-sm" type="submit">Anonimizar e concluir</button></form>
                  <?php else: ?>
                    <form class="inline-form" method="post" action="<?= url('admin/lgpd/' . $r['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="done"><button class="btn btn-sm" type="submit">Marcar atendido</button></form>
                  <?php endif; ?>
                  <form class="inline-form" method="post" action="<?= url('admin/lgpd/' . $r['id']) ?>" data-confirm="Recusar este pedido? Registre o motivo legal ao titular."><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><button class="btn btn-ghost btn-sm" type="submit">Recusar</button></form>
                <?php else: ?><span class="muted" style="font-size:var(--step--1)"><?= date_br($r['processed_at']) ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$requests): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:2rem">Nenhum pedido recebido.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <header class="card-head"><div><h2>Consentimentos registrados</h2><p>Com data, versão do texto e IP</p></div></header>
    <div class="card-body">
      <ul class="hbar-list">
        <?php foreach ($consentStats as $c): $total = max(1, $c['yes'] + $c['no']); ?>
          <li>
            <div class="top"><span><?= e($types[$c['type']] ?? $c['type']) ?></span><strong><?= round($c['yes'] / $total * 100) ?>% aceitam</strong></div>
            <div class="hbar"><i style="width:<?= round($c['yes'] / $total * 100) ?>%"></i></div>
            <small class="muted"><?= (int) $c['yes'] ?> aceites · <?= (int) $c['no'] ?> recusas</small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>
