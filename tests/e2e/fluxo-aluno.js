/*
 * Teste ponta a ponta da jornada do aluno (Playwright).
 * Cadastro → matrícula → aulas em vídeo e texto → avaliação final → certificado → validação pública.
 *
 *   npm i -D playwright && npx playwright install chromium
 *   BASE_URL=http://127.0.0.1:8000 node tests/e2e/fluxo-aluno.js
 *
 * Requer o banco com os dados de demonstração (php bin/ead demo).
 */
const { chromium } = require('playwright');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const COURSE = 'gestao-do-tempo-e-produtividade';
const RIGHT_ANSWERS = ['Agendar', 'Desligar notificações e fazer uma tarefa por vez', 'Três', 'Pedir a pauta e o objetivo'];

const step = (msg) => console.log('  ✓', msg);
const fail = (msg) => { console.error('  ✗', msg); process.exit(1); };

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
  const page = await browser.newPage({ viewport: { width: 1280, height: 860 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

  // 1. Cadastro
  const email = `teste.${Date.now()}@exemplo.com`;
  await page.goto(`${BASE}/cadastro?curso=${COURSE}`);
  await page.fill('#name', 'Teste Automatizado da Silva');
  await page.fill('#email', email);
  await page.fill('#password', 'senha1234');
  await page.fill('#password_confirmation', 'senha1234');
  await page.check('input[name=terms]');
  await Promise.all([page.waitForNavigation(), page.click('form [type=submit]')]);
  if (!page.url().includes(`/curso/${COURSE}`)) fail('cadastro não voltou para a página do curso: ' + page.url());
  step('cadastro criado e de volta à página do curso');

  // 2. Matrícula
  await Promise.all([page.waitForNavigation(), page.click('text=Matricular-se gratuitamente')]);
  if (!/\/aluno\/curso\/.+\/aula\/\d+/.test(page.url())) fail('matrícula não abriu o player: ' + page.url());
  step('matrícula feita, player aberto na primeira aula');

  // 3. Aulas
  for (let guard = 0; guard < 12; guard++) {
    const url = page.url();
    if (url.includes('/avaliacao/')) break;
    const title = await page.textContent('.lesson-head h1');
    if (await page.$('#lesson-video')) {
      // reproduz em 4x: o rastreador conta tempo real de reprodução, não saltos
      await page.evaluate(() => { const v = document.getElementById('lesson-video'); v.muted = true; v.playbackRate = 4; return v.play(); });
      await page.waitForFunction(() => document.getElementById('lesson-video').ended, null, { timeout: 60000 });
      await page.waitForSelector('#done-badge:not([hidden])', { timeout: 15000 });
      step(`vídeo assistido e concluído: ${title.trim()}`);
      await Promise.all([page.waitForNavigation(), page.click('#next-btn')]);
    } else {
      await Promise.all([page.waitForNavigation(), page.click('text=Marcar como concluída e seguir')]);
      step(`aula de leitura concluída: ${title.trim()}`);
    }
  }

  // 4. Avaliação final: primeiro uma tentativa errada, depois a certa
  if (!page.url().includes('/avaliacao/')) fail('não chegou à avaliação final');
  const questions = await page.$$('fieldset.question');
  for (const q of questions) {
    const options = await q.$$('label.option');
    await options[options.length - 1].click();
  }
  await Promise.all([page.waitForNavigation(), page.click('text=Enviar respostas')]);
  if (!(await page.isVisible('.result-fail'))) fail('tentativa errada deveria reprovar');
  step('tentativa com respostas erradas reprovada');

  await Promise.all([page.waitForNavigation(), page.click('text=Tentar de novo')]);
  for (const q of await page.$$('fieldset.question')) {
    for (const opt of await q.$$('label.option')) {
      if (RIGHT_ANSWERS.includes((await opt.textContent()).trim())) await opt.click();
    }
  }
  await Promise.all([page.waitForNavigation(), page.click('text=Enviar respostas')]);
  if (!page.url().includes('/aluno/certificados?novo=')) fail('aprovação não emitiu certificado: ' + page.url());
  const code = new URL(page.url()).searchParams.get('novo');
  step(`aprovado na avaliação final, certificado ${code} emitido`);

  // 5. PDF e validação pública
  const pdf = await page.request.get(`${BASE}/aluno/certificados/${code}/pdf`);
  if (pdf.status() !== 200 || !(await pdf.body()).subarray(0, 5).toString().startsWith('%PDF')) fail('PDF inválido');
  step('PDF do certificado baixado');
  const anon = await browser.newPage();
  await anon.goto(`${BASE}/certificado/${code}`);
  if (!(await anon.isVisible('text=Certificado válido'))) fail('validação pública não confirmou o certificado');
  if (!(await anon.isVisible('text=Teste Automatizado da Silva'))) fail('nome não aparece na validação');
  step('validação pública confirma o certificado');

  if (errors.length) fail('erros de JavaScript: ' + errors.join(' | '));
  step('sem erros de JavaScript no navegador');
  await browser.close();
  console.log('\nJornada do aluno OK.');
})().catch((e) => fail(e.message));
