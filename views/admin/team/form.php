<?php /** @var array $member @var array $roles */
$isNew = empty($member['id']);
$v = fn (string $k) => old($k, $member[$k] ?? '');
?>
<div class="admin-head"><div><nav class="breadcrumb"><a href="<?= url('admin/equipe') ?>">Equipe</a></nav><h1><?= $isNew ? 'Adicionar pessoa' : e($member['name']) ?></h1></div></div>
<form class="card card-body" method="post" action="<?= url($isNew ? 'admin/equipe' : 'admin/equipe/' . $member['id']) ?>" style="max-width:720px" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" value="<?= e($v('name')) ?>"<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
  <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" value="<?= e($v('email')) ?>"<?= invalid_attr('email') ?>><?= field_error('email') ?></div>
  <div class="form-row">
    <div class="field"><label for="role_id">Perfil</label>
      <select class="select" id="role_id" name="role_id"<?= invalid_attr('role_id') ?>><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= (string) $v('role_id') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
      <?= field_error('role_id') ?>
    </div>
    <div class="field"><label for="status">Situação</label>
      <select class="select" id="status" name="status"><option value="active" <?= $v('status') === 'active' ? 'selected' : '' ?>>Ativo</option><option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Inativo</option></select>
    </div>
  </div>
  <div class="field"><label for="password"><?= $isNew ? 'Senha inicial' : 'Nova senha' ?></label><input class="input" type="password" id="password" name="password" autocomplete="new-password"<?= invalid_attr('password') ?>><p class="hint"><?= $isNew ? 'Mínimo de 10 caracteres, com letras e números.' : 'Deixe em branco para manter a atual.' ?></p><?= field_error('password') ?></div>
  <div class="field"><label for="bio">Apresentação (aparece nos cursos em que é instrutor)</label><textarea class="textarea" id="bio" name="bio" rows="3"><?= e($v('bio')) ?></textarea></div>
  <div class="form-actions"><button class="btn btn-lg" type="submit">Salvar</button><a class="btn btn-ghost" href="<?= url('admin/equipe') ?>">Cancelar</a></div>
</form>
