<div class="container container-narrow verify">
  <nav class="breadcrumb" aria-label="Você está em"><a href="<?= url('/') ?>">Início</a> <?= icon('chevron-right') ?> <span>Validar certificado</span></nav>
  <h1 style="font-size:var(--step-3)">Validar certificado</h1>
  <p class="muted" style="max-width:58ch">Digite o código impresso no certificado, ou aponte a câmera do celular para o QR Code dele. Mostramos os dados registrados no momento da emissão.</p>
  <form class="panel" action="<?= url('validar') ?>" method="get" style="margin-top:1.5rem">
    <div class="field">
      <label for="codigo">Código de validação</label>
      <div class="input-group">
        <input class="input code" id="codigo" name="codigo" placeholder="XXXX-XXXX-XXXX" data-mask="cert" autocomplete="off" required autofocus style="font-size:1.25rem">
        <button class="btn" type="submit"><?= icon('shield-check') ?> Validar</button>
      </div>
      <p class="hint">O código fica no canto inferior direito do certificado.</p>
    </div>
  </form>
  <div class="cluster muted" style="margin-top:1.5rem;font-size:var(--step--1)">
    <span><?= icon('shield') ?> Cada certificado tem uma assinatura digital: qualquer alteração nos dados é detectada.</span>
  </div>
</div>
