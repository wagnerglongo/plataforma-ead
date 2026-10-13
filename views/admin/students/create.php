<?php /** @var array $courses */ ?>
<div class="admin-head"><div><nav class="breadcrumb"><a href="<?= url('admin/alunos') ?>">Alunos</a></nav><h1>Novo aluno</h1><p>Use para matrículas feitas pela secretaria. O aluno recebe um e-mail para criar a própria senha.</p></div></div>
<form class="card card-body" method="post" action="<?= url('admin/alunos') ?>" style="max-width:720px" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" value="<?= e(old('name')) ?>" required<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
  <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required<?= invalid_attr('email') ?>><?= field_error('email') ?></div>
  <div class="form-row">
    <div class="field"><label for="cpf">CPF (opcional)</label><input class="input" id="cpf" name="cpf" value="<?= e(old('cpf')) ?>" data-mask="cpf"<?= invalid_attr('cpf') ?>><?= field_error('cpf') ?></div>
    <div class="field"><label for="phone">Telefone (opcional)</label><input class="input" id="phone" name="phone" value="<?= e(old('phone')) ?>" data-mask="phone"></div>
  </div>
  <div class="field"><label for="course_id">Matricular em</label>
    <select class="select" id="course_id" name="course_id"><option value="">Não matricular agora</option><?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (string) old('course_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select>
  </div>
  <label class="check"><input type="checkbox" name="send_email" value="1" checked> Enviar convite por e-mail para o aluno definir a senha</label>
  <div class="form-actions"><button class="btn btn-lg" type="submit">Cadastrar aluno</button><a class="btn btn-ghost" href="<?= url('admin/alunos') ?>">Cancelar</a></div>
</form>
