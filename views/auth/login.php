<div class="auth">
  <?php partial('auth/_aside') ?>
  <div class="auth-main">
    <div class="auth-box">
      <h1>Entrar</h1>
      <p class="sub">Acesse seus cursos, avaliações e certificados.</p>
      <?php partial('partials/flash') ?>

      <form method="post" action="<?= url('entrar') ?>" id="login-form" novalidate>
        <?= csrf_field() ?>
        <?php if (config('app.demo')): ?>
          <div class="demo-logins">
            <p><strong>Demonstração:</strong> entre com um clique ou use a senha <strong>demo1234</strong>.</p>
            <div class="cluster">
              <button type="button" class="btn btn-sm" data-demo-login="aluno@demo.com"><?= icon('graduation') ?> Como aluno</button>
              <button type="button" class="btn btn-secondary btn-sm" data-demo-login="admin@demo.com"><?= icon('settings') ?> Como administrador</button>
              <button type="button" class="btn btn-ghost btn-sm" data-demo-login="coordenacao@demo.com">Como coordenação</button>
            </div>
          </div>
        <?php endif; ?>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required autofocus<?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <div class="split"><label for="password" style="margin:0">Senha</label><a href="<?= url('recuperar-senha') ?>" style="font-size:var(--step--1)">Esqueci a senha</a></div>
          <input class="input" type="password" id="password" name="password" autocomplete="current-password" required style="margin-top:.35rem">
        </div>
        <button class="btn btn-lg btn-block" type="submit" data-loading="Entrando…">Entrar</button>
      </form>
      <p class="auth-foot">Ainda não tem conta? <a href="<?= url('cadastro') ?>">Crie grátis</a></p>
    </div>
  </div>
</div>
