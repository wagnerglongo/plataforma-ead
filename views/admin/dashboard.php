<?php
/** @var array $kpis @var array $chartLabels @var array $chartValues @var array $courses @var array $recent @var array $activity @var int $pendingLgpd */
$completionRate = $kpis['enrollments'] > 0 ? round($kpis['completed'] / $kpis['enrollments'] * 100) : 0;
$delta = $kpis['enrollments_prev_30'] > 0 ? round(($kpis['enrollments_30'] - $kpis['enrollments_prev_30']) / $kpis['enrollments_prev_30'] * 100) : null;
$maxEnrolled = max(1, ...array_map(fn ($c) => (int) $c['enrolled'], $courses ?: [['enrolled' => 1]]));
?>
<div class="admin-head">
  <div>
    <h1>Painel</h1>
    <p><?= e(date_long_br(now())) ?> · visão dos últimos 30 dias</p>
  </div>
  <?php if (can('courses.manage')): ?><a class="btn" href="<?= url('admin/cursos/novo') ?>"><?= icon('plus') ?> Novo curso</a><?php endif; ?>
</div>

<?php if ($pendingLgpd && can('lgpd.manage')): ?>
  <div class="alert alert-warning"><?= icon('shield') ?><p><?= plural($pendingLgpd, 'pedido de titular aguarda', 'pedidos de titulares aguardam') ?> atendimento (LGPD, prazo de 15 dias). <a href="<?= url('admin/lgpd') ?>">Atender agora</a></p></div>
<?php endif; ?>

<dl class="kpis">
  <div class="kpi">
    <dt><?= icon('users') ?> Alunos ativos</dt>
    <dd><?= number_format((int) $kpis['students'], 0, ',', '.') ?></dd>
    <div class="delta"><strong>+<?= (int) $kpis['students_30'] ?></strong> novos em 30 dias</div>
  </div>
  <div class="kpi">
    <dt><?= icon('book') ?> Matrículas em 30 dias</dt>
    <dd><?= (int) $kpis['enrollments_30'] ?></dd>
    <div class="delta"><?php if ($delta !== null): ?><strong style="color:<?= $delta < 0 ? 'var(--danger)' : 'var(--ok)' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%</strong> sobre os 30 dias anteriores<?php else: ?><?= (int) $kpis['enrollments'] ?> no total<?php endif; ?></div>
  </div>
  <div class="kpi">
    <dt><?= icon('target') ?> Taxa de conclusão</dt>
    <dd><?= $completionRate ?>%</dd>
    <div class="delta"><?= (int) $kpis['completed'] ?> de <?= (int) $kpis['enrollments'] ?> matrículas</div>
  </div>
  <div class="kpi">
    <dt><?= icon('award') ?> Certificados válidos</dt>
    <dd><?= (int) $kpis['certificates'] ?></dd>
    <div class="delta"><strong>+<?= (int) $kpis['certificates_30'] ?></strong> em 30 dias</div>
  </div>
</dl>

<div class="cols">
  <section class="card">
    <header class="card-head"><div><h2>Matrículas por dia</h2><p><?= array_sum($chartValues) ?> nos últimos 30 dias · <?= (int) $kpis['active_learners'] ?> alunos estudaram no período</p></div></header>
    <div class="card-body"><?= App\Lib\Chart::line($chartLabels, $chartValues, 'Matrículas por dia nos últimos 30 dias') ?></div>
  </section>
  <section class="card">
    <header class="card-head"><h2>Cursos com mais alunos</h2><?php if (can('reports.view')): ?><a href="<?= url('admin/relatorios') ?>">Relatório</a><?php endif; ?></header>
    <div class="card-body">
      <ul class="hbar-list">
        <?php foreach ($courses as $c): $rate = $c['enrolled'] ? round($c['completed'] / $c['enrolled'] * 100) : 0; ?>
          <li>
            <div class="top"><span><?= e(str_limit($c['title'], 38)) ?></span><strong><?= (int) $c['enrolled'] ?></strong></div>
            <div class="hbar"><i style="width:<?= round($c['enrolled'] / $maxEnrolled * 100) ?>%"></i></div>
            <small class="muted"><?= $rate ?>% concluíram · progresso médio <?= (int) $c['avg_progress'] ?>%</small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>

<div class="cols" style="margin-top:1.5rem">
  <section class="card">
    <header class="card-head"><h2>Matrículas recentes</h2><?php if (can('students.view')): ?><a href="<?= url('admin/alunos') ?>">Todos os alunos</a><?php endif; ?></header>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Aluno</th><th>Curso</th><th>Progresso</th><th>Data</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><a class="row-link" href="<?= url('admin/alunos/' . $r['user_id']) ?>"><?= e($r['name']) ?></a></td>
              <td><?= e(str_limit($r['title'], 34)) ?></td>
              <td style="min-width:120px"><div class="progress" style="--value:<?= (int) $r['progress_percent'] ?>%" data-done="<?= $r['status'] === 'completed' ? 1 : 0 ?>"></div></td>
              <td class="muted"><?= time_ago($r['enrolled_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <header class="card-head"><h2>Atividade recente</h2><?php if (can('audit.view')): ?><a href="<?= url('admin/atividades') ?>">Ver tudo</a><?php endif; ?></header>
    <ul class="feed">
      <?php foreach ($activity as $a): ?>
        <li>
          <span><strong><?= e($a['name'] ?? 'Sistema') ?></strong> <?= e(mb_strtolower(App\Core\Audit::label($a['action']))) ?></span>
          <span class="when"><?= time_ago($a['created_at']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
