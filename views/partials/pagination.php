<?php
/** @var array $p resultado do Paginator @var string $path */
if (($p['pages'] ?? 1) <= 1) {
    return;
}
$query = App\Core\Request::current()?->queryAll() ?? [];
$link = function (int $page) use ($query, $path) {
    $query['pagina'] = $page;
    return url($path, $query);
};
$pages = [];
for ($i = 1; $i <= $p['pages']; $i++) {
    if ($i === 1 || $i === $p['pages'] || abs($i - $p['page']) <= 2) {
        $pages[] = $i;
    } elseif (end($pages) !== '…') {
        $pages[] = '…';
    }
}
?>
<nav class="pagination" aria-label="Paginação">
  <?php if ($p['page'] > 1): ?>
    <a href="<?= e($link($p['page'] - 1)) ?>" rel="prev"><?= icon('chevron-left') ?><span class="sr-only">Página anterior</span></a>
  <?php endif; ?>
  <?php foreach ($pages as $n): ?>
    <?php if ($n === '…'): ?><span aria-hidden="true">…</span>
    <?php elseif ($n === $p['page']): ?><span aria-current="page"><?= $n ?></span>
    <?php else: ?><a href="<?= e($link($n)) ?>"><?= $n ?></a><?php endif; ?>
  <?php endforeach; ?>
  <?php if ($p['page'] < $p['pages']): ?>
    <a href="<?= e($link($p['page'] + 1)) ?>" rel="next"><?= icon('chevron-right') ?><span class="sr-only">Próxima página</span></a>
  <?php endif; ?>
</nav>
