<?php /** @var array $user */ ?>
<div class="profile-layout">
  <aside class="panel" style="text-align:center">
    <span class="avatar avatar-lg" style="margin:0 auto 1rem">
      <?php if ($user['avatar']): ?><img src="<?= e(upload_url($user['avatar'])) ?>" alt=""><?php else: ?><?= e(initials($user['name'])) ?><?php endif; ?>
    </span>
    <strong><?= e($user['name']) ?></strong>
    <p class="muted" style="margin:.2rem 0 1rem;font-size:var(--step--1)">Aluno desde <?= date_br($user['created_at']) ?></p>
    <form method="post" action="<?= url('aluno/perfil/foto') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="btn btn-secondary btn-sm" for="avatar" style="cursor:pointer"><?= icon('upload') ?> Trocar foto</label>
      <input class="sr-only" type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" data-autosubmit>
      <?= field_error('avatar') ?>
    </form>
  </aside>

  <div>
    <form class="panel" method="post" action="<?= url('aluno/perfil') ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Dados pessoais</h2>
      <div class="field">
        <label for="name">Nome completo</label>
        <input class="input" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" required<?= invalid_attr('name') ?>>
        <p class="hint">Certificados já emitidos mantêm o nome usado na emissão.</p>
        <?= field_error('name') ?>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" required<?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <label for="cpf">CPF</label>
          <input class="input" id="cpf" name="cpf" value="<?= e(old('cpf', $user['cpf'])) ?>" data-mask="cpf" inputmode="numeric"<?= invalid_attr('cpf') ?>>
          <?= field_error('cpf') ?>
        </div>
        <div class="field">
          <label for="phone">Telefone</label>
          <input class="input" id="phone" name="phone" value="<?= e(old('phone', $user['phone'])) ?>" data-mask="phone" inputmode="tel">
        </div>
        <div class="field">
          <label for="city">Cidade</label>
          <input class="input" id="city" name="city" value="<?= e(old('city', $user['city'])) ?>">
        </div>
      </div>
      <div class="field">
        <label for="bio">Sobre você <span class="muted" style="font-weight:400">(opcional)</span></label>
        <textarea class="textarea" id="bio" name="bio" rows="3" maxlength="500"><?= e(old('bio', $user['bio'])) ?></textarea>
      </div>
      <button class="btn" type="submit">Salvar alterações</button>
    </form>

    <form class="panel" method="post" action="<?= url('aluno/perfil/senha') ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Trocar senha</h2>
      <div class="field">
        <label for="current_password">Senha atual</label>
        <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password"<?= invalid_attr('current_password') ?>>
        <?= field_error('current_password') ?>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="password">Nova senha</label>
          <input class="input" type="password" id="password" name="password" autocomplete="new-password"<?= invalid_attr('password') ?>>
          <?= field_error('password') ?>
        </div>
        <div class="field">
          <label for="password_confirmation">Repita a nova senha</label>
          <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>
      </div>
      <button class="btn btn-secondary" type="submit">Trocar senha</button>
    </form>
  </div>
</div>
