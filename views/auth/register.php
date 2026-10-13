<div class="auth">
  <?php partial('auth/_aside', ['heading' => 'Certificado com validação pública.', 'text' => 'Ao concluir, o certificado sai na hora com o nome que você informar aqui, com código e QR Code para conferência.']) ?>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Criar conta</h1>
      <p class="sub">Leva menos de um minuto. Depois é só escolher o curso.</p>
      <?php partial('partials/flash') ?>
      <form method="post" action="<?= url('cadastro') ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="course" value="<?= e(old('course', $course ?? '')) ?>">
        <div class="field">
          <label for="name">Nome completo</label>
          <input class="input" id="name" name="name" value="<?= e(old('name')) ?>" autocomplete="name" required<?= invalid_attr('name') ?>>
          <p class="hint">É assim que ele vai aparecer no certificado.</p>
          <?= field_error('name') ?>
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required<?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <label for="cpf">CPF <span class="muted" style="font-weight:400">(opcional)</span></label>
          <input class="input" id="cpf" name="cpf" value="<?= e(old('cpf')) ?>" inputmode="numeric" data-mask="cpf" placeholder="000.000.000-00"<?= invalid_attr('cpf') ?>>
          <p class="hint">Se informado, aparece mascarado no certificado (***.456.789-**).</p>
          <?= field_error('cpf') ?>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="password">Senha</label>
            <input class="input" type="password" id="password" name="password" autocomplete="new-password" required minlength="8"<?= invalid_attr('password') ?>>
            <?= field_error('password') ?>
          </div>
          <div class="field">
            <label for="password_confirmation">Repita a senha</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
          </div>
        </div>
        <p class="hint" style="margin:-.6rem 0 1rem">Pelo menos 8 caracteres, com letras e números.</p>
        <div class="field">
          <label class="check"><input type="checkbox" name="terms" value="1" required <?= old('terms') ? 'checked' : '' ?>> <span>Li e aceito os <a href="<?= url('termos') ?>" target="_blank">termos de uso</a> e a <a href="<?= url('privacidade') ?>" target="_blank">política de privacidade</a>.</span></label>
          <?= field_error('terms') ?>
        </div>
        <div class="field">
          <label class="check"><input type="checkbox" name="marketing" value="1" <?= old('marketing') ? 'checked' : '' ?>> <span>Quero receber novidades sobre cursos por e-mail. Você pode cancelar quando quiser.</span></label>
        </div>
        <button class="btn btn-lg btn-block" type="submit" data-loading="Criando conta…">Criar conta</button>
      </form>
      <p class="auth-foot">Já tem conta? <a href="<?= url('entrar') ?>">Entrar</a></p>
    </div>
  </div>
</div>
