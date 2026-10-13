<?php /** @var array $roles @var array $permissions @var array $grants */
$groups = [];
foreach ($permissions as $p) {
    $groups[$p['group_name']][] = $p;
}
?>
<div class="admin-head"><div><h1>Perfis e permissões</h1><p>Cada pessoa da equipe tem um perfil. O administrador sempre tem acesso total.</p></div></div>
<div class="cols-even cols" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
  <?php foreach ($roles as $r): $fixed = $r['slug'] === 'admin'; ?>
    <form class="card" method="post" action="<?= url('admin/perfis/' . $r['id']) ?>" id="perfil-<?= $r['id'] ?>">
      <?= csrf_field() ?>
      <header class="card-head"><div><h2><?= e($r['name']) ?></h2><p><?= e($r['description'] ?? '') ?> · <?= plural((int) $r['users'], 'pessoa', 'pessoas') ?></p></div></header>
      <div class="card-body">
        <?php foreach ($groups as $group => $perms): ?>
          <fieldset style="margin-bottom:1rem">
            <legend style="font-size:var(--step--1);font-family:var(--font-body);color:var(--muted);margin-bottom:.35rem"><?= e($group) ?></legend>
            <?php foreach ($perms as $p): ?>
              <label class="check" style="padding:.2rem 0;font-size:.9375rem"><input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" <?= $fixed || isset($grants[$r['id']][$p['id']]) ? 'checked' : '' ?> <?= $fixed ? 'disabled' : '' ?>> <?= e($p['name']) ?></label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
        <?php if (!$fixed): ?><button class="btn btn-secondary btn-block" type="submit">Salvar permissões</button><?php else: ?><p class="muted" style="margin:0;font-size:var(--step--1)">Acesso total, não editável.</p><?php endif; ?>
      </div>
    </form>
  <?php endforeach; ?>
  <form class="card card-body" method="post" action="<?= url('admin/perfis') ?>" style="align-self:start">
    <?= csrf_field() ?>
    <h2 style="font-size:1.0625rem">Novo perfil</h2>
    <div class="field"><label for="role-name">Nome</label><input class="input" id="role-name" name="name" placeholder="Ex.: Instrutor" required<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label for="role-desc">Descrição</label><input class="input" id="role-desc" name="description"></div>
    <button class="btn" type="submit"><?= icon('plus') ?> Criar perfil</button>
  </form>
</div>
