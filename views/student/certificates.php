<?php /** @var array $certificates @var ?array $new */ ?>
<div style="padding-top:2rem">
<?php if ($new): ?>
  <section class="celebrate" aria-labelledby="celebrate-title" data-track-event="course_complete" data-track-params="<?= e(json_encode(['course' => $new['course_title']])) ?>">
    <?= icon('award', 'icon icon-lg') ?>
    <h2 id="celebrate-title">Curso concluído. Parabéns!</h2>
    <p>Seu certificado de <strong><?= e($new['course_title']) ?></strong> (<?= (int) $new['workload_hours'] ?> horas) foi emitido agora.</p>
    <div class="cluster" style="justify-content:center;margin-top:1rem">
      <a class="btn btn-lg" href="<?= url('aluno/certificados/' . $new['code'] . '/pdf') ?>"><?= icon('download') ?> Baixar certificado em PDF</a>
      <a class="btn btn-lg btn-secondary" href="<?= url('certificado/' . $new['code']) ?>" target="_blank"><?= icon('shield-check') ?> Ver página de validação</a>
    </div>
  </section>
<?php endif; ?>

<?php if (!$certificates): ?>
  <div class="empty" style="margin-bottom:3rem">
    <?= icon('award') ?>
    <h3>Seu primeiro certificado vai aparecer aqui</h3>
    <p>Conclua todas as aulas de um curso e seja aprovado nas avaliações. O certificado é emitido automaticamente.</p>
    <a class="btn" href="<?= url('aluno') ?>">Ir para meus cursos</a>
  </div>
<?php else: ?>
  <div class="cert-grid">
    <?php foreach ($certificates as $c): $valid = $c['status'] === 'valid'; ?>
      <article class="cert-card">
        <?php if ($valid): ?>
          <img src="<?= url('certificado/' . $c['code'] . '/imagem') ?>" alt="Certificado de <?= e($c['course_title']) ?>" loading="lazy" width="1400" height="990">
        <?php endif; ?>
        <div class="body">
          <h3><?= e($c['course_title']) ?></h3>
          <p class="muted" style="margin:0"><?= (int) $c['workload_hours'] ?> horas · emitido em <?= date_br($c['issued_at']) ?></p>
          <p style="margin:0">Código <span class="code"><?= e($c['code']) ?></span> <?= $valid ? '<span class="badge badge-ok">Válido</span>' : '<span class="badge badge-danger">Revogado</span>' ?></p>
          <?php if ($valid): ?>
          <div class="actions">
            <a class="btn btn-sm" href="<?= url('aluno/certificados/' . $c['code'] . '/pdf') ?>"><?= icon('download') ?> PDF</a>
            <button class="btn btn-secondary btn-sm" type="button" data-copy="<?= e(absolute_url('certificado/' . $c['code'])) ?>"><?= icon('copy') ?> Copiar link de validação</button>
            <a class="btn btn-ghost btn-sm" href="https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name=<?= rawurlencode($c['course_title']) ?>&organizationName=<?= rawurlencode(site_name()) ?>&issueYear=<?= date('Y', strtotime($c['issued_at'])) ?>&issueMonth=<?= date('n', strtotime($c['issued_at'])) ?>&certUrl=<?= rawurlencode(absolute_url('certificado/' . $c['code'])) ?>&certId=<?= rawurlencode($c['code']) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Adicionar ao LinkedIn</a>
          </div>
          <?php else: ?>
            <p class="muted" style="margin:.5rem 0 0;font-size:var(--step--1)">Motivo: <?= e($c['revoke_reason'] ?: 'não informado') ?></p>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
