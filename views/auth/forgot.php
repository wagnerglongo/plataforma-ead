<div class="auth">
  <?php partial('auth/_aside') ?>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Recuperar senha</h1>
      <p class="sub">Informe o e-mail da sua conta. Vamos enviar um link para você criar uma nova senha.</p>
      <?php partial('partials/flash') ?>
      <form method="post" action="<?= url('recuperar-senha') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required autofocus<?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <button class="btn btn-lg btn-block" type="submit" data-loading="Enviando…">Enviar link</button>
      </form>
      <p class="auth-foot"><a href="<?= url('entrar') ?>">Voltar para o login</a></p>
    </div>
  </div>
</div>
