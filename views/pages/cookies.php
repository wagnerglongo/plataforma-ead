<div class="container container-narrow" style="padding-top:2.5rem;padding-bottom:4rem">
  <h1 style="font-size:var(--step-3)">Cookies</h1>
  <div class="prose">
    <p>Cookies são pequenos arquivos que o site guarda no seu navegador. Usamos dois tipos:</p>
    <h3>Essenciais (sempre ativos)</h3>
    <ul>
      <li><strong>ead_session</strong>: mantém você conectado e protege os formulários contra fraudes. Expira ao fechar o navegador ou após <?= (int) config('session.lifetime') ?> minutos sem uso.</li>
      <li><strong>ead_consent</strong>: guarda a sua escolha sobre cookies por 6 meses.</li>
    </ul>
    <h3>Medição (só com a sua permissão)</h3>
    <p>Google Analytics e Google Tag Manager, para entender quais páginas e cursos são mais acessados. Eles só são carregados depois que você clica em "Aceitar todos". Sem permissão, nenhum script de medição é executado.</p>
    <p><button type="button" class="btn btn-secondary" data-open-cookies>Rever minha escolha</button></p>
  </div>
</div>
