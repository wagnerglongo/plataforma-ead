<?php /** @var array $user @var array $requests @var bool $pendingDeletion */ ?>
<div style="padding:2rem 0 4rem;max-width:820px">
  <section class="panel">
    <h2>Baixar uma cópia dos seus dados</h2>
    <p class="muted">Um arquivo JSON com cadastro, consentimentos, matrículas, progresso, notas, certificados e registros de acesso. É o seu direito de acesso e portabilidade.</p>
    <form method="post" action="<?= url('aluno/privacidade/exportar') ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= icon('download') ?> Baixar meus dados</button></form>
  </section>

  <section class="panel">
    <h2>Comunicações</h2>
    <form method="post" action="<?= url('aluno/privacidade/consentimentos') ?>">
      <?= csrf_field() ?>
      <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="marketing" value="1" <?= $user['marketing_opt_in'] ? 'checked' : '' ?>> <span>Quero receber e-mails com novidades e novos cursos.</span></label>
      <p class="muted" style="font-size:var(--step--1)">E-mails sobre a sua conta (redefinição de senha, certificados) continuam sendo enviados, porque fazem parte do serviço.</p>
      <button class="btn btn-secondary" type="submit">Salvar preferência</button>
    </form>
    <p style="margin:1.25rem 0 0"><button type="button" class="link-btn" data-open-cookies>Rever preferências de cookies</button></p>
  </section>

  <section class="panel panel-danger">
    <h2>Excluir minha conta</h2>
    <?php if ($pendingDeletion): ?>
      <div class="alert alert-info"><?= icon('info') ?><p>Seu pedido de exclusão está em análise. Você recebe a confirmação por e-mail quando for concluído.</p></div>
    <?php else: ?>
      <p class="muted">Seus dados pessoais são anonimizados e o acesso é encerrado. Certificados já emitidos continuam válidos para quem precisar conferi-los. Isso não pode ser desfeito.</p>
      <form method="post" action="<?= url('aluno/privacidade/excluir') ?>" data-confirm="Confirma o pedido de exclusão da sua conta?" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="reason">Quer contar o motivo? <span class="muted" style="font-weight:400">(opcional)</span></label>
          <textarea class="textarea" id="reason" name="reason" rows="2" maxlength="500" style="min-height:70px"></textarea>
        </div>
        <div class="field">
          <label for="password">Confirme com sua senha</label>
          <input class="input" type="password" id="password" name="password" autocomplete="current-password" style="max-width:320px"<?= invalid_attr('password') ?>>
          <?= field_error('password') ?>
        </div>
        <button class="btn btn-danger" type="submit">Pedir exclusão da conta</button>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($requests): ?>
  <section class="panel">
    <h2>Histórico de pedidos</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Pedido</th><th>Data</th><th>Situação</th></tr></thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= $r['type'] === 'export' ? 'Cópia dos dados' : 'Exclusão da conta' ?></td>
              <td><?= date_br($r['created_at'], true) ?></td>
              <td><?= ['pending' => '<span class="badge badge-warn">Em análise</span>', 'done' => '<span class="badge badge-ok">Atendido</span>', 'rejected' => '<span class="badge badge-danger">Recusado</span>'][$r['status']] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <p class="muted" style="margin-top:1.5rem">Dúvidas sobre seus dados? Fale com <?= e(setting('dpo_name', 'o encarregado')) ?><?= setting('dpo_email') ? ' em <a href="mailto:' . e(setting('dpo_email')) . '">' . e(setting('dpo_email')) . '</a>' : '' ?>. Veja também a <a href="<?= url('privacidade') ?>">política de privacidade</a>.</p>
</div>
