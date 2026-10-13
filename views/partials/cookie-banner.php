<?php if (setting('cookie_banner', '1') === '1'): ?>
<section class="cookie-banner" id="cookie-banner" aria-labelledby="cookie-title" hidden>
  <h2 id="cookie-title"><?= icon('cookie') ?> Cookies neste site</h2>
  <p>Usamos cookies essenciais para login e segurança. Com a sua permissão, também usamos cookies de medição para entender como o site é usado. <a href="<?= url('cookies') ?>">Saiba mais</a></p>
  <div class="cluster">
    <button type="button" class="btn btn-sm" data-consent="all">Aceitar todos</button>
    <button type="button" class="btn btn-secondary btn-sm" data-consent="essential">Só os essenciais</button>
  </div>
</section>
<?php endif; ?>
<?php if ($wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''))): ?>
<a class="whatsapp-float no-print" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Olá! Vim pelo site e tenho uma dúvida sobre os cursos.') ?>" target="_blank" rel="noopener" aria-label="Falar pelo WhatsApp">
  <?= icon('whatsapp') ?>
</a>
<?php endif; ?>
