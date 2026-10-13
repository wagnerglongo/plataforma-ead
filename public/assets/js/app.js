/* Plataforma EAD · comportamentos globais (sem dependências). */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';

  // ------------------------------------------------ menus que abrem/fecham
  $$('[data-toggle]').forEach((btn) => {
    const target = document.getElementById(btn.dataset.toggle);
    if (!target) return;
    btn.addEventListener('click', () => {
      const open = target.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    $$('.is-open').forEach((el) => el.classList.remove('is-open'));
    $$('[data-toggle][aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  });
  const adminSide = $('#admin-side');
  if (adminSide) {
    adminSide.addEventListener('click', (e) => { if (e.target === adminSide) adminSide.classList.remove('is-open'); });
  }

  // ------------------------------------------------ confirmação de ações
  document.addEventListener('submit', (e) => {
    const form = e.target;
    const msg = form.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
    if (!e.defaultPrevented) {
      const btn = form.querySelector('[type="submit"][data-loading]');
      if (btn) { btn.disabled = true; btn.textContent = btn.dataset.loading; }
    }
  });

  // ------------------------------------------------ envia ao trocar (filtros, ordenação)
  $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form && el.form.requestSubmit()));

  // ------------------------------------------------ preencher acesso de demonstração
  $$('[data-demo-login]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const form = btn.closest('form') || $('#login-form');
      form.querySelector('[name="email"]').value = btn.dataset.demoLogin;
      form.querySelector('[name="password"]').value = 'demo1234';
      form.requestSubmit();
    });
  });

  // ------------------------------------------------ copiar texto
  $$('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copy);
        const original = btn.innerHTML;
        btn.textContent = 'Copiado';
        setTimeout(() => { btn.innerHTML = original; }, 1600);
      } catch (_) { /* navegador sem permissão: ignora */ }
    });
  });

  // ------------------------------------------------ máscaras simples
  $$('[data-mask="cpf"]').forEach((input) => input.addEventListener('input', () => {
    const d = input.value.replace(/\D/g, '').slice(0, 11);
    input.value = d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }));
  $$('[data-mask="phone"]').forEach((input) => input.addEventListener('input', () => {
    const d = input.value.replace(/\D/g, '').slice(0, 11);
    input.value = d.length > 10
      ? d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
      : d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/-$/, '');
  }));
  $$('[data-mask="cert"]').forEach((input) => input.addEventListener('input', () => {
    const d = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
    input.value = d.match(/.{1,4}/g)?.join('-') || '';
  }));
  $$('[data-slug-from]').forEach((slug) => {
    const source = document.getElementById(slug.dataset.slugFrom);
    if (!source) return;
    let touched = slug.value !== '';
    slug.addEventListener('input', () => { touched = true; });
    source.addEventListener('input', () => {
      if (touched) return;
      slug.value = source.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
  });

  // ------------------------------------------------ duração lida do arquivo de vídeo escolhido
  $$('[data-duration-target]').forEach((input) => input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    const target = document.getElementById(input.dataset.durationTarget);
    if (!file || !target) return;
    const probe = document.createElement('video');
    probe.preload = 'metadata';
    probe.onloadedmetadata = () => {
      const t = Math.round(probe.duration);
      target.value = Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0');
      URL.revokeObjectURL(probe.src);
    };
    probe.src = URL.createObjectURL(file);
  }));

  // ------------------------------------------------ campos condicionais (ex.: tipo de aula)
  $$('[data-show-when]').forEach((block) => {
    const [name, values] = block.dataset.showWhen.split('=');
    const inputs = $$(`[name="${name}"]`);
    const sync = () => {
      const current = inputs.find((i) => (i.type === 'radio' ? i.checked : true))?.value;
      block.hidden = !values.split(',').includes(current);
    };
    inputs.forEach((i) => i.addEventListener('change', sync));
    sync();
  });

  // ------------------------------------------------ reordenar (arrastar e soltar + teclado)
  $$('[data-sortable]').forEach((list) => {
    let dragged = null;
    const save = () => {
      const url = list.dataset.sortable;
      const ids = $$(':scope > [data-id]', list).map((li) => li.dataset.id);
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ type: list.dataset.type, ids }),
      }).then((r) => r.ok && announce('Ordem salva.'));
    };
    $$(':scope > [data-id]', list).forEach((item) => {
      // Só arrasta pela alça: o resto do item continua clicável e com texto selecionável.
      const grip = item.querySelector(':scope > .handle, :scope > header > .handle');
      grip?.addEventListener('pointerdown', () => { item.draggable = true; });
      grip?.addEventListener('pointerup', () => { item.draggable = false; });
      item.addEventListener('dragstart', (e) => { e.stopPropagation(); dragged = item; item.classList.add('is-dragging'); });
      item.addEventListener('dragend', (e) => { e.stopPropagation(); item.classList.remove('is-dragging'); item.draggable = false; dragged = null; save(); });
      item.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!dragged || dragged === item || dragged.parentNode !== list) return;
        e.stopPropagation();
        const rect = item.getBoundingClientRect();
        list.insertBefore(dragged, e.clientY < rect.top + rect.height / 2 ? item : item.nextSibling);
      });
      const handle = grip;
      handle?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowUp' && item.previousElementSibling) { list.insertBefore(item, item.previousElementSibling); handle.focus(); save(); e.preventDefault(); }
        if (e.key === 'ArrowDown' && item.nextElementSibling) { list.insertBefore(item.nextElementSibling, item); handle.focus(); save(); e.preventDefault(); }
      });
    });
  });

  // ------------------------------------------------ editor de avaliação: adicionar questão/alternativa
  const qWrap = $('#questions');
  if (qWrap) {
    const tpl = $('#question-template');
    let counter = Date.now();
    $('#add-question')?.addEventListener('click', () => {
      const html = tpl.innerHTML.replaceAll('__Q__', 'n' + (counter++));
      qWrap.insertAdjacentHTML('beforeend', html);
      renumber();
      qWrap.lastElementChild.querySelector('textarea')?.focus();
    });
    qWrap.addEventListener('click', (e) => {
      const add = e.target.closest('[data-add-option]');
      const rm = e.target.closest('[data-remove-option]');
      const rmq = e.target.closest('[data-remove-question]');
      if (add) {
        const box = add.closest('.q-editor');
        const key = box.dataset.key;
        const opts = box.querySelector('.opts');
        const idx = 'n' + (counter++);
        opts.insertAdjacentHTML('beforeend',
          `<div class="opt"><input type="radio" name="questions[${key}][correct]" value="${idx}" aria-label="Alternativa correta">` +
          `<input class="input" name="questions[${key}][options][${idx}]" placeholder="Texto da alternativa" required>` +
          `<button type="button" class="btn btn-ghost btn-sm" data-remove-option aria-label="Remover alternativa">✕</button></div>`);
        opts.lastElementChild.querySelector('.input').focus();
      }
      if (rm) {
        const opts = rm.closest('.opts');
        if (opts.children.length > 2) rm.closest('.opt').remove();
      }
      if (rmq && $$('.q-editor', qWrap).length > 1) { rmq.closest('.q-editor').remove(); renumber(); }
    });
    const renumber = () => $$('.q-editor .q-num', qWrap).forEach((el, i) => { el.textContent = 'Questão ' + (i + 1); });
  }

  // ------------------------------------------------ região de anúncios para leitores de tela
  function announce(text) {
    let live = $('#live-region');
    if (!live) {
      live = document.createElement('div');
      live.id = 'live-region';
      live.className = 'sr-only';
      live.setAttribute('aria-live', 'polite');
      document.body.appendChild(live);
    }
    live.textContent = text;
  }
  window.eadAnnounce = announce;

  // ------------------------------------------------ cookies (LGPD) + Google Tag Manager / GA4
  const body = document.body;
  const gtmId = body.dataset.gtm;
  const gaId = body.dataset.ga;
  const banner = $('#cookie-banner');
  const readConsent = () => (document.cookie.match(/(?:^|; )ead_consent=([^;]+)/) || [])[1] || null;

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = window.gtag || gtag;
  // Consent Mode v2: tudo negado até o visitante aceitar.
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });

  function loadTags() {
    if (window.__tagsLoaded) return;
    window.__tagsLoaded = true;
    gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
    if (gtmId) {
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(gtmId);
      document.head.appendChild(s);
    }
    if (gaId) {
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
      document.head.appendChild(s);
      gtag('js', new Date());
      gtag('config', gaId);
    }
  }

  function saveConsent(value) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `ead_consent=${value}; Max-Age=${60 * 60 * 24 * 180}; Path=/; SameSite=Lax${secure}`;
    fetch(body.dataset.consentUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'Accept': 'application/json' },
      body: JSON.stringify({ choice: value }),
    }).catch(() => {});
    if (value === 'all') loadTags();
    if (banner) banner.hidden = true;
  }

  const consent = readConsent();
  if (consent === 'all') loadTags();
  if (banner && !consent) banner.hidden = false;
  $$('[data-consent]').forEach((btn) => btn.addEventListener('click', () => saveConsent(btn.dataset.consent)));
  $$('[data-open-cookies]').forEach((btn) => btn.addEventListener('click', (e) => { e.preventDefault(); if (banner) banner.hidden = false; }));

  // Eventos de negócio para o GTM (cadastro, matrícula, conclusão).
  $$('[data-track-event]').forEach((el) => window.dataLayer.push({ event: el.dataset.trackEvent, ...(el.dataset.trackParams ? JSON.parse(el.dataset.trackParams) : {}) }));
})();
