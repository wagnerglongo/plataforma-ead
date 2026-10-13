<div class="container container-narrow" style="padding-top:2.5rem;padding-bottom:4rem">
  <h1 style="font-size:var(--step-3)">Termos de uso</h1>
  <p class="muted">Versão <?= e(config('lgpd.terms_version')) ?>. Ao criar uma conta você concorda com as regras abaixo.</p>
  <div class="prose">
    <h3>A conta é pessoal</h3>
    <p>Cada conta pertence a uma pessoa. Não compartilhe sua senha: o progresso e o certificado são emitidos em nome de quem está cadastrado.</p>
    <h3>Conteúdo dos cursos</h3>
    <p>Vídeos, textos e materiais são de uso pessoal para estudo. Não é permitido redistribuir, revender ou publicar o conteúdo sem autorização.</p>
    <h3>Certificados</h3>
    <p>O certificado é emitido automaticamente quando todas as aulas são concluídas e as avaliações aprovadas. A instituição pode revogar certificados emitidos por fraude ou erro, e a revogação aparece na página de validação.</p>
    <h3>Cursos livres</h3>
    <p>Os cursos são livres, de capacitação e atualização profissional, e não substituem cursos regulamentados pelo MEC.</p>
    <h3>Disponibilidade</h3>
    <p>Trabalhamos para manter a plataforma sempre no ar, mas podem ocorrer pausas para manutenção. Seu progresso fica salvo.</p>
    <h3>Contato</h3>
    <p>Dúvidas sobre estes termos: <?= e(setting('contact_email', '')) ?>.</p>
  </div>
</div>
