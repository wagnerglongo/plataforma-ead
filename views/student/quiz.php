<?php
/** @var array $quiz @var array $questions @var array $attempts @var bool $passed @var ?int $remaining @var ?string $lockReason */
$old = old('answers', []);
$best = $attempts ? max(array_column($attempts, 'score')) : null;
?>
<div class="quiz">
  <div class="lesson-head">
    <p class="muted" style="margin:0 0 .25rem"><?= $quiz['module_id'] ? 'Teste do módulo' : 'Avaliação final' ?></p>
    <h1><?= e($quiz['title']) ?></h1>
    <p><?= e($quiz['description']) ?></p>
  </div>

  <dl class="facts" style="margin:0 0 1.5rem">
    <div><dt>Questões</dt><dd><?= count($questions) ?></dd></div>
    <div><dt>Nota mínima</dt><dd><?= (int) $quiz['pass_score'] ?>%</dd></div>
    <div><dt>Tentativas</dt><dd><?= $quiz['max_attempts'] === null ? 'Ilimitadas' : (int) $remaining . ' de ' . (int) $quiz['max_attempts'] . ' restantes' ?></dd></div>
    <div><dt>Sua melhor nota</dt><dd><?= $best === null ? '—' : (int) $best . '%' ?></dd></div>
  </dl>

  <?php if ($lockReason): ?>
    <div class="empty">
      <?= icon('lock') ?>
      <h3>Avaliação bloqueada por enquanto</h3>
      <p><?= e($lockReason) ?></p>
      <?php if ($prevUrl): ?><a class="btn btn-secondary" href="<?= e($prevUrl) ?>">Voltar para a aula anterior</a><?php endif; ?>
    </div>
  <?php elseif ($passed): ?>
    <div class="alert alert-success"><?= icon('check-circle') ?><p>Você já foi aprovado nesta avaliação com <?= (int) $best ?>%. Pode refazer para praticar; a melhor nota é a que vale.</p></div>
  <?php elseif ($remaining === 0): ?>
    <div class="alert alert-error"><?= icon('alert') ?><p>Você usou todas as tentativas. Fale com o suporte para liberar uma nova.</p></div>
  <?php endif; ?>

  <?php if (!$lockReason && $remaining !== 0 && $questions): ?>
  <form method="post" action="<?= url('aluno/curso/' . $course['slug'] . '/avaliacao/' . $quiz['id']) ?>">
    <?= csrf_field() ?>
    <?php foreach ($questions as $i => $q): ?>
      <fieldset class="question">
        <legend><span class="num"><?= $i + 1 ?>.</span> <span><?= e($q['statement']) ?></span></legend>
        <?php foreach ($q['options'] as $o): ?>
          <label class="option">
            <input type="radio" name="answers[<?= (int) $q['id'] ?>]" value="<?= (int) $o['id'] ?>" required <?= (string) ($old[$q['id']] ?? '') === (string) $o['id'] ? 'checked' : '' ?>>
            <span><?= e($o['text']) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="split" style="margin-top:1.5rem">
      <p class="muted" style="margin:0">Confira as respostas: <?= $quiz['max_attempts'] === null ? 'você pode refazer quantas vezes quiser.' : 'cada envio conta como uma tentativa.' ?></p>
      <button class="btn btn-lg" type="submit" data-loading="Corrigindo…" <?= $preview ? 'disabled' : '' ?>>Enviar respostas</button>
    </div>
  </form>
  <?php endif; ?>

  <nav class="lesson-nav">
    <?php if ($prevUrl): ?><a class="btn btn-ghost" href="<?= e($prevUrl) ?>"><?= icon('arrow-left') ?> Anterior</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($nextUrl && $passed): ?><a class="btn btn-marker" href="<?= e($nextUrl) ?>">Próxima: <?= e(str_limit($next['title'], 34)) ?> <?= icon('arrow-right') ?></a><?php endif; ?>
  </nav>
</div>
