/* ==========================================================================
   ZERO26. Coaching Club — consentement aux cookies et mesure d'audience
   Google Analytics 4 et le Pixel Meta ne sont chargés qu'après l'accord du
   visiteur, conformément aux recommandations de la CNIL :
   « Tout refuser » est aussi simple que « Tout accepter », le choix est
   conservé 6 mois et reste modifiable à tout moment (« Gérer les cookies »).
   ========================================================================== */
(() => {
  'use strict';

  const CONSENT = {
    ga4: 'G-345CGTNBDV',            // Google Analytics 4 (même propriété que l'ancien site)
    metaPixel: '191594117100468',   // Pixel Meta (Facebook / Instagram)
    key: 'z26-consent',
    version: 1,
    maxAgeDays: 182                 // le choix est redemandé au bout de 6 mois
  };

  const root = document.documentElement;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  /* ---------- Choix du visiteur ---------- */
  const read = () => {
    try {
      const c = JSON.parse(localStorage.getItem(CONSENT.key) || 'null');
      if (!c || c.v !== CONSENT.version || Date.now() - c.t > CONSENT.maxAgeDays * 864e5) return null;
      return { analytics: !!c.analytics, ads: !!c.ads };
    } catch (err) { return null; }
  };
  const write = c => {
    try { localStorage.setItem(CONSENT.key, JSON.stringify({ v: CONSENT.version, t: Date.now(), analytics: c.analytics, ads: c.ads })); } catch (err) { /* stockage indisponible */ }
  };
  let state = read();

  /* ---------- Chargement des outils (uniquement après accord) ---------- */
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });

  let gaLoaded = false;
  let pixelLoaded = false;
  const addScript = src => { const s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); };

  const loadGA = () => {
    if (gaLoaded || !CONSENT.ga4) return;
    gaLoaded = true;
    const ads = state.ads ? 'granted' : 'denied';
    gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: ads, ad_user_data: ads, ad_personalization: ads });
    gtag('js', new Date());
    // Cookies limités à 13 mois, sans prolongation automatique (recommandation CNIL)
    gtag('config', CONSENT.ga4, { cookie_expires: 34128000, cookie_update: false });
    addScript(`https://www.googletagmanager.com/gtag/js?id=${CONSENT.ga4}`);
  };

  const loadPixel = () => {
    if (pixelLoaded || !CONSENT.metaPixel) return;
    pixelLoaded = true;
    // Code officiel du Pixel Meta
    !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', CONSENT.metaPixel);
    window.fbq('track', 'PageView');
  };

  // Les outils se chargent après l'affichage de la page, pour ne pas la ralentir
  const apply = () => {
    if (!state) return;
    const run = () => { if (state.analytics) loadGA(); if (state.ads) loadPixel(); };
    if (document.readyState === 'complete') run();
    else window.addEventListener('load', run, { once: true });
  };

  // Retrait du consentement : suppression des cookies de mesure déjà déposés
  const clearCookies = () => {
    const host = location.hostname;
    const domains = ['', host, `.${host}`, `.${host.replace(/^www\./, '')}`];
    document.cookie.split(';').map(c => c.split('=')[0].trim()).filter(n => /^(_ga|_gid|_gat|_gcl|_fbp|_fbc)/.test(n)).forEach(n => {
      domains.forEach(d => { document.cookie = `${n}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${d ? `; domain=${d}` : ''}`; });
    });
  };

  /* ---------- Suivi des actions utiles (envoyé seulement si accepté) ---------- */
  const EVENTS = {
    lead: { ga: 'generate_lead', meta: 'Lead' },              // demande envoyée via le formulaire (paramètre demande : essai / information)
    trial: { ga: 'reservation_essai' },                       // clic sur « Réserver un essai » (mène au formulaire de contact)
    checkout: { ga: 'begin_checkout', meta: 'InitiateCheckout' }, // clic vers un paiement bsport (carte, abonnement)
    contact: { ga: 'contact', meta: 'Contact' },              // clic sur le téléphone ou l'e-mail
    member: { ga: 'espace_membre' }                           // clic vers l'espace membre
  };
  window.z26track = (name, params = {}) => {
    const e = EVENTS[name];
    if (!e || !state) return;
    if (state.analytics && gaLoaded && e.ga) gtag('event', e.ga, params);
    if (state.ads && pixelLoaded && e.meta && window.fbq) window.fbq('track', e.meta, params.label ? { content_name: params.label } : {});
  };
  document.addEventListener('click', ev => {
    const trial = ev.target.closest && ev.target.closest('[data-trial]');
    if (trial) {
      const label = trial.dataset.trial === 'private' ? 'Essai coaching privé' : trial.dataset.trial === 'group' ? 'Essai small group' : 'Essai';
      window.z26track('trial', { label, format: label });
      return;
    }
    const a = ev.target.closest && ev.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (/^tel:/.test(href)) window.z26track('contact', { method: 'telephone' });
    else if (/^mailto:/.test(href)) window.z26track('contact', { method: 'email' });
    else if (/backoffice\.bsport\.io/.test(href)) {
      if (/302211|private-pass\/(18599|18603|18607)/.test(href)) {
        const label = /private-pass/.test(href) ? 'Essai coaching privé' : 'Essai small group';
        window.z26track('trial', { label, format: label });
      } else if (/\/c\/1748\/?$/.test(href)) window.z26track('member');
      else window.z26track('checkout', { label: 'Paiement bsport', link_url: href });
    }
  }, true);

  /* ---------- Bandeau ---------- */
  const ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.4 8.3-8 9-4.6-.7-8-4-8-9V6l8-3z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>';
  const banner = document.createElement('div');
  banner.className = 'consent';
  banner.hidden = true;
  banner.setAttribute('role', 'dialog');
  banner.setAttribute('aria-modal', 'false');
  banner.setAttribute('aria-labelledby', 'consent-title');
  banner.setAttribute('aria-describedby', 'consent-desc');
  banner.innerHTML = `
    <div class="consent__card">
      <p class="consent__title" id="consent-title" tabindex="-1"><span class="consent__ico">${ICON}</span>Vos données, votre choix</p>
      <p class="consent__text" id="consent-desc">Avec votre accord, nous mesurons la fréquentation du site (Google Analytics) et l’efficacité de nos publicités (Meta&nbsp;: Facebook, Instagram). Sans accord, aucun de ces outils n’est chargé. Vous pouvez changer d’avis à tout moment via «&nbsp;Gérer les cookies&nbsp;», en bas de page. <a href="/mentions-legales.html#cookies">En savoir plus</a></p>
      <div class="consent__prefs" hidden>
        <label class="consent__opt is-locked">
          <span class="consent__opt-txt"><b>Indispensables</b><small>Mémorisation de vos choix. Aucun suivi.</small></span>
          <input type="checkbox" checked disabled><span class="consent__switch" aria-hidden="true"></span>
        </label>
        <label class="consent__opt">
          <span class="consent__opt-txt"><b>Mesure d’audience</b><small>Google Analytics&nbsp;: pages vues, durée et provenance des visites.</small></span>
          <input type="checkbox" data-consent="analytics"><span class="consent__switch" aria-hidden="true"></span>
        </label>
        <label class="consent__opt">
          <span class="consent__opt-txt"><b>Publicité</b><small>Pixel Meta&nbsp;: mesure de nos publicités Facebook et Instagram.</small></span>
          <input type="checkbox" data-consent="ads"><span class="consent__switch" aria-hidden="true"></span>
        </label>
      </div>
      <div class="consent__actions">
        <button type="button" class="consent__more" data-act="custom">Personnaliser</button>
        <button type="button" class="consent__btn" data-act="deny">Tout refuser</button>
        <button type="button" class="consent__btn" data-act="accept">Tout accepter</button>
        <button type="button" class="consent__btn consent__btn--save" data-act="save" hidden>Enregistrer mes choix</button>
      </div>
    </div>`;

  const prefs = $('.consent__prefs', banner);
  const boxes = $$('[data-consent]', banner);
  const btn = act => $(`[data-act="${act}"]`, banner);

  const setCustom = on => {
    prefs.hidden = !on;
    btn('custom').hidden = on;
    btn('save').hidden = !on;
    banner.classList.toggle('is-custom', on);
  };
  const open = custom => {
    boxes.forEach(b => { b.checked = !!(state && state[b.dataset.consent]); });
    setCustom(!!custom);
    banner.hidden = false;
    root.classList.add('consent-open');
    requestAnimationFrame(() => requestAnimationFrame(() => banner.classList.add('is-in')));
  };
  const close = () => {
    banner.classList.remove('is-in');
    root.classList.remove('consent-open');
    setTimeout(() => { if (!banner.classList.contains('is-in')) banner.hidden = true; }, 600);
  };
  const decide = next => {
    const before = state;
    state = next;
    write(next);
    close();
    if (before && ((before.analytics && !next.analytics) || (before.ads && !next.ads))) {
      // Retrait d'un accord : on efface les cookies et on recharge la page sans les outils
      clearCookies();
      setTimeout(() => location.reload(), 300);
      return;
    }
    apply();
  };

  banner.addEventListener('click', e => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const act = b.dataset.act;
    if (act === 'custom') { setCustom(true); boxes[0].focus(); }
    else if (act === 'deny') decide({ analytics: false, ads: false });
    else if (act === 'accept') decide({ analytics: true, ads: true });
    else if (act === 'save') decide({ analytics: boxes.find(x => x.dataset.consent === 'analytics').checked, ads: boxes.find(x => x.dataset.consent === 'ads').checked });
  });

  const init = () => {
    // En tête de page (juste après le lien d'évitement) pour être atteint tout de suite au clavier
    const skip = $('.skip');
    if (skip) skip.after(banner); else document.body.prepend(banner);
    // Liens « Gérer les cookies » (masqués si JavaScript est désactivé, car rien n'est alors chargé)
    $$('[data-consent-open]').forEach(el => {
      el.hidden = false;
      el.addEventListener('click', e => { e.preventDefault(); open(true); $('#consent-title', banner).focus({ preventScroll: true }); });
    });
    if (state) apply();
    else open(false);
  };
  if (document.body) init(); else document.addEventListener('DOMContentLoaded', init, { once: true });
})();
