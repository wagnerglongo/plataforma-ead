<section class="error-page">
  <div class="container container-narrow">
    <p class="status"><span><?= (int) $status ?></span></p>
    <h1 style="font-size:var(--step-3)"><?= e($title) ?></h1>
    <p><?= e($message ?: match ((int) $status) {
        404 => 'O endereço pode ter mudado ou o conteúdo foi removido.',
        403 => 'Sua conta não tem acesso a esta área. Se precisar, fale com a administração.',
        419 => 'Por segurança, recarregue a página e tente de novo.',
        default => 'Registramos o problema. Tente de novo em alguns instantes.',
    }) ?></p>
    <div class="cluster" style="justify-content:center">
      <a class="btn" href="<?= url('/') ?>">Ir para a página inicial</a>
      <a class="btn btn-secondary" href="<?= url('cursos') ?>">Ver cursos</a>
    </div>
  </div>
</section>
