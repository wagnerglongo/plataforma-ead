<?php /** @var array $backups */ ?>
<div class="admin-head">
  <div><h1>Backups</h1><p>Cópia completa do banco de dados, compactada. Os 14 mais recentes ficam guardados.</p></div>
  <form method="post" action="<?= url('admin/backups') ?>"><?= csrf_field() ?><button class="btn" type="submit" data-loading="Gerando…"><?= icon('database') ?> Gerar backup agora</button></form>
</div>
<div class="cols">
  <section class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Arquivo</th><th>Gerado em</th><th class="num">Tamanho</th><th class="actions"><span class="sr-only">Baixar</span></th></tr></thead>
        <tbody>
          <?php foreach ($backups as $b): ?>
            <tr>
              <td><?= icon('database') ?> <?= e($b['name']) ?></td>
              <td class="muted"><?= date_br($b['date'], true) ?></td>
              <td class="num"><?= bytes_fmt($b['size']) ?></td>
              <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= url('admin/backups/' . $b['name']) ?>"><?= icon('download') ?> Baixar</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$backups): ?><tr><td colspan="4" class="muted" style="text-align:center;padding:2rem">Nenhum backup gerado ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card card-body">
    <h2 style="font-size:1.0625rem">Backup automático</h2>
    <p class="muted">Agende no painel da hospedagem (Hostinger: Avançado › Cron Jobs) para rodar todo dia:</p>
    <pre style="background:var(--paper);border:1px solid var(--rule);border-radius:var(--radius);padding:.75rem;white-space:pre-wrap;font-size:.8125rem">php <?= e(BASE_PATH) ?>/bin/ead backup</pre>
    <p class="muted" style="font-size:var(--step--1)">Para restaurar: <code>php bin/ead restore arquivo.sql.gz</code>. Vídeos e materiais enviados ficam em <code>storage/app</code> e entram no backup de arquivos da hospedagem.</p>
    <?php if (config('app.demo')): ?><p class="badge badge-marker">Na demonstração o download fica bloqueado.</p><?php endif; ?>
  </section>
</div>
