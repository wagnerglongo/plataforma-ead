<div class="auth">
  <?php partial('auth/_aside') ?>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Criar nova senha</h1>
      <p class="sub">Escolha uma senha com pelo menos 8 caracteres, com letras e números.</p>
      <?php partial('partials/flash') ?>
      <form method="post" action="<?= url('redefinir-senha') ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label for="password">Nova senha</label>
          <input class="input" type="password" id="password" name="password" autocomplete="new-password" required<?= invalid_attr('password') ?>>
          <?= field_error('password') ?>
        </div>
        <div class="field">
          <label for="password_confirmation">Repita a nova senha</label>
          <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <button class="btn btn-lg btn-block" type="submit">Salvar nova senha</button>
      </form>
    </div>
  </div>
</div>
