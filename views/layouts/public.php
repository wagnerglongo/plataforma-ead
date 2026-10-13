<?php partial('partials/head', get_defined_vars()) ?>
<?php partial('partials/header') ?>
<main id="conteudo">
  <?php if (empty($noFlashContainer)): ?>
    <?php if (App\Core\Session::get('_flash')): ?>
      <div class="container" style="padding-top:1.25rem"><?php partial('partials/flash') ?></div>
    <?php endif; ?>
  <?php endif; ?>
  <?= $content ?>
</main>
<?php partial('partials/footer') ?>
<?php partial('partials/cookie-banner') ?>
<?php if ($track = App\Core\Session::get('_track')): ?><span hidden data-track-event="<?= e($track['event']) ?>" data-track-params="<?= e(json_encode(array_diff_key($track, ['event' => 1]))) ?>"></span><?php endif; ?>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= App\Core\View::stack('scripts') ?>
</body>
</html>
