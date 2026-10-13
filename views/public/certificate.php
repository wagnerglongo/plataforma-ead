<?php /** @var ?array $cert @var string $code @var bool|null $intact */ ?>
<div class="container verify">
  <nav class="breadcrumb" aria-label="Você está em"><a href="<?= url('validar') ?>">Validar certificado</a> <?= icon('chevron-right') ?> <span class="code"><?= e($code) ?></span></nav>

  <?php if (!$cert): ?>
    <div class="verify-status verify-bad" role="alert">
      <?= icon('alert') ?>
      <div>
        <h1>Nenhum certificado com este código</h1>
        <p>Confira se o código foi digitado exatamente como aparece no documento. Se o problema continuar, o certificado pode não ter sido emitido por <?= e(site_name()) ?>.</p>
      </div>
    </div>
    <a class="btn btn-secondary" href="<?= url('validar') ?>">Tentar outro código</a>

  <?php elseif ($cert['status'] === 'revoked'): ?>
    <div class="verify-status verify-bad" role="alert">
      <?= icon('ban') ?>
      <div>
        <h1>Certificado revogado</h1>
        <p>Este certificado foi emitido em <?= date_br($cert['issued_at']) ?>, mas foi cancelado pela instituição em <?= date_br($cert['revoked_at']) ?> e não tem mais validade.</p>
      </div>
    </div>

  <?php elseif (!$intact): ?>
    <div class="verify-status verify-bad" role="alert">
      <?= icon('alert') ?>
      <div>
        <h1>Não foi possível confirmar este certificado</h1>
        <p>Os dados registrados não conferem com a assinatura digital do documento. Entre em contato com a instituição.</p>
      </div>
    </div>

  <?php else: ?>
    <div class="verify-status verify-ok" role="status">
      <?= icon('shield-check') ?>
      <div>
        <h1>Certificado válido</h1>
        <p>Emitido por <?= e(site_name()) ?> em <?= date_long_br($cert['issued_at']) ?>. Os dados abaixo conferem com a assinatura digital do documento.</p>
      </div>
    </div>
    <div class="verify-grid">
      <a href="<?= url('certificado/' . $cert['code'] . '/pdf') ?>" target="_blank" rel="noopener">
        <img src="<?= url('certificado/' . $cert['code'] . '/imagem') ?>" alt="Imagem do certificado de <?= e($cert['student_name']) ?> no curso <?= e($cert['course_title']) ?>" width="1400" height="990">
      </a>
      <div class="panel">
        <dl class="data-list">
          <dt>Aluno</dt><dd><?= e($cert['student_name']) ?></dd>
          <?php if ($cert['student_doc']): ?><dt>Documento</dt><dd><?= e($cert['student_doc']) ?></dd><?php endif; ?>
          <dt>Curso</dt><dd><?= e($cert['course_title']) ?></dd>
          <dt>Carga horária</dt><dd><?= (int) $cert['workload_hours'] ?> horas</dd>
          <?php if ($cert['final_score'] !== null): ?><dt>Nota final</dt><dd><?= (int) $cert['final_score'] ?>%</dd><?php endif; ?>
          <dt>Emissão</dt><dd><?= date_br($cert['issued_at'], true) ?></dd>
          <dt>Código</dt><dd class="code"><?= e($cert['code']) ?></dd>
        </dl>
        <hr style="margin:1.25rem 0">
        <div class="cluster">
          <a class="btn" href="<?= url('certificado/' . $cert['code'] . '/pdf') ?>" target="_blank" rel="noopener"><?= icon('download') ?> Abrir PDF</a>
          <button class="btn btn-secondary" type="button" data-copy="<?= e(absolute_url('certificado/' . $cert['code'])) ?>"><?= icon('copy') ?> Copiar link</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
