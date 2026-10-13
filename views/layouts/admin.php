<?php
$bodyClass = 'admin-body';
$noindex = true;
partial('partials/head', get_defined_vars());
$u = auth();
$path = App\Core\Request::current()->path();
$nav = [
    'Visão geral' => [
        ['admin', 'Painel', 'grid', 'dashboard.view', '/admin$'],
        ['admin/relatorios', 'Relatórios', 'chart', 'reports.view', '/admin/relatorios'],
    ],
    'Ensino' => [
        ['admin/cursos', 'Cursos', 'book', 'courses.view', '/admin/(cursos|modulos|aulas|avaliacoes)'],
        ['admin/categorias', 'Categorias', 'layers', 'courses.manage', '/admin/categorias'],
        ['admin/alunos', 'Alunos', 'users', 'students.view', '/admin/alunos'],
        ['admin/certificados', 'Certificados', 'award', 'certificates.view', '/admin/certificados'],
    ],
    'Administração' => [
        ['admin/equipe', 'Equipe', 'user', 'users.manage', '/admin/equipe'],
        ['admin/perfis', 'Perfis e permissões', 'key', 'users.manage', '/admin/perfis'],
        ['admin/lgpd', 'Pedidos LGPD', 'shield', 'lgpd.manage', '/admin/lgpd'],
        ['admin/atividades', 'Registro de atividades', 'history', 'audit.view', '/admin/atividades'],
        ['admin/backups', 'Backups', 'database', 'backups.manage', '/admin/backups'],
        ['admin/configuracoes', 'Configurações', 'settings', 'settings.manage', '/admin/configuracoes'],
    ],
];
$pendingLgpd = can('lgpd.manage') ? (int) App\Core\Database::value("SELECT COUNT(*) FROM data_requests WHERE status = 'pending'") : 0;
?>
<div class="admin">
  <aside class="admin-side" id="admin-side" aria-label="Menu do painel">
    <?php partial('partials/brand') ?>
    <nav class="admin-nav">
      <?php foreach ($nav as $group => $items):
          $visible = array_filter($items, fn ($i) => can($i[3]));
          if (!$visible) continue; ?>
        <span class="group"><?= e($group) ?></span>
        <?php foreach ($visible as [$href, $label, $ic, , $match]): ?>
          <a href="<?= url($href) ?>"<?= preg_match('#^' . $match . '#', $path) ? ' aria-current="page"' : '' ?>>
            <?= icon($ic) ?> <span><?= e($label) ?></span>
            <?php if ($href === 'admin/lgpd' && $pendingLgpd): ?><span class="badge badge-marker" style="margin-left:auto"><?= $pendingLgpd ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('/') ?>" target="_blank"><?= icon('external') ?> Ver o site</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="btn btn-ghost admin-menu-btn" type="button" data-toggle="admin-side" aria-controls="admin-side" aria-expanded="false"><?= icon('menu') ?><span class="sr-only">Abrir menu</span></button>
      <?php if (config('app.demo')): ?><span class="badge badge-marker">Demonstração: exclusões bloqueadas</span><?php endif; ?>
      <div class="who">
        <span class="avatar avatar-sm"><?= e(initials($u['name'])) ?></span>
        <span><strong><?= e($u['name']) ?></strong><small><?= e($u['role_name']) ?></small></span>
        <form method="post" action="<?= url('sair') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" title="Sair"><?= icon('logout') ?><span class="sr-only">Sair</span></button></form>
      </div>
    </header>
    <main class="admin-content" id="conteudo">
      <?php partial('partials/flash') ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= App\Core\View::stack('scripts') ?>
</body>
</html>
