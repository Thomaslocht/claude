# HYROX Online — mode d'emploi

Espace privé : **zero26coachingclub.com/online**
Page de présentation publique : **zero26coachingclub.com/hyrox-online.html**

## Pour les coachs (Thomas, Alexis)

**Se connecter** : ouvrir zero26coachingclub.com/online, saisir son adresse e-mail, puis le code à 6 chiffres reçu (valable 10 minutes). On arrive directement dans l'espace coach. La connexion reste active 60 jours sur l'appareil.

**Un achat sur bsport crée l'espace tout seul.** Quand un client paie l'un des trois produits HYROX Online de la boutique bsport, bsport prévient le site (webhook « invoice-pay ») :

- l'espace du client est créé avec son prénom, son e-mail, son téléphone et l'offre achetée (pack, programme, programme RUN) ;
- il reçoit tout de suite un e-mail « Votre espace HYROX Online est prêt » (depuis site@zero26coachingclub.com, réponses vers team@) ;
- à sa première connexion, il choisit sa **date de démarrage** (jour 1 du programme) ; l'accès dure 10 semaines à partir de cette date ;
- pack : son espace affiche « Réserver ma séance test » et « Réserver ma simulation » (2 h chacune), vers les créneaux bsport (prévoir 2 × 2 crédits dans le produit) ;
- team@zero26coachingclub.com et les coachs reçoivent une alerte « Nouvel achat » avec ses coordonnées : l'équipe le rappelle pour valider la mise en place.

Les produits sont reconnus par leur **nom exact** dans bsport (liste dans l'espace coach, section « Achats bsport », et dans `ON_OFFRES`, `includes/online/app.php`) : renommer un produit dans bsport demande de changer ce nom dans le code. Un ancien membre qui rachète retrouve son espace (nouveau cycle si l'ancien est terminé).

**Régler le webhook dans bsport** (une seule fois) : espace coach → « Achats bsport » → « Réglage dans bsport » : copier le lien (il contient une clé secrète, créée sur le serveur), puis dans bsport → Réglages → Webhooks → Ajouter un webhook → événement **invoice-pay** → coller le lien → Valider → Tester. Le dernier appel reçu s'affiche dans l'espace coach.

