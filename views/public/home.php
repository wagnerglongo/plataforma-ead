<?php
/** @var array $featured @var array $categories @var array $stats @var array|null $heroCourse */
$topics = array_slice($categories, 0, 4);
?>
<section class="hero grid-paper">
  <div class="container">
    <div>
      <h1>Estude no seu ritmo e comprove o que aprendeu.</h1>
      <p class="hero-lead">Aulas em vídeo, materiais para baixar e avaliações em cada etapa. Ao concluir, seu certificado sai na hora, com código e QR Code que qualquer empresa pode conferir.</p>
      <form class="hero-search" action="<?= url('cursos') ?>" method="get" role="search">
        <label for="hero-q" class="sr-only">Buscar cursos</label>
        <input class="input" id="hero-q" name="q" type="search" placeholder="O que você quer aprender?" autocomplete="off">
        <button class="btn" type="submit"><?= icon('search') ?> Buscar</button>
      </form>
      <?php if ($topics): ?>
        <p class="hero-topics">Áreas:
          <?php foreach ($topics as $t): ?>
            <a href="<?= url('cursos', ['categoria' => $t['slug']]) ?>"><?= e($t['name']) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($heroCourse): ?>
    <div class="hero-visual" aria-hidden="true">
      <div class="hv-cert">
        <div class="hv-cert-band">
          <div class="hv-seal"><?= date('Y') ?></div>
          <div class="hv-qr"><?= App\Lib\QrCode::svg(absolute_url('validar'), 2, 1) ?></div>
        </div>
        <div class="hv-cert-body">
          <span class="t">Certificado de conclusão</span>
          <span class="n"><span class="mark">Seu nome aqui</span></span>
          <span class="l" style="width:86%"></span>
          <span class="l" style="width:64%"></span>
          <span class="l" style="width:40%;margin-top:auto"></span>
        </div>
      </div>
      <div class="hv-card">
        <div class="k">Continuar de onde parou</div>
        <div class="c"><?= e($heroCourse['title']) ?></div>
        <div class="progress-label"><span>Módulo 2 de 3</span><strong>64%</strong></div>
        <div class="progress" style="--value:64%"></div>
        <div class="row"><?= icon('check-circle') ?> Teste rápido: aprovado com 100%</div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Cursos em destaque</h2>
        <p><?= plural((int) $stats['courses'], 'curso disponível', 'cursos disponíveis') ?>, com <?= plural((int) $stats['certificates'], 'certificado emitido', 'certificados emitidos') ?> até hoje.</p>
      </div>
      <a class="btn btn-secondary" href="<?= url('cursos') ?>">Ver todos os cursos</a>
    </div>
    <div class="course-grid">
      <?php foreach ($featured as $course): ?>
        <?php partial('partials/course-card', ['course' => $course]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section surface-band">
  <div class="container">
    <div class="section-head"><h2>Como funciona</h2></div>
    <div class="steps">
      <div class="step">
        <h3>Crie sua conta e matricule-se</h3>
        <p>Escolha um curso, veja a grade completa e a aula gratuita antes de começar.</p>
      </div>
      <div class="step">
        <h3>Assista, pratique e faça as avaliações</h3>
        <p>A plataforma guarda seu progresso e abre a próxima aula exatamente onde você parou.</p>
      </div>
      <div class="step">
        <h3>Receba o certificado em PDF</h3>
        <p>Emitido automaticamente ao concluir, com código e QR Code para validação pública.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($categories): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>Explore por área</h2></div>
    <ul class="topic-list">
      <?php foreach ($categories as $cat): ?>
        <li><a href="<?= url('cursos', ['categoria' => $cat['slug']]) ?>"><?= e($cat['name']) ?> <span><?= plural((int) $cat['courses_count'], 'curso', 'cursos') ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="validate-band">
      <div>
        <h2>Recebeu um certificado nosso?</h2>
        <p>Digite o código impresso no documento para conferir nome, curso, carga horária e data de emissão.</p>
      </div>
      <form action="<?= url('validar') ?>" method="get">
        <label for="code-home" class="sr-only">Código do certificado</label>
        <div class="input-group">
          <input class="input code" id="code-home" name="codigo" placeholder="XXXX-XXXX-XXXX" data-mask="cert" autocomplete="off" required>
          <button class="btn btn-marker" type="submit">Validar</button>
        </div>
      </form>
    </div>
  </div>
</section>
