/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online
   Mode jour / nuit (même réglage que la page SuperLeague), confirmations,
   date de fin proposée dans l'espace coach. Chargé dans le <head> : le thème
   est appliqué avant l'affichage. Sans ce script, tout reste utilisable.
   ========================================================================== */
(function () {
  'use strict';

  var root = document.documentElement;
  try { if (localStorage.getItem('z26-theme') === 'day') root.setAttribute('data-theme', 'day'); } catch (e) { /* stockage indisponible */ }

  document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Mode jour / nuit ---------- */
    var btn = document.querySelector('[data-on-theme]');
    var meta = document.querySelector('meta[name="theme-color"]');
    var setTheme = function (day, save) {
      if (day) root.setAttribute('data-theme', 'day'); else root.removeAttribute('data-theme');
      if (btn) {
        btn.setAttribute('aria-pressed', String(day));
        btn.setAttribute('aria-label', day ? 'Passer en mode nuit' : 'Passer en mode jour');
      }
      if (meta) meta.content = day ? '#f3f2f8' : '#08070d';
      if (save) { try { localStorage.setItem('z26-theme', day ? 'day' : 'night'); } catch (e) { /* stockage indisponible */ } }
    };
    setTheme(root.getAttribute('data-theme') === 'day', false);
    if (btn) btn.addEventListener('click', function () { setTheme(root.getAttribute('data-theme') !== 'day', true); });

    /* ---------- Confirmation avant une suppression ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
      });
    });

    /* ---------- Espace coach : fin de l'accès = début + 10 semaines − 1 jour ---------- */
    document.querySelectorAll('form').forEach(function (f) {
      var debut = f.querySelector('[data-on-debut]');
      var fin = f.querySelector('[data-on-fin][data-on-auto]');
      if (!debut || !fin) return;
      var touched = false;
      fin.addEventListener('input', function () { touched = true; });
      debut.addEventListener('change', function () {
        if (touched || !debut.value) return;
        var d = new Date(debut.value + 'T12:00:00');
        d.setDate(d.getDate() + 69);
        fin.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
      });
    });

    /* ---------- Lien du webhook bsport : sélectionné d'un clic, prêt à copier ---------- */
    document.querySelectorAll('[data-on-select]').forEach(function (el) {
      el.addEventListener('focus', function () { el.select(); });
    });

    /* ---------- Code de connexion : chiffres seulement ---------- */
    var code = document.querySelector('.on-code');
    if (code) code.addEventListener('input', function () { code.value = code.value.replace(/\D/g, '').slice(0, 6); });
  });
})();
