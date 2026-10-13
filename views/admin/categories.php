<?php /** @var array $categories */ ?>
<div class="admin-head"><div><h1>Categorias</h1><p>Organizam o catálogo e os filtros da página de cursos.</p></div></div>
<div class="cols">
  <section class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Nome</th><th class="num">Cursos</th><th class="actions"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td>
                <form method="post" action="<?= url('admin/categorias/' . $c['id']) ?>" class="tag-input-row">
                  <?= csrf_field() ?>
                  <label class="sr-only" for="cat-<?= $c['id'] ?>">Nome</label>
                  <input class="input" id="cat-<?= $c['id'] ?>" name="name" value="<?= e($c['name']) ?>" style="min-height:38px;max-width:300px">
                  <button class="btn btn-secondary btn-sm" type="submit">Salvar</button>
                </form>
              </td>
              <td class="num"><?= (int) $c['courses'] ?></td>
              <td class="actions">
                <form method="post" action="<?= url('admin/categorias/' . $c['id'] . '/excluir') ?>" data-confirm="Excluir a categoria “<?= e($c['name']) ?>”?" class="inline-form">
                  <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" <?= $c['courses'] ? 'disabled title="Tem cursos vinculados"' : '' ?>><?= icon('trash') ?><span class="sr-only">Excluir</span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <form class="card card-body" method="post" action="<?= url('admin/categorias') ?>">
    <?= csrf_field() ?>
    <h2 style="font-size:1.0625rem">Nova categoria</h2>
    <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" required<?= invalid_attr('name') ?>><?= field_error('name') ?></div>
    <button class="btn" type="submit"><?= icon('plus') ?> Criar categoria</button>
  </form>
</div>
