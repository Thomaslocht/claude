/* ==========================================================================
   ZERO26. Coaching Club — interactions & animations
   GSAP + ScrollTrigger + Lenis (via CDN). Sans eux, le site reste entièrement
   lisible : toutes les animations sont ajoutées par ce script.
   ========================================================================== */
(() => {
  'use strict';

  const CONFIG = {
    email: 'team@zero26coachingclub.com',
    phone: '07 64 62 30 85',
    // Les demandes du formulaire de contact arrivent directement dans la boîte ci-dessus,
    // via FormSubmit (service gratuit, sans compte). À la toute première demande, FormSubmit
    // envoie un e-mail « Activate Form » à cette adresse : il suffit de cliquer une fois dessus.
    // Pour changer de service (Formspree, Getform…), remplacez l'URL ; laissez '' pour
    // simplement ouvrir la messagerie du visiteur avec la demande pré-remplie.
    formEndpoint: 'https://formsubmit.co/ajax/team@zero26coachingclub.com'
  };

  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const root = document.documentElement;
  const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const FINE = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const HAS_GSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
  const ANIM = HAS_GSAP && !REDUCE;
  const NBSP = ' ';

  const pad = (n, l = 2) => String(n).padStart(l, '0');
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const cap = s => s.charAt(0).toUpperCase() + s.slice(1);

  // Liens de paiement bsport (cartes et abonnements). Les séances d'essai, elles,
  // passent par le formulaire de contact : l'équipe rappelle chaque demande.
  const BS = 'https://backoffice.bsport.io/';
  const pass = id => `${BS}customer/payment/pass/${id}/?membership=1748&force=true`;
  const ppass = id => `${BS}customer/payment/private-pass/${id}/?membership=1748&force=true`;
  const sub = id => `${BS}checkout/1748/subscription/${id}?force=true`;

  let lenis = null;
  let closeMenu = () => {};

  /* ---------- Découpage des textes en mots ---------- */
  function splitWords(el, mask) {
    const words = [];
    const walk = (node, target) => {
      Array.from(node.childNodes).forEach(child => {
        if (child.nodeType === Node.TEXT_NODE) {
          // Uniquement les espaces « normales » : les espaces insécables restent dans le mot.
          child.textContent.split(/([ \t\n\r]+)/).forEach(part => {
            if (!part) return;
            if (/^[ \t\n\r]+$/.test(part)) { target.appendChild(document.createTextNode(' ')); return; }
            const w = document.createElement('span');
            w.className = 'sw';
            if (mask) {
              const inner = document.createElement('span');
              inner.className = 'sw__i';
              inner.textContent = part;
              w.appendChild(inner);
              words.push(inner);
            } else {
              w.textContent = part;
              words.push(w);
            }
            target.appendChild(w);
          });
        } else if (child.nodeType === Node.ELEMENT_NODE) {
          if (child.tagName === 'BR') { target.appendChild(child.cloneNode()); return; }
          const clone = child.cloneNode(false);
          target.appendChild(clone);
          walk(child, clone);
        }
      });
    };
    const frag = document.createDocumentFragment();
    walk(el, frag);
    el.textContent = '';
    el.appendChild(frag);
    glue(el);
    return words;
  }

  // Deux blocs collés sans espace (ex. « l’<em>expertise</em> » ou « humeur. » ») ne doivent pas être séparés en fin de ligne
  function glue(el) {
    let node = el.firstChild;
    while (node) {
      const next = node.nextSibling;
      if (node.nodeType === 1 && next && next.nodeType === 1 && node.tagName !== 'BR' && next.tagName !== 'BR') {
        let g = node;
        if (!node.classList.contains('sw-glue')) {
          g = document.createElement('span');
          g.className = 'sw-glue';
          node.before(g);
          g.appendChild(node);
        }
        g.appendChild(next);
        node = g;
        continue;
      }
      node = next;
    }
  }

  /* ======================================================================
     Fonctionnalités communes (avec ou sans GSAP)
     ====================================================================== */

  // Libellés qui « roulent » lettre par lettre au survol
  function initRoll(scope = document) {
    $$('[data-roll]', scope).forEach(el => {
      if (el.dataset.rolled) return;
      const text = el.textContent.trim();
      if (!text) return;
      el.dataset.rolled = '1';
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
  }

  // Menu mobile
  function initMenu() {
    const burger = $('.nav__burger');
    const menu = $('#menu');
    if (!burger || !menu) return;
    let open = false;
    const set = v => {
      open = v;
      menu.classList.toggle('is-open', v);
      menu.toggleAttribute('inert', !v);
      menu.setAttribute('aria-hidden', String(!v));
      burger.setAttribute('aria-expanded', String(v));
      burger.setAttribute('aria-label', v ? 'Fermer le menu' : 'Ouvrir le menu');
      root.classList.toggle('menu-open', v);
      if (lenis) { v ? lenis.stop() : lenis.start(); }
      else document.body.style.overflow = v ? 'hidden' : '';
    };
    burger.addEventListener('click', () => set(!open));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && open) { set(false); burger.focus(); }
    });
    window.matchMedia('(min-width: 1181px)').addEventListener('change', e => { if (e.matches && open) set(false); });
    closeMenu = () => { if (open) set(false); };
  }

  // Ancres : défilement fluide (Lenis) ou natif
  function scrollToTarget(target, id) {
    if (!target) return;
    if (lenis) {
      lenis.scrollTo(id === '#top' ? 0 : target, { duration: 1.6 });
    } else {
      const y = id === '#top' ? 0 : target.getBoundingClientRect().top + window.scrollY;
      window.scrollTo({ top: y, behavior: REDUCE ? 'auto' : 'smooth' });
    }
  }
  function initAnchors() {
    document.addEventListener('click', e => {
      // « Réserver un essai » (boutons, badges, pop-up) : formulaire de contact pré-rempli.
      // Le bouton du hero est géré par son formulaire (objectif choisi).
      const trial = e.target.closest('[data-trial]');
      if (trial && !trial.closest('[data-goal]') && $('#contact')) {
        e.preventDefault();
        closeMenu();
        if (trialPop) trialPop.close();
        prefillContact({ trial: trial.dataset.trial, format: trial.dataset.trialFormat });
        try { history.replaceState(null, '', '#contact'); } catch (err) { /* file:// */ }
        return;
      }
      const a = e.target.closest('a[href^="#"]');
      if (!a) return;
      const id = a.getAttribute('href');
      if (!id || id.length < 2) return;
      const target = id === '#top' ? document.body : document.getElementById(id.slice(1));
      if (!target) return;
      e.preventDefault();
      closeMenu();
      scrollToTarget(target, id);
      try { history.replaceState(null, '', id === '#top' ? location.pathname : id); } catch (err) { /* file:// */ }
    });
  }

  // FAQ (accordéon)
  function initFaq() {
    $$('.qa').forEach(qa => {
      const btn = $('.qa__q', qa);
      btn.addEventListener('click', () => {
        const open = !qa.classList.contains('is-open');
        $$('.qa.is-open').forEach(o => {
          if (o === qa) return;
          o.classList.remove('is-open');
          $('.qa__q', o).setAttribute('aria-expanded', 'false');
        });
        qa.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', String(open));
        if (HAS_GSAP) setTimeout(() => ScrollTrigger.refresh(), 650);
      });
    });
  }

  // Sélecteur d'objectif du hero (fait défiler les objectifs tant qu'on n'y touche pas)
  function initGoal() {
    const form = $('[data-goal]');
    if (!form) return;
    const btn = $('.goal__select', form);
    const menu = $('.goal__menu', form);
    const text = $('.goal__text', form);
    const opts = $$('[role="option"]', menu);
    let idx = 0;
    let touched = false;
    let timer = null;

    const setText = (label, animate) => {
      if (!animate || !ANIM) { text.textContent = label; return; }
      gsap.timeline()
        .to(text, { yPercent: -110, opacity: 0, duration: .3, ease: 'power2.in' })
        .add(() => { text.textContent = label; })
        .fromTo(text, { yPercent: 110, opacity: 0 }, { yPercent: 0, opacity: 1, duration: .6, ease: 'expo.out' });
    };
    const select = (i, animate = true) => {
      idx = i;
      opts.forEach((o, k) => o.setAttribute('aria-selected', String(k === i)));
      setText(opts[i].textContent, animate);
    };
    const stop = () => { touched = true; clearInterval(timer); };
    const open = v => {
      form.classList.toggle('is-open', v);
      btn.setAttribute('aria-expanded', String(v));
      if (v) { stop(); opts[idx].focus({ preventScroll: true }); }
    };

    if (!REDUCE) {
      timer = setInterval(() => {
        if (!touched && !document.hidden) select((idx + 1) % opts.length);
      }, 2600);
    }
    btn.addEventListener('click', () => open(!form.classList.contains('is-open')));
    btn.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(true); }
    });
    menu.addEventListener('keydown', e => {
      const i = opts.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); opts[(i + 1) % opts.length].focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); opts[(i - 1 + opts.length) % opts.length].focus(); }
      else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (i > -1) { select(i); open(false); btn.focus(); } }
      else if (e.key === 'Escape') { open(false); btn.focus(); }
      else if (e.key === 'Tab') { open(false); }
    });
    opts.forEach((o, i) => o.addEventListener('click', () => { select(i); open(false); btn.focus(); }));
    document.addEventListener('pointerdown', e => { if (!form.contains(e.target)) open(false); });
    form.addEventListener('submit', e => {
      e.preventDefault();
      stop();
      open(false);
      prefillContact({ goal: opts[idx].dataset.value });
    });
  }

  // Demande de séance d'essai : le formulaire est adapté (titre, offre, message) avant d'y descendre
  const TRIAL_OFFERS = { group: 'Small group', private: 'Coaching privé' };
  let trialAsked = false;
  let autoMsg = '';
  function prefillContact({ goal, trial, format } = {}) {
    const form = $('[data-form]');
    const sel = $('#f-objectif');
    const msg = $('#f-msg');
    const title = $('.form__title');
    trialAsked = true;
    if (title) title.textContent = 'Réserver ma séance d’essai';
    if (sel && goal) sel.value = goal;
    const offre = TRIAL_OFFERS[trial];
    const radio = form && offre ? $(`input[name="offre"][value="${offre}"]`, form) : null;
    if (radio) radio.checked = true;
    // Le message n'est remplacé que s'il est vide ou s'il vient d'un clic précédent
    if (msg && (!msg.value.trim() || msg.value === autoMsg)) {
      const what = trial === 'group' ? ' offerte en small group'
        : trial === 'private' ? ` en coaching privé${format ? ` (${format})` : ''}` : '';
      autoMsg = `Bonjour, je souhaiterais réserver une séance d’essai${what}.`;
      msg.value = autoMsg;
      msg.dispatchEvent(new Event('input'));
    }
    scrollToTarget($('#contact'), '#contact');
    setTimeout(() => { const f = $('#f-prenom'); if (f) f.focus({ preventScroll: true }); }, lenis ? 1700 : 800);
  }

  /* ---------- Tarifs ---------- */
  function initPricing() {
    const grid = $('[data-pricing-grid]');
    if (!grid) return;

    const eur = v => `${v.toLocaleString('fr-FR')}${NBSP}€`;

    const DESC = [
      'Parfait pour compléter la séance d’essai et découvrir de nouvelles thématiques.',
      'Le forfait courte durée idéal pour se remettre en forme à son rythme.',
      'Une offre sans engagement, au rapport qualité/prix imbattable.'
    ];
    const F_SG = ['Séances coachées par des professionnels', `Club privé, 9${NBSP}personnes max par créneau`, `Annulation jusqu’à 12${NBSP}h avant`];
    const F_CP = [`Séances 100${NBSP}% personnalisées`, 'Un coach référent dédié', `Annulation jusqu’à 12${NBSP}h avant`];
    const F_ABO = ['Sans préavis de résiliation', 'Suspension possible', `Annulation jusqu’à 12${NBSP}h avant`];

    // Small group
    const SG = {
      cartes: [
        { name: '1 séance', price: 15, total: 0, id: 302206 },
        { name: '10 séances', price: 13, total: 130, id: 302207 },
        { name: '20 séances', price: 11, total: 220, id: 302208 }
      ],
      abos: {
        6: [{ price: 47, id: 8356 }, { price: 105, id: 8358 }],
        12: [{ price: 42, id: 8357 }, { price: 95, id: 8359 }]
      }
    };
    // Coaching privé : [prix/séance, total carte, id bsport] — abonnements : [prix/mois, id]
    const CP = {
      solo: { per: false, cartes: [[45, 0, 18583], [40, 400, 18585], [38, 760, 18586]], abos: { 6: [[160, 8381], [305, 8383]], 12: [[155, 8385], [295, 8386]] } },
      duo: { per: true, cartes: [[30, 0, 18600], [26, 260, 18601], [24, 480, 18602]], abos: { 6: [[105, 8370], [185, 8372]], 12: [[95, 8376], [175, 8379]] } },
      trio: { per: true, cartes: [[24, 0, 18604], [21, 210, 18605], [20, 400, 18606]], abos: { 6: [[85, 8361], [155, 8362]], 12: [[80, 8365], [145, 8369]] } }
    };

    const state = { service: 'sg', type: 'cartes', format: 'solo', duration: '6' };

    function cards() {
      const commit = `Durée minimale d’engagement ${state.duration === '6' ? `de${NBSP}6${NBSP}mois` : `d’un${NBSP}an`}`;
      if (state.service === 'sg') {
        if (state.type === 'cartes') {
          return SG.cartes.map((c, i) => ({
            name: c.name, price: c.price, unit: '/séance',
            meta: (c.total ? `${eur(c.total)} la carte` : 'À l’unité') + ' · validité 6 mois',
            desc: DESC[i], feats: F_SG, url: pass(c.id), badge: i === 2 ? 'Meilleur prix' : ''
          }));
        }
        return SG.abos[state.duration].map((c, i) => ({
          name: i ? 'Forfait Premium' : 'Forfait Basique', price: c.price, unit: '/mois',
          meta: i ? 'Séances illimitées*' : '1 séance par semaine', commit,
          desc: i ? 'Tarif préférentiel : s’entraîner plus devient rentable.' : 'Un rendez-vous hebdomadaire pour installer une routine durable.',
          feats: F_ABO, url: sub(c.id), badge: i ? 'Illimité' : ''
        }));
      }
      const f = CP[state.format];
      const per = f.per ? 'par personne · ' : '';
      if (state.type === 'cartes') {
        const names = ['1 séance', '10 séances', '20 séances'];
        return f.cartes.map(([price, total, id], i) => ({
          name: names[i], price, unit: '/séance',
          meta: cap(per + (total ? `${eur(total)} la carte` : 'à l’unité') + ' · validité 6 mois'),
          desc: DESC[i], feats: F_CP, url: ppass(id), badge: i === 2 ? 'Meilleur prix' : ''
        }));
      }
      return f.abos[state.duration].map(([price, id], i) => ({
        name: i ? '2 séances / semaine' : '1 séance / semaine', price, unit: '/mois',
        meta: f.per ? 'Tarif par personne' : 'Coaching en solo', commit,
        desc: i ? 'Le rythme idéal pour progresser vite et durablement.' : 'Un rendez-vous hebdomadaire avec votre coach référent.',
        feats: F_ABO, url: sub(id), badge: ''
      }));
    }

    const tTitle = $('[data-trial-title]');
    const tSub = $('[data-trial-sub]');
    const tLink = $('[data-trial-link]');
    const note = $('[data-pricing-note]');
    let prev = [];

    function render(animate) {
      const list = cards();
      grid.classList.toggle('is-2', list.length === 2);
      grid.innerHTML = list.map(c => `
        <article class="price spot${c.badge ? ' is-best' : ''}" data-tilt="2">
          <div class="price__head"><h3 class="price__name">${c.name}</h3>${c.badge ? `<span class="badge">${c.badge}</span>` : ''}</div>
          <p class="price__amount"><span class="price__num" data-price="${c.price}">${c.price}</span><span class="price__cur">€</span><span class="price__unit">${c.unit}</span></p>
          <div class="price__info"><p class="price__meta">${c.meta}</p>${c.commit ? `<p class="price__commit"><svg class="i"><use href="#i-cal"/></svg>${c.commit}</p>` : ''}</div>
          <p class="price__desc">${c.desc}</p>
          <ul class="price__list">${c.feats.map(t => `<li><svg class="i"><use href="#i-check"/></svg><span>${t}</span></li>`).join('')}</ul>
          <a class="btn btn--ghost price__cta" href="${c.url}" target="_blank" rel="noopener">Commander en ligne<svg class="i"><use href="#i-arrow-ur"/></svg></a>
        </article>`).join('');

      if (state.service === 'sg') {
        tTitle.textContent = 'Séance d’essai offerte';
        tSub.textContent = `Visite du club, rencontre avec l’équipe et séance 100${NBSP}% coachée.`;
        tLink.dataset.trial = 'group';
        delete tLink.dataset.trialFormat;
      } else {
        const f = CP[state.format];
        const half = f.cartes[0][0] / 2;
        const halfTxt = half.toLocaleString('fr-FR', { minimumFractionDigits: half % 1 ? 2 : 0 });
        tTitle.textContent = `Séance d’essai à −50${NBSP}%`;
        tSub.textContent = `Soit ${halfTxt}${NBSP}€${f.per ? ' par personne' : ''} : entretien, évaluation et première séance sur mesure.`;
        tLink.dataset.trial = 'private';
        tLink.dataset.trialFormat = state.format;
      }
      note.textContent = state.type === 'abos'
        ? `Abonnements : durée minimale d’engagement de 6${NBSP}mois ou d’un an selon la formule, sans préavis de résiliation. Annulation d’une séance jusqu’à 12${NBSP}h avant. Paiement sécurisé via bsport.` +
          (state.service === 'sg' ? ' *Forfait Premium : dans la limite d’une séance par jour, sur les créneaux dédiés et sous réserve de places disponibles.' : '')
        : `Validité des cartes : 6${NBSP}mois. Annulation possible jusqu’à 12${NBSP}h avant la séance. Paiement sécurisé via bsport.`;

      if (animate && ANIM) {
        const els = $$('.price', grid);
        gsap.fromTo(els, { y: 26, opacity: 0 }, { y: 0, opacity: 1, duration: .8, ease: 'expo.out', stagger: .07, clearProps: 'transform,opacity' });
        $$('.price__num', grid).forEach((n, i) => {
          const to = Number(n.dataset.price);
          const o = { v: prev[i] != null ? prev[i] : 0 };
          gsap.to(o, { v: to, duration: 1.1, ease: 'power3.out', onUpdate: () => { n.textContent = Math.round(o.v); } });
        });
        bindTilt(els);
        clearTimeout(render.t);
        render.t = setTimeout(() => ScrollTrigger.refresh(), 900);
      }
      prev = list.map(c => c.price);
    }

    function moveInd(seg, instant) {
      const ind = $('.seg__ind', seg);
      const b = $('button[aria-pressed="true"]', seg);
      if (!ind || !b || seg.hidden) return;
      if (instant) ind.style.transition = 'none';
      ind.style.width = `${b.offsetWidth}px`;
      ind.style.transform = `translateX(${b.offsetLeft}px)`;
      if (instant) { void ind.offsetWidth; ind.style.transition = ''; }
    }
    function toggleSeg(seg, show) {
      if (seg.hidden === !show) return;
      seg.hidden = !show;
      if (!show) return;
      moveInd(seg, true);
      if (ANIM) gsap.fromTo(seg, { opacity: 0, y: -8, scale: .96 }, { opacity: 1, y: 0, scale: 1, duration: .55, ease: 'expo.out', clearProps: 'all' });
    }
    const segs = $$('[data-seg]');
    segs.forEach(seg => {
      seg.addEventListener('click', e => {
        const b = e.target.closest('button');
        if (!b || b.getAttribute('aria-pressed') === 'true') return;
        $$('button', seg).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
        state[seg.dataset.seg] = b.dataset.value;
        moveInd(seg);
        toggleSeg($('[data-seg="format"]'), state.service === 'cp');
        toggleSeg($('[data-seg="duration"]'), state.type === 'abos');
        render(true);
      });
    });
    const syncAll = () => segs.forEach(s => moveInd(s, true));
    syncAll();
    window.addEventListener('resize', syncAll);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(syncAll);
    root.classList.add('seg-ready');
    render(false);
  }

  /* ---------- Formulaire de contact ---------- */
  function initForm() {
    const form = $('[data-form]');
    if (!form) return;
    const status = $('.form__status', form);
    const msg = $('#f-msg', form);
    const cnt = $('[data-count-chars]', form);
    const setStatus = (t, type) => { status.textContent = t; status.className = 'form__status' + (type ? ` is-${type}` : ''); };

    if (msg && cnt) msg.addEventListener('input', () => { cnt.textContent = msg.value.length; });
    $$('input, textarea', form).forEach(f => f.addEventListener('input', () => {
      const field = f.closest('.field');
      if (field) field.classList.remove('is-invalid');
    }));

    form.addEventListener('submit', async e => {
      e.preventDefault();
      let first = null;
      $$('[required]', form).forEach(f => {
        const ok = f.value.trim() !== '' && f.checkValidity();
        f.closest('.field').classList.toggle('is-invalid', !ok);
        if (!ok && !first) first = f;
      });
      if (first) { setStatus('Merci de compléter les champs indiqués.', 'error'); first.focus(); return; }

      const d = Object.fromEntries(new FormData(form).entries());
      const btn = $('button[type="submit"]', form);
      // Les demandes venues d'un bouton « Réserver un essai » sont signalées dès l'objet de l'e-mail
      const demande = trialAsked ? 'Séance d’essai' : 'Information';
      const subject = `Site ZERO26 — ${trialAsked ? 'Séance d’essai · ' : ''}${d.offre} — ${d.prenom} ${d.nom}`;

      // Champ piège invisible : rempli uniquement par les robots
      if (d._honey) { form.reset(); setStatus('Merci ! Votre demande a bien été envoyée.', 'ok'); return; }

      const openMail = () => {
        const body = [
          `Demande : ${demande}`,
          `Prénom : ${d.prenom}`,
          `Nom : ${d.nom}`,
          `E-mail : ${d.email}`,
          d.telephone ? `Téléphone : ${d.telephone}` : null,
          `Intéressé(e) par : ${d.offre}`,
          `Objectif : ${d.objectif}`,
          '',
          d.message || ''
        ].filter(l => l !== null).join('\n');
        window.location.href = `mailto:${CONFIG.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      };

      if (!CONFIG.formEndpoint) {
        openMail();
        setStatus('Votre messagerie s’ouvre avec la demande pré-remplie : il ne reste plus qu’à l’envoyer.', 'ok');
        return;
      }

      btn.disabled = true;
      setStatus('Envoi en cours…');
      try {
        const r = await fetch(CONFIG.formEndpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            _subject: subject,
            _template: 'table',
            _captcha: 'false',
            _replyto: d.email,
            'Demande': demande,
            'Prénom': d.prenom,
            'Nom': d.nom,
            'E-mail': d.email,
            'Téléphone': d.telephone || '—',
            'Intéressé(e) par': d.offre,
            'Objectif': d.objectif,
            'Message': d.message || '—'
          })
        });
        const res = await r.json().catch(() => ({}));
        if (!r.ok || String(res.success) !== 'true') throw new Error(res.message || String(r.status));
        form.reset();
        if (cnt) cnt.textContent = '0';
        trialAsked = false;
        autoMsg = '';
        const title = $('.form__title', form);
        if (title) title.textContent = 'Demande d’informations';
        setStatus('Merci ! Votre demande a bien été envoyée, nous revenons vers vous très vite.', 'ok');
        // Mesure (uniquement si le visiteur l'a acceptée, voir consent.js)
        if (window.z26track) window.z26track('lead', { offre: d.offre, label: d.offre, demande });
      } catch (err) {
        // Sans réponse du service, la demande n'est pas perdue : on ouvre la messagerie du visiteur
        setStatus(`L’envoi automatique n’a pas abouti : votre messagerie va s’ouvrir avec la demande pré-remplie (ou appelez-nous au ${CONFIG.phone}).`, 'error');
        setTimeout(openMail, 1400);
      }
      btn.disabled = false;
    });
  }

  /* ---------- Effets pointeur (projecteur des cartes, origine du remplissage des boutons) ---------- */
  function initPointerFx() {
    if (!FINE) return;
    document.addEventListener('pointermove', e => {
      const s = e.target.closest && e.target.closest('.spot');
      if (!s) return;
      const r = s.getBoundingClientRect();
      s.style.setProperty('--mx', `${e.clientX - r.left}px`);
      s.style.setProperty('--my', `${e.clientY - r.top}px`);
    }, { passive: true });

    const origin = (b, e) => {
      const r = b.getBoundingClientRect();
      b.style.setProperty('--x', `${e.clientX - r.left}px`);
      b.style.setProperty('--y', `${e.clientY - r.top}px`);
      b.style.setProperty('--d', `${Math.hypot(r.width, r.height) * 2.3}px`);
    };
    document.addEventListener('pointerover', e => {
      const b = e.target.closest && e.target.closest('.btn');
      if (b && !b.contains(e.relatedTarget)) origin(b, e);
    });
    document.addEventListener('pointerout', e => {
      const b = e.target.closest && e.target.closest('.btn');
      if (b && !b.contains(e.relatedTarget)) origin(b, e);
    });
  }

  // Coordonnées qui se « brouillent » au survol
  function initScramble() {
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
  }

  /* ---------- Pop-up « séance d'essai » (après 10 s de navigation) ----------
     Le visiteur choisit son profil (en groupe / en privé) : le visuel se réorganise
     (9 participants en orbite autour du coach, ou un duo coach + vous) et l'offre change. */
  const trialPop = (() => {
    const pop = $('#trialpop');
    if (!pop) return null;
    const panel = $('.trialpop__panel', pop);
    const stage = $('.tp-stage', pop);
    const coach = $('.tp-dot--coach', pop);
    const parts = $$('[data-tp-p]', pop);
    const orbitEl = $('.tp-orbit', pop);
    const link = $('.tp-link', pop);
    const caption = $('[data-tp-caption]', pop);
    const priceEl = $('[data-tp-price]', pop);
    const noteEl = $('[data-tp-note]', pop);
    const descEl = $('[data-tp-desc]', pop);
    const pointsEl = $('[data-tp-points]', pop);
    const cta = $('[data-tp-cta]', pop);
    const ctaLabel = $('[data-tp-cta-label]', pop);
    const sw = $('.tp-switch', pop);
    const radios = $$('[role="radio"]', sw);
    const KEY = 'z26-trialpop';
    const DELAY = 10000;

    const PROFILES = {
      group: {
        price: 'Offerte', note: 'votre séance d’essai', caption: `1 coach · jusqu’à 9${NBSP}personnes`,
        desc: 'Découvrez le club, l’équipe et une thématique au choix, en petit groupe.',
        points: [`9${NBSP}personnes max`, 'Un coach à chaque séance', 'Tous niveaux'],
        cta: 'Réserver mon essai offert', trial: 'group'
      },
      private: {
        price: `−50${NBSP}%`, note: 'sur votre séance d’essai', caption: '1 coach · solo, duo ou trio',
        desc: 'Entretien, évaluation et première séance sur mesure avec votre coach référent.',
        points: [`Soit 22,50${NBSP}€ en solo`, 'Solo, duo ou trio', `100${NBSP}% personnalisé`],
        cta: `Réserver mon essai à −50${NBSP}%`, trial: 'private'
      }
    };

    let open = false;
    let profile = 'group';
    let orbit = 0;
    let busy = false;
    let lastFocus = null;
    let hideTimer = 0;

    const seen = () => { try { return sessionStorage.getItem(KEY) === '1'; } catch (err) { return false; } };
    const remember = () => { try { sessionStorage.setItem(KEY, '1'); } catch (err) { /* stockage indisponible */ } };

    // Positions des points, relatives au centre de la scène
    const R = () => stage.offsetWidth * .37;
    const groupPos = i => {
      const a = (orbit + i * 40 - 90) * Math.PI / 180;
      return { x: Math.cos(a) * R(), y: Math.sin(a) * R(), scale: 1, opacity: 1 };
    };
    const privatePos = i => {
      const r = R();
      const d = r * .52;
      if (i === 0) return { x: d, y: 0, scale: 1.15, opacity: 1 };
      if (i === 1) return { x: d + r * .3, y: -r * .56, scale: .72, opacity: .32 };
      if (i === 2) return { x: d + r * .3, y: r * .56, scale: .72, opacity: .32 };
      return { x: -d, y: 0, scale: 0, opacity: 0 };
    };
    const posFor = (key, i) => (key === 'private' ? privatePos(i) : groupPos(i));
    const coachPos = key => (key === 'private' ? { x: -R() * .52, y: 0, scale: 1.08 } : { x: 0, y: 0, scale: 1 });

    const put = (el, s) => {
      const o = s.opacity == null ? 1 : s.opacity;
      if (ANIM) { gsap.set(el, { x: s.x, y: s.y, scale: s.scale, opacity: o }); return; }
      el.style.transform = `translate(${s.x.toFixed(2)}px, ${s.y.toFixed(2)}px) scale(${s.scale})`;
      el.style.opacity = o;
    };
    const layout = key => {
      const d = R() * .52;
      link.style.width = `${d * 2}px`;
      link.style.marginLeft = `${-d}px`;
      put(coach, coachPos(key));
      parts.forEach((p, i) => put(p, posFor(key, i)));
      const priv = key === 'private';
      if (ANIM) {
        gsap.set(link, { scaleX: priv ? 1 : 0, opacity: priv ? 1 : 0 });
        gsap.set(orbitEl, { scale: priv ? .6 : 1, opacity: priv ? 0 : 1 });
      } else {
        link.style.transform = `scaleX(${priv ? 1 : 0})`;
        link.style.opacity = priv ? 1 : 0;
        orbitEl.style.opacity = priv ? 0 : 1;
      }
    };

    // Changement de profil animé : les points se réorganisent
    const morph = key => {
      busy = true;
      const tl = gsap.timeline({ onComplete: () => { busy = false; } });
      const c = coachPos(key);
      tl.to(coach, { x: c.x, y: c.y, scale: c.scale, duration: 1, ease: 'expo.inOut' }, 0);
      parts.forEach((p, i) => {
        const q = posFor(key, i);
        const leaving = key === 'private' && i > 2;
        tl.to(p, { x: q.x, y: q.y, scale: q.scale, opacity: q.opacity, duration: leaving ? .7 : 1, ease: leaving ? 'power3.in' : 'expo.inOut' }, i * .035);
      });
      if (key === 'private') {
        tl.to(orbitEl, { opacity: 0, scale: .6, duration: .6, ease: 'power2.in' }, 0)
          .fromTo(link, { scaleX: 0, opacity: 0 }, { scaleX: 1, opacity: 1, duration: .7, ease: 'expo.out' }, .55);
      } else {
        tl.to(link, { scaleX: 0, opacity: 0, duration: .35, ease: 'power2.in' }, 0)
          .to(orbitEl, { opacity: 1, scale: 1, duration: .9, ease: 'expo.out' }, .25);
      }
    };

    const fill = p => {
      priceEl.textContent = p.price;
      noteEl.textContent = p.note;
      descEl.textContent = p.desc;
      pointsEl.innerHTML = p.points.map(t => `<li>${t}</li>`).join('');
      caption.textContent = p.caption;
      cta.dataset.trial = p.trial;
      ctaLabel.textContent = p.cta;
    };
    const setProfile = (key, animate) => {
      if (animate && key === profile) return;
      profile = key;
      sw.dataset.active = key;
      radios.forEach(r => {
        const on = r.dataset.profile === key;
        r.setAttribute('aria-checked', String(on));
        r.tabIndex = on ? 0 : -1;
      });
      const p = PROFILES[key];
      cta.dataset.trial = p.trial; // tout de suite, sans attendre la fin de l'animation
      if (!animate || !ANIM) { fill(p); layout(key); return; }
      morph(key);
      gsap.timeline()
        .to([priceEl, noteEl, descEl, pointsEl, caption], { opacity: 0, y: -10, duration: .22, ease: 'power2.in', stagger: .02 })
        .add(() => fill(p))
        .set(pointsEl, { opacity: 1, y: 0 })
        .fromTo(priceEl, { yPercent: 70, y: 0, opacity: 0 }, { yPercent: 0, opacity: 1, duration: .75, ease: 'expo.out' })
        .fromTo([noteEl, descEl, caption], { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: .6, ease: 'expo.out', stagger: .05 }, '<.06')
        .fromTo(pointsEl.children, { y: 10, opacity: 0 }, { y: 0, opacity: 1, duration: .5, ease: 'expo.out', stagger: .06 }, '<.08');
    };

    // Les participants tournent lentement autour du coach (profil « groupe »)
    if (ANIM) {
      gsap.ticker.add((time, dt) => {
        if (!open || busy || profile !== 'group') return;
        orbit = (orbit + dt * .012) % 360;
        parts.forEach((p, i) => { const q = groupPos(i); gsap.set(p, { x: q.x, y: q.y }); });
      });
    }

    const focusables = () => $$('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])', panel).filter(el => el.offsetParent !== null);

    const openPop = () => {
      if (open) return;
      open = true;
      remember();
      clearTimeout(hideTimer);
      lastFocus = document.activeElement;
      pop.hidden = false;
      root.classList.add('pop-open');
      if (lenis) lenis.stop(); else document.body.style.overflow = 'hidden';
      setProfile(profile, false);
      void pop.offsetWidth;
      pop.classList.add('is-open');
      if (ANIM) {
        busy = true;
        gsap.fromTo(coach, { scale: 0 }, { scale: coachPos(profile).scale, duration: .9, ease: 'back.out(2)', delay: .25 });
        gsap.fromTo(parts, { x: 0, y: 0, scale: 0, opacity: 0 }, {
          x: i => posFor(profile, i).x,
          y: i => posFor(profile, i).y,
          scale: i => posFor(profile, i).scale,
          opacity: i => posFor(profile, i).opacity,
          duration: 1.1,
          ease: 'expo.out',
          stagger: .05,
          delay: .35,
          onComplete: () => { busy = false; }
        });
        gsap.fromTo($$('.trialpop__body > *', pop), { y: 18, opacity: 0 }, { y: 0, opacity: 1, duration: .8, ease: 'expo.out', stagger: .05, delay: .2, clearProps: 'transform,opacity' });
      }
      setTimeout(() => {
        const r = radios.find(x => x.getAttribute('aria-checked') === 'true');
        if (r && open) r.focus({ preventScroll: true });
      }, 400);
    };

    const closePop = () => {
      if (!open) return;
      open = false;
      pop.classList.remove('is-open');
      root.classList.remove('pop-open');
      if (lenis) lenis.start(); else document.body.style.overflow = '';
      hideTimer = setTimeout(() => { pop.hidden = true; }, 700);
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
      setTimeout(showFab, 450);
    };

    // Bouton de rappel, en bas à droite : rouvre la pop-up une fois qu'elle a été vue
    const fab = $('.trialfab');
    let fabOn = false;
    function showFab() {
      if (!fab || fabOn) return;
      fabOn = true;
      fab.hidden = false;
      root.classList.add('has-fab');
      void fab.offsetWidth;
      fab.classList.add('is-visible');
    }
    if (fab) {
      fab.addEventListener('click', openPop);
      let lastY = window.scrollY;
      window.addEventListener('scroll', () => {
        const y = window.scrollY;
        if (Math.abs(y - lastY) < 8) return;
        fab.classList.toggle('is-compact', y > lastY && y > 240);
        lastY = y;
      }, { passive: true });
    }

    $$('[data-tp-close]', pop).forEach(el => el.addEventListener('click', closePop));
    radios.forEach(r => r.addEventListener('click', () => setProfile(r.dataset.profile, true)));
    sw.addEventListener('keydown', e => {
      if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
      e.preventDefault();
      const next = profile === 'group' ? 'private' : 'group';
      setProfile(next, true);
      radios.find(r => r.dataset.profile === next).focus();
    });
    // Le clic mène au formulaire de contact : la pop-up est refermée par la gestion des ancres
    cta.addEventListener('click', remember);
    document.addEventListener('keydown', e => {
      if (!open) return;
      if (e.key === 'Escape') { e.preventDefault(); closePop(); return; }
      if (e.key !== 'Tab') return;
      const f = focusables();
      if (!f.length) return;
      const first = f[0];
      const last = f[f.length - 1];
      if (!panel.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    window.addEventListener('resize', () => { if (open && !busy) layout(profile); });

    // Un visiteur qui a déjà cliqué sur « Réserver un essai » ne verra pas la pop-up
    document.addEventListener('click', e => {
      const t = e.target;
      if (!(t instanceof Element) || pop.contains(t)) return;
      const a = t.closest('a[href]');
      if (t.closest('[data-trial]') || (a && a.getAttribute('href') === '#essai')) remember();
    });

    // Compte uniquement le temps passé sur la page visible.
    // Pop-up déjà vue (ou essai déjà cliqué) : seul le bouton de rappel apparaît.
    const arm = () => {
      if (seen()) { showFab(); return; }
      let elapsed = 0;
      let last = performance.now();
      const id = setInterval(() => {
        const now = performance.now();
        if (!document.hidden) elapsed += now - last;
        last = now;
        if (open) { clearInterval(id); return; }
        if (elapsed < DELAY) return;
        if (seen()) { clearInterval(id); showFab(); return; }
        // On attend que le visiteur ait fermé le menu, la liste d'objectifs ou le bandeau cookies
        if (root.classList.contains('menu-open') || root.classList.contains('consent-open') || $('.goal.is-open')) return;
        clearInterval(id);
        openPop();
      }, 250);
    };

    return { arm, open: openPop, close: closePop };
  })();

  /* ---------- Carte « Votre suivi » du hero ----------
     Le point parcourt la courbe semaine après semaine ; le détail du programme
     change selon la phase. Au survol, le point suit la souris. */
  const chart = (() => {
    const wrap = $('[data-chart]');
    if (!wrap) return null;
    const svg = $('.pcard__svg', wrap);
    const box = $('.pcard__chart', wrap);
    const track = $('.pcard__track', wrap);
    const line = $('.pcard__line', wrap);
    const area = $('.pcard__area', wrap);
    const clipRect = $('.pcard__clip', wrap);
    const dot = $('.pcard__dot', wrap);
    const halo = $('.pcard__halo', wrap);
    const guide = $('.pcard__guide', wrap);
    const tip = $('.pcard__tip', wrap);
    const tipTxt = $('[data-chart-tip]', wrap);
    const weekEl = $('[data-chart-week]', wrap);
    const badge = $('[data-chart-badge]', wrap);
    const steps = $$('.pcard__step', wrap);
    const fills = steps.map(s => $('b', s));
    const phaseBox = $('[data-chart-phase]', wrap);

    // pv = coaching privé (point blanc) · gr = séance de groupe (point doré)
    const PHASES = [
      { title: 'Apprentissage des mouvements', per: 2, dots: ['pv', 'pv'],
        rows: [['pv', '<b>2</b> séances de coaching privé']] },
      { title: 'Spécification des points à améliorer', per: 3, dots: ['pv', 'gr', 'gr'],
        rows: [['pv', '<b>1</b> séance de coaching privé <em>· points à améliorer</em>'], ['gr', '<b>2</b> séances small group FORCE <em>· + de volume</em>']] },
      { title: 'Développement complet des qualités physiques', per: 4, dots: ['gr', 'gr', 'gr', 'gr'],
        rows: [['gr', '<b>2</b> séances small group FORCE'], ['gr', '<b>2</b> séances Hybride / Endurance']] }
    ];

    const W = 320, H = 120, PX = 10, PT = 14, PB = 10;
    const vals = [10, 14, 19, 23, 32, 38, 44, 50, 60, 67, 74, 82];
    const step = (W - PX * 2) / 11;
    const pts = vals.map((v, i) => [PX + i * step, H - PB - (v / 90) * (H - PT - PB)]);
    const f = n => n.toFixed(1);
    let d = `M${f(pts[0][0])},${f(pts[0][1])}`;
    for (let i = 0; i < pts.length - 1; i++) {
      const p0 = pts[i - 1] || pts[i];
      const p1 = pts[i];
      const p2 = pts[i + 1];
      const p3 = pts[i + 2] || p2;
      const t = .18;
      d += ` C${f(p1[0] + (p2[0] - p0[0]) * t)},${f(p1[1] + (p2[1] - p0[1]) * t)} ${f(p2[0] - (p3[0] - p1[0]) * t)},${f(p2[1] - (p3[1] - p1[1]) * t)} ${f(p2[0])},${f(p2[1])}`;
    }
    track.setAttribute('d', d);
    line.setAttribute('d', d);
    area.setAttribute('d', `${d} L${f(pts[11][0])},${H} L${f(pts[0][0])},${H} Z`);

    // Échantillonnage de la courbe pour placer le point exactement dessus
    const total = line.getTotalLength();
    const samples = [];
    for (let k = 0; k <= 200; k++) {
      const p = line.getPointAtLength(total * k / 200);
      samples.push([p.x, p.y]);
    }
    const yAt = x => {
      let lo = 0;
      let hi = samples.length - 1;
      if (x <= samples[lo][0]) return samples[lo][1];
      if (x >= samples[hi][0]) return samples[hi][1];
      while (hi - lo > 1) {
        const mid = (lo + hi) >> 1;
        if (samples[mid][0] < x) lo = mid; else hi = mid;
      }
      const [x0, y0] = samples[lo];
      const [x1, y1] = samples[hi];
      return y0 + (y1 - y0) * ((x - x0) / ((x1 - x0) || 1));
    };

    let week = -1;
    let phase = -1;
    const phaseHtml = p => `
      <div class="pcard__phase-head">
        <p class="pcard__phase-t">${p.title}</p>
        <span class="pcard__dots">${p.dots.map(c => `<i class="is-${c}"></i>`).join('')}</span>
      </div>
      <ul class="pcard__rows">${p.rows.map(([c, t]) => `<li><span class="pcard__mk is-${c}"></span><span>${t}</span></li>`).join('')}</ul>`;
    const setPhase = (ph, animate) => {
      phase = ph;
      badge.textContent = `Phase ${ph + 1}`;
      steps.forEach((s, k) => s.classList.toggle('is-active', k === ph));
      const html = phaseHtml(PHASES[ph]);
      if (!animate || !ANIM) { phaseBox.innerHTML = html; return; }
      gsap.killTweensOf(phaseBox);
      gsap.to(phaseBox, {
        opacity: 0, y: -6, duration: .22, ease: 'power2.in',
        onComplete: () => {
          phaseBox.innerHTML = html;
          gsap.fromTo(phaseBox, { opacity: 0, y: 8 }, { opacity: 1, y: 0, duration: .6, ease: 'expo.out' });
          gsap.fromTo($$('.pcard__dots i', phaseBox), { scale: 0 }, { scale: 1, duration: .5, ease: 'back.out(2.6)', stagger: .07 });
          gsap.fromTo($$('.pcard__rows li', phaseBox), { opacity: 0, x: -10 }, { opacity: 1, x: 0, duration: .55, ease: 'expo.out', stagger: .09, delay: .05 });
        }
      });
    };

    const render = w => {
      const x = PX + w * step;
      const y = yAt(x);
      clipRect.setAttribute('width', f(x + 1));
      dot.setAttribute('cx', f(x)); dot.setAttribute('cy', f(y));
      halo.setAttribute('cx', f(x)); halo.setAttribute('cy', f(y));
      guide.setAttribute('x1', f(x)); guide.setAttribute('x2', f(x));
      tip.style.left = `${x / W * 100}%`;
      tip.style.top = `${y / H * 100}%`;
      // L'infobulle reste dans la carte près des bords
      const shift = x / W > .78 ? -96 : x / W < .22 ? -4 : -50;
      tip.style.transform = `translate(${shift}%, calc(-100% - 12px))`;
      fills.forEach((b, k) => { b.style.transform = `scaleX(${clamp((w - k * 4 + 1) / 4, 0, 1).toFixed(3)})`; });
      const i = clamp(Math.round(w), 0, 11);
      if (i === week) return;
      week = i;
      const ph = i < 4 ? 0 : i < 8 ? 1 : 2;
      weekEl.textContent = pad(i + 1);
      tipTxt.textContent = `S${pad(i + 1)} · ${PHASES[ph].per} séances / sem.`;
      if (ph !== phase) setPhase(ph, phase !== -1);
    };

    // Lecture automatique (avec une pause plus longue au début de chaque phase)
    const state = { w: 4 };
    let hovering = false;
    let visible = true;
    let started = false;
    let targetW = state.w;
    let moving = null;
    let wait = null;
    const holdFor = i => (i === 11 ? 2.4 : i === 0 || i === 4 || i === 8 ? 1.8 : .5);
    const stop = () => {
      if (moving) moving.kill();
      if (wait) wait.kill();
      moving = null;
      wait = null;
    };
    const next = () => {
      if (!ANIM || hovering || !visible) return;
      const to = Math.round(state.w) >= 11 ? 0 : Math.round(state.w) + 1;
      moving = gsap.to(state, {
        w: to,
        duration: to === 0 ? 1.4 : .85,
        ease: to === 0 ? 'power3.inOut' : 'power2.inOut',
        onUpdate: () => render(state.w),
        onComplete: () => { wait = gsap.delayedCall(holdFor(to), next); }
      });
    };
    const play = delay => {
      stop();
      if (ANIM) wait = gsap.delayedCall(delay || 0, next);
    };

    render(state.w);

    box.addEventListener('pointerenter', () => { hovering = true; targetW = state.w; stop(); });
    box.addEventListener('pointermove', e => {
      const r = svg.getBoundingClientRect();
      targetW = clamp(((e.clientX - r.left) / r.width * W - PX) / step, 0, 11);
      if (!ANIM) { state.w = targetW; render(state.w); }
    });
    box.addEventListener('pointerleave', () => {
      hovering = false;
      if (!ANIM || !started) return;
      stop();
      moving = gsap.to(state, {
        w: Math.round(state.w), duration: .45, ease: 'power2.out',
        onUpdate: () => render(state.w),
        onComplete: () => play(1.4)
      });
    });
    if (ANIM) {
      gsap.ticker.add(() => {
        if (!hovering) return;
        state.w += (targetW - state.w) * .2;
        render(state.w);
      });
    }
    return {
      still() {
        stop();
        started = false;
        state.w = 4;
        render(4);
      },
      prepare() {
        const len = track.getTotalLength();
        gsap.set(track, { strokeDasharray: len, strokeDashoffset: len });
        gsap.set([dot, halo, guide, tip], { opacity: 0 });
        state.w = 0;
        render(0);
        clipRect.setAttribute('width', '0');
      },
      draw() {
        // La lecture automatique ne tourne que lorsque la carte est à l'écran
        ScrollTrigger.create({
          trigger: wrap,
          start: 'top bottom',
          end: 'bottom top',
          onToggle: s => {
            visible = s.isActive;
            if (!started || hovering) return;
            if (visible) play(.6); else stop();
          }
        });
        return gsap.timeline()
          .to(track, { strokeDashoffset: 0, duration: 1.6, ease: 'power2.inOut' }, 0)
          .add(() => render(0), 1.1)
          .to([dot, halo, guide], { opacity: 1, duration: .4 }, 1.1)
          .to(tip, { opacity: 1, duration: .4 }, 1.3)
          .add(() => { started = true; play(1.2); }, 1.4);
      }
    };
  })();

  /* ---------- Boucle « Backyard » ---------- */
  const loop = (() => {
    const wrap = $('[data-loop]');
    if (!wrap) return null;
    const NS = 'http://www.w3.org/2000/svg';
    const ticks = $('.loop__ticks', wrap);
    const prog = $('.loop__prog', wrap);
    const runner = $('.loop__runner', wrap);
    const halo = $('.loop__runner-halo', wrap);
    const lapEl = $('[data-loop-lap]', wrap);
    const kmEl = $('[data-loop-km]', wrap);
    const timeEl = $('[data-loop-time]', wrap);
    const R = 170;
    const C = 2 * Math.PI * R;

    for (let i = 0; i < 60; i++) {
      const a = i / 60 * Math.PI * 2 - Math.PI / 2;
      const major = i % 5 === 0;
      const r1 = R + 14;
      const r2 = R + (major ? 26 : 20);
      const l = document.createElementNS(NS, 'line');
      l.setAttribute('x1', (200 + r1 * Math.cos(a)).toFixed(2));
      l.setAttribute('y1', (200 + r1 * Math.sin(a)).toFixed(2));
      l.setAttribute('x2', (200 + r2 * Math.cos(a)).toFixed(2));
      l.setAttribute('y2', (200 + r2 * Math.sin(a)).toFixed(2));
      if (major) l.setAttribute('class', 'is-major');
      ticks.appendChild(l);
    }
    prog.style.strokeDasharray = C;

    // v = heures écoulées depuis le départ (0 → 15 boucles ≈ 100 km)
    const render = v => {
      const done = v >= 15;
      const frac = done ? 1 : v - Math.floor(v);
      prog.style.strokeDashoffset = C * (1 - frac);
      const a = frac * Math.PI * 2 - Math.PI / 2;
      const x = 200 + R * Math.cos(a);
      const y = 200 + R * Math.sin(a);
      runner.setAttribute('cx', x.toFixed(2)); runner.setAttribute('cy', y.toFixed(2));
      halo.setAttribute('cx', x.toFixed(2)); halo.setAttribute('cy', y.toFixed(2));
      lapEl.textContent = pad(Math.min(15, Math.floor(v) + 1));
      kmEl.textContent = (Math.min(v, 15) * 6.706).toLocaleString('fr-FR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
      const s = Math.round(Math.min(v, 15) * 3600);
      timeEl.textContent = `H+${pad(Math.floor(s / 3600))}:${pad(Math.floor(s % 3600 / 60))}:${pad(s % 60)}`;
    };
    render(15);
    return { render };
  })();

  /* ======================================================================
     Animations (GSAP + ScrollTrigger + Lenis)
     ====================================================================== */

  function bindTilt(els) {
    if (!ANIM || !FINE) return;
    els.forEach(card => {
      if (card.dataset.tiltBound) return;
      card.dataset.tiltBound = '1';
      const max = parseFloat(card.dataset.tilt) || 3;
      const rx = gsap.quickTo(card, 'rotationX', { duration: .8, ease: 'power3' });
      const ry = gsap.quickTo(card, 'rotationY', { duration: .8, ease: 'power3' });
      card.addEventListener('pointermove', e => {
        const r = card.getBoundingClientRect();
        ry(((e.clientX - r.left) / r.width - .5) * max * 2);
        rx(-((e.clientY - r.top) / r.height - .5) * max * 2);
      });
      card.addEventListener('pointerleave', () => { rx(0); ry(0); });
    });
  }

  function setupMagnetic() {
    if (!FINE) return;
    $$('[data-magnetic]').forEach(el => {
      const k = parseFloat(el.dataset.magnetic) || .3;
      const xTo = gsap.quickTo(el, 'x', { duration: .9, ease: 'elastic.out(1, .35)' });
      const yTo = gsap.quickTo(el, 'y', { duration: .9, ease: 'elastic.out(1, .35)' });
      el.addEventListener('pointermove', e => {
        const r = el.getBoundingClientRect();
        xTo((e.clientX - r.left - r.width / 2) * k);
        yTo((e.clientY - r.top - r.height / 2) * k);
      });
      el.addEventListener('pointerleave', () => { xTo(0); yTo(0); });
    });
  }

  function setupCursor() {
    if (!FINE) return;
    root.classList.add('has-cursor');
    const ring = $('.cursor');
    const dot = $('.cursor-dot');
    const label = $('.cursor__label');
    const rx = gsap.quickTo(ring, 'x', { duration: .55, ease: 'power3' });
    const ry = gsap.quickTo(ring, 'y', { duration: .55, ease: 'power3' });
    const dx = gsap.quickTo(dot, 'x', { duration: .12, ease: 'power3' });
    const dy = gsap.quickTo(dot, 'y', { duration: .12, ease: 'power3' });
    let shown = false;
    window.addEventListener('pointermove', e => {
      if (e.pointerType && e.pointerType !== 'mouse') return;
      if (!shown) { gsap.set([ring, dot], { x: e.clientX, y: e.clientY }); shown = true; }
      root.classList.add('cursor-on');
      rx(e.clientX); ry(e.clientY); dx(e.clientX); dy(e.clientY);
    }, { passive: true });
    document.documentElement.addEventListener('mouseleave', () => root.classList.remove('cursor-on'));
    window.addEventListener('pointerdown', () => root.classList.add('cur-down'));
    window.addEventListener('pointerup', () => root.classList.remove('cur-down'));
    document.addEventListener('pointerover', e => {
      const t = e.target;
      if (!(t instanceof Element)) return;
      const txt = t.closest('input, textarea, select');
      const lab = t.closest('[data-cursor]');
      const hide = t.closest('[data-cursor-hide]');
      const link = t.closest('a, button, label, [role="option"], .pcard__chart');
      root.classList.toggle('cur-text', !!txt);
      root.classList.toggle('cur-hide', !!hide && !txt);
      root.classList.toggle('cur-label', !!lab && !txt && !hide);
      root.classList.toggle('cur-link', !!link && !lab && !txt && !hide);
      if (lab) label.textContent = lab.dataset.cursor;
    });
  }

  // Bandeaux défilants : vitesse et sens liés au scroll
  function setupMarquees() {
    $$('[data-marquee]').forEach(row => {
      const track = $('.marquee__track', row);
      const group = $('.marquee__group', track);
      if (!track || !group) return;
      let groupW = 0;
      const fill = () => {
        groupW = group.getBoundingClientRect().width;
        while (track.children.length < 2 || track.scrollWidth < window.innerWidth + groupW * 1.2) {
          const c = group.cloneNode(true);
          c.setAttribute('aria-hidden', 'true');
          $$('a, button', c).forEach(x => { x.tabIndex = -1; });
          track.appendChild(c);
        }
      };
      fill();
      window.addEventListener('resize', fill);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(fill);

      const base = parseFloat(row.dataset.speed || '1');
      const dir = parseFloat(row.dataset.dir || '-1');
      const pauseOnHover = row.hasAttribute('data-pause');
      const skew = row.hasAttribute('data-skew');
      let x = 0, k = 1, kTarget = 1, sdir = 1, sk = 0, visible = true;
      row.addEventListener('pointerenter', () => { kTarget = pauseOnHover ? 0 : .3; });
      row.addEventListener('pointerleave', () => { kTarget = 1; });
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(([en]) => { visible = en.isIntersecting; }).observe(row);
      }
      gsap.ticker.add((time, dt) => {
        if (!visible || !groupW) return;
        const v = lenis ? lenis.velocity : 0;
        if (v > .3) sdir = 1; else if (v < -.3) sdir = -1;
        k += (kTarget - k) * .08;
        const speed = (base + Math.min(Math.abs(v) * .14, 7)) * k;
        x += speed * dir * sdir * (dt / 16.667);
        if (x <= -groupW) x += groupW;
        if (x > 0) x -= groupW;
        sk += ((skew ? clamp(-v * .35, -9, 9) : 0) - sk) * .12;
        track.style.transform = `translate3d(${x.toFixed(2)}px,0,0)${skew ? ` skewX(${sk.toFixed(2)}deg)` : ''}`;
      });
    });
  }

  // Anneaux de texte en rotation (accélèrent au survol et au scroll)
  function setupRings() {
    const rings = $$('.hero__badge-ring, .ring__text').map(el => ({ el, rot: 0, k: 1, target: 1 }));
    rings.forEach(r => {
      const host = r.el.closest('a');
      if (!host) return;
      host.addEventListener('pointerenter', () => { r.target = 4; });
      host.addEventListener('pointerleave', () => { r.target = 1; });
    });
    gsap.ticker.add((time, dt) => {
      const v = lenis ? Math.abs(lenis.velocity) : 0;
      rings.forEach(r => {
        r.k += (r.target - r.k) * .06;
        r.rot = (r.rot + (.22 * r.k + v * .1) * (dt / 16.667)) % 360;
        r.el.style.transform = `rotate(${r.rot.toFixed(2)}deg)`;
      });
    });
  }

  function setupHero() {
    gsap.set('.hero__lines span', { scaleX: 0, transformOrigin: 'left center' });
    gsap.set('.hero__title .line__i', { yPercent: 115 });
    gsap.set('[data-hero]', { opacity: 0, y: 28 });
    gsap.set('.hero__photo', { clipPath: 'inset(100% 0% 0% 0% round 10px)' });
    gsap.set('.hero__photo img', { scale: 1.35 });
    gsap.set('.hero__card', { opacity: 0, y: 70 });
    gsap.set('.hero__badge', { opacity: 0, scale: .5, rotation: -60 });
    gsap.set('.hero__tag', { opacity: 0, y: -8 });
    gsap.set('.nav__inner', { opacity: 0, y: -16 });
    gsap.set('.hero__scroll', { opacity: 0 });
    if (chart) chart.prepare();

    // Parallaxe à la souris (profondeurs différentes) + halo cuivré
    const hero = $('.hero');
    if (FINE) {
      const layers = $$('[data-depth]', hero).map(el => ({
        d: parseFloat(el.dataset.depth) || 1,
        x: gsap.quickTo(el, 'x', { duration: 1.1, ease: 'power3' }),
        y: gsap.quickTo(el, 'y', { duration: 1.1, ease: 'power3' })
      }));
      const glow = $('.hero__glow');
      const gx = gsap.quickTo(glow, 'x', { duration: 1.6, ease: 'power3' });
      const gy = gsap.quickTo(glow, 'y', { duration: 1.6, ease: 'power3' });
      hero.addEventListener('pointermove', e => {
        const r = hero.getBoundingClientRect();
        const nx = (e.clientX - r.left) / r.width - .5;
        const ny = (e.clientY - r.top) / r.height - .5;
        layers.forEach(l => { l.x(nx * 26 * l.d); l.y(ny * 26 * l.d); });
        gx(e.clientX - r.left);
        gy(e.clientY - r.top);
      });
      hero.addEventListener('pointerleave', () => layers.forEach(l => { l.x(0); l.y(0); }));
    }

    // Sortie du hero au scroll
    const st = { trigger: hero, start: 'top top', end: 'bottom top', scrub: true };
    gsap.to('.hero__copy', { yPercent: -16, ease: 'none', scrollTrigger: st });
    gsap.to('.hero__visual', { yPercent: 9, ease: 'none', scrollTrigger: { ...st } });
    gsap.to('.hero__fx', { opacity: 0, ease: 'none', scrollTrigger: { ...st } });
  }

  function heroIntro() {
    const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
    tl.to('.hero__lines span', { scaleX: 1, duration: 1.8, stagger: .14, ease: 'expo.inOut' }, 0)
      .to('.nav__inner', { opacity: 1, y: 0, duration: 1.2, clearProps: 'transform' }, .2)
      .to('.hero__title .line__i', { yPercent: 0, duration: 1.4, stagger: .11 }, .1)
      .to('[data-hero]', { opacity: 1, y: 0, duration: 1.2, stagger: .09, clearProps: 'transform' }, .45)
      .to('.hero__photo', { clipPath: 'inset(0% 0% 0% 0% round 10px)', duration: 1.6, ease: 'expo.inOut' }, .15)
      .to('.hero__photo img', { scale: 1, duration: 2.2 }, .35)
      .to('.hero__tag', { opacity: 1, y: 0, duration: .8 }, 1.3)
      .to('.hero__card', { opacity: 1, y: 0, duration: 1.4 }, .75)
      .to('.hero__badge', { opacity: 1, scale: 1, rotation: 0, duration: 1.4, ease: 'back.out(1.5)' }, 1.1)
      .to('.hero__scroll', { opacity: 1, duration: 1 }, 1.6);
    if (chart) tl.add(chart.draw(), 1);
    return tl;
  }

  function setupReveals() {
    // Titres : mots qui sortent d'un masque
    $$('[data-split]').forEach(el => {
      const words = splitWords(el, true);
      gsap.set(words, { yPercent: 115 });
      ScrollTrigger.create({
        trigger: el,
        start: 'top 88%',
        once: true,
        onEnter: () => gsap.to(words, { yPercent: 0, duration: 1.25, ease: 'expo.out', stagger: .05 })
      });
    });

    // Apparitions en fondu
    gsap.set('[data-reveal]', { opacity: 0, y: 34 });
    ScrollTrigger.batch('[data-reveal]', {
      start: 'top 90%',
      once: true,
      onEnter: batch => gsap.to(batch, { opacity: 1, y: 0, duration: 1.1, ease: 'expo.out', stagger: .08, overwrite: true, clearProps: 'transform,opacity' })
    });

    // Images révélées par un rideau (décalé entre éléments voisins)
    $$('[data-clip]').forEach(el => {
      const img = $('img', el);
      const rad = parseFloat(getComputedStyle(el).borderTopLeftRadius) || 0;
      const zoom = img && !el.hasAttribute('data-parallax') && !img.closest('[data-parallax]');
      const siblings = el.parentElement ? Array.from(el.parentElement.children).filter(c => c.hasAttribute('data-clip')) : [];
      const delay = Math.max(0, siblings.indexOf(el)) * .14;
      gsap.set(el, { clipPath: `inset(100% 0% 0% 0% round ${rad}px)` });
      if (zoom) gsap.set(img, { scale: 1.3 });
      ScrollTrigger.create({
        trigger: el,
        start: 'top 88%',
        once: true,
        onEnter: () => {
          gsap.to(el, { clipPath: `inset(0% 0% 0% 0% round ${rad}px)`, duration: 1.5, ease: 'expo.inOut', delay, clearProps: 'clipPath' });
          if (zoom) gsap.to(img, { scale: 1, duration: 2.2, ease: 'expo.out', delay: delay + .15, clearProps: 'transform' });
        }
      });
    });

    // Parallaxe des images
    $$('[data-parallax]').forEach(el => {
      const t = $('img, video', el);
      if (!t) return;
      const a = parseFloat(el.dataset.parallax) || 10;
      gsap.set(t, { scale: 1 + a / 45 });
      gsap.fromTo(t, { yPercent: -a }, { yPercent: a, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
  }

  // Philosophie : la valeur au centre de l'écran (ou survolée) se remplit,
  // et une photo suit le curseur au survol.
  function setupValues() {
    const section = $('.values');
    if (!section) return;
    const list = $('.values__list', section);
    const rows = $$('.value', section);
    const float = $('.values__float', section);
    const imgs = float ? $$('img', float) : [];
    let scrollIdx = 0;
    let hoverIdx = -1;
    const apply = () => {
      const idx = hoverIdx > -1 ? hoverIdx : scrollIdx;
      rows.forEach((r, k) => r.classList.toggle('is-active', k === idx));
      imgs.forEach((im, k) => im.classList.toggle('is-on', k === idx));
    };
    rows.forEach((r, i) => ScrollTrigger.create({
      trigger: r,
      start: 'top 60%',
      end: 'bottom 60%',
      onToggle: s => { if (s.isActive) { scrollIdx = i; apply(); } }
    }));
    apply();

    if (!FINE || !float) return;
    gsap.set(float, { autoAlpha: 0, scale: .8 });
    const xTo = gsap.quickTo(float, 'x', { duration: .75, ease: 'power3' });
    const yTo = gsap.quickTo(float, 'y', { duration: .75, ease: 'power3' });
    const rTo = gsap.quickTo(float, 'rotation', { duration: .9, ease: 'power3' });
    let lastX = 0;
    let lastMove = 0;
    const place = (e, instant) => {
      const r = section.getBoundingClientRect();
      const fw = float.offsetWidth;
      const fh = float.offsetHeight;
      const cx = e.clientX - r.left;
      const fx = cx > r.width * .55 ? cx - fw - 40 : cx + 40;
      const fy = e.clientY - r.top - fh / 2;
      if (instant) gsap.set(float, { x: fx, y: fy }); else { xTo(fx); yTo(fy); }
    };
    list.addEventListener('pointerenter', e => {
      place(e, true);
      lastX = e.clientX;
      gsap.to(float, { autoAlpha: 1, scale: 1, duration: .6, ease: 'expo.out', overwrite: 'auto' });
    });
    list.addEventListener('pointermove', e => {
      place(e, false);
      rTo(clamp((e.clientX - lastX) * .7, -14, 14));
      lastX = e.clientX;
      lastMove = performance.now();
      const i = rows.indexOf(e.target.closest('.value'));
      if (i > -1 && i !== hoverIdx) { hoverIdx = i; apply(); }
    });
    list.addEventListener('pointerleave', () => {
      hoverIdx = -1;
      apply();
      rTo(0);
      gsap.to(float, { autoAlpha: 0, scale: .8, duration: .45, ease: 'power2.in', overwrite: 'auto' });
    });
    gsap.ticker.add(() => { if (lastMove && performance.now() - lastMove > 120) { rTo(0); lastMove = 0; } });
  }

  function setupSections() {
    // Philosophie : la phrase s'allume mot à mot au fil du scroll
    const m = $('[data-words]');
    if (m) {
      const ws = splitWords(m, false);
      gsap.fromTo(ws, { opacity: .14 }, { opacity: 1, ease: 'none', stagger: .08, scrollTrigger: { trigger: m, start: 'top 85%', end: 'bottom 55%', scrub: .5 } });
    }
    setupValues();

    // Offres : les titres des panneaux montent ligne par ligne
    $$('.panel').forEach((p, i) => {
      const lines = $$('.panel__line > span', p);
      gsap.set(lines, { yPercent: 115 });
      ScrollTrigger.create({
        trigger: p,
        start: 'top 82%',
        once: true,
        onEnter: () => gsap.to(lines, { yPercent: 0, duration: 1.3, ease: 'expo.out', stagger: .1, delay: .45 + i * .14, clearProps: 'transform' })
      });
    });

    // Compteurs (avec décimales éventuelles) + filets dorés
    $$('[data-count]').forEach(el => {
      const to = parseFloat(el.dataset.count);
      const dec = parseInt(el.dataset.decimals || '0', 10);
      const fmt = v => v.toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
      const o = { v: 0 };
      el.textContent = fmt(0);
      ScrollTrigger.create({
        trigger: el,
        start: 'top 92%',
        once: true,
        onEnter: () => gsap.to(o, { v: to, duration: 2.2, ease: 'power3.out', onUpdate: () => { el.textContent = fmt(dec ? o.v : Math.round(o.v)); } })
      });
    });
    $$('.stat__line').forEach((l, i) => gsap.fromTo(l, { scaleX: 0 }, {
      scaleX: 1, duration: 1.6, ease: 'expo.inOut', delay: i * .12,
      scrollTrigger: { trigger: l, start: 'top 96%', once: true }
    }));

    // Méthode : étape active, image associée, rail doré
    const steps = $$('.step');
    const imgs = $$('.method__img');
    const cur = $('[data-step-current]');
    let active = 0;
    const setStep = i => {
      if (i === active) return;
      imgs.forEach((im, k) => {
        im.classList.toggle('was-active', k === active);
        im.classList.toggle('is-active', k === i);
      });
      steps.forEach((s, k) => s.classList.toggle('is-active', k === i));
      active = i;
      if (cur) cur.textContent = pad(i + 1);
    };
    steps.forEach((s, i) => ScrollTrigger.create({ trigger: s, start: 'top 62%', end: 'bottom 62%', onToggle: st => { if (st.isActive) setStep(i); } }));
    gsap.fromTo('.method__rail span', { scaleY: 0 }, { scaleY: 1, ease: 'none', scrollTrigger: { trigger: '.method__steps', start: 'top 62%', end: 'bottom 75%', scrub: true } });

    const mm = gsap.matchMedia();

    // Small group : défilement horizontal épinglé (desktop)
    mm.add('(min-width: 961px)', () => {
      const sec = $('.classes');
      const inner = $('.classes__inner');
      const cards = $$('.cls', inner);
      const bar = $('.classes__bar span');
      const count = $('[data-class-current]');
      const dist = () => Math.max(0, inner.scrollWidth - window.innerWidth);
      const tween = gsap.to(inner, {
        x: () => -dist(),
        ease: 'none',
        scrollTrigger: {
          trigger: sec,
          start: 'top top',
          end: () => `+=${dist()}`,
          pin: true,
          scrub: .8,
          anticipatePin: 1,
          invalidateOnRefresh: true,
          refreshPriority: 2,
          onUpdate: s => {
            bar.style.transform = `scaleX(${s.progress})`;
            count.textContent = pad(Math.min(6, Math.floor(s.progress * 6) + 1));
          }
        }
      });
      cards.forEach(card => {
        const media = $('.cls__media', card);
        if (media) gsap.fromTo(media, { xPercent: -7 }, { xPercent: 7, ease: 'none', scrollTrigger: { trigger: card, containerAnimation: tween, start: 'left right', end: 'right left', scrub: true } });
        gsap.fromTo(card, { y: 70, rotation: 3.5, opacity: .2 }, { y: 0, rotation: 0, opacity: 1, ease: 'none', scrollTrigger: { trigger: card, containerAnimation: tween, start: 'left 100%', end: 'left 72%', scrub: true } });
      });
    });

    // Séance d'essai : la ligne de progression se trace
    mm.add({ desk: '(min-width: 961px)', mob: '(max-width: 960px)' }, ctx => {
      const prop = ctx.conditions.desk ? 'scaleX' : 'scaleY';
      gsap.fromTo('.trial__line span', { [prop]: 0 }, { [prop]: 1, ease: 'none', scrollTrigger: { trigger: '.trial__wrap', start: 'top 75%', end: 'bottom 55%', scrub: true } });
    });

    // Transparence : les lignes du registre s'inscrivent une à une
    gsap.set('[data-lrow]', { opacity: 0, x: -16 });
    ScrollTrigger.batch('[data-lrow]', {
      start: 'top 92%',
      once: true,
      onEnter: b => gsap.to(b, { opacity: 1, x: 0, duration: .9, ease: 'expo.out', stagger: .07, clearProps: 'transform,opacity' })
    });


    // Boucle Backyard : tourne uniquement quand elle est visible
    if (loop) {
      const st = { v: 0 };
      const tl = gsap.timeline({ repeat: -1, repeatDelay: 1.6, paused: true });
      tl.to(st, { v: 15, duration: 36, ease: 'none', onUpdate: () => loop.render(st.v) });
      loop.render(0);
      ScrollTrigger.create({ trigger: '[data-loop]', start: 'top bottom', end: 'bottom top', onToggle: s => (s.isActive ? tl.play() : tl.pause()) });
    }
  }

  function setupNav() {
    const nav = $('[data-nav]');
    const totop = $('.totop');
    const bar = $('.totop__bar');
    ScrollTrigger.create({
      start: 0,
      end: 'max',
      onUpdate: self => {
        const y = self.scroll();
        nav.classList.toggle('is-scrolled', y > 24);
        if (!root.classList.contains('menu-open')) nav.classList.toggle('is-hidden', self.direction === 1 && y > window.innerHeight * .6);
        totop.classList.toggle('is-visible', y > window.innerHeight * .9);
        bar.style.strokeDashoffset = String(1 - self.progress);
      }
    });

    // Lien actif selon la section visible (les sections épinglées utilisent leur « pin-spacer »)
    const links = $$('.nav__links a');
    const setActive = a => links.forEach(l => l.classList.toggle('is-active', l === a));
    links.forEach(a => {
      const sec = document.getElementById(a.getAttribute('href').slice(1));
      if (!sec) return;
      const trg = sec.parentElement && sec.parentElement.classList.contains('pin-spacer') ? sec.parentElement : sec;
      ScrollTrigger.create({ trigger: trg, start: 'top 45%', end: 'bottom 45%', onToggle: s => { if (s.isActive) setActive(a); } });
    });
    ScrollTrigger.create({ trigger: '.hero', start: 'top top', end: 'bottom 45%', onToggle: s => { if (s.isActive) setActive(null); } });
  }

  // Lance l'animation d'entrée dès que les polices et la photo du haut de page sont prêtes
  // (au plus tard après 1,2 s, pour ne jamais faire attendre le visiteur)
  function whenHeroReady(done) {
    const heroImg = $('.hero__photo img');
    const imgReady = heroImg && !heroImg.complete
      ? new Promise(r => { heroImg.addEventListener('load', r, { once: true }); heroImg.addEventListener('error', r, { once: true }); })
      : Promise.resolve();
    const fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready.catch(() => {}) : Promise.resolve();
    let called = false;
    const go = () => { if (!called) { called = true; done(); } };
    Promise.all([imgReady, fontsReady]).then(go);
    setTimeout(go, 1200);
  }

  function initMotion() {
    gsap.registerPlugin(ScrollTrigger);
    ScrollTrigger.config({ ignoreMobileResize: true });

    const hash = location.hash;
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    window.scrollTo(0, 0);

    if (typeof window.Lenis === 'function') {
      lenis = new window.Lenis({ duration: 1.15, smoothWheel: true, wheelMultiplier: .95 });
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add(t => lenis.raf(t * 1000));
      gsap.ticker.lagSmoothing(0);
    }

    setupHero();
    root.classList.add('hero-ready');
    setupReveals();
    setupSections();
    setupNav();
    setupMarquees();
    setupRings();
    setupCursor();
    setupMagnetic();
    bindTilt($$('[data-tilt]'));

    whenHeroReady(() => {
      ScrollTrigger.refresh();
      heroIntro();
      if (trialPop) trialPop.arm();
      if (hash && hash.length > 1) {
        const t = document.getElementById(hash.slice(1));
        if (t) setTimeout(() => scrollToTarget(t, hash), 900);
      }
    });

    window.addEventListener('load', () => ScrollTrigger.refresh());
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => ScrollTrigger.refresh());
  }

  function fallback() {
    root.classList.add('no-anim', 'hero-ready');
    if (HAS_GSAP) {
      try {
        ScrollTrigger.getAll().forEach(t => t.kill());
        gsap.globalTimeline.clear();
        gsap.set([
          '[data-hero]', '[data-reveal]', '[data-lrow]', '[data-clip]', '[data-clip] img', '[data-parallax] img',
          '.hero__title .line__i', '.hero__photo', '.hero__photo img', '.hero__card', '.hero__badge', '.hero__tag',
          '.nav__inner', '.hero__scroll', '.hero__copy', '.hero__visual', '.hero__fx', '.sw__i', '.sw', '.stat__line',
          '.classes__inner', '.cls', '.cls__media',
          '.method__rail span', '.trial__line span', '.panel__line > span', '.values__float',
          '.pcard__track', '.pcard__dot', '.pcard__halo', '.pcard__guide', '.pcard__tip', '[data-chart-phase]'
        ].join(','), { clearProps: 'all' });
      } catch (err) { /* rien */ }
    }
    $$('[data-count]').forEach(el => {
      const dec = parseInt(el.dataset.decimals || '0', 10);
      el.textContent = Number(el.dataset.count).toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    });
    if (chart) chart.still();
    if (loop) loop.render(15);
    if (trialPop) trialPop.arm();
  }

  /* ---------- Démarrage ---------- */
  initRoll();
  initMenu();
  initAnchors();
  initFaq();
  initGoal();
  initPricing();
  initForm();
  initPointerFx();
  initScramble();

  if (ANIM) {
    try { initMotion(); }
    catch (err) {
      console.error('[ZERO26] Animations désactivées :', err);
      if (lenis) { try { lenis.destroy(); } catch (e) { /* rien */ } lenis = null; }
      fallback();
    }
  } else {
    fallback();
  }
})();
