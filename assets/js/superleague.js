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
      $('[data-closed-title]', closed).textContent = full ? 'C’est complet.' : 'Inscriptions clôturées.';
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
