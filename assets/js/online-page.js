/* ==========================================================================
   ZERO26. Coaching Club — page HYROX Online
   Calculateur d'exemple (vitesse critique et domaines d'intensité, watts des
   ergomètres, charges en % du 1RM), aperçu interactif de l'espace en ligne,
   course HYROX type, comparaison des offres, mode jour / nuit et animations
   (GSAP + ScrollTrigger, assets/vendor).
   Sans ce script, ou avec « réduire les animations », la page reste complète :
   les valeurs d'exemple sont déjà écrites dans la page.
   ========================================================================== */
(() => {
  'use strict';

  const root = document.documentElement;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const FINE = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const G = window.gsap;
  const ST = window.ScrollTrigger;
  const ANIM = !REDUCE && !!G && !!ST;
  if (ANIM) G.registerPlugin(ST);

  /* ---------- Mode jour / nuit (mémorisé, appliqué avant l'affichage par le script du <head>) ---------- */
  const themeBtn = $('[data-ho-theme]');
  const metaTheme = $('meta[name="theme-color"]');
  const setTheme = (day, save) => {
    if (save && !REDUCE) {
      root.classList.add('ho-theming');
      setTimeout(() => root.classList.remove('ho-theming'), 650);
    }
    if (day) root.dataset.theme = 'day'; else delete root.dataset.theme;
    if (themeBtn) {
      themeBtn.setAttribute('aria-pressed', String(day));
      themeBtn.setAttribute('aria-label', day ? 'Passer en mode nuit' : 'Passer en mode jour');
    }
    if (metaTheme) metaTheme.content = day ? '#f5f5f1' : '#0b0b0b';
    if (save) { try { localStorage.setItem('z26-theme', day ? 'day' : 'night'); } catch (e) { /* stockage indisponible */ } }
  };
  setTheme(root.dataset.theme === 'day', false);
  if (themeBtn) themeBtn.addEventListener('click', () => setTheme(root.dataset.theme !== 'day', true));

  /* ---------- Calculateur ----------
     Mêmes calculs que l'espace en ligne (includes/online/engine.php) :
     VC = (D2 − D1) / (T2 − T1), seuil 1 = 82 % de la VC, seuil 2 = VC,
     D′ = D1 − VC × T1, watts Concept2 = 2,80 / (s/m)³, charges arrondies à 2,5 kg. */
  const SEUIL1 = .82;
  const fmtTime = sec => {
    const s = Math.max(0, Math.round(sec));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const x = String(s % 60).padStart(2, '0');
    return h ? `${h}:${String(m).padStart(2, '0')}:${x}` : `${m}:${x}`;
  };
  const fmtNum = (v, d = 2) => String(Math.round(v * 10 ** d) / 10 ** d).replace('.', ',');
  const step = (v, by) => Math.round(v / by) * by;
  const watts = (t, m) => 2.8 / ((t / m) ** 3);
  const FMT = {
    time: fmtTime,
    kg: v => fmtNum(step(v, .5)),
    int: v => String(Math.round(v)),
    kmh: v => fmtNum(v, 1)
  };

  const state = { t1200: 264, t3600: 864, row: 220, ski: 238, squat: 110, press: 55 };

  const compute = () => {
    const out = {};
    const vc = state.t3600 > state.t1200 ? 2400 / (state.t3600 - state.t1200) : null;
    if (vc) {
      const dp = 1200 - vc * state.t1200;
      out.vc = vc;
      out.vc_pace = [1000 / vc, 'time'];
      out.vc_kmh = [vc * 3.6, 'kmh'];
      out.s1_pace = [1000 / (vc * SEUIL1), 'time'];
      out.dprime = [Math.max(0, dp), 'int'];
      out.p105 = [1000 / (vc * 1.05), 'time'];
      if (dp > 0) out.lim105 = [dp / (vc * .05), 'time'];
    }
    out.row_w = [watts(state.row, 1000), 'int'];
    out.ski_w = [watts(state.ski, 1000), 'int'];
    out.sq50 = [step(state.squat * .5, 2.5), 'kg'];
    out.sq75 = [step(state.squat * .75, 2.5), 'kg'];
    out.sq90 = [step(state.squat * .9, 2.5), 'kg'];
    out.pr50 = [step(state.press * .5, 2.5), 'kg'];
    out.pr75 = [step(state.press * .75, 2.5), 'kg'];
    out.pr90 = [step(state.press * .9, 2.5), 'kg'];
    out.pp60 = [step(state.press * .6, 2.5), 'kg'];
    return out;
  };
  let current = compute();

  // Les nombres glissent vers leur nouvelle valeur
  const setOut = (el, value, kind, animate) => {
    const fmt = FMT[kind];
    const from = typeof el._v === 'number' ? el._v : value;
    el._v = value;
    if (!animate || !ANIM || Math.abs(from - value) < 1e-6) { el.textContent = fmt(value); return; }
    const o = { v: from };
    G.to(o, { v: value, duration: .5, ease: 'power3.out', overwrite: true, onUpdate: () => { el.textContent = fmt(o.v); } });
    el.classList.remove('is-flash');
    void el.offsetWidth;
    el.classList.add('is-flash');
  };

  // Graphe des domaines : axe de vitesse fixe, de 8 à 22 km/h
  const AXIS = [8, 22];
  const chart = $('[data-ho-chart]');
  const axis = $('[data-ho-axis]');
  if (axis) {
    for (let k = AXIS[0]; k <= AXIS[1]; k += 2) {
      const sp = document.createElement('span');
      sp.style.left = `${(k - AXIS[0]) / (AXIS[1] - AXIS[0]) * 100}%`;
      sp.textContent = k === AXIS[1] ? `${k} km/h` : String(k);
      axis.appendChild(sp);
    }
  }
  const pos = kmh => Math.min(100, Math.max(0, (kmh - AXIS[0]) / (AXIS[1] - AXIS[0]) * 100));
  const drawChart = () => {
    if (!chart || !current.vc) return;
    const v = current.vc * 3.6;
    const s1 = v * SEUIL1;
    const set = (sel, a, b) => { const el = $(sel, chart); el.style.setProperty('--a', a); el.style.setProperty('--b', b); };
    set('[data-band="modere"]', 0, pos(s1));
    set('[data-band="eleve"]', pos(s1), pos(v));
    set('[data-band="severe"]', pos(v), 100);
    $('[data-mark="s1"]', chart).style.setProperty('--x', pos(s1));
    $('[data-mark="s2"]', chart).style.setProperty('--x', pos(v));
  };

  const render = animate => {
    current = compute();
    $$('[data-ho-out]').forEach(el => {
      const r = current[el.dataset.hoOut];
      if (!r) { el.textContent = '—'; el._v = null; return; }
      setOut(el, r[0], r[1], animate);
    });
    $$('[data-ho-show]').forEach(el => {
      const k = el.dataset.hoShow;
      el.textContent = k === 'squat' || k === 'press' ? `${fmtNum(state[k])} kg` : fmtTime(state[k]);
    });
    drawChart();
  };

  const calc = $('[data-ho-calc]');
  if (calc) {
    $('form', calc).addEventListener('submit', e => e.preventDefault());
    $$('input[type="range"]', calc).forEach(input => {
      const key = input.dataset.hoIn;
      const paint = () => input.style.setProperty('--fill', `${(input.value - input.min) / (input.max - input.min) * 100}%`);
      state[key] = Number(input.value);
      paint();
      input.addEventListener('input', () => { state[key] = Number(input.value); paint(); render(true); });
    });
  }

  /* ---------- Aperçu de l'espace en ligne : la semaine des tests, jour par jour ---------- */
  const app = $('[data-ho-app]');
  // Les séances de la semaine 1, telles qu'écrites dans le programme (includes/online/programmes/hyrox.json).
  // Une charge ne s'affiche que si le test correspondant existe ; les résultats des tests sont à part.
  const OUT = (key, unit = '') => `<span class="ho-pace"><b data-ho-out="${key}"></b>${unit ? `<small>${unit}</small>` : ''}</span>`;
  const SHOW = key => `<span class="ho-pace"><b data-ho-show="${key}"></b></span>`;
  const TEST_RUN = 'Au mieux, sur piste ou parcours plat. Entrez ensuite votre chrono dans «&nbsp;Mes tests&nbsp;».';
  const DAYS = [
    {
      d: 'L', tag: 'RUN', t: 'Test 1 200 m + endurance fondamentale', desc: 'Avec le test 3 600 m du jour 5, il détermine votre vitesse critique.',
      blocs: [['Test 1 200 m', '', [[TEST_RUN]]], ['Endurance fondamentale', '', [['Retour au calme en aisance respiratoire']]]],
      res: [['Test 1 200 m', SHOW('t1200')]]
    },
    {
      d: 'M', tag: 'FORCE', t: 'LOWER + METCON HYROX', desc: 'Test 1RM back squat.',
      blocs: [
        ['Warm up', '2 sets', [['Hip CAR’s&nbsp;: 5 par côté'], ['Jefferson curls&nbsp;: 5 reps lentes'], ['Overhead plate squats&nbsp;: 5 reps, 4" de pause en bas']]],
        ['Back squats', 'test 1RM', [['10 reps à vide'], ['5 reps à 50&nbsp;%', OUT('sq50', 'kg')], ['3 reps à 75&nbsp;%', OUT('sq75', 'kg')], ['1 rep à 90&nbsp;%', OUT('sq90', 'kg')], ['Puis 3 essais au maximum pour le 1RM']]],
        ['Plyo', '4 sets · R = 1\'30"', [['3 duo DB squat jumps @8/12,5 > 3 horizontal jumps, intention maximale, au poids du corps']]],
        ['Romanian deadlifts (7/10 cm)', 'R = 1\'30"', [['4 × 6 @RER2']]],
        ['HYROX conditioning', '4 rounds, for time', [['20 cals EchoBike'], ['20 m sled push @raceweight'], ['20 m walking lunges @raceweight']]],
        ['Access. 1 · Sandbag carries > dead hang', '3 sets · R = 1\'', [['50 m UBK @poids max > dead hang, max time']]]
      ],
      res: [['Back squat 1RM', SHOW('squat')]]
    },
    {
      d: 'M', tag: 'ERG', t: 'Test 1K SKI + 1K ROW', desc: 'Commencez par le SkiErg. Ergomètre réglé sur la distance et en watts, pour le chrono final et la puissance moyenne.',
      blocs: [
        ['Protocole avant chaque test', '', [["2' easy · 1' à l’allure cible · 2' easy"], ["1' à 120&nbsp;% de l’allure cible · 1' easy · 2' de récupération"]]],
        ['Test 1K SkiErg', '', [['1 000 m au mieux']]],
        ['Récupération', '', [["20', passive ou vélo à basse intensité"]]],
        ['Test 1K RowErg', '', [['Même protocole, puis 1 000 m au mieux']]]
      ],
      res: [['1K SkiErg · <span data-ho-show="ski"></span>', OUT('ski_w', 'W')], ['1K RowErg · <span data-ho-show="row"></span>', OUT('row_w', 'W')]]
    },
    { d: 'J', rest: true },
    {
      d: 'V', tag: 'RUN', t: 'Test 3 600 m + endurance fondamentale', desc: 'Avec le test 1 200 m, il détermine votre vitesse critique.',
      blocs: [['Test 3 600 m', '', [[TEST_RUN]]], ['Endurance fondamentale', '', [['Retour au calme en aisance respiratoire']]]],
      res: [['Test 3 600 m', SHOW('t3600')], ['Vitesse critique (seuil 2)', OUT('vc_pace', '/km')], ['Seuil 1', OUT('s1_pace', '/km')]]
    },
    {
      d: 'S', tag: 'FORCE', t: 'UPPER + METCON HYROX', desc: 'Test 1RM strict press.',
      blocs: [
        ['Warm up', '', [['10 Y raises'], ['10 behind the neck press (à vide)'], ['10 scapular push ups']]],
        ['Strict press', 'test 1RM', [['10 reps à vide'], ['5 reps à 50&nbsp;%', OUT('pr50', 'kg')], ['3 reps à 75&nbsp;%', OUT('pr75', 'kg')], ['1 rep à 90&nbsp;%', OUT('pr90', 'kg')], ['Puis 3 essais au maximum pour le 1RM']]],
        ['Pull ups', '', [['1 × AMRAP, jusqu’à l’échec (avec élastique si besoin, pour au moins 6 reps)'], ['Puis 5 sets&nbsp;: AMRAP / 2 reps, départ toutes les 1\'30"']]],
        ['HYROX conditioning 1', '3 sets · R = 90"', [['5 push presses @60&nbsp;%', OUT('pp60', 'kg')], ['15 wall ball shots @9/12'], ['30" wall ball overhead hold']]],
        ['Repos', '', [["2'"]]],
        ['HYROX conditioning 2', "EMOM 16'", [['Minute 1 · X wall ball shots @6/9'], ['Minute 2 · X cals RowErg']]]
      ],
      res: [['Strict press 1RM', SHOW('press')]]
    },
    { d: 'D', rest: true }
  ];
  const sessions = DAYS.filter(x => !x.rest).length;
  const done = {};
  let sel = 0;
  let auto = null;
  let pauseUntil = 0;
  let startAuto = () => {};

  if (app) {
    const daysEl = $('[data-ho-days]', app);
    const screen = $('[data-ho-app-screen]', app);
    const foot = $('[data-ho-app-foot]', app);
    const doneCount = $('[data-ho-app-done]', app);
    const ring = $('.ho-app__ring', app);

    daysEl.innerHTML = DAYS.map((x, i) => `<button type="button" class="ho-day" role="tab" aria-selected="false" data-i="${i}">${x.d}<small>${x.rest ? '—' : x.tag}</small><span class="ho-day__bar"></span></button>`).join('');
    const dayBtns = $$('.ho-day', daysEl);

    const paintFoot = () => {
      const x = DAYS[sel];
      if (x.rest) { foot.innerHTML = ''; return; }
      if (done[sel]) {
        foot.innerHTML = `<div class="ho-done"><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>Séance faite<span>RPE ${done[sel]}</span><button type="button" data-undo>Modifier</button></div>`;
      } else {
        foot.innerHTML = `<button type="button" class="ho-done-btn" data-done><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>J’ai fait ma séance</button>`;
      }
    };
    const paintRing = () => {
      const n = Object.keys(done).length;
      doneCount.textContent = String(n);
      ring.style.setProperty('--done', String(n / sessions));
      dayBtns.forEach((b, i) => b.classList.toggle('is-done', !!done[i]));
    };

    const show = (i, animate) => {
      sel = i;
      const x = DAYS[i];
      dayBtns.forEach((b, k) => b.setAttribute('aria-selected', String(k === i)));
      const lines = ls => `<ul class="ho-app__lines">${ls.map(([a, b]) => `<li><span>${a}</span>${b || ''}</li>`).join('')}</ul>`;
      screen.innerHTML = x.rest
        ? '<div class="ho-app__rest"><div><p>Récupération</p><span>Pas de séance aujourd’hui.</span></div></div>'
        : `<p class="ho-app__k"><span class="ho-tag">${x.tag}</span>Jour ${i + 1}</p><p class="ho-app__t">${x.t}</p><p class="ho-app__d">${x.desc}</p>`
          + x.blocs.map(([bt, note, ls]) => `<p class="ho-app__b">${bt}${note ? ` <small>${note}</small>` : ''}</p>${lines(ls)}`).join('')
          + (x.res ? `<div class="ho-app__res"><p>Vos résultats</p>${lines(x.res)}</div>` : '');
      screen.scrollTop = 0;
      render(false);
      paintFoot();
      if (animate && ANIM) {
        const parts = Array.from(screen.children).slice(0, 8);
        G.fromTo(parts, { y: 14, opacity: 0 }, { y: 0, opacity: 1, duration: .55, ease: 'power3.out', stagger: .05 });
      }
      if (ANIM) {
        dayBtns.forEach(b => G.killTweensOf($('.ho-day__bar', b)));
        dayBtns.forEach(b => G.set($('.ho-day__bar', b), { scaleX: 0 }));
      }
    };

    // Défilement automatique des jours, comme une semaine qui avance ; la main l'arrête
    const DUR = 4.8;
    const next = () => {
      if (Date.now() < pauseUntil) return;
      show((sel + 1) % DAYS.length, true);
      tick();
    };
    const tick = () => {
      if (!ANIM) return;
      const bar = $('.ho-day__bar', dayBtns[sel]);
      G.fromTo(bar, { scaleX: 0 }, { scaleX: 1, duration: DUR, ease: 'none', onComplete: next });
    };
    const hold = (ms = 20000) => {
      pauseUntil = Date.now() + ms;
      if (ANIM) dayBtns.forEach(b => G.killTweensOf($('.ho-day__bar', b)));
      clearTimeout(auto);
      auto = setTimeout(() => { pauseUntil = 0; tick(); }, ms);
    };

    daysEl.addEventListener('click', e => {
      const b = e.target.closest('.ho-day');
      if (!b) return;
      show(Number(b.dataset.i), true);
      hold();
    });
    foot.addEventListener('click', e => {
      if (e.target.closest('[data-done]')) {
        hold();
        foot.innerHTML = `<div class="ho-rpe"><p>Votre ressenti (RPE)&nbsp;: 1 très facile, 10 effort maximal</p><div class="ho-rpe__scale">${Array.from({ length: 10 }, (_, k) => `<button type="button" data-rpe="${k + 1}">${k + 1}</button>`).join('')}</div></div>`;
        if (ANIM) G.from($$('.ho-rpe__scale button', foot), { y: 10, opacity: 0, duration: .35, stagger: .025, ease: 'power2.out' });
        return;
      }
      const r = e.target.closest('[data-rpe]');
      if (r) {
        done[sel] = Number(r.dataset.rpe);
        paintFoot();
        paintRing();
        if (ANIM) G.from($('.ho-done', foot), { scale: .96, opacity: 0, duration: .45, ease: 'back.out(2)' });
        hold(6000);
        return;
      }
      if (e.target.closest('[data-undo]')) {
        delete done[sel];
        paintFoot();
        paintRing();
      }
    });
    if (FINE) {
      app.addEventListener('pointerenter', () => { if (!pauseUntil) hold(60000); });
      app.addEventListener('pointerleave', () => hold(1500));
    }
    show(0, false);
    paintRing();
    startAuto = () => { if (!pauseUntil) tick(); };
  }

  render(false);

  /* ---------- Course HYROX type (médiane homme, source HYRESULT) ---------- */
  const STATIONS = [
    ['SKI', 'SkiErg', 270, '1 000 m'], ['PUSH', 'Sled push', 170, '50 m'], ['PULL', 'Sled pull', 285, '50 m'], ['BBJ', 'Burpee broad jumps', 315, '80 m'],
    ['ROW', 'RowErg', 290, '1 000 m'], ['FARM', 'Farmers carry', 125, '200 m'], ['LUNGE', 'Sandbag lunges', 310, '100 m'], ['WB', 'Wall balls', 405, '100 reps']
  ];
  const RACE = [];
  STATIONS.forEach((s, i) => {
    RACE.push({ k: 'run', label: `Course ${i + 1} · 1 km`, d: 310 });
    RACE.push({ k: 'rox', label: 'Roxzone', d: 430 / 8 });
    RACE.push({ k: 'st', short: s[0], label: `Station ${i + 1} · ${s[1]} ${s[3]}`, d: s[2] });
  });
  const TOTAL = RACE.reduce((a, s) => a + s.d, 0); // 5 080 s = 1:24:40
  const bar = $('[data-ho-race-bar]');
  const nowEl = $('[data-ho-race-now]');
  let segEls = [];
  if (bar) {
    bar.insertAdjacentHTML('afterbegin', RACE.map(s => `<span class="ho-seg ho-seg--${s.k}" style="--d:${s.d.toFixed(1)}">${s.k === 'st' ? `<span>${s.short}</span>` : ''}</span>`).join(''));
    segEls = $$('.ho-seg', bar);
  }
  const at = t => {
    let acc = 0;
    for (let i = 0; i < RACE.length; i++) {
      if (t < acc + RACE[i].d) return { i, start: acc };
      acc += RACE[i].d;
    }
    return { i: RACE.length - 1, start: TOTAL - RACE[RACE.length - 1].d };
  };
  let lastSeg = -1;
  const paintRace = t => {
    const { i, start } = at(t);
    if (i !== lastSeg) {
      segEls.forEach((el, k) => el.classList.toggle('is-now', k === i));
      if (nowEl) nowEl.textContent = `${RACE[i].label} · ${fmtTime(RACE[i].d)}`;
      lastSeg = i;
    }
    const head = $('[data-ho-race-head]');
    if (head && bar) head.style.left = `${t / TOTAL * 100}%`;
    return start;
  };

  // Avec « réduire les animations » ou sans GSAP : tout est déjà affiché
  root.classList.remove('ho-pending');
  if (!ANIM) {
    return;
  }
  root.classList.add('ho-anim');

  // La course défile en boucle (20 s pour 1:24:40), seulement quand elle est visible
  const race = { t: 0 };
  const raceLoop = G.to(race, { t: TOTAL, duration: 20, ease: 'none', repeat: -1, paused: true, onUpdate: () => paintRace(race.t) });
  ST.create({ trigger: '.ho-hero', start: 'top bottom', end: 'bottom top', onToggle: self => (self.isActive ? raceLoop.play() : raceLoop.pause()) });

  /* ---------- Titres : lettres qui montent ---------- */
  const split = el => {
    if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
    const chars = [];
    const walk = node => {
      Array.from(node.childNodes).forEach(n => {
        if (n.nodeType === 3) {
          // Les lettres d'un même mot restent groupées : le titre ne se coupe jamais au milieu d'un mot
          const frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(part => {
            if (part === '') return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            const word = document.createElement('span');
            word.className = 'ho-w';
            word.setAttribute('aria-hidden', 'true');
            Array.from(part).forEach(c => {
              const outer = document.createElement('span');
              outer.className = 'ho-ch';
              const inner = document.createElement('span');
              inner.textContent = c;
              outer.appendChild(inner);
              word.appendChild(outer);
              chars.push(inner);
            });
            frag.appendChild(word);
          });
          n.replaceWith(frag);
        } else if (n.nodeType === 1) {
          n.setAttribute('aria-hidden', 'true');
          walk(n);
        }
      });
    };
    walk(el);
    return chars;
  };

  /* ---------- Haut de page ---------- */
  const title = $('[data-ho-title]');
  const intro = G.timeline({ defaults: { ease: 'expo.out' } });
  // Logo HYROX : les bandes glissent en se dessinant, le mot monte, puis le bandeau ONLINE se déroule
  if (title) {
    G.set(title, { opacity: 1 });
    const stripes = $('.ho-logo__stripes', title);
    const word = $('.ho-logo__word', title);
    const badge = $('.ho-title__badge', title);
    if (stripes) intro.fromTo(stripes, { x: -60, clipPath: 'inset(0% 100% 0% 0%)' }, { x: 0, clipPath: 'inset(0% 0% 0% 0%)', duration: 1.3 }, 0);
    if (word) intro.from(word, { y: 26, opacity: 0, duration: 1 }, .3);
    if (badge) {
      intro.from(badge, { scaleX: 0, transformOrigin: 'left center', duration: .9, ease: 'expo.inOut' }, .55);
      intro.from(badge, { color: 'rgba(0,0,0,0)', duration: .4, ease: 'none' }, 1.15);
    }
  }
  const heroIn = $$('[data-ho-in]');
  if (heroIn.length) intro.from(heroIn, { y: 28, opacity: 0, duration: 1, stagger: .09 }, .35);
  if (app) {
    intro.from(app, { y: 60, rotate: 2, opacity: 0, duration: 1.3 }, .45);
    intro.from($$('.ho-day', app), { y: 16, opacity: 0, duration: .6, stagger: .05 }, .8);
    intro.add(() => startAuto(), 1.4);
  }
  const raceFig = $('[data-ho-race]');
  if (raceFig) intro.from(raceFig, { y: 30, opacity: 0, duration: 1.1 }, .9);

  /* ---------- Bandeau : accélère avec le défilement, change de sens en remontant ---------- */
  const track = $('[data-ho-ticker]');
  if (track) {
    const loop = G.to(track, { xPercent: -50, duration: 30, ease: 'none', repeat: -1 });
    let settle;
    ST.create({
      onUpdate: self => {
        const boost = Math.min(Math.abs(self.getVelocity()) / 220, 7);
        G.to(loop, { timeScale: self.direction * (1 + boost), duration: .35, overwrite: true });
        clearTimeout(settle);
        settle = setTimeout(() => G.to(loop, { timeScale: 1, duration: 1.2, overwrite: true }), 180);
      }
    });
  }

  /* ---------- Apparitions au défilement ---------- */
  $$('[data-ho-reveal]').forEach(el => {
    const st = { trigger: el, start: 'top 88%', once: true };
    if (el.classList.contains('ho-display')) G.from(split(el), { yPercent: 115, duration: 1.1, ease: 'expo.out', stagger: .035, scrollTrigger: st });
    else G.from(el, { y: 36, opacity: 0, duration: 1.1, ease: 'expo.out', scrollTrigger: st });
  });
  [$$('.ho-offer'), $$('.ho-ex')].forEach(cards => {
    if (!cards.length) return;
    G.from(cards, { y: 70, opacity: 0, duration: 1.2, ease: 'expo.out', stagger: .1, scrollTrigger: { trigger: cards[0], start: 'top 85%', once: true } });
  });
  // Les coches se dessinent, offre par offre
  $$('.ho-offer').forEach(card => {
    const ticks = $$('.ho-feat.is-on i', card);
    G.from(ticks, { '--tick': 0, scale: .4, duration: .5, ease: 'back.out(2.2)', stagger: .07, scrollTrigger: { trigger: card, start: 'top 75%', once: true } });
  });
  const bases = $$('.ho-bases li');
  if (bases.length) G.from(bases, { y: 24, opacity: 0, duration: .9, ease: 'expo.out', stagger: .07, scrollTrigger: { trigger: bases[0], start: 'top 88%', once: true } });
  if (calc) {
    G.from(calc, { y: 50, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: calc, start: 'top 85%', once: true } });
    G.from($$('.ho-dom__band', calc), { scaleX: 0, transformOrigin: 'left center', duration: 1, ease: 'power3.out', stagger: .15, scrollTrigger: { trigger: chart, start: 'top 85%', once: true } });
  }

  // Halo qui suit la souris sur les cartes
  if (FINE) {
    $$('[data-ho-card]').forEach(card => {
      card.addEventListener('pointermove', e => {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${e.clientX - r.left}px`);
        card.style.setProperty('--my', `${e.clientY - r.top}px`);
      });
    });
  }

  /* ---------- Prix : le total au détail se raye, le pack décompte ---------- */
  $$('[data-ho-count]').forEach(el => {
    const to = Number(el.dataset.hoCount);
    const from = el.dataset.hoFrom ? Number(el.dataset.hoFrom) : 0;
    const o = { v: from };
    el.textContent = String(from);
    G.to(o, {
      v: to,
      duration: el.dataset.hoFrom ? 1.6 : 1.1,
      ease: 'power3.out',
      delay: el.dataset.hoFrom ? .5 : 0,
      onUpdate: () => { el.textContent = String(Math.round(o.v)); },
      scrollTrigger: { trigger: el, start: 'top 90%', once: true }
    });
  });
  $$('[data-ho-strike]').forEach(el => {
    el.style.setProperty('--strike', '0');
    G.to(el, { '--strike': 1, duration: .6, ease: 'power2.inOut', delay: .3, scrollTrigger: { trigger: el, start: 'top 90%', once: true } });
  });

  /* ---------- Le pack, étape par étape : la ligne se remplit avec le défilement ---------- */
  const steps = $('[data-ho-steps]');
  if (steps) {
    steps.setAttribute('data-ho-live', '');
    const line = $('.ho-steps__line span', steps);
    const items = $$('.ho-step', steps);
    line.style.setProperty('--p', '0');
    ST.create({
      trigger: steps,
      start: 'top 80%',
      end: 'bottom 55%',
      scrub: .6,
      onUpdate: self => {
        line.style.setProperty('--p', self.progress.toFixed(3));
        items.forEach((it, i) => it.classList.toggle('is-on', self.progress >= i / items.length + .02 || self.progress > .98));
      }
    });
    G.from(items, { y: 40, opacity: 0, duration: 1, ease: 'expo.out', stagger: .1, scrollTrigger: { trigger: steps, start: 'top 85%', once: true } });
  }
})();

/* ---------- Offres : un point survolé se compare d'une formule à l'autre ----------
   (hors du bloc d'animation : fonctionne aussi avec « réduire les animations ») */
(() => {
  const offers = document.querySelector('[data-ho-offers]');
  const info = document.querySelector('[data-ho-feat-info]');
  if (!offers || !info) return;
  const INFO = {
    test: ['Phase de test au club', '2 h avec votre coach pour vos tests de référence, puis l’explication du programme. Inclus dans le pack.'],
    prog: ['Programmation complète', '5 séances par semaine pendant 8 semaines : course, force, ergomètres et HYROX. Dans le pack et le programme seul.'],
    run: ['Séances de course à pied', '2 par semaine, avec des allures calculées depuis votre vitesse critique. Dans les trois formules.'],
    calc: ['Cibles calculées', 'Vos allures, vos watts et vos charges, à partir de vos propres tests, mis à jour à chaque re-test. Dans les trois formules.'],
    simu: ['Simulation au club à J-15', '2 h avec votre coach, le format de course en entier, pour valider vos allures. Inclus dans le pack.'],
    plan: ['Plan de course', 'Vos allures station par station et, en duo, la répartition du travail. Inclus dans le pack.']
  };
  const DEFAULT = info.innerHTML;
  let pinned = '';
  const show = feat => {
    if (feat) {
      offers.dataset.hl = feat;
      info.innerHTML = `<b>${INFO[feat][0]}.</b> ${INFO[feat][1]}`;
    } else {
      delete offers.dataset.hl;
      info.innerHTML = DEFAULT;
    }
  };
  offers.addEventListener('pointerover', e => { const f = e.target.closest('.ho-feat'); if (f) show(f.dataset.feat); });
  offers.addEventListener('pointerleave', () => show(pinned));
  offers.addEventListener('focusin', e => { const f = e.target.closest('.ho-feat'); if (f) show(f.dataset.feat); });
  offers.addEventListener('focusout', e => { if (!offers.contains(e.relatedTarget)) show(pinned); });
  offers.addEventListener('click', e => {
    const f = e.target.closest('.ho-feat');
    if (!f) return;
    pinned = pinned === f.dataset.feat ? '' : f.dataset.feat;
    show(pinned || f.dataset.feat);
  });
})();
