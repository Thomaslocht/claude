# ZERO26. Coaching Club — site web

Site statique servi tel quel : la racine du dépôt = le dossier `public_html` du site. Pas de build, pas de dépendances.

- Pages : `index.html`, `cgv.html`, `mentions-legales.html`, `reglement-interieur.html`, `404.html`.
- `assets/css/style.css`, `assets/js/main.js` (dont `CONFIG` : e-mail, téléphone, formulaire FormSubmit), `assets/js/consent.js`, `assets/js/legal.js`, `assets/vendor/` (GSAP, ScrollTrigger, Lenis — ne pas modifier).
- `.htaccess` : HTTPS + domaine sans www, redirections de l'ancien WordPress, cache, en-têtes de sécurité, blocage de `.git` et `*.md`.
- Après une modification de `style.css`, `main.js` ou `legal.js`, changer le `?v=…` correspondant dans toutes les pages HTML (cache navigateur d'une semaine).
- Ajouter toute nouvelle page à `sitemap.xml`.

## SuperLeague Concept2 Triathlon (`superleague.html`)

- Page de l'événement, en onglet dans la navigation de toutes les pages. Styles `assets/css/superleague.css` (police Barlow Condensed hébergée), script `assets/js/superleague.js` ; elle réutilise `legal.js` pour la navigation.
- Date, clôture et nombre de places : `assets/data/superleague.json` (`date` au format `2026-12-12`, `cloture` au format `2026-12-05T23:59`, `placesMax` à 0 = illimité ; `dateLabel` / `clotureLabel` remplacent le texte affiché si besoin). Vide = « à venir ». Le serveur refuse les inscriptions après la clôture ou une fois complet.
- Inscriptions (PHP) : `superleague-inscription.php` les enregistre dans `zero26-data/superleague-inscriptions.csv`, à côté de `public_html` (hors du site, la publication ne le touche pas ; repli sur `public_html/.data`, fermé aux visiteurs). Colonnes `prenom,nom,sexe,email,tel,inscrit_le` : c'est le format importé par l'app de gestion du jour J (`prenom`, `nom`, `sexe` obligatoires). Une copie part aussi par e-mail via FormSubmit (`notify` dans `superleague.js`).
- Page organisateur : `superleague-admin.php` (liste, téléchargement du CSV, suppression). Mot de passe stocké haché dans `includes/superleague.php` (`SL_ADMIN_HASH`) ; jamais le mot de passe en clair dans le dépôt.

## Publication

Hébergement Hostinger, compte `u819785776`, site `zero26coachingclub.com`. Un `git push` sur `main` publie automatiquement (auto-déploiement GitHub de Hostinger). Vérifier ensuite la page en ligne.
