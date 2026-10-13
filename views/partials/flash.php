<?php
$flash = App\Core\Session::get('_flash');
if (!$flash) {
    return;
}
$icons = ['success' => 'check-circle', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
$type = $flash['type'] ?? 'info';
?>
<div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
  <?= icon($icons[$type] ?? 'info') ?>
  <p><?= e($flash['message']) ?></p>
</div>
