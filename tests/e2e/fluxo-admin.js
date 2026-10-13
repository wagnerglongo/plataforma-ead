/*
 * Teste ponta a ponta da área administrativa (Playwright).
 * Cria curso → módulo → aula do YouTube → aula de texto → material → avaliação final,
 * publica, confere no catálogo, revoga/reativa um certificado e exporta CSV.
 *
 *   BASE_URL=http://127.0.0.1:8000 node tests/e2e/fluxo-admin.js
 */
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const step = (m) => console.log('  ✓', m);
const fail = (m) => { console.error('  ✗', m); process.exit(1); };

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('dialog', (d) => d.accept());
  const go = async (fn) => Promise.all([page.waitForNavigation(), fn()]);
  const flash = async () => (await page.textContent('.alert'))?.trim() || '';

  await page.goto(`${BASE}/entrar`);
  await page.fill('#email', 'admin@demo.com');
  await page.fill('#password', 'demo1234');
  await go(() => page.click('#login-form [type=submit]'));
  if (!page.url().endsWith('/admin')) fail('login do admin não abriu o painel');
  step('admin entrou no painel');

  // Curso
  const title = 'Curso de Teste ' + Date.now();
  await page.goto(`${BASE}/admin/cursos/novo`);
  await page.fill('#title', title);
  await page.fill('#summary', 'Curso criado pelo teste automatizado para validar o fluxo do painel.');
  await page.selectOption('#status', 'published');
  await page.fill('#workload_hours', '4');
  await go(() => page.click('text=Criar curso'));
  if (!page.url().includes('/conteudo')) fail('criar curso não abriu a montagem do conteúdo');
  const courseId = page.url().match(/cursos\/(\d+)/)[1];
  step(`curso criado (#${courseId})`);

  // Módulo
  await page.fill('#new-module', 'Módulo do teste');
  await go(() => page.click('text=Adicionar módulo'));
  if (!(await page.isVisible('text=Módulo 1: Módulo do teste'))) fail('módulo não apareceu');
  step('módulo criado');

  // Aula do YouTube
  await go(() => page.click('.builder-foot >> text=Aula'));
  await page.fill('#title', 'Aula em vídeo do YouTube');
  await page.selectOption('#video_provider', 'youtube');
  await page.fill('#video_ref', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ');
  await page.fill('#duration', '1:00');
  await page.fill('#content', 'Descrição da aula de teste.');
  await page.check('input[name=is_preview]');
  await go(() => page.click('text=Criar aula'));
  if (!(await flash()).includes('Aula criada')) fail('aula de vídeo não foi criada: ' + (await flash()));
  step('aula com vídeo do YouTube criada');

  // Validação: link inválido
  await page.fill('#video_ref', 'https://site-qualquer.com/video');
  await page.selectOption('#video_provider', 'embed');
  await go(() => page.click('text=Salvar aula'));
  if (!(await page.isVisible('#err-video_ref'))) fail('link de incorporação inválido deveria ser recusado');
  step('link de vídeo de site não permitido foi recusado');

  // Material
  await page.goto(page.url().split('#')[0]);
  const tmp = path.join(os.tmpdir(), 'material-teste.txt');
  fs.writeFileSync(tmp, 'Conteúdo do material de teste.');
  await page.fill('#mat-title', 'Roteiro de estudo');
  await page.setInputFiles('#mat-file', tmp);
  await go(() => page.click('text=Anexar'));
  if (!(await page.isVisible('text=Roteiro de estudo'))) fail('material não foi anexado');
  step('material anexado');

  // Upload proibido (PHP disfarçado)
  const evil = path.join(os.tmpdir(), 'malicioso.pdf');
  fs.writeFileSync(evil, '<?php echo "invasao"; ?>');
  await page.fill('#mat-title', 'Arquivo suspeito');
  await page.setInputFiles('#mat-file', evil);
  await go(() => page.click('text=Anexar'));
  if (!(await page.isVisible('#err-file'))) fail('arquivo com extensão falsa deveria ser recusado');
  step('arquivo PHP disfarçado de PDF foi recusado');

  // Aula de texto
  await page.goto(`${BASE}/admin/cursos/${courseId}/conteudo`);
  await go(() => page.click('.builder-foot >> text=Aula'));
  await page.fill('#title', 'Leitura de fechamento');
  await page.check('input[name=type][value=text]');
  await page.fill('#content', '## Resumo\nTexto de leitura.\n\n- item um\n- item dois');
  await go(() => page.click('text=Criar aula'));
  step('aula de texto criada');

  // Avaliação final
  await page.goto(`${BASE}/admin/cursos/${courseId}/conteudo`);
  await go(() => page.click('text=Criar avaliação final'));
  const q1 = page.locator('.q-editor').first();
  await q1.locator('textarea').fill('Quanto é 2 + 2?');
  const inputs = q1.locator('.opt .input');
  await inputs.nth(0).fill('4');
  await inputs.nth(1).fill('5');
  await inputs.nth(2).fill('22');
  await page.click('#add-question');
  const q2 = page.locator('.q-editor').nth(1);
  await q2.locator('textarea').fill('A capital do Brasil é…');
  await q2.locator('.opt .input').nth(0).fill('Brasília');
  await q2.locator('.opt .input').nth(1).fill('Rio de Janeiro');
  await q2.locator('.opt .input').nth(2).fill('São Paulo');
  await go(() => page.click('text=Criar avaliação'));
  if (!(await page.isVisible('text=2 questões'))) fail('avaliação final não aparece com 2 questões');
  step('avaliação final com 2 questões criada');

  // Catálogo público
  const pub = await browser.newPage();
  await pub.goto(`${BASE}/cursos?q=${encodeURIComponent('Curso de Teste')}`);
  if (!(await pub.isVisible(`text=${title}`))) fail('curso publicado não aparece no catálogo');
  await pub.click(`text=${title}`);
  if (!(await pub.isVisible('text=Aula gratuita'))) fail('aula gratuita não aparece na página do curso');
  step('curso aparece no catálogo com aula gratuita');

  // Certificados: revogar e reativar
  await page.goto(`${BASE}/admin/certificados`);
  const code = (await page.textContent('tbody tr td.code')).trim();
  await page.locator('tbody tr').first().locator('summary', { hasText: 'Revogar' }).click();
  await page.locator('tbody tr').first().locator('input[name=reason]').fill('Teste automatizado de revogação');
  await go(() => page.locator('tbody tr').first().locator('text=Confirmar revogação').click());
  await pub.goto(`${BASE}/certificado/${code}`);
  if (!(await pub.isVisible('text=Certificado revogado'))) fail('validação não mostra revogação');
  await page.goto(`${BASE}/admin/certificados?q=${code}`);
  await go(() => page.click('text=Reativar'));
  await pub.goto(`${BASE}/certificado/${code}`);
  if (!(await pub.isVisible('text=Certificado válido'))) fail('certificado não voltou a valer');
  step(`certificado ${code} revogado e reativado, refletindo na validação pública`);

  // Exportação
  const csv = await page.request.get(`${BASE}/admin/relatorios/exportar?tipo=alunos`);
  const text = (await csv.body()).toString('utf8');
  if (csv.status() !== 200 || !text.includes('Nome;E-mail')) fail('CSV de alunos inválido');
  step(`CSV de alunos exportado (${text.trim().split('\n').length - 1} linhas)`);

  // Proteções do modo demonstração
  await page.goto(`${BASE}/admin/configuracoes`);
  await go(() => page.click('text=Salvar configurações'));
  const msg = await flash();
  if (process.env.DEMO_MODE !== 'false' && !msg.includes('demonstração')) fail('modo demonstração deveria bloquear configurações');
  step('modo demonstração bloqueia alterações sensíveis');

  if (errors.length) fail('erros de JavaScript: ' + errors.join(' | '));
  step('sem erros de JavaScript no navegador');
  await browser.close();
  console.log('\nÁrea administrativa OK.');
})().catch((e) => fail(e.message));
