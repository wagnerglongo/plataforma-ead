<?php /** @var array $users */ ?>
<div class="admin-head">
  <div><h1>Equipe</h1><p>Quem administra a plataforma. O que cada um pode fazer depende do perfil.</p></div>
  <div class="cluster"><a class="btn btn-secondary" href="<?= url('admin/perfis') ?>"><?= icon('key') ?> Perfis e permissões</a><a class="btn" href="<?= url('admin/equipe/novo') ?>"><?= icon('plus') ?> Adicionar pessoa</a></div>
</div>
<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Nome</th><th>Perfil</th><th>Último acesso</th><th>Situação</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><div class="person"><span class="avatar avatar-sm"><?= e(initials($u['name'])) ?></span><span><a class="row-link" href="<?= url('admin/equipe/' . $u['id']) ?>"><?= e($u['name']) ?></a><span class="sub"><?= e($u['email']) ?></span></span></div></td>
            <td><span class="badge<?= $u['role_slug'] === 'admin' ? ' badge-marker' : '' ?>"><?= e($u['role_name']) ?></span></td>
            <td class="muted"><?= time_ago($u['last_login_at']) ?></td>
            <td><?= $u['status'] === 'active' ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-muted">Inativo</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
