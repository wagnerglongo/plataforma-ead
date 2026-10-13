<?php
/** @var array $course @var string|null $size @var bool|null $showTitle */
$glyph = mb_substr($course['category_name'] ?? $course['title'], 0, 1);
$cls = 'cover' . (($size ?? '') === 'lg' ? ' cover-lg' : '');
?>
<div class="<?= $cls ?>" style="--c: <?= e($course['cover_color'] ?? '#1F3A93') ?>" aria-hidden="true">
  <?php if (!empty($course['thumbnail'])): ?>
    <img src="<?= e(upload_url($course['thumbnail'])) ?>" alt="" loading="lazy">
  <?php else: ?>
    <span class="cover-glyph"><?= e($glyph) ?></span>
    <?php if (!empty($course['category_name'])): ?><span class="cover-cat"><?= e($course['category_name']) ?></span><?php endif; ?>
    <?php if ($showTitle ?? true): ?><span class="cover-title"><?= e($course['title']) ?></span><?php endif; ?>
    <span class="cover-hours"><?= (int) $course['workload_hours'] ?>h<small>carga horária</small></span>
  <?php endif; ?>
</div>
