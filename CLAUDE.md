# ZERO26. Coaching Club — site web

Site statique servi tel quel : la racine du dépôt = le dossier `public_html` du site. Pas de build, pas de dépendances.

- Pages : `index.html`, `cgv.html`, `mentions-legales.html`, `reglement-interieur.html`, `404.html`.
- `assets/css/style.css`, `assets/js/main.js` (dont `CONFIG` : e-mail, téléphone, formulaire FormSubmit), `assets/js/consent.js`, `assets/js/legal.js`, `assets/vendor/` (GSAP, ScrollTrigger, Lenis — ne pas modifier).
- `.htaccess` : HTTPS + domaine sans www, redirections de l'ancien WordPress, cache, en-têtes de sécurité, blocage de `.git` et `*.md`.
- Après une modification de `style.css`, `main.js` ou `legal.js`, changer le `?v=…` correspondant dans toutes les pages HTML (cache navigateur d'une semaine).
- Ajouter toute nouvelle page à `sitemap.xml`.

## Publication

Hébergement Hostinger, compte `u819785776`, site `zero26coachingclub.com`. Un `git push` sur `main` publie automatiquement (auto-déploiement GitHub de Hostinger). Vérifier ensuite la page en ligne.
