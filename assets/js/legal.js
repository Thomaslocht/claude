/* ==========================================================================
   ZERO26. Coaching Club — pages légales (CGV, règlement intérieur)
   Script léger, sans bibliothèque : menu mobile, libellés animés au survol,
   barre de progression de lecture et sommaire qui suit la lecture.
   ========================================================================== */
(() => {
  'use strict';

  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const root = document.documentElement;
  const NBSP = ' ';

  root.classList.add('hero-ready');

  // Libellés qui « roulent » lettre par lettre au survol (comme sur l'accueil)
  $$('[data-roll]').forEach(el => {
    const text = el.textContent.trim();
    if (!text) return;
    el.textContent = '';
    const sr = document.createElement('span');
    sr.className = 'sr-only';
    sr.textContent = text;
    const roll = document.createElement('span');
    roll.className = 'roll';
    roll.setAttribute('aria-hidden', 'true');
    Array.from(text).forEach((c, i) => {
      const s = document.createElement('span');
      const ch = c === ' ' ? NBSP : c;
      s.className = 'roll__c';
      s.style.setProperty('--i', i);
      s.dataset.c = ch;
      s.textContent = ch;
      roll.appendChild(s);
    });
    el.append(sr, roll);
  });

  // Menu mobile
  const burger = $('.nav__burger');
  const menu = $('#menu');
  if (burger && menu) {
    let open = false;
    const set = v => {
      open = v;
      menu.classList.toggle('is-open', v);
      menu.toggleAttribute('inert', !v);
      menu.setAttribute('aria-hidden', String(!v));
      burger.setAttribute('aria-expanded', String(v));
      burger.setAttribute('aria-label', v ? 'Fermer le menu' : 'Ouvrir le menu');
      root.classList.toggle('menu-open', v);
      document.body.style.overflow = v ? 'hidden' : '';
    };
    burger.addEventListener('click', () => set(!open));
    $$('a', menu).forEach(a => a.addEventListener('click', () => set(false)));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && open) { set(false); burger.focus(); }
    });
    window.matchMedia('(min-width: 1181px)').addEventListener('change', e => { if (e.matches && open) set(false); });
  }

  // Sommaire : ouvert en permanence sur ordinateur, repliable sur mobile
  const toc = $('.toc');
  const wide = window.matchMedia('(min-width: 961px)');
  if (toc) {
    const sync = () => { toc.open = wide.matches; };
    sync();
    wide.addEventListener('change', sync);
    $('summary', toc).addEventListener('click', e => { if (wide.matches) e.preventDefault(); });
    $$('a', toc).forEach(a => a.addEventListener('click', () => { if (!wide.matches) toc.open = false; }));
  }

  // Sommaire qui suit la lecture
  const links = toc ? $$('a[href^="#"]', toc) : [];
  const targets = links.map(a => document.getElementById(a.getAttribute('href').slice(1)));
  const ind = toc && $('.toc__ind', toc);
  let current = -1;

  const keepVisible = a => {
    if (!wide.matches || toc.scrollHeight <= toc.clientHeight) return;
    const r = a.getBoundingClientRect();
    const s = toc.getBoundingClientRect();
    if (r.top < s.top + 48 || r.bottom > s.bottom - 48) {
      toc.scrollTo({ top: toc.scrollTop + (r.top - s.top) - s.height / 2 + r.height / 2, behavior: 'smooth' });
    }
  };
  const spy = () => {
    if (!targets.length) return;
    const line = window.innerHeight * .3;
    let idx = 0;
    targets.forEach((t, i) => { if (t && t.getBoundingClientRect().top - line <= 0) idx = i; });
    if (window.innerHeight + window.scrollY >= root.scrollHeight - 4) idx = targets.length - 1;
    if (idx === current) return;
    current = idx;
    links.forEach((a, i) => {
      const on = i === idx;
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
    });
    const a = links[idx];
    if (ind) {
      ind.style.opacity = '1';
      ind.style.height = `${a.offsetHeight}px`;
      ind.style.transform = `translateY(${a.offsetTop}px)`;
    }
    keepVisible(a);
  };

  // Navigation, bouton « haut de page » et barre de progression
  const nav = $('[data-nav]');
  const totop = $('.totop');
  const bar = $('.totop__bar');
  const progress = $('.legal-progress');
  let lastY = window.scrollY;
  let ticking = false;
  const onScroll = () => {
    ticking = false;
    const y = window.scrollY;
    const max = Math.max(1, root.scrollHeight - window.innerHeight);
    const p = Math.min(1, Math.max(0, y / max));
    if (nav) {
      nav.classList.toggle('is-scrolled', y > 24);
      if (!root.classList.contains('menu-open')) nav.classList.toggle('is-hidden', y > lastY && y > window.innerHeight * .6);
    }
    if (totop) totop.classList.toggle('is-visible', y > window.innerHeight * .9);
    if (bar) bar.style.strokeDashoffset = String(1 - p);
    if (progress) progress.style.setProperty('--p', p.toFixed(4));
    lastY = y;
    spy();
  };
  const request = () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } };
  window.addEventListener('scroll', request, { passive: true });
  window.addEventListener('resize', () => { current = -1; request(); });
  window.addEventListener('load', () => { current = -1; request(); });
  onScroll();

  // « Haut de page »
  $$('a[href="#top"]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }));

  // Coordonnées qui se « brouillent » au survol
  $$('[data-scramble]').forEach(el => {
    const orig = el.textContent;
    let raf = 0;
    el.addEventListener('pointerenter', () => {
      cancelAnimationFrame(raf);
      let f = 0;
      const total = 24;
      const tick = () => {
        f += 1;
        const p = f / total;
        el.textContent = orig.replace(/\d/g, (c, i) => (i / orig.length < p ? c : String((Math.random() * 10) | 0)));
        if (f < total) raf = requestAnimationFrame(tick);
        else el.textContent = orig;
      };
      tick();
    });
  });
})();
