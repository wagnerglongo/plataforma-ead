/*
 * Player da aula: retoma de onde o aluno parou e registra o tempo REALMENTE assistido.
 * Avançar a barra não conta como assistido: só soma o tempo entre atualizações
 * consecutivas da reprodução (saltos maiores que 1,5 s são ignorados).
 */
(function () {
  'use strict';

  const root = document.getElementById('player-root');
  if (!root) return;

  const cfg = {
    trackUrl: root.dataset.trackUrl,
    start: parseInt(root.dataset.start || '0', 10),
    duration: parseInt(root.dataset.duration || '0', 10),
    completed: root.dataset.completed === '1',
    provider: root.dataset.provider,
  };
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const watchBar = document.getElementById('watch-bar');
  const watchText = document.getElementById('watch-text');
  const courseBar = document.getElementById('course-bar');
  const courseText = document.getElementById('course-text');
  const nextBtn = document.getElementById('next-btn');

  let pending = 0;
  let lastTime = null;
  let position = cfg.start;
  let sending = false;

  function onTime(current) {
    if (lastTime !== null) {
      const diff = current - lastTime;
      if (diff > 0 && diff < 1.5) pending += diff;
    }
    lastTime = current;
    position = current;
  }

  function send(keepalive) {
    if (sending && !keepalive) return;
    const delta = Math.floor(pending);
    if (delta < 1 && !keepalive) return;
    pending -= delta;
    sending = true;
    fetch(cfg.trackUrl, {
      method: 'POST',
      keepalive: !!keepalive,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
      body: JSON.stringify({ position: Math.floor(position), delta, duration: Math.floor(cfg.duration) }),
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then(update)
      .catch(() => { pending += delta; })
      .finally(() => { sending = false; });
  }

  function update(data) {
    if (!data) return;
    if (watchBar) watchBar.style.setProperty('--value', data.watched_percent + '%');
    if (watchText && !cfg.completed) watchText.textContent = data.completed ? 'Aula concluída' : `Assistido: ${data.watched_percent}% (conclui com 85%)`;
    if (data.completed && !cfg.completed) {
      cfg.completed = true;
      markDone();
    }
    if (typeof data.course_percent === 'number') {
      courseBar?.style.setProperty('--value', data.course_percent + '%');
      if (courseText) courseText.textContent = data.course_percent + '%';
    }
    if (data.certificate_url) {
      window.location.href = data.certificate_url;
    }
  }

  function markDone() {
    if (watchText) watchText.textContent = 'Aula concluída';
    if (watchBar) { watchBar.style.setProperty('--value', '100%'); watchBar.dataset.done = '1'; }
    document.getElementById('done-badge')?.removeAttribute('hidden');
    const current = document.querySelector('.side-items a[aria-current="page"]');
    if (current) {
      current.parentElement.classList.add('is-done');
      const ico = current.querySelector('svg');
      if (ico) ico.outerHTML = document.getElementById('icon-done').innerHTML;
    }
    nextBtn?.classList.replace('btn-secondary', 'btn-marker');
    window.eadAnnounce && window.eadAnnounce('Aula concluída. Siga para a próxima.');
  }

  setInterval(() => send(false), 10000);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') send(true); });
  window.addEventListener('pagehide', () => send(true));

  // -------------------------------------------- vídeo próprio (HTML5)
  if (cfg.provider === 'local') {
    const video = document.getElementById('lesson-video');
    video.addEventListener('loadedmetadata', () => {
      if (!cfg.duration) cfg.duration = video.duration;
      if (cfg.start > 2 && cfg.start < video.duration - 5) {
        video.currentTime = cfg.start;
        document.getElementById('resume-note')?.removeAttribute('hidden');
      }
      lastTime = video.currentTime;
    });
    video.addEventListener('timeupdate', () => onTime(video.currentTime));
    video.addEventListener('seeking', () => { lastTime = null; });
    video.addEventListener('pause', () => send(true));
    video.addEventListener('ended', () => send(true));
  }

  // -------------------------------------------- YouTube (IFrame API)
  if (cfg.provider === 'youtube') {
    const el = document.getElementById('yt-player');
    window.onYouTubeIframeAPIReady = function () {
      const player = new window.YT.Player(el, {
        videoId: el.dataset.video,
        host: 'https://www.youtube-nocookie.com',
        playerVars: { start: cfg.start > 2 ? cfg.start : 0, rel: 0, modestbranding: 1 },
        events: {
          onReady: () => { if (!cfg.duration) cfg.duration = player.getDuration(); },
          onStateChange: (e) => { if (e.data === 2 || e.data === 0) send(true); if (e.data === 1) lastTime = player.getCurrentTime(); },
        },
      });
      setInterval(() => {
        if (player.getPlayerState && player.getPlayerState() === 1) onTime(player.getCurrentTime());
      }, 500);
    };
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
  }

  // -------------------------------------------- Vimeo (Player API via postMessage)
  if (cfg.provider === 'vimeo') {
    const tag = document.createElement('script');
    tag.src = 'https://player.vimeo.com/api/player.js';
    tag.onload = () => {
      const player = new window.Vimeo.Player(document.getElementById('vimeo-player'));
      if (cfg.start > 2) player.setCurrentTime(cfg.start).catch(() => {});
      player.getDuration().then((d) => { if (!cfg.duration) cfg.duration = d; });
      player.on('timeupdate', (d) => onTime(d.seconds));
      player.on('seeked', () => { lastTime = null; });
      player.on('pause', () => send(true));
      player.on('ended', () => send(true));
    };
    document.head.appendChild(tag);
  }
})();
