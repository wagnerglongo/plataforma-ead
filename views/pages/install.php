<div class="container container-narrow" style="padding:3rem var(--gutter) 4rem">
  <h1 style="font-size:var(--step-3)">Instalação</h1>
  <?php if (!empty($done)): ?>
    <div class="alert alert-success"><?= icon('check-circle') ?><div><p><strong>Pronto.</strong> O instalador foi fechado. Se você usou INSTALL_TOKEN no .env, apague essa linha.</p><ul><?php foreach ($done as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul></div></div>
    <a class="btn" href="<?= url('entrar') ?>">Ir para o login</a>
  <?php else: ?>
    <?php if ($installed): ?><div class="alert alert-warning"><?= icon('alert') ?><p>O banco já tem usuários. A opção de demonstração apaga tudo.</p></div><?php endif; ?>
    <form class="panel" method="post" action="<?= url('instalar') ?>">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <fieldset>
        <legend>Instalação limpa</legend>
        <div class="field"><label for="name">Nome do administrador</label><input class="input" id="name" name="name"></div>
        <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email"></div>
        <div class="field"><label for="password">Senha (mínimo 10, letras e números)</label><input class="input" type="password" id="password" name="password"></div>
        <button class="btn" name="mode" value="clean" type="submit">Instalar</button>
      </fieldset>
      <hr>
      <p class="muted">Ou recrie tudo com os dados de demonstração:</p>
      <button class="btn btn-danger" name="mode" value="demo" type="submit" formnovalidate data-confirm="Isso apaga todos os dados. Continuar?">Instalar com demonstração</button>
    </form>
  <?php endif; ?>
</div>
