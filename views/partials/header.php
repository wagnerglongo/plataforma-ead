<?php $user = auth(); ?>
<?php if (config('app.demo')): ?>
<div class="demo-bar">
  <div class="container">
    <span>Versão de demonstração com instituição e alunos fictícios.</span>
    <a href="<?= url('sobre-o-projeto') ?>">Conheça o projeto</a>
  </div>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="container">
    <?php partial('partials/brand') ?>
    <button class="btn btn-ghost nav-toggle" type="button" data-toggle="site-nav" aria-controls="site-nav" aria-expanded="false">
      <?= icon('menu') ?><span class="sr-only">Abrir menu</span>
    </button>
    <nav class="site-nav" id="site-nav" aria-label="Principal">
      <a class="nav-link" href="<?= url('cursos') ?>"<?= is_path('/curso') ? ' aria-current="page"' : '' ?>>Cursos</a>
      <a class="nav-link" href="<?= url('validar') ?>"<?= is_path('/validar') || is_path('/certificado') ? ' aria-current="page"' : '' ?>>Validar certificado</a>
      <?php if ($user): ?>
        <?php if (App\Core\Auth::isStaff()): ?>
          <a class="nav-link" href="<?= url('admin') ?>">Painel</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= url('aluno') ?>"><?= icon('user') ?> Minha área</a>
      <?php else: ?>
        <a class="nav-link" href="<?= url('entrar') ?>"<?= is_path('/entrar') ? ' aria-current="page"' : '' ?>>Entrar</a>
        <a class="btn" href="<?= url('cadastro') ?>">Criar conta</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
