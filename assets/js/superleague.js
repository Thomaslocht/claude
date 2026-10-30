/* ==========================================================================
   ZERO26. Coaching Club — page SuperLeague
   Apparitions au défilement, « Suivre un appareil », date et clôture
   (assets/data/superleague.json) et formulaire d'inscription.
   Sans ce script, la page reste entièrement lisible.
   ========================================================================== */
(() => {
  'use strict';

  const CONFIG = {
    // Enregistre l'inscription dans la liste téléchargeable (superleague-admin.php)
    endpoint: 'superleague-inscription.php',
    // Date de l'épreuve, clôture, places : à modifier dans ce fichier
    settings: 'assets/data/superleague.json',
    // Copie de chaque inscription envoyée à l'équipe par e-mail (même service que le
    // formulaire de contact). Laissez '' pour ne rien envoyer par e-mail.
    notify: 'https://formsubmit.co/ajax/team@zero26coachingclub.com',
    email: 'team@zero26coachingclub.com',
    phone: '07 64 62 30 85'
  };

  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const root = document.documentElement;
  const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const NBSP = ' ';

  /* ---------- Apparitions au défilement ---------- */
  const reveals = $$('[data-sl-reveal]');
  if (!REDUCE && 'IntersectionObserver' in window) {
    root.classList.add('sl-anim');
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    reveals.forEach(el => io.observe(el));
  } else {
    reveals.forEach(el => el.classList.add('is-in'));
  }

  const FINE = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const frac = (n, d = 1) => n.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
  const mmss = sec => `${Math.floor(sec / 60)}:${String(Math.floor(sec % 60)).padStart(2, '0')}`;
  const visibleWatch = (el, cb) => {
    if (!('IntersectionObserver' in window)) { cb(true); return; }
    new IntersectionObserver(([e]) => cb(e.isIntersecting), { threshold: 0 }).observe(el);
  };

  /* ---------- Mode jour / nuit (mémorisé ; appliqué avant l'affichage par le script du <head>) ---------- */
  const themeBtn = $('[data-sl-theme]');
  const metaTheme = $('meta[name="theme-color"]');
  const setTheme = (day, save) => {
    if (save && !REDUCE) {
      root.classList.add('sl-theming');
      setTimeout(() => root.classList.remove('sl-theming'), 650);
    }
    if (day) root.dataset.theme = 'day'; else delete root.dataset.theme;
    if (themeBtn) {
      themeBtn.setAttribute('aria-pressed', String(day));
      themeBtn.setAttribute('aria-label', day ? 'Passer en mode nuit' : 'Passer en mode jour');
    }
    if (metaTheme) metaTheme.content = day ? '#f3f2f8' : '#08080a';
    if (save) { try { localStorage.setItem('z26-theme', day ? 'day' : 'night'); } catch (e) { /* stockage indisponible */ } }
  };
  setTheme(root.dataset.theme === 'day', false);
  if (themeBtn) themeBtn.addEventListener('click', () => setTheme(root.dataset.theme !== 'day', true));

  /* ---------- Haut de page ---------- */
  const hero = $('[data-sl-hero]');
  const title = hero && $('.sl-hero__title', hero);
  const hud = hero && $('[data-sl-hud]', hero);

  // Fond : un écosystème de statistiques d'athlète (décoratif, valeurs d'illustration)
  const AXES = ['Endurance', 'Force', 'Puissance', 'Vitesse', 'Explosivité', 'Gainage'];
  const PROFILES = [
    { st: 'ski', name: 'SKI', v: [.72, .78, .92, .62, .82, .88] },
    { st: 'run', name: 'RUN', v: [.92, .56, .7, .9, .66, .6] },
    { st: 'row', name: 'ROW', v: [.84, .88, .84, .58, .72, .76] }
  ];
  let hudState = null;
  if (hud) {
    const W = window.innerWidth;
    const small = W < 760;
    const R = 78;
    const radarPts = v => v.map((x, i) => {
      const a = -Math.PI / 2 + i * Math.PI * 2 / AXES.length;
      return `${(Math.cos(a) * R * x).toFixed(1)},${(Math.sin(a) * R * x).toFixed(1)}`;
    }).join(' ');
    const ring = k => AXES.map((_, i) => {
      const a = -Math.PI / 2 + i * Math.PI * 2 / AXES.length;
      return `${(Math.cos(a) * R * k).toFixed(1)},${(Math.sin(a) * R * k).toFixed(1)}`;
    }).join(' ');
    const axisLabels = AXES.map((t, i) => {
      const a = -Math.PI / 2 + i * Math.PI * 2 / AXES.length;
      const x = Math.cos(a) * (R + 14);
      const y = Math.sin(a) * (R + 14);
      const anchor = Math.abs(x) < 4 ? 'middle' : x > 0 ? 'start' : 'end';
      return `<line x1="0" y1="0" x2="${(Math.cos(a) * R).toFixed(1)}" y2="${(Math.sin(a) * R).toFixed(1)}"/><text x="${x.toFixed(1)}" y="${(y + 3).toFixed(1)}" text-anchor="${anchor}">${t}</text>`;
    }).join('');
    const bars = Array.from({ length: 28 }, (_, i) => `<rect x="${i * 8}" width="5" rx="1.5" y="60" height="0"/>`).join('');
    const widgets = [
      { cls: 'sl-hud__chrono', pos: small ? 'right:4%;top:25%' : 'right:2%;top:39%', d: .25, html: '<span data-hud="chrono">0:00,0</span>' },
      { cls: 'sl-hud__panel', pos: 'left:1.5%;top:17%', d: .7, min: 600, html: `<div class="sl-hud__k"><span>Fréquence cardiaque</span><span>FC</span></div><div class="sl-hud__v"><span data-hud="hr">168</span><small>bpm</small></div><svg width="236" height="58" viewBox="0 0 236 58" style="margin-top:8px"><polyline data-hud="hr-line" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points=""/></svg>` },
      { cls: 'sl-hud__panel', pos: 'right:1.5%;top:19%', d: 1, min: 760, html: `<div class="sl-hud__k"><span>Profil athlète</span><span data-hud="profile">SKI</span></div><svg width="230" height="200" viewBox="-115 -100 230 200"><g fill="none" style="stroke:rgba(var(--hud),.22)" stroke-width="1"><polygon points="${ring(1)}"/><polygon points="${ring(.66)}"/><polygon points="${ring(.33)}"/></g><g style="stroke:rgba(var(--hud),.18);fill:rgba(var(--hud),.8)" font-size="9.5" font-weight="600" letter-spacing=".04em">${axisLabels}</g><polygon data-hud="radar" points="${radarPts(PROFILES[0].v)}" style="fill:var(--ski);stroke:var(--ski)" fill-opacity=".2" stroke-width="2" stroke-linejoin="round"/></svg>` },
      { cls: 'sl-hud__panel', pos: 'right:25%;top:8.5%', d: .55, min: 1100, html: `<div class="sl-hud__k"><span>Puissance</span><span>W</span></div><div class="sl-hud__v"><span data-hud="watt">312</span><small>W</small></div><svg width="220" height="60" viewBox="0 0 220 60" style="margin-top:8px"><g data-hud="bars" fill="currentColor" fill-opacity=".55">${bars}</g></svg>` },
      { cls: '', pos: 'left:2.5%;top:43%', d: .4, min: 960, html: `<div class="sl-hud__k"><span>Allure / 500 m</span></div><div class="sl-hud__v"><span data-hud="pace">1:42,6</span></div><div class="sl-hud__k" style="margin-top:16px"><span>Cadence</span></div><div class="sl-hud__v"><span data-hud="spm">31</span><small>spm</small></div><div class="sl-hud__k" style="margin-top:16px"><span>VO2 max</span></div><div class="sl-hud__v">54<small>ml/kg/min</small></div>` },
      { cls: 'sl-hud__panel', pos: 'right:1.5%;top:50%', d: .8, min: 1100, html: `<div class="sl-hud__k"><span>Splits · manche 1</span><span>5:43</span></div><svg width="230" height="8" viewBox="0 0 230 8"><rect x="0" width="117" height="8" rx="4" style="fill:var(--ski)"/><rect x="119" width="75" height="8" rx="4" style="fill:var(--run)"/><rect x="196" width="34" height="8" rx="4" style="fill:var(--row)"/></svg><div class="sl-hud__k" style="margin:10px 0 0;justify-content:flex-start;gap:14px"><span>SKI 2:58</span><span>RUN 1:54</span><span>ROW 0:51</span></div>` }
    ].filter(w => !w.min || W >= w.min);
    hud.innerHTML = '<div class="sl-hud__grid"></div>'
      + widgets.map((w, i) => `<div class="sl-hud__w ${w.cls}" style="${w.pos};--i:${i}" data-depth="${w.d}">${w.html}</div>`).join('')
      + (FINE ? '<div class="sl-hud__cross"><i></i><i></i><b></b></div>' : '');
    if (small) hud.classList.add('is-small');

    const q = k => $(`[data-hud="${k}"]`, hud);
    hudState = {
      ws: $$('.sl-hud__w', hud).map(el => ({ el, d: Number(el.dataset.depth), x: 0, y: 0 })),
      hr: q('hr'), hrLine: q('hr-line'), radar: q('radar'), profile: q('profile'),
      bars: q('bars') ? $$('rect', q('bars')) : [], watt: q('watt'), pace: q('pace'), spm: q('spm'), chrono: q('chrono'),
      cross: $('.sl-hud__cross', hud),
      hrPts: Array.from({ length: 48 }, (_, i) => 166 + Math.sin(i / 3) * 5),
      power: Array.from({ length: 28 }, () => 260 + Math.random() * 110),
      t0: performance.now(), radarPts
    };
  }

  // Titre : entrée « police qui se serre », puis « SUPER » passe en italique
  if (title) {
    let started = false;
    const go = () => {
      if (started) return;
      started = true;
      if (!REDUCE) { root.classList.add('sl-intro'); if (hud) hud.classList.add('sl-hud-in'); }
      title.classList.add('is-ready');
    };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(go);
    setTimeout(go, 1200);
  }

  // Animation continue du fond, du titre et du réticule (uniquement quand le haut de page est visible)
  if (hero) {
    let px = null;
    let py = null;
    let onScreen = true;
    let raf = 0;
    let frame = 0;
    const H = hudState;
    const drawHr = () => {
      if (!H.hrLine) return;
      const n = H.hrPts.length;
      H.hrLine.setAttribute('points', H.hrPts.map((v, i) => `${(i * 236 / (n - 1)).toFixed(1)},${(58 - (v - 150) * 58 / 35).toFixed(1)}`).join(' '));
    };
    const drawBars = () => H.bars.forEach((r, i) => {
      const h = Math.max(4, (H.power[i] - 180) * 60 / 220);
      r.setAttribute('y', (60 - h).toFixed(1));
      r.setAttribute('height', h.toFixed(1));
    });
    if (H) { drawHr(); drawBars(); }

    const tick = now => {
      raf = 0;
      if (!onScreen || document.hidden) return;
      frame += 1;
      const rect = hero.getBoundingClientRect();
      const scrolled = Math.max(0, -rect.top);
      if (H) {
        const t = Math.max(0, (now - H.t0) / 1000);
        if (!REDUCE) {
          if (frame % 4 === 0) {
            H.hrPts.shift();
            H.hrPts.push(168 + Math.sin(t / 1.6) * 6 + Math.sin(t * 3.1) * 2 + (Math.random() - .5) * 3);
            drawHr();
          }
          if (frame % 30 === 0 && H.hr) H.hr.textContent = Math.round(H.hrPts[H.hrPts.length - 1]);
          if (frame % 9 === 0 && H.bars.length) {
            H.power.shift();
            H.power.push(250 + Math.random() * 130 + Math.sin(t) * 20);
            drawBars();
            if (H.watt) H.watt.textContent = Math.round(H.power[H.power.length - 1]);
          }
          if (frame % 40 === 0) {
            if (H.pace) H.pace.textContent = `1:${frac(40 + Math.random() * 5)}`.replace(/^1:(\d),/, '1:0$1,');
            if (H.spm) H.spm.textContent = 29 + Math.round(Math.random() * 4);
          }
          if (H.chrono) H.chrono.textContent = `${mmss(t % 1500)},${Math.floor((t * 10) % 10)}`;
          if (H.radar) {
            // Le profil passe d'un appareil à l'autre toutes les 4 secondes
            const cyc = t / 4;
            const k = Math.floor(cyc) % PROFILES.length;
            const nxt = PROFILES[(k + 1) % PROFILES.length];
            const p = Math.min(1, Math.max(0, (cyc % 1 - .7) / .3));
            const e = p * p * (3 - 2 * p);
            const cur = PROFILES[k];
            H.radar.setAttribute('points', H.radarPts(cur.v.map((v, i) => v + (nxt.v[i] - v) * e)));
            const show = e > .5 ? nxt : cur;
            if (H.profile.textContent !== show.name) {
              H.profile.textContent = show.name;
              H.radar.style.fill = `var(--${show.st})`;
              H.radar.style.stroke = `var(--${show.st})`;
            }
          }
        }
        // Profondeur : chaque élément du fond suit le pointeur et le défilement à sa propre vitesse
        const mx = px === null ? 0 : (px - rect.left) / rect.width - .5;
        const my = py === null ? 0 : (py - rect.top) / Math.min(rect.height, window.innerHeight) - .5;
        H.ws.forEach(w => {
          const tx = REDUCE ? 0 : -mx * 36 * w.d;
          const ty = REDUCE ? 0 : -my * 24 * w.d - scrolled * .35 * w.d;
          w.x += (tx - w.x) * .08;
          w.y += (ty - w.y) * .08;
          w.el.style.transform = `translate3d(${w.x.toFixed(1)}px, ${w.y.toFixed(1)}px, 0)`;
        });
        if (H.cross && px !== null) {
          H.cross.style.setProperty('--cx', `${(px - rect.left).toFixed(0)}px`);
          H.cross.style.setProperty('--cy', `${(py - rect.top).toFixed(0)}px`);
          $('b', H.cross).textContent = `x ${frac(mx + .5, 2)} · y ${frac(my + .5, 2)}`;
        }
      }
      // Le titre remonte un peu plus vite que la page : il ne chevauche jamais le texte du dessous
      if (title && !REDUCE) title.style.transform = scrolled ? `translate3d(0, ${(-scrolled * .12).toFixed(1)}px, 0)` : '';
      if (!REDUCE) raf = requestAnimationFrame(tick);
    };
    const request = () => { if (!raf) raf = requestAnimationFrame(tick); };
    visibleWatch(hero, v => { onScreen = v; if (v) request(); });
    document.addEventListener('visibilitychange', request);
    if (FINE) {
      hero.addEventListener('pointermove', e => {
        px = e.clientX;
        py = e.clientY;
        if (H && H.cross) H.cross.classList.add('is-on');
        request();
      });
      hero.addEventListener('pointerleave', () => { px = null; py = null; if (H && H.cross) H.cross.classList.remove('is-on'); });
    }
    request();
  }

  /* ---------- Photos du club : dévoilées une à une, chiffres de l'appareil ---------- */
  const cards = $$('.sl-reel__card');
  if (cards.length && 'IntersectionObserver' in window) {
    cards.forEach((c, i) => c.querySelector('.sl-reel__media').style.setProperty('--delay', `${i * .14}s`));
    const reveal = new IntersectionObserver(entries => entries.forEach(e => {
      if (!e.isIntersecting) return;
      e.target.classList.add('is-in');
      reveal.unobserve(e.target);
    }), { threshold: .15 });
    cards.forEach(c => reveal.observe(c));
    // Sur écran tactile (pas de survol), les chiffres s'affichent quand la photo est bien visible
    if (!FINE) {
      const data = new IntersectionObserver(entries => entries.forEach(e => e.target.classList.toggle('is-data', e.intersectionRatio > .6)), { threshold: [0, .6, 1] });
      cards.forEach(c => data.observe(c));
    }
  } else {
    cards.forEach(c => c.classList.add('is-in'));
  }
  if (cards.length && !REDUCE && window.gsap && window.ScrollTrigger) {
    const { gsap, ScrollTrigger } = window;
    gsap.registerPlugin(ScrollTrigger);
    // L'image glisse lentement dans son cadre (le cadre, lui, reste aligné)
    cards.forEach(c => gsap.fromTo($('img', c), { yPercent: 0 }, { yPercent: -10, ease: 'none', scrollTrigger: { trigger: c, start: 'top bottom', end: 'bottom top', scrub: .5 } }));
    window.addEventListener('load', () => ScrollTrigger.refresh());
  }

  /* ---------- Le déroulé : planning de la manche 1 qui se joue en boucle ---------- */
  const board = $('[data-sl-board]');
  if (board) {
    const plot = $('.sl-board__plot', board);
    const span = Number(getComputedStyle(plot).getPropertyValue('--span')) || 22;
    const segs = $$('.sl-seg', board).map(el => ({ el, s: Number(el.style.getPropertyValue('--s')), l: Number(el.style.getPropertyValue('--l')) }));
    const head = $('[data-board-playhead]', board);
    const clock = $('[data-board-clock]', board);
    const render = t => {
      head.style.setProperty('--t', t.toFixed(3));
      segs.forEach(({ el, s, l }) => {
        el.classList.toggle('is-done', t >= s + l);
        el.classList.toggle('is-on', t >= s && t < s + l);
      });
      clock.textContent = mmss(t * 60);
    };
    if (!REDUCE) {
      board.classList.add('is-live');
      let t = 0;
      let last = 0;
      let hold = 0;
      let raf = 0;
      let onScreen = false;
      const step = now => {
        raf = 0;
        if (!onScreen || document.hidden) { last = 0; return; }
        const dt = last ? Math.min(.1, (now - last) / 1000) : 0;
        last = now;
        if (hold > 0) {
          hold -= dt;
          if (hold <= 0) t = 0;
        } else {
          t += dt * 2; // 2 minutes de course par seconde
          if (t >= span) { t = span; hold = 1.8; }
        }
        render(t);
        raf = requestAnimationFrame(step);
      };
      visibleWatch(board, v => { onScreen = v; if (v && !raf) raf = requestAnimationFrame(step); });
      document.addEventListener('visibilitychange', () => { if (!raf && onScreen) raf = requestAnimationFrame(step); });
      render(0);
    } else {
      clock.textContent = `${span} min`;
    }
  }

  // Pastilles SKI / RUN / ROW du haut de page : mènent à l'épreuve, appareil déjà sélectionné
  $$('[data-station]').forEach(a => a.addEventListener('click', () => {
    const btn = $(`[data-focus="${a.dataset.station}"]`);
    if (btn) btn.click();
  }));

  /* ---------- Suivre un appareil ---------- */
  const manches = $('[data-manches]');
  const note = $('[data-focus-note]');
  const NAMES = { ski: 'SKI', run: 'RUN', row: 'ROW' };
  const defaultNote = note ? note.innerHTML : '';
  $$('[data-focus]').forEach(btn => {
    btn.addEventListener('click', () => {
      const st = btn.dataset.focus;
      $$('[data-focus]').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
      if (!manches) return;
      if (st) manches.dataset.focus = st; else delete manches.dataset.focus;
      if (!note) return;
      if (!st) { note.innerHTML = defaultNote; return; }
      const parts = $$('.sl-manche', manches).map((m, i) => {
        const step = $(`.sl-step[data-st="${st}"]`, m);
        return step ? `${step.dataset.m}${NBSP}m en manche${NBSP}${i + 1}` : null;
      }).filter(Boolean);
      const total = $$(`.sl-step[data-st="${st}"]`, manches).reduce((s, el) => s + Number(el.dataset.m || 0), 0);
      note.innerHTML = `<strong>${NAMES[st]}</strong>${NBSP}: ${parts.join(', ')}. Soit ${total.toLocaleString('fr-FR')}${NBSP}m au total.`;
    });
  });

  /* ---------- Date, clôture et état des inscriptions ---------- */
  const stateEl = $('[data-sl-state]');
  const stateTxt = $('[data-sl-state-text]');
  const form = $('[data-sl-form]');
  const done = $('[data-sl-done]');
  const closed = $('[data-sl-closed]');
  let isOpen = true;

  const longDate = iso => {
    const d = new Date(/T/.test(iso) ? iso : `${iso}T12:00:00`);
    if (Number.isNaN(d.getTime())) return '';
    const s = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
      .format(d)
      .replace(/ 1 (?=\D)/, ' 1er ');
    return s.charAt(0).toUpperCase() + s.slice(1);
  };

  const showClosed = reason => {
    isOpen = false;
    const full = reason === 'full';
    if (stateEl) stateEl.classList.add('is-closed');
    if (stateTxt) stateTxt.textContent = full ? 'Complet' : 'Inscriptions clôturées';
    if (closed) {
      $('[data-closed-title]', closed).textContent = full ? 'C’est complet' : 'Inscriptions clôturées';
      if (done && done.hidden) { form.hidden = true; closed.hidden = false; }
    }
  };

  const applySettings = cfg => {
    const date = cfg.dateLabel || (cfg.date ? longDate(cfg.date) : '');
    if (date) $$('[data-sl-date]').forEach(el => { el.textContent = date; });
    const cloture = cfg.cloture ? new Date(cfg.cloture) : null;
    const clotureLabel = cfg.clotureLabel || (cloture ? longDate(cfg.cloture) : '');
    if (clotureLabel) $$('[data-sl-cloture]').forEach(el => { el.textContent = clotureLabel; });
    if (cloture && !Number.isNaN(cloture.getTime())) {
      const left = cloture - Date.now();
      if (left <= 0) { showClosed('closed'); return; }
      const days = Math.floor(left / 86400000);
      if (stateTxt) stateTxt.textContent = days >= 1
        ? `Inscriptions ouvertes · clôture dans ${days}${NBSP}jour${days > 1 ? 's' : ''}`
        : 'Inscriptions ouvertes · dernières heures';
    }
  };

  fetch(CONFIG.settings, { cache: 'no-cache' })
    .then(r => (r.ok ? r.json() : {}))
    .then(applySettings)
    .catch(() => {});

  // État réel côté serveur (clôture, places restantes)
  fetch(CONFIG.endpoint, { cache: 'no-store', headers: { Accept: 'application/json' } })
    .then(r => (r.ok ? r.json() : null))
    .then(s => {
      if (!s || !s.ok) return;
      if (!s.open) { showClosed(s.reason); return; }
      if (typeof s.remaining === 'number' && stateTxt) {
        stateTxt.textContent = `Inscriptions ouvertes · ${s.remaining}${NBSP}place${s.remaining > 1 ? 's' : ''} restante${s.remaining > 1 ? 's' : ''}`;
      }
    })
    .catch(() => {});

  /* ---------- Formulaire d'inscription ---------- */
  if (!form) return;
  const status = $('.form__status', form);
  const btn = $('button[type="submit"]', form);
  const sexeSet = $('[data-sl-sexe]', form);
  const setStatus = (t, type) => { status.textContent = t; status.className = 'form__status' + (type ? ` is-${type}` : ''); };
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const telOk = v => v.replace(/\D/g, '').length >= 9;

  const markField = (input, ok) => {
    if (input.type === 'radio') sexeSet.classList.toggle('is-invalid', !ok);
    else input.closest('.field').classList.toggle('is-invalid', !ok);
  };
  $$('input', form).forEach(f => f.addEventListener('input', () => markField(f, true)));
  $$('input[name="sexe"]', form).forEach(f => f.addEventListener('change', () => markField(f, true)));

  const validate = () => {
    let first = null;
    const check = (input, ok) => { markField(input, ok); if (!ok && !first) first = input; };
    check(form.prenom, form.prenom.value.trim() !== '');
    check(form.nom, form.nom.value.trim() !== '');
    const sexe = $('input[name="sexe"]:checked', form);
    check($('input[name="sexe"]', form), !!sexe);
    check(form.email, EMAIL_RE.test(form.email.value.trim()));
    check(form.tel, telOk(form.tel.value));
    return first;
  };

  const values = () => ({
    prenom: form.prenom.value.trim(),
    nom: form.nom.value.trim(),
    sexe: ($('input[name="sexe"]:checked', form) || {}).value || '',
    email: form.email.value.trim(),
    tel: form.tel.value.trim(),
    site: form.site.value
  });

  // Copie par e-mail à l'équipe ; si l'enregistrement a échoué, c'est elle qui sauve l'inscription
  const mailTeam = (d, saved) => {
    if (!CONFIG.notify) return Promise.resolve(false);
    return fetch(CONFIG.notify, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        _subject: `SuperLeague — Inscription — ${d.prenom} ${d.nom}`,
        _template: 'table',
        _captcha: 'false',
        _replyto: d.email,
        'Prénom': d.prenom,
        'Nom': d.nom,
        'Sexe': d.sexe,
        'E-mail': d.email,
        'Téléphone': d.tel,
        'Liste des inscrits': saved ? 'Ajoutée automatiquement' : 'NON ENREGISTRÉE : à ajouter à la main'
      })
    }).then(r => r.json().then(res => r.ok && String(res.success) === 'true')).catch(() => false);
  };

  const openMail = d => {
    const body = [`Prénom : ${d.prenom}`, `Nom : ${d.nom}`, `Sexe : ${d.sexe}`, `E-mail : ${d.email}`, `Téléphone : ${d.tel}`].join('\n');
    window.location.href = `mailto:${CONFIG.email}?subject=${encodeURIComponent(`Inscription SuperLeague — ${d.prenom} ${d.nom}`)}&body=${encodeURIComponent(body)}`;
  };

  const showDone = d => {
    $('[data-done-name]', done).textContent = d.prenom ? `, ${d.prenom}` : '';
    const recap = $('[data-done-recap]', done);
    recap.textContent = '';
    [['Athlète', `${d.prenom} ${d.nom}`.trim()], ['Catégorie', d.sexe], ['E-mail', d.email], ['Téléphone', d.tel]].forEach(([k, v]) => {
      const row = document.createElement('div');
      const dt = document.createElement('dt');
      const dd = document.createElement('dd');
      dt.textContent = k;
      dd.textContent = v;
      row.append(dt, dd);
      recap.appendChild(row);
    });
    form.hidden = true;
    done.hidden = false;
    done.focus({ preventScroll: true });
    done.scrollIntoView({ behavior: REDUCE ? 'auto' : 'smooth', block: 'center' });
    if (window.z26track) window.z26track('lead', { label: 'Inscription SuperLeague', offre: 'SuperLeague' });
  };

  $('[data-sl-again]', done).addEventListener('click', () => {
    form.reset();
    setStatus('');
    done.hidden = true;
    if (!isOpen) { closed.hidden = false; return; }
    form.hidden = false;
    form.prenom.focus();
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const first = validate();
    if (first) { setStatus('Merci de compléter les champs indiqués.', 'error'); first.focus(); return; }

    const d = values();
    if (d.site) { showDone(d); return; } // robot : on ne garde rien

    btn.disabled = true;
    setStatus('Envoi en cours…');
    let saved = false;
    try {
      const r = await fetch(CONFIG.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(d)
      });
      const res = await r.json().catch(() => null);
      if (r.ok && res && res.ok) {
        saved = true;
      } else if (res && res.error && res.error !== 'server') {
        // Refus explicite du serveur : on explique, sans contourner
        btn.disabled = false;
        if (res.error === 'closed' || res.error === 'full') { showClosed(res.error); return; }
        if (res.error === 'fields' && Array.isArray(res.fields)) {
          res.fields.forEach(name => { const f = form.elements[name]; if (f) markField(f.length ? f[0] : f, false); });
        }
        setStatus(res.message || 'L’inscription n’a pas pu être enregistrée.', 'error');
        return;
      }
    } catch (err) { /* serveur injoignable : on passe à la copie par e-mail */ }

    if (saved) {
      mailTeam(d, true);
      showDone(d);
      btn.disabled = false;
      return;
    }
    if (await mailTeam(d, false)) {
      showDone(d);
      btn.disabled = false;
      return;
    }
    setStatus(`L’envoi automatique n’a pas abouti : votre messagerie va s’ouvrir avec l’inscription pré-remplie (ou appelez-nous au ${CONFIG.phone}).`, 'error');
    setTimeout(() => openMail(d), 1400);
    btn.disabled = false;
  });
})();
