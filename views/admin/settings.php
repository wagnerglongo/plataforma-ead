<?php /** @var array $s */
$v = fn (string $k) => old($k, $s[$k] ?? '');
?>
<div class="admin-head"><div><h1>Configurações</h1><p>Identidade, certificado, integrações e privacidade</p></div></div>
<form method="post" action="<?= url('admin/configuracoes') ?>" novalidate>
  <?= csrf_field() ?>
  <div class="cols-even cols">
    <section class="card">
      <header class="card-head"><h2>Instituição</h2></header>
      <div class="card-body">
        <div class="field"><label for="site_name">Nome da instituição</label><input class="input" id="site_name" name="site_name" value="<?= e($v('site_name')) ?>"<?= invalid_attr('site_name') ?>><?= field_error('site_name') ?></div>
        <div class="field"><label for="site_tagline">Frase de apresentação</label><input class="input" id="site_tagline" name="site_tagline" value="<?= e($v('site_tagline')) ?>"><p class="hint">Aparece no rodapé e no Google.</p></div>
        <div class="field"><label for="contact_email">E-mail de contato</label><input class="input" type="email" id="contact_email" name="contact_email" value="<?= e($v('contact_email')) ?>"<?= invalid_attr('contact_email') ?>><?= field_error('contact_email') ?></div>
        <div class="field"><label for="whatsapp_number">WhatsApp</label><input class="input" id="whatsapp_number" name="whatsapp_number" value="<?= e($v('whatsapp_number')) ?>" placeholder="5517999990000" inputmode="numeric"><p class="hint">Com DDI e DDD, só números. Preenchido, mostra o botão flutuante de WhatsApp no site.</p></div>
      </div>
    </section>
    <section class="card">
      <header class="card-head"><h2>Certificado</h2></header>
      <div class="card-body">
        <div class="field"><label for="certificate_signer_name">Quem assina</label><input class="input" id="certificate_signer_name" name="certificate_signer_name" value="<?= e($v('certificate_signer_name')) ?>"<?= invalid_attr('certificate_signer_name') ?>><?= field_error('certificate_signer_name') ?></div>
        <div class="field"><label for="certificate_signer_role">Cargo</label><input class="input" id="certificate_signer_role" name="certificate_signer_role" value="<?= e($v('certificate_signer_role')) ?>"<?= invalid_attr('certificate_signer_role') ?>><?= field_error('certificate_signer_role') ?></div>
        <label class="check"><input type="checkbox" name="require_video_watch" value="1" <?= $v('require_video_watch') === '1' || $v('require_video_watch') === 1 ? 'checked' : '' ?>> <span>Exigir que o aluno assista pelo menos 85% de cada vídeo (YouTube, Vimeo e arquivo enviado) para concluir a aula</span></label>
      </div>
    </section>
    <section class="card">
      <header class="card-head"><div><h2>Google Analytics e Tag Manager</h2><p>Carregados só depois do aceite de cookies (Consent Mode v2)</p></div></header>
      <div class="card-body">
        <div class="field"><label for="gtm_id">ID do Google Tag Manager</label><input class="input" id="gtm_id" name="gtm_id" value="<?= e($v('gtm_id')) ?>" placeholder="GTM-XXXXXXX"<?= invalid_attr('gtm_id') ?>><?= field_error('gtm_id') ?><p class="hint">Pelo GTM você liga Google Ads, Meta Pixel e outras ferramentas sem mexer no código. Eventos enviados: sign_up, enroll e course_complete.</p></div>
        <div class="field"><label for="ga_id">ID do Google Analytics 4 (sem GTM)</label><input class="input" id="ga_id" name="ga_id" value="<?= e($v('ga_id')) ?>" placeholder="G-XXXXXXXXXX"<?= invalid_attr('ga_id') ?>><?= field_error('ga_id') ?></div>
      </div>
    </section>
    <section class="card">
      <header class="card-head"><h2>Privacidade (LGPD)</h2></header>
      <div class="card-body">
        <div class="form-row">
          <div class="field"><label for="dpo_name">Encarregado de dados (DPO)</label><input class="input" id="dpo_name" name="dpo_name" value="<?= e($v('dpo_name')) ?>"></div>
          <div class="field"><label for="dpo_email">E-mail do encarregado</label><input class="input" type="email" id="dpo_email" name="dpo_email" value="<?= e($v('dpo_email')) ?>"<?= invalid_attr('dpo_email') ?>><?= field_error('dpo_email') ?></div>
        </div>
        <p class="hint" style="margin-top:-.5rem">Aparecem na política de privacidade e recebem os pedidos de exclusão.</p>
        <label class="check"><input type="checkbox" name="cookie_banner" value="1" <?= $v('cookie_banner') === '1' || $v('cookie_banner') === 1 ? 'checked' : '' ?>> Mostrar o aviso de cookies para novos visitantes</label>
      </div>
    </section>
  </div>
  <div class="form-actions"><button class="btn btn-lg" type="submit">Salvar configurações</button></div>
</form>
