<?php
/** @var array $quiz @var array $attempt @var array $answers @var array $questions @var ?int $remaining */
$passed = (bool) $attempt['passed'];
$correctCount = 0;
foreach ($questions as $q) {
    foreach ($q['options'] as $o) {
        if ($o['is_correct'] && (int) ($answers[$q['id']] ?? 0) === (int) $o['id']) {
            $correctCount++;
        }
    }
}
?>
<div class="quiz">
  <div class="result-score <?= $passed ? 'result-pass' : 'result-fail' ?>" role="status">
    <span class="num"><?= (int) $attempt['score'] ?>%</span>
    <div>
      <h2><?= $passed ? 'Aprovado!' : 'Ainda não foi desta vez' ?></h2>
      <p><?= $correctCount ?> de <?= count($questions) ?> questões certas. A nota mínima é <?= (int) $quiz['pass_score'] ?>%.
        <?php if (!$passed): ?><?= $remaining === null ? 'Revise os pontos abaixo e tente de novo.' : ($remaining > 0 ? 'Você ainda tem ' . plural($remaining, 'tentativa', 'tentativas') . '.' : 'Suas tentativas acabaram: fale com o suporte.') ?><?php endif; ?>
      </p>
    </div>
  </div>

  <div class="cluster" style="margin-bottom:2rem">
    <?php if ($passed && $nextUrl): ?>
      <a class="btn btn-lg btn-marker" href="<?= e($nextUrl) ?>">Seguir para: <?= e(str_limit($next['title'], 36)) ?> <?= icon('arrow-right') ?></a>
    <?php elseif (!$passed && $remaining !== 0): ?>
      <a class="btn btn-lg" href="<?= url('aluno/curso/' . $course['slug'] . '/avaliacao/' . $quiz['id']) ?>"><?= icon('refresh') ?> Tentar de novo</a>
    <?php endif; ?>
    <a class="btn btn-lg btn-secondary" href="<?= url('aluno') ?>">Voltar aos meus cursos</a>
  </div>

  <h2 style="font-size:var(--step-1)">Correção</h2>
  <?php foreach ($questions as $i => $q):
      $chosen = (int) ($answers[$q['id']] ?? 0);
      $right = false;
      foreach ($q['options'] as $o) {
          if ($o['is_correct'] && (int) $o['id'] === $chosen) {
              $right = true;
          }
      }
  ?>
    <fieldset class="question">
      <legend><span class="num"><?= $i + 1 ?>.</span> <span><?= e($q['statement']) ?></span></legend>
      <?php foreach ($q['options'] as $o):
          $isChosen = (int) $o['id'] === $chosen;
          // A alternativa correta só é revelada depois da aprovação, para a próxima tentativa continuar valendo.
          $cls = $isChosen ? ($o['is_correct'] ? 'is-right' : 'is-wrong') : (($passed && $o['is_correct']) ? 'is-right' : '');
      ?>
        <div class="option <?= $cls ?>">
          <?= $cls === 'is-right' ? icon('check-circle') : ($cls === 'is-wrong' ? icon('x') : icon('circle')) ?>
          <span><?= e($o['text']) ?><?= $isChosen ? ' <strong>(sua resposta)</strong>' : '' ?></span>
        </div>
      <?php endforeach; ?>
      <?php if ($q['explanation'] && ($right || $passed)): ?>
        <p class="feedback"><?= e($q['explanation']) ?></p>
      <?php elseif (!$right): ?>
        <p class="feedback">Resposta incorreta. Reveja a aula correspondente antes de tentar de novo.</p>
      <?php endif; ?>
    </fieldset>
  <?php endforeach; ?>
</div>
