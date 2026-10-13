<?php
/** @var array $course @var array $quiz @var array $questions @var array $modules @var array $usedModules @var bool $hasFinal @var int $attempts */
$isNew = empty($quiz['id']);
$v = fn (string $k, $d = '') => old($k, $quiz[$k] ?? $d);
?>
<div class="admin-head">
  <div>
    <nav class="breadcrumb"><a href="<?= url('admin/cursos') ?>">Cursos</a> <?= icon('chevron-right') ?> <a href="<?= url('admin/cursos/' . $course['id'] . '/conteudo') ?>"><?= e($course['title']) ?></a> <?= icon('chevron-right') ?> <span>Avaliação</span></nav>
    <h1><?= $isNew ? 'Nova avaliação' : e($quiz['title']) ?></h1>
    <?php if ($attempts): ?><p><?= plural($attempts, 'tentativa registrada', 'tentativas registradas') ?>. Alterar o gabarito não muda notas já dadas.</p><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= url($isNew ? 'admin/cursos/' . $course['id'] . '/avaliacoes' : 'admin/avaliacoes/' . $quiz['id']) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="cols">
    <div>
      <?= field_error('questions') ? '<div class="alert alert-error">' . icon('alert') . '<p>' . e(error_for('questions')) . '</p></div>' : '' ?>
      <div id="questions">
        <?php foreach ($questions as $qi => $q):
            $qkey = $q['key'] ?? ($q['id'] ? 'e' . $q['id'] : 'n' . $qi); ?>
          <div class="q-editor" data-key="<?= $qkey ?>">
            <div class="split" style="margin-bottom:.5rem"><strong class="q-num">Questão <?= $qi + 1 ?></strong><button type="button" class="btn btn-ghost btn-sm" data-remove-question><?= icon('trash') ?> Remover</button></div>
            <label class="sr-only" for="st-<?= $qkey ?>">Enunciado</label>
            <textarea class="textarea" id="st-<?= $qkey ?>" name="questions[<?= $qkey ?>][statement]" rows="2" style="min-height:70px" placeholder="Enunciado da questão"><?= e($q['statement']) ?></textarea>
            <p class="hint" style="margin:.6rem 0 0">Alternativas: marque a correta.</p>
            <div class="opts">
              <?php foreach ($q['options'] as $oi => $o): $okey = $o['key'] ?? ($o['id'] ? 'e' . $o['id'] : 'n' . $qi . '_' . $oi); ?>
                <div class="opt">
                  <input type="radio" name="questions[<?= $qkey ?>][correct]" value="<?= $okey ?>" <?= $o['is_correct'] ? 'checked' : '' ?> aria-label="Alternativa correta">
                  <input class="input" name="questions[<?= $qkey ?>][options][<?= $okey ?>]" value="<?= e($o['text']) ?>" placeholder="Texto da alternativa">
                  <button type="button" class="btn btn-ghost btn-sm" data-remove-option aria-label="Remover alternativa">✕</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-add-option style="margin-top:.5rem"><?= icon('plus') ?> Alternativa</button>
            <div class="field" style="margin:.75rem 0 0">
              <label for="ex-<?= $qkey ?>" style="font-weight:400;font-size:var(--step--1)">Explicação mostrada após a correção (opcional)</label>
              <input class="input" id="ex-<?= $qkey ?>" name="questions[<?= $qkey ?>][explanation]" value="<?= e($q['explanation'] ?? '') ?>">
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary" id="add-question"><?= icon('plus') ?> Adicionar questão</button>
    </div>

    <section class="card">
      <header class="card-head"><h2>Configuração</h2></header>
      <div class="card-body">
        <div class="field">
          <label for="title">Título</label>
          <input class="input" id="title" name="title" value="<?= e($v('title')) ?>" required<?= invalid_attr('title') ?>>
          <?= field_error('title') ?>
        </div>
        <div class="field">
          <label for="module_id">Posição no curso</label>
          <select class="select" id="module_id" name="module_id"<?= invalid_attr('module_id') ?>>
            <option value="" <?= !$v('module_id') ? 'selected' : '' ?> <?= $hasFinal ? 'disabled' : '' ?>>Avaliação final (libera o certificado)<?= $hasFinal ? ' · já existe' : '' ?></option>
            <?php foreach ($modules as $m): $used = in_array((int) $m['id'], $usedModules, true); ?>
              <option value="<?= $m['id'] ?>" <?= (string) $v('module_id') === (string) $m['id'] ? 'selected' : '' ?> <?= $used ? 'disabled' : '' ?>>Fim do módulo: <?= e($m['title']) ?><?= $used ? ' · já tem teste' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error('module_id') ?>
        </div>
        <div class="field">
          <label for="description">Instruções</label>
          <textarea class="textarea" id="description" name="description" rows="2" style="min-height:70px"><?= e($v('description')) ?></textarea>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="pass_score">Nota mínima (%)</label>
            <input class="input" type="number" id="pass_score" name="pass_score" min="1" max="100" value="<?= e($v('pass_score', 70)) ?>">
          </div>
          <div class="field">
            <label for="max_attempts">Tentativas</label>
            <input class="input" type="number" id="max_attempts" name="max_attempts" min="1" value="<?= e($v('max_attempts')) ?>" placeholder="Ilimitadas">
          </div>
        </div>
        <button class="btn btn-lg btn-block" type="submit"><?= $isNew ? 'Criar avaliação' : 'Salvar avaliação' ?></button>
      </div>
    </section>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" action="<?= url('admin/avaliacoes/' . $quiz['id'] . '/excluir') ?>" data-confirm="Excluir esta avaliação e as tentativas dos alunos?" style="margin-top:1rem;text-align:right"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Excluir avaliação</button></form>
<?php endif; ?>

<template id="question-template">
  <div class="q-editor" data-key="__Q__">
    <div class="split" style="margin-bottom:.5rem"><strong class="q-num">Questão</strong><button type="button" class="btn btn-ghost btn-sm" data-remove-question><?= icon('trash') ?> Remover</button></div>
    <textarea class="textarea" name="questions[__Q__][statement]" rows="2" style="min-height:70px" placeholder="Enunciado da questão" aria-label="Enunciado"></textarea>
    <p class="hint" style="margin:.6rem 0 0">Alternativas: marque a correta.</p>
    <div class="opts">
      <?php foreach (['a', 'b', 'c'] as $i => $k): ?>
        <div class="opt"><input type="radio" name="questions[__Q__][correct]" value="n<?= $k ?>" <?= $i === 0 ? 'checked' : '' ?> aria-label="Alternativa correta"><input class="input" name="questions[__Q__][options][n<?= $k ?>]" placeholder="Texto da alternativa"><button type="button" class="btn btn-ghost btn-sm" data-remove-option aria-label="Remover alternativa">✕</button></div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" data-add-option style="margin-top:.5rem"><?= icon('plus') ?> Alternativa</button>
    <div class="field" style="margin:.75rem 0 0"><input class="input" name="questions[__Q__][explanation]" placeholder="Explicação após a correção (opcional)" aria-label="Explicação"></div>
  </div>
</template>
