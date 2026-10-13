<?php
/** @var array $course @var array $stats @var array $funnel @var array $questions @var array $students */
$enrolled = max(1, (int) ($stats['enrolled'] ?? 0) - (int) ($stats['cancelled'] ?? 0));
$statusBadge = ['active' => '<span class="badge">Em andamento</span>', 'completed' => '<span class="badge badge-ok">Concluído</span>', 'cancelled' => '<span class="badge badge-muted">Cancelada</span>'];
$hard = array_filter($questions, fn ($q) => $q['answered'] > 0);
usort($hard, fn ($a, $b) => ($a['right'] / $a['answered']) <=> ($b['right'] / $b['answered']));
?>
<div class="admin-head">
  <div>
    <nav class="breadcrumb"><a href="<?= url('admin/relatorios') ?>">Relatórios</a></nav>
    <h1><?= e($course['title']) ?></h1>
    <p><?= (int) $course['workload_hours'] ?> horas · <?= (int) $course['lessons_count'] ?> aulas</p>
  </div>
  <div class="cluster">
    <?php if (can('reports.export')): ?><a class="btn btn-secondary" href="<?= url('admin/relatorios/exportar', ['tipo' => 'matriculas', 'curso' => $course['id']]) ?>"><?= icon('download') ?> Matrículas (CSV)</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= url('admin/cursos/' . $course['id']) ?>"><?= icon('edit') ?> Editar curso</a>
  </div>
</div>

<dl class="kpis">
  <div class="kpi"><dt>Matrículas</dt><dd><?= (int) ($stats['enrolled'] ?? 0) ?></dd><div class="delta"><?= (int) ($stats['cancelled'] ?? 0) ?> canceladas</div></div>
  <div class="kpi"><dt>Concluíram</dt><dd><?= (int) ($stats['completed'] ?? 0) ?></dd><div class="delta"><?= round(($stats['completed'] ?? 0) / $enrolled * 100) ?>% das ativas</div></div>
  <div class="kpi"><dt>Progresso médio</dt><dd><?= (int) ($stats['avg_progress'] ?? 0) ?>%</dd></div>
  <div class="kpi"><dt>Nota média final</dt><dd><?= isset($stats['avg_score']) && $stats['avg_score'] !== null ? (int) $stats['avg_score'] . '%' : '—' ?></dd></div>
</dl>

<div class="cols">
  <section class="card">
    <header class="card-head"><div><h2>Conclusão aula a aula</h2><p>Mostra em que ponto os alunos param de avançar</p></div></header>
    <div class="card-body">
      <ul class="hbar-list">
        <?php foreach ($funnel as $l): $pct = round($l['completed'] / $enrolled * 100); ?>
          <li>
            <div class="top"><span><?= e($l['title']) ?> <small class="muted"><?= e($l['module_title']) ?></small></span><strong><?= $pct ?>%</strong></div>
            <div class="hbar"><i style="width:<?= $pct ?>%"></i></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <section class="card">
    <header class="card-head"><div><h2>Questões com mais erros</h2><p>Taxa de acerto nas tentativas registradas</p></div></header>
    <div class="card-body">
      <?php if (!$hard): ?>
        <p class="muted">Ainda não há respostas suficientes para analisar.</p>
      <?php else: ?>
        <ul class="hbar-list">
          <?php foreach (array_slice($hard, 0, 6) as $q): $pct = round($q['right'] / $q['answered'] * 100); ?>
            <li>
              <div class="top"><span><?= e(str_limit($q['statement'], 70)) ?></span><strong><?= $pct ?>%</strong></div>
              <div class="hbar"><i style="width:<?= $pct ?>%;background:<?= $pct < 60 ? 'var(--danger)' : 'var(--ok)' ?>"></i></div>
              <small class="muted"><?= e($q['quiz_title']) ?> · <?= plural((int) $q['answered'], 'resposta', 'respostas') ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="card" style="margin-top:1.5rem">
  <header class="card-head"><h2>Alunos do curso</h2></header>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Aluno</th><th>Progresso</th><th>Situação</th><th class="num">Nota final</th><th>Matrícula</th><th>Último acesso</th></tr></thead>
      <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><a class="row-link" href="<?= url('admin/alunos/' . $s['user_id']) ?>"><?= e($s['name']) ?></a><span class="sub"><?= e($s['email']) ?></span></td>
            <td style="min-width:140px"><div class="progress-label" style="margin-bottom:3px"><span></span><strong><?= (int) $s['progress_percent'] ?>%</strong></div><div class="progress" style="--value:<?= (int) $s['progress_percent'] ?>%;height:6px" data-done="<?= $s['status'] === 'completed' ? 1 : 0 ?>"></div></td>
            <td><?= $statusBadge[$s['status']] ?></td>
            <td class="num"><?= $s['final_score'] !== null ? (int) $s['final_score'] . '%' : '—' ?></td>
            <td class="muted"><?= date_br($s['enrolled_at']) ?></td>
            <td class="muted"><?= time_ago($s['last_access_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
