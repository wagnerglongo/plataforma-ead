<?php /** @var array $labels @var array $enrolled @var array $completed @var array $courses @var array $totals */
$funnel = [
    ['Matriculados', $totals['enrolled']],
    ['Começaram o curso', $totals['started']],
    ['Passaram da metade', $totals['half']],
    ['Concluíram', $totals['completed']],
];
$top = max(1, $totals['enrolled']);
?>
<div class="admin-head">
  <div><h1>Relatórios</h1><p>Engajamento e resultados por curso</p></div>
  <?php if (can('reports.export')): ?>
    <div class="cluster">
      <a class="btn btn-secondary" href="<?= url('admin/relatorios/exportar', ['tipo' => 'cursos']) ?>"><?= icon('download') ?> Cursos (CSV)</a>
      <a class="btn btn-secondary" href="<?= url('admin/relatorios/exportar', ['tipo' => 'matriculas']) ?>"><?= icon('download') ?> Matrículas (CSV)</a>
    </div>
  <?php endif; ?>
</div>

<div class="cols">
  <section class="card">
    <header class="card-head">
      <div><h2>Matrículas e conclusões por mês</h2><p>Últimos 6 meses</p></div>
      <div class="chart-legend"><span><i style="background:var(--ink)"></i> Matrículas</span><span><i style="background:var(--marker)"></i> Conclusões</span></div>
    </header>
    <div class="card-body"><?= App\Lib\Chart::bars($labels, $enrolled, $completed, 'Matrículas e conclusões por mês') ?></div>
  </section>
  <section class="card">
    <header class="card-head"><div><h2>Funil de engajamento</h2><p>Todas as matrículas ativas e concluídas</p></div></header>
    <div class="card-body">
      <ul class="hbar-list">
        <?php foreach ($funnel as [$label, $n]): ?>
          <li>
            <div class="top"><span><?= $label ?></span><strong><?= (int) $n ?> <small class="muted" style="font-family:var(--font-body);font-weight:400">· <?= round($n / $top * 100) ?>%</small></strong></div>
            <div class="hbar"><i style="width:<?= round($n / $top * 100) ?>%"></i></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>

<section class="card" style="margin-top:1.5rem">
  <header class="card-head"><h2>Indicadores por curso</h2></header>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Curso</th><th class="num">Matrículas</th><th class="num">Em andamento</th><th class="num">Concluídas</th><th>Taxa de conclusão</th><th class="num">Progresso médio</th><th class="num">Nota média</th><th class="num">Certificados</th></tr></thead>
      <tbody>
        <?php foreach ($courses as $c): $rate = $c['enrolled'] ? round($c['completed'] / $c['enrolled'] * 100) : 0; ?>
          <tr>
            <td><a class="row-link" href="<?= url('admin/relatorios/curso/' . $c['id']) ?>"><?= e($c['title']) ?></a><?= $c['status'] === 'draft' ? ' <span class="badge badge-warn">Rascunho</span>' : '' ?></td>
            <td class="num"><?= (int) $c['enrolled'] ?></td>
            <td class="num"><?= (int) $c['active'] ?></td>
            <td class="num"><?= (int) $c['completed'] ?></td>
            <td style="min-width:140px"><div class="progress-label" style="margin-bottom:3px"><span></span><strong><?= $rate ?>%</strong></div><div class="hbar"><i style="width:<?= $rate ?>%;background:var(--ok)"></i></div></td>
            <td class="num"><?= (int) $c['avg_progress'] ?>%</td>
            <td class="num"><?= $c['avg_score'] !== null ? (int) $c['avg_score'] . '%' : '—' ?></td>
            <td class="num"><?= (int) $c['certificates'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
