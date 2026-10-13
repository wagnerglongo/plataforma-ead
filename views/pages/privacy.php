<?php
$dpoName = setting('dpo_name', 'Encarregado de dados');
$dpoEmail = setting('dpo_email', setting('contact_email', ''));
?>
<div class="container container-narrow" style="padding-top:2.5rem;padding-bottom:4rem">
  <h1 style="font-size:var(--step-3)">Política de privacidade</h1>
  <p class="muted">Versão <?= e(config('lgpd.privacy_version')) ?>. Esta política explica quais dados pessoais <?= e(site_name()) ?> trata, por quê e quais são os seus direitos, conforme a Lei nº 13.709/2018 (LGPD).</p>
  <div class="prose">
    <h3>Quem trata os seus dados</h3>
    <p><?= e(site_name()) ?> é o controlador dos dados tratados nesta plataforma. O encarregado pelo tratamento de dados (DPO) é <?= e($dpoName) ?><?= $dpoEmail ? ', pelo e-mail <a href="mailto:' . e($dpoEmail) . '">' . e($dpoEmail) . '</a>' : '' ?>.</p>

    <h3>Quais dados coletamos e para quê</h3>
    <ul>
      <li><strong>Cadastro</strong> (nome, e-mail, senha protegida por criptografia, CPF opcional): criar sua conta, emitir certificados e falar com você sobre os cursos. Base legal: execução de contrato.</li>
      <li><strong>Progresso de estudo</strong> (aulas assistidas, tempo, notas): controlar a conclusão e emitir o certificado. Base legal: execução de contrato.</li>
      <li><strong>Registros de acesso</strong> (endereço IP, data e hora): segurança e cumprimento do Marco Civil da Internet. Base legal: obrigação legal.</li>
      <li><strong>Comunicação de novidades</strong>: só se você marcar essa opção. Base legal: consentimento, que pode ser retirado a qualquer momento.</li>
      <li><strong>Medição de audiência</strong> (Google Analytics/Tag Manager): só se você aceitar os cookies de medição. Base legal: consentimento.</li>
    </ul>

    <h3>Certificados e validação pública</h3>
    <p>Quem tem o código de um certificado pode confirmar, na página de validação, o nome do aluno, o curso, a carga horária e a data de emissão. É isso que permite a empresas e instituições confiarem no documento. O CPF, quando informado, aparece mascarado.</p>

    <h3>Com quem compartilhamos</h3>
    <p>Não vendemos dados pessoais. Usamos fornecedores necessários para a operação: hospedagem do site e do banco de dados, envio de e-mails e, quando a aula usa vídeo externo, o provedor de vídeo. Todos tratam os dados apenas para prestar o serviço.</p>

    <h3>Por quanto tempo guardamos</h3>
    <p>Os dados da conta ficam guardados enquanto ela existir. Registros de acesso são mantidos por no mínimo 6 meses, como exige o Marco Civil da Internet. Ao pedir a exclusão da conta, seus dados pessoais são anonimizados; certificados já emitidos continuam disponíveis para validação, porque são documentos entregues a terceiros.</p>

    <h3>Seus direitos</h3>
    <p>Você pode, a qualquer momento: confirmar se tratamos seus dados, acessá-los, corrigi-los, pedir a portabilidade, revogar consentimentos e pedir a exclusão. Na <a href="<?= url('aluno/privacidade') ?>">área do aluno, em Privacidade</a>, você baixa uma cópia completa dos seus dados e gerencia consentimentos sem precisar falar com ninguém.</p>

    <h3>Segurança</h3>
    <p>Senhas são guardadas com hash bcrypt, a conexão é criptografada (HTTPS), o acesso administrativo é separado por perfis com permissões e todas as ações da equipe ficam registradas. Fazemos backups periódicos do banco de dados.</p>

    <h3>Alterações</h3>
    <p>Se esta política mudar de forma relevante, avisaremos por e-mail ou na própria plataforma.</p>
  </div>
</div>