**Ajouter un membre à la main** (en bas de l'espace coach, sans achat bsport) : prénom, e-mail, offre, téléphone, date de début. Le jour 1 du programme est la date de début : choisir un lundi de préférence. La fin de l'accès est proposée automatiquement (8 semaines + 2 semaines de marge). Le membre peut se connecter tout de suite avec son adresse.

**Les tests** : l'athlète les saisit lui-même dans « Mes tests » (et peut supprimer une erreur de saisie). Le coach peut aussi les saisir depuis la fiche du membre (« Saisir un test ou un re-test »). Temps au format `3:40` ou `1:24:40`, 1RM en kg (`110` ou `82,5`). Chaque résultat sert tout de suite au calcul, sans validation.

Les références du calculateur :

- **Vitesse critique** : calculée seule dès que les tests 1 200 m et 3 600 m sont saisis. Elle donne le seuil 1 (82 % de la VC), le seuil 2 (= VC), les trois domaines d'intensité (modéré, élevé, sévère) et la réserve D′. Le **test 5 km** (semaine 5) l'affine : une fois saisi, la VC est calculée sur les trois chronos pour les séances qui suivent sa date (les séances d'avant gardent la VC des deux tests). Il peut aussi remplacer un des deux tests de base s'il manque.
- **1K ROW et 1K SKI** : le temps saisi donne la puissance moyenne en watts (formule Concept2).
- **1RM** (back squat, strict press…) : l'espace affiche les charges de 50 à 90 % ; les séances utilisent ces %.
- **METCON HYROX** (au club, avant la semaine 1, pour établir le niveau ; format à venir, entre 20 et 40 min) et **Simulation HYROX** (au club, semaine 7, environ 15 jours avant la course) : deux tests séparés, chacun un temps de référence ; au re-test, l'espace affiche la progression depuis le premier.

**Re-test** : saisir le nouveau résultat avec sa date. Il s'applique aux séances ultérieures. Les séances d'avant gardent l'ancien résultat, et l'historique est conservé.

**Derniers tests saisis** : la page d'accueil coach liste les résultats des 14 derniers jours ; la fiche du membre montre tout l'historique (supprimable en cas d'erreur).

**Suivre un membre** : la liste montre la semaine en cours, les séances faites, le dernier RPE et la dernière activité. La fiche détaille chaque semaine (faite, RPE, commentaire). « Voir son espace » montre exactement ce qu'il voit, en lecture seule.

**Le membre ne reçoit pas le code ?** Sur sa fiche, cliquer sur « Code de connexion de secours » et lui envoyer le code par SMS. Il saisit son e-mail sur la page de connexion, puis ce code.

**Désactiver / supprimer** : décocher « Accès actif » coupe l'accès tout de suite et garde les données. « Supprimer définitivement » efface le membre, ses tests et son suivi. Sans action de votre part, les données sont supprimées automatiquement un an après la fin de l'accès.

## Modifier le programme

Les fichiers sont dans le site, enregistrés sur GitHub :

- `includes/online/programmes/hyrox.json` : semaines → séances → blocs → lignes ; la variante `run` (dans `"variantes"`) donne le programme RUN seul : les séances `run` du même fichier, avec ses propres blocs, thèmes et tests ;
- `includes/online/tests.json` : les tests de référence.

Le plus simple : demander la modification à Claude, qui vérifie le fichier et le publie. On peut aussi modifier le fichier sur GitHub (bouton crayon) : la publication est automatique.

**Après chaque modification**, ouvrir l'espace coach → « Programmes » → HYROX. La page vérifie le fichier, compte les séances encore vides et montre les blocs du programme, puis chaque semaine, séance par séance (dépliez une séance pour voir son détail, calculé avec les valeurs d'exemple des tests). En cas d'erreur, elle indique où (semaine, séance, bloc, ligne), et les membres voient « mise à jour en cours » jusqu'à la correction.

### Une séance

```json
{
  "id": "s2-run-a",
  "jour": 1,
  "type": "run",
  "titre": "6 × 800 m",
  "description": "Texte facultatif affiché sous le titre.",
  "blocs": [
    { "titre": "Échauffement", "texte": "15' en endurance fondamentale", "intensite": { "ref": "run_vc", "type": "domaine", "value": "modere" } },
    {
      "titre": "6 × 800 m", "note": "R = 1'30\"",
      "lignes": [
        { "texte": "Chaque 800 m", "intensite": { "ref": "run_vc", "type": "speed", "value": 105, "distance": 800 } }
      ]
    }
  ]
}
```

- `jour` : 1 = date de début du membre, 2 = le lendemain… jusqu'à 7.
- `type` : texte libre affiché en étiquette (run, force, erg, hyrox…).
- Une séance **sans `blocs`** est annoncée avec son titre et la mention « Le détail de cette séance arrive bientôt ». Il suffit d'ajouter ses blocs quand elle est prête.
- Un bloc a un `titre`, une `note` facultative (sets, récupération) et soit un `texte` (une ligne), soit des `lignes`. Une ligne peut être un simple texte : `"4 × 6 @RER2"`.
- `id` : identifiant de la séance. **Ne jamais changer l'`id` d'une séance déjà commencée par des membres** : leur suivi (faite, RPE, commentaire) y est rattaché.

### Les cibles calculées (« intensite »)

| Écriture | Résultat (VC 4:10 /km, 1K ROW 3:40, squat 110 kg) |
| --- | --- |
| `{ "ref": "run_vc", "type": "domaine", "value": "modere" }` | domaine modéré : plus lent que 5:05 /km |
| `{ "ref": "run_vc", "type": "domaine", "value": "eleve" }` | domaine élevé : 4:10 – 5:05 /km |
| `{ "ref": "run_vc", "type": "domaine", "value": "severe" }` | domaine sévère : plus vite que 4:10 /km |
| `{ "ref": "run_vc", "type": "speed", "value": 105 }` | 105 % de la VC : 3:58 /km |
| `{ "ref": "run_vc", "type": "speed", "value": 100, "distance": 800 }` | 4:10 /km, soit 3:20 le 800 m |
| `{ "ref": "run_vc", "type": "speed", "value": [90, 100] }` | fourchette : 4:38 – 4:10 /km |
| `{ "ref": "row_1000", "type": "power", "value": 80 }` | 80 % des watts du 1K : 210 W |
| `{ "ref": "back_squat_1rm", "type": "percent", "value": 75 }` | 75 % du 1RM, arrondi à 2,5 kg : 82,5 kg |

- Plusieurs cibles sur la même ligne : une liste, par exemple `"intensite": [ { … }, { … } ]`.
- Écrire le pourcentage dans le texte de la ligne (« 5 reps à 50 % ») : tant que le test n'est pas saisi, aucune charge ni allure n'est affichée, et une note en bas de séance indique le test manquant.
- Les charges femme / homme et `@raceweight` restent écrites dans le texte (`@8/12,5`).
- La structure du programme se règle dans `"structure"`, en tête du fichier : pour chaque bloc, un `titre`, ses `semaines` (première et dernière : `[1, 4]`), les types de séances (`seances`) et, si besoin, ses `tests`. Elle s'affiche dans l'aperçu coach et dans l'onglet Programme des membres, avec le bloc de la semaine mis en avant.
- `theme` d'une semaine : seulement ce qui la distingue (« Semaine des tests », « Test 5 km cette semaine »…) ; le bloc s'affiche déjà à part.

### Ajouter un test

Dans `tests.json`, ajouter par exemple :

```json
"deadlift_1rm": { "nom": "Deadlift 1RM", "etiquette": "1RM", "mesure": "charge", "unite": "kg", "arrondi": 2.5, "exemple": "140" }
```

- Chrono sur une distance : `"mesure": "temps"`, `distance` (m) et `allure` (1000 = /km) ; ajouter `"watts": true` pour un ergomètre Concept2.
- Chrono seul (METCON, simulation) : `"mesure": "chrono"`.
- `note` (facultatif) : une ligne affichée sous le nom du test dans « Mes tests » (quand et où il se passe).
- Répétitions : `"mesure": "reps"`.
- Ne jamais changer l'identifiant d'un test déjà utilisé : les résultats des membres y sont rattachés.

### Ajouter un programme (ATHX plus tard)

Créer le fichier `programmes/athx.json`, puis l'ajouter à `ON_PROGRAMMES` dans `includes/online/app.php`.

## Côté technique

- Pages : `online/index.php` (membres, connexion), `online/coach.php` (coachs). Code commun : `includes/online/app.php` (réglages : adresses des coachs, durées) et `engine.php` (calculateur).
- La page publique `hyrox-online.html` refait les mêmes calculs dans `assets/js/online-page.js` (seuil 1 à 82 %, watts, charges) : modifier les deux si une règle change.
- Données : `zero26-data/online.sqlite`, à côté de `public_html`. La publication depuis GitHub n'y touche jamais. Une copie par jour est gardée dans `zero26-data/sauvegardes` (30 jours).
- E-mails : envoyés par le serveur du site (`mail()`), de site@zero26coachingclub.com. Si les codes arrivent en spam, vérifier le SPF du domaine (il doit autoriser Hostinger).
- Essais sur un ordinateur : avec le serveur intégré de PHP, aucun e-mail ne part et le code s'affiche à l'écran.
