<section class="hero grid-paper" style="border-bottom:1px solid var(--rule)">
  <div class="container" style="grid-template-columns:1fr">
    <div style="max-width:760px">
      <p class="badge badge-marker" style="margin-bottom:1rem">Projeto de portfólio · WGL Software</p>
      <h1 style="max-width:20ch">Uma plataforma EAD completa, feita do zero em PHP.</h1>
      <p class="hero-lead">Este site é uma demonstração funcionando de verdade: catálogo, área do aluno, avaliações, certificados com validação pública e um painel administrativo completo. A instituição e os alunos são fictícios.</p>
      <div class="cluster">
        <a class="btn btn-lg" href="<?= url('entrar') ?>">Testar a demonstração</a>
        <a class="btn btn-lg btn-secondary" href="https://github.com/wagnerglongo/plataforma-ead" rel="noopener">Ver o código no GitHub</a>
      </div>
    </div>
  </div>
</section>

<div class="container" style="padding-top:3rem;padding-bottom:4rem">
  <div class="cols-even cols" style="gap:2.5rem">
    <section>
      <h2>O que dá para testar</h2>
      <ul class="check-list">
        <li><?= icon('check-circle') ?> <span><strong>Aluno</strong> (aluno@demo.com): continuar um curso de onde parou, assistir aulas, baixar materiais, fazer avaliações e baixar certificados.</span></li>
        <li><?= icon('check-circle') ?> <span><strong>Administrador</strong> (admin@demo.com): painel com indicadores, cursos, módulos e aulas, avaliações, alunos, matrículas, certificados, relatórios com exportação, equipe e permissões, auditoria e backup.</span></li>
        <li><?= icon('check-circle') ?> <span><strong>Validação pública</strong>: abra qualquer certificado pelo código ou pelo QR Code impresso no PDF.</span></li>
        <li><?= icon('check-circle') ?> <span><strong>Cadastro</strong>: crie uma conta nova e faça um curso do início ao certificado. Os vídeos de demonstração são curtos.</span></li>
      </ul>
      <p class="muted" style="margin-top:1rem">Senha de todas as contas de demonstração: <strong>demo1234</strong>. Ações destrutivas ficam bloqueadas e os dados voltam ao estado original todo dia.</p>
    </section>
    <section>
      <h2>Como foi feito</h2>
      <dl class="data-list" style="grid-template-columns:max-content 1fr">
        <dt>Back-end</dt><dd>PHP 8.1+ sem framework, arquitetura MVC própria (roteador, middlewares, validação, views)</dd>
        <dt>Banco</dt><dd>MySQL/MariaDB com 23 tabelas, chaves estrangeiras e índices</dd>
        <dt>Dependências</dt><dd>Nenhuma. Gerador de PDF, QR Code, cliente SMTP e backup escritos do zero</dd>
        <dt>Front-end</dt><dd>HTML, CSS e JavaScript puros, responsivo do celular ao desktop</dd>
        <dt>Hospedagem</dt><dd>Roda em hospedagem compartilhada comum (Apache/LiteSpeed)</dd>
      </dl>
    </section>
  </div>

  <hr>

  <div class="cols-even cols" style="gap:2.5rem">
    <section>
      <h3>Segurança</h3>
      <ul class="check-list">
        <li><?= icon('shield') ?> <span>Senhas com bcrypt, proteção CSRF em todos os formulários e limite de tentativas de login</span></li>
        <li><?= icon('shield') ?> <span>Consultas preparadas (sem SQL injection) e saída escapada (sem XSS), com Content-Security-Policy</span></li>
        <li><?= icon('shield') ?> <span>Vídeos e materiais fora da pasta pública: só saem para alunos matriculados</span></li>
        <li><?= icon('shield') ?> <span>Perfis com permissões granulares e registro de todas as ações da equipe</span></li>
        <li><?= icon('shield') ?> <span>Certificados assinados com HMAC-SHA256: alteração no banco é detectada na validação</span></li>
      </ul>
    </section>
    <section>
      <h3>LGPD</h3>
      <ul class="check-list">
        <li><?= icon('check') ?> <span>Consentimentos registrados com data, versão e IP</span></li>
        <li><?= icon('check') ?> <span>Banner de cookies: Google Analytics e Tag Manager só carregam após aceite (Consent Mode v2)</span></li>
        <li><?= icon('check') ?> <span>O aluno baixa todos os seus dados em JSON e pede a exclusão pela própria conta</span></li>
        <li><?= icon('check') ?> <span>Exclusão por anonimização, preservando a validade dos certificados emitidos</span></li>
        <li><?= icon('check') ?> <span>Fontes hospedadas no próprio servidor (sem envio de IP ao Google Fonts)</span></li>
      </ul>
    </section>
  </div>
</div>
