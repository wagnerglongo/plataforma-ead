<?php /** @var array $result @var string $scope @var string $action @var array $actions */
$groupLabels = ['auth' => 'Acesso', 'course' => 'Cursos', 'module' => 'Módulos', 'lesson' => 'Aulas', 'quiz' => 'Avaliações', 'student' => 'Alunos', 'user' => 'Equipe', 'role' => 'Perfis', 'enrollment' => 'Matrículas', 'certificate' => 'Certificados', 'settings' => 'Configurações', 'backup' => 'Backups', 'lgpd' => 'LGPD', 'report' => 'Relatórios', 'profile' => 'Perfil', 'material' => 'Materiais'];
?>
<div class="admin-head"><div><h1>Registro de atividades</h1><p>Quem fez o quê e quando. Os registros não podem ser editados pelo painel.</p></div></div>
<section class="card">
  <form class="toolbar" method="get">
    <div class="field"><label class="sr-only" for="quem">Quem</label>
      <select class="select" id="quem" name="quem" data-autosubmit>
        <option value="equipe" <?= $scope === 'equipe' ? 'selected' : '' ?>>Ações da equipe</option>
        <option value="alunos" <?= $scope === 'alunos' ? 'selected' : '' ?>>Ações de alunos</option>
        <option value="todos" <?= $scope === 'todos' ? 'selected' : '' ?>>Todos</option>
      </select>
    </div>
    <div class="field"><label class="sr-only" for="acao">Tipo</label>
      <select class="select" id="acao" name="acao" data-autosubmit>
        <option value="">Todos os tipos</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($groupLabels[$a] ?? $a) ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($result['items'] as $a): $details = $a['details'] ? json_decode($a['details'], true) : []; ?>
          <tr>
            <td class="muted" style="white-space:nowrap"><?= date_br($a['created_at'], true) ?></td>
            <td><?= e($a['name'] ?? 'Sistema') ?><span class="sub"><?= e($a['role_name'] ?? '') ?></span></td>
            <td><?= e(App\Core\Audit::label($a['action'])) ?></td>
            <td class="muted" style="font-size:var(--step--1)"><?= e(implode(' · ', array_map(fn ($k, $val) => $k . ': ' . (is_array($val) ? implode(', ', $val) : $val), array_keys((array) $details), (array) $details))) ?></td>
            <td class="muted" style="font-size:var(--step--1)"><?= e($a['ip'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$result['items']): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:2rem">Nenhuma atividade com esse filtro.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php partial('partials/pagination', ['p' => $result, 'path' => 'admin/atividades']) ?>
