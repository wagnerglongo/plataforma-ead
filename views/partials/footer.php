<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <?php partial('partials/brand') ?>
        <p><?= e(setting('site_tagline', '')) ?></p>
        <?php if ($mail = setting('contact_email')): ?>
          <p><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></p>
        <?php endif; ?>
      </div>
      <div>
        <h3>Estude</h3>
        <ul>
          <li><a href="<?= url('cursos') ?>">Todos os cursos</a></li>
          <li><a href="<?= url('cadastro') ?>">Criar conta</a></li>
          <li><a href="<?= url('aluno') ?>">Área do aluno</a></li>
        </ul>
      </div>
      <div>
        <h3>Certificados</h3>
        <ul>
          <li><a href="<?= url('validar') ?>">Validar um certificado</a></li>
          <li><a href="<?= url('aluno/certificados') ?>">Meus certificados</a></li>
        </ul>
      </div>
      <div>
        <h3>Privacidade</h3>
        <ul>
          <li><a href="<?= url('privacidade') ?>">Política de privacidade</a></li>
          <li><a href="<?= url('termos') ?>">Termos de uso</a></li>
          <li><a href="<?= url('cookies') ?>">Cookies</a></li>
          <li><button type="button" class="link-btn" data-open-cookies>Preferências de cookies</button></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e(site_name()) ?></span>
      <span>Plataforma desenvolvida por <a href="https://wglsoftware.com" rel="noopener">WGL Software</a></span>
    </div>
  </div>
</footer>
