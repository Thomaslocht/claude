<?php
/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online : espace coach
   Membres (création, modification, désactivation, suppression), résultats de
   tests (saisis par l'athlète ou par le coach, re-tests avec historique),
   suivi des séances, code de connexion de secours, aperçu du programme.
   Accès : adresses de ON_COACHES (includes/online/app.php), connexion par code.
   ========================================================================== */
declare(strict_types=1);

require __DIR__ . '/../includes/online/app.php';

on_headers();

try {
    $user = on_user();
} catch (Throwable $e) {
    error_log('Online, coach : ' . $e->getMessage());
    http_response_code(503);
    on_page_start('Indisponible', null, 'coach');
    echo on_notice('L’espace est indisponible pour le moment (' . $e->getMessage() . ').', true);
    on_page_end();
    exit;
}
if (!$user || $user['role'] !== 'coach') on_redirect('./');

$coach = $user['prenom'];
$tests = on_tests();
$today = on_today();
$error = '';
$generated = null; // code de secours, affiché une seule fois
$old = [];         // valeurs du formulaire à réafficher après une erreur

$action = (string) ($_POST['action'] ?? '');
if ($action !== '' && !on_post_ok($user)) {
    $error = 'La page a expiré. Réessayez.';
    $action = '';
}

// Champs d'un membre (création et modification) ; renvoie [données, erreur]
function coach_member_fields(?int $id): array
{
    $email = sl_clean_email($_POST['email'] ?? '');
    $prenom = sl_clean_name($_POST['prenom'] ?? '');
    $offre = (string) ($_POST['offre'] ?? '');
    $programme = ON_OFFRES[$offre]['programme'] ?? '';
    $tel = mb_substr(preg_replace('/[^0-9+ .()-]/', '', (string) ($_POST['tel'] ?? '')) ?? '', 0, 30);
    $debut = on_valid_date($_POST['date_debut'] ?? '');
    $fin = on_valid_date($_POST['date_fin'] ?? '');
    if ($fin === '' && $debut !== '' && trim((string) ($_POST['date_fin'] ?? '')) === '') $fin = on_date_add($debut, ON_DEFAULT_WEEKS * 7 - 1);
    $data = ['email' => $email, 'prenom' => $prenom, 'offre' => $offre, 'programme' => $programme, 'tel' => trim($tel), 'date_debut' => $debut, 'date_fin' => $fin];
    if ($email === '') return [$data, 'Adresse e-mail invalide.'];
    if (isset(ON_COACHES[$email])) return [$data, 'Cette adresse est celle d’un coach.'];
    $dup = on_row('SELECT id FROM membres WHERE email = ?', [$email]);
    if ($dup && (int) $dup['id'] !== $id) return [$data, 'Un membre utilise déjà cette adresse.'];
    if ($prenom === '') return [$data, 'Indiquez le prénom.'];
    if (!isset(ON_OFFRES[$offre]) || !isset(ON_PROGRAMMES[$programme])) return [$data, 'Choisissez l’offre.'];
    if ($debut === '') return [$data, 'Date de début invalide.'];
    if ($fin === '' || $fin < $debut) return [$data, 'La date de fin doit suivre la date de début.'];
    return [$data, ''];
}

/* ---------- Enregistrements ---------- */
try {
    on_purge_old();

    if ($action === 'membre_ajout') {
        [$d, $error] = coach_member_fields(null);
        if ($error === '') {
            on_q('INSERT INTO membres (email, prenom, programme, date_debut, date_fin, actif, cree_le, cree_par, offre, tel, debut_choisi) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 1)',
                [$d['email'], $d['prenom'], $d['programme'], $d['date_debut'], $d['date_fin'], on_now_str(), $coach, $d['offre'], $d['tel']]);
            on_redirect('coach.php?m=' . on_db()->lastInsertId() . '&ok=cree#contenu');
        }
        $old = $d;
    } elseif ($action === 'membre_maj') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($before = on_member($id)) {
            [$d, $error] = coach_member_fields($id);
            if ($error === '') {
                // Une date de début changée par le coach vaut choix : le membre n'a plus à la donner
                $chosen = (int) $before['debut_choisi'] || $d['date_debut'] !== $before['date_debut'] ? 1 : 0;
                on_q('UPDATE membres SET email = ?, prenom = ?, offre = ?, programme = ?, tel = ?, date_debut = ?, date_fin = ?, debut_choisi = ?, actif = ? WHERE id = ?',
                    [$d['email'], $d['prenom'], $d['offre'], $d['programme'], $d['tel'], $d['date_debut'], $d['date_fin'], $chosen, empty($_POST['actif']) ? 0 : 1, $id]);
                if (empty($_POST['actif'])) on_q('DELETE FROM sessions WHERE email = ?', [$d['email']]);
                on_redirect("coach.php?m=$id&ok=maj");
            }
            $old = $d;
        }
    } elseif ($action === 'membre_suppr') {
        on_delete_member((int) ($_POST['id'] ?? 0));
        on_redirect('coach.php?ok=suppr');
    } elseif ($action === 'test_ajout') {
        $id = (int) ($_POST['id'] ?? 0);
        $tid = (string) ($_POST['test'] ?? '');
        $date = on_valid_date($_POST['date'] ?? '');
        $value = isset($tests[$tid]) ? on_parse_value($tests[$tid], (string) ($_POST['valeur'] ?? '')) : null;
        if (!on_member($id)) $error = 'Membre introuvable.';
        elseif (!isset($tests[$tid]) || !$tests[$tid]['saisie']) $error = 'Choisissez un test.';
        elseif ($value === null) $error = on_value_hint($tests[$tid]);
        elseif ($date === '') $error = 'Date du test invalide.';
        else {
            on_q('INSERT INTO tests (membre_id, test, valeur, date_test, statut, saisi_par, saisi_le) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $tid, $value, $date, 'valide', $coach, on_now_str()]);
            on_redirect("coach.php?m=$id&ok=test#tests");
        }
        $old = ['test' => $tid, 'valeur' => (string) ($_POST['valeur'] ?? ''), 'date' => $date];
    } elseif ($action === 'test_suppr') {
        $r = on_row('SELECT * FROM tests WHERE id = ?', [(int) ($_POST['test_id'] ?? 0)]);
        if ($r) {
            on_q('DELETE FROM tests WHERE id = ?', [(int) $r['id']]);
            on_redirect('coach.php?m=' . (int) $r['membre_id'] . '&ok=test_suppr#tests');
        }
    } elseif ($action === 'code') {
        $m = on_member((int) ($_POST['id'] ?? 0));
        if ($m && on_account($m['email'])) $generated = ['code' => on_new_code($m['email']), 'membre' => (int) $m['id']];
        else $error = 'Ce membre ne peut pas se connecter (désactivé ou accès terminé).';
        $_GET['m'] = (string) ($m['id'] ?? '');
    }
} catch (Throwable $e) {
    error_log('Online, coach : ' . $e->getMessage());
    $error = 'L’enregistrement n’a pas fonctionné (' . $e->getMessage() . ').';
}

$notice = match ($_GET['ok'] ?? '') {
    'cree' => 'Membre créé. Il peut se connecter dès maintenant avec son adresse e-mail.',
    'maj' => 'Fiche enregistrée.',
    'suppr' => 'Membre supprimé, avec ses tests et son suivi.',
    'test' => 'Résultat enregistré : les cibles des séances ultérieures sont recalculées.',
    'test_suppr' => 'Résultat supprimé.',
    default => '',
};

// État d'un membre : « Semaine 3/8 », « Commence le … », « Terminé », « Désactivé »
function coach_state(array $m, array $prog): array
{
    if (!(int) $m['actif']) return ['Désactivé', 'off'];
    if ($m['date_fin'] < on_today()) return ['Accès terminé', 'off'];
    if (!(int) $m['debut_choisi']) return ['Date de début à choisir', 'soon'];
    $nb = count($prog['semaines']);
    $w = on_week_now($m['date_debut'], max(1, $nb));
    if ($w === 0) return ['Commence le ' . on_fmt_date($m['date_debut'], 'court'), 'soon'];
    if ($w > $nb) return ['Programme fini', 'done'];
    return ["Semaine $w/$nb", 'now'];
}

// Une séance repliable : jour, type, titre ; le détail s'ouvre au clic
function coach_seance_fold(array $s, array $values, string $dateLabel, bool $open = false): string
{
    $empty = !$s['blocs'];
    return '<details class="on-sfold' . ($empty ? ' is-empty' : '') . '"' . ($open && !$empty ? ' open' : '') . '>'
        . '<summary><span class="on-sfold__d">' . h($dateLabel) . '</span><span class="on-type">' . h(mb_strtoupper($s['type'])) . '</span>'
        . '<span class="on-sfold__t">' . h($s['titre']) . '</span>'
        . ($empty ? '<span class="on-sfold__s">à remplir</span>' : '<span class="on-sfold__s">' . count($s['blocs']) . ' bloc' . (count($s['blocs']) > 1 ? 's' : '') . '</span>')
        . '</summary><div class="on-sfold__body">'
        . ($s['description'] !== '' ? '<p class="on-desc">' . h($s['description']) . '</p>' : '')
        . on_render_blocs($s, $values) . on_render_sources($s, $values) . '</div></details>';
}

/* ======================================================================
   Aperçu d'un programme
   ====================================================================== */
if (isset($_GET['programme'])):
    $pid = (string) $_GET['programme'];
    $prog = on_programme($pid);
    // Valeurs d'exemple (tests.json) pour voir les cibles calculées
    $exRows = [];
    foreach ($tests as $tid => $t) {
        $v = $t['saisie'] ? on_parse_value($t, $t['exemple']) : null;
        if ($v !== null) $exRows[] = ['id' => 0, 'valeur' => $v, 'date_test' => $today, 'statut' => 'valide', 'test' => $tid];
    }
    $ex = on_values_at($exRows, $today);
    $nbW = count($prog['semaines']);
    $w = max(1, min($nbW, (int) ($_GET['semaine'] ?? 1)));
    on_page_start('Programme ' . (ON_PROGRAMMES[$pid] ?? $pid), $user, 'coach');
    ?>
  <a class="on-back" href="coach.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>Membres</a>
  <section class="on-hero on-hero--sm">
    <p class="on-k">Aperçu du programme</p>
    <h1 class="on-h1"><?= h($prog['nom']) ?></h1>
  </section>
  <?php if (!$prog['ok']): ?>
  <div class="on-note on-note--err" role="alert">
    <p><b><?= count($prog['erreurs']) ?> erreur<?= count($prog['erreurs']) > 1 ? 's' : '' ?></b> : tant qu’elles restent, les membres de ce programme voient « mise à jour en cours ».</p>
    <ul><?php foreach ($prog['erreurs'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php else: $empty = count(array_filter($prog['seances'], fn($s) => !$s['blocs'])); ?>
  <?= on_render_structure($prog['structure'], $w) ?>
  <p class="on-help on-preview__info"><?= $nbW ?> semaines, <?= count($prog['seances']) ?> séances<?= $empty ? ", dont $empty encore à remplir" : '' ?>. Charges et allures calculées avec les valeurs d’exemple des tests&nbsp;:
    <?= h(implode(' · ', array_map(fn($tid) => $tests[$tid]['nom'] . ' ' . on_fmt_value($tests[$tid], (float) $ex[$tid]['valeur']), array_keys($ex)))) ?>.</p>
  <?php if ($prog['infos'] !== ''): ?><p class="on-note on-note--info"><?= h($prog['infos']) ?></p><?php endif; ?>

  <nav class="on-weeks" aria-label="Semaines du programme">
    <?php for ($i = 1; $i <= $nbW; $i++): ?>
    <a href="coach.php?<?= h(http_build_query(['programme' => $pid, 'semaine' => $i])) ?>"<?= $i === $w ? ' aria-current="page"' : '' ?>><span class="on-sr">Semaine </span><?= $i ?></a>
    <?php endfor; ?>
  </nav>
  <?php $week = $prog['semaines'][$w - 1]; ?>
  <section class="on-sec on-sec--tight">
    <h2 class="on-h2">Semaine <?= $w ?><?= $week['theme'] !== '' ? ' <small>' . h($week['theme']) . '</small>' : '' ?></h2>
    <div class="on-sfolds">
      <?php foreach ($week['seances'] as $k => $s) echo coach_seance_fold($s, $ex, 'Jour ' . $s['jour'], $k === 0); ?>
    </div>
  </section>
  <?php endif; ?>
<?php
    on_page_end();
    exit;
endif;

/* ======================================================================
   Fiche d'un membre
   ====================================================================== */
$m = isset($_GET['m']) ? on_member((int) $_GET['m']) : null;
if ($m):
    $mid = (int) $m['id'];
    $prog = on_programme($m['programme']);
    $rows = on_member_tests($mid);
    $suivi = on_member_suivi($mid);
    $now = on_values_at($rows, $today);
    [$state, $stateCls] = coach_state($m, $prog);
    $f = $old && $action === 'membre_maj' ? $old + $m : $m;
    $done = count(array_filter($suivi, fn($r) => (int) $r['fait'] && isset($prog['seances'][$r['seance']])));
    on_page_start($m['prenom'], $user, 'coach');
    ?>
  <a class="on-back" href="coach.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>Membres</a>

  <section class="on-hero on-hero--sm">
    <p class="on-k"><?= h(ON_OFFRES[$m['offre']]['nom'] ?? $m['offre']) ?> · <span class="on-state is-<?= $stateCls ?>"><?= h($state) ?></span></p>
    <h1 class="on-h1"><?= h($m['prenom']) ?></h1>
    <p class="on-lead"><?= h($m['email']) ?><?php if ($m['tel'] !== ''): ?> · <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $m['tel'])) ?>"><?= h($m['tel']) ?></a><?php endif; ?> · <?= $done ?>/<?= count($prog['seances']) ?> séances faites</p>
    <?php if (!(int) $m['debut_choisi']): ?><p class="on-note on-note--warn">Espace créé par l’achat bsport&nbsp;: <?= h($m['prenom']) ?> n’a pas encore choisi sa date de démarrage (il la choisit à sa première connexion, ou vous la fixez dans la fiche).</p><?php endif; ?>
    <div class="on-actions">
      <a class="on-btn on-btn--grad" href="./?voir=<?= $mid ?>">Voir son espace</a>
      <form method="post" action="coach.php">
        <?= on_csrf_field($user) ?><input type="hidden" name="action" value="code"><input type="hidden" name="id" value="<?= $mid ?>">
        <button class="on-btn on-btn--ghost" type="submit">Code de connexion de secours</button>
      </form>
    </div>
  </section>

  <?= on_notice($error, true) ?>
  <?= on_notice($notice) ?>
  <?php if ($generated): ?>
  <div class="on-note on-note--code" role="status">
    <p>Code de connexion pour <?= h($m['prenom']) ?>&nbsp;: <b><?= h($generated['code']) ?></b></p>
    <p class="on-help">Valable 10 minutes, une seule fois. À envoyer par SMS si l’e-mail n’arrive pas&nbsp;: <?= h($m['prenom']) ?> saisit son adresse sur zero26coachingclub.com/online, puis ce code. Il remplace tout code envoyé avant.</p>
  </div>
  <?php endif; ?>
  <?php if (!$prog['ok']): ?>
  <p class="on-note on-note--err">Le programme contient des erreurs&nbsp;: <a href="coach.php?programme=<?= h($m['programme']) ?>">voir le détail</a>.</p>
  <?php endif; ?>

  <!-- Tests -->
  <section class="on-sec" id="tests">
    <h2 class="on-h2">Tests</h2>
    <p class="on-help on-sec__intro">Saisis par l’athlète dans son espace, ou par vous ici. Chaque résultat sert tout de suite au calcul, à partir de sa date.</p>
    <div class="on-tests">
      <?php foreach (on_prog_tests($prog) as $tid => $t):
          $hist = array_reverse(array_values(array_filter($rows, fn($r) => $r['test'] === $tid)));
          $cur = $now[$tid] ?? null; ?>
      <article class="on-card on-test<?= $t['mesure'] === 'vc' ? ' on-test--vc' : '' ?>">
        <header class="on-test__head"><?= on_tag($t['etiquette'], $t['appareil']) ?><h3><?= h($t['nom']) ?></h3></header>
        <?php if ($t['note'] !== ''): ?><p class="on-test__note"><?= h($t['note']) ?></p><?php endif; ?>
        <?= on_render_test_now($t, $cur, $hist, 'Pas encore de résultat.') ?>
        <?php if ($hist): ?>
        <ul class="on-histlist">
          <?php foreach ($hist as $r): ?>
          <li>
            <span class="on-histlist__d"><?= h(on_fmt_date($r['date_test'], 'court')) ?> <?= h(substr($r['date_test'], 0, 4)) ?></span>
            <b><?= h(on_fmt_value($t, (float) $r['valeur'])) ?></b>
            <span class="on-histlist__s">saisi par <?= $r['saisi_par'] === 'membre' ? h($m['prenom']) : h($r['saisi_par']) ?></span>
            <form class="on-histlist__a" method="post" action="coach.php" data-confirm="Supprimer ce résultat (<?= h(on_fmt_value($t, (float) $r['valeur'])) ?>) ?"><?= on_csrf_field($user) ?><input type="hidden" name="action" value="test_suppr"><input type="hidden" name="test_id" value="<?= (int) $r['id'] ?>"><button class="on-link on-link--danger" type="submit">Supprimer</button></form>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>

    <form method="post" action="coach.php" class="on-card on-form on-addtest">
      <?= on_csrf_field($user) ?>
      <input type="hidden" name="action" value="test_ajout">
      <input type="hidden" name="id" value="<?= $mid ?>">
      <p class="on-form__t">Saisir un test ou un re-test</p>
      <div class="on-form__row on-form__row--3">
        <label class="on-field"><span>Test</span>
          <select name="test" required><?php foreach (on_prog_tests($prog) as $tid => $t): if (!$t['saisie']) continue; ?><option value="<?= h($tid) ?>"<?= ($old['test'] ?? '') === $tid ? ' selected' : '' ?>><?= h($t['nom']) ?></option><?php endforeach; ?></select>
        </label>
        <label class="on-field"><span>Résultat</span><input type="text" name="valeur" value="<?= h($old['valeur'] ?? '') ?>" placeholder="3:40 ou 110" maxlength="12" required></label>
        <label class="on-field"><span>Date du test</span><input type="date" name="date" value="<?= h(($old['date'] ?? '') ?: $today) ?>" required></label>
      </div>
      <p class="on-help">Il s’applique aux séances à partir de sa date&nbsp;; les séances d’avant gardent l’ancien résultat (historique conservé).</p>
      <button class="on-btn on-btn--grad" type="submit">Enregistrer le résultat</button>
    </form>
  </section>

  <!-- Suivi -->
  <section class="on-sec" id="suivi">
    <h2 class="on-h2">Suivi des séances</h2>
    <?php if ($prog['ok']): $wNow = on_week_now($m['date_debut'], count($prog['semaines'])); foreach ($prog['semaines'] as $w): ?>
    <details class="on-card on-weekfold"<?= $wNow === $w['numero'] ? ' open' : '' ?>>
      <summary>
        <span>Semaine <?= $w['numero'] ?><?= $w['theme'] !== '' ? ' · ' . h($w['theme']) : '' ?></span>
        <span class="on-help"><?= count(array_filter($w['seances'], fn($s) => (int) ($suivi[$s['id']]['fait'] ?? 0))) ?>/<?= count($w['seances']) ?></span>
      </summary>
      <div class="on-table">
        <table>
          <thead><tr><th>Date</th><th>Séance</th><th>Faite</th><th>RPE</th><th>Commentaire</th></tr></thead>
          <tbody>
          <?php foreach ($w['seances'] as $s): $st = $suivi[$s['id']] ?? null; ?>
            <tr>
              <td class="num"><?= h(on_fmt_date(on_seance_date($m['date_debut'], $s), 'court')) ?></td>
              <td><span class="on-type"><?= h(mb_strtoupper($s['type'])) ?></span> <?= h($s['titre']) ?></td>
              <td><?= $st && (int) $st['fait'] ? '<span class="on-yes">Oui</span>' : '<span class="on-help">—</span>' ?></td>
              <td class="num"><?= $st && $st['rpe'] ? (int) $st['rpe'] : '' ?></td>
              <td class="on-table__wrap"><?= $st ? nl2br(h($st['commentaire'])) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
    <?php endforeach; endif; ?>
  </section>

  <!-- Fiche -->
  <section class="on-sec" id="fiche">
    <h2 class="on-h2">Fiche</h2>
    <form method="post" action="coach.php" class="on-card on-form">
      <?= on_csrf_field($user) ?>
      <input type="hidden" name="action" value="membre_maj">
      <input type="hidden" name="id" value="<?= $mid ?>">
      <div class="on-form__row">
        <label class="on-field"><span>Prénom</span><input type="text" name="prenom" value="<?= h($f['prenom']) ?>" maxlength="40" required></label>
        <label class="on-field"><span>E-mail</span><input type="email" name="email" value="<?= h($f['email']) ?>" maxlength="120" required></label>
      </div>
      <div class="on-form__row">
        <label class="on-field"><span>Offre</span>
          <select name="offre"><?php foreach (ON_OFFRES as $oid => $o): ?><option value="<?= h($oid) ?>"<?= $f['offre'] === $oid ? ' selected' : '' ?>><?= h($o['nom']) ?></option><?php endforeach; ?></select>
        </label>
        <label class="on-field"><span>Téléphone</span><input type="tel" name="tel" value="<?= h($f['tel']) ?>" maxlength="30" autocomplete="off"></label>
      </div>
      <div class="on-form__row">
        <label class="on-field"><span>Début</span><input type="date" name="date_debut" value="<?= h($f['date_debut']) ?>" required data-on-debut></label>
        <label class="on-field"><span>Fin de l’accès</span><input type="date" name="date_fin" value="<?= h($f['date_fin']) ?>" required data-on-fin></label>
      </div>
      <label class="on-check"><input type="checkbox" name="actif" value="1"<?= (int) $m['actif'] ? ' checked' : '' ?>><span>Accès actif <i>(décocher pour désactiver&nbsp;: le membre est déconnecté, ses données sont gardées)</i></span></label>
      <button class="on-btn on-btn--grad" type="submit">Enregistrer la fiche</button>
      <p class="on-help">Créé le <?= h(on_fmt_date(substr($m['cree_le'], 0, 10), 'annee')) ?> <?= $m['cree_par'] === 'bsport' ? 'par l’achat sur bsport' : 'par ' . h($m['cree_par']) ?>. Données supprimées automatiquement un an après la fin de l’accès.</p>
      <?php $buys = on_all('SELECT * FROM achats WHERE membre_id = ? ORDER BY recu_le DESC', [$mid]); if ($buys): ?>
      <ul class="on-buys"><?php foreach ($buys as $b): ?><li><span><?= h(on_fmt_date(substr($b['recu_le'], 0, 10), 'annee')) ?></span><b><?= h($b['produit']) ?></b><span><?= h(number_format($b['montant'] / 100, 2, ',', ' ')) ?>&nbsp;€</span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </form>
    <form method="post" action="coach.php" class="on-danger" data-confirm="Supprimer définitivement <?= h($m['prenom']) ?>, ses tests et son suivi ? Cette action est irréversible.">
      <?= on_csrf_field($user) ?><input type="hidden" name="action" value="membre_suppr"><input type="hidden" name="id" value="<?= $mid ?>">
      <button class="on-link on-link--danger" type="submit">Supprimer définitivement ce membre</button>
    </form>
  </section>
<?php
    on_page_end();
    exit;
endif;

/* ======================================================================
   Liste des membres
   ====================================================================== */
$members = on_all('SELECT * FROM membres ORDER BY actif DESC, date_debut DESC, prenom');
$recent = on_all("SELECT t.*, m.prenom FROM tests t JOIN membres m ON m.id = t.membre_id WHERE t.saisi_le >= ? ORDER BY t.saisi_le DESC LIMIT 8", [on_date_add($today, -14)]);
$progs = [];
foreach (ON_PROGRAMMES as $pid => $pn) $progs[$pid] = on_programme($pid);
$f = $action === 'membre_ajout' ? $old : [];
$nextMonday = date('Y-m-d', strtotime('monday this week') < strtotime($today) ? strtotime('next monday') : strtotime($today));
$debut = $f['date_debut'] ?? $nextMonday;
$current = count(array_filter($members, fn($mm) => (int) $mm['actif'] && $mm['date_fin'] >= $today));

on_page_start('Espace coach', $user, 'coach');
?>
  <section class="on-hero on-hero--sm">
    <p class="on-k">Bonjour <?= h($coach) ?></p>
    <h1 class="on-h1">Espace <span class="on-grad">coach</span></h1>
  </section>

  <?= on_notice($error, true) ?>
  <?= on_notice($notice) ?>

  <ul class="on-stats">
    <li><b><?= $current ?></b><span>membre<?= $current > 1 ? 's' : '' ?> avec un accès en cours</span></li>
    <li><b><?= count($recent) ?></b><span>test<?= count($recent) > 1 ? 's' : '' ?> saisi<?= count($recent) > 1 ? 's' : '' ?> ces 14 derniers jours</span></li>
    <li><b><?= count($members) ?></b><span>membre<?= count($members) > 1 ? 's' : '' ?> au total</span></li>
  </ul>

  <section class="on-sec">
    <h2 class="on-h2">Membres</h2>
    <?php if (!$members): ?>
    <p class="on-card on-help">Aucun membre pour l’instant. Ajoutez le premier ci-dessous.</p>
    <?php else: ?>
    <div class="on-card on-table">
      <table>
        <thead><tr><th>Prénom</th><th>État</th><th>Séances</th><th>Dernier RPE</th><th>Dernière activité</th><th>Accès jusqu’au</th></tr></thead>
        <tbody>
        <?php foreach ($members as $mm):
            $p = $progs[$mm['programme']] ?? on_programme($mm['programme']);
            [$state, $cls] = coach_state($mm, $p);
            $sv = on_all('SELECT * FROM suivi WHERE membre_id = ? ORDER BY maj_le DESC', [(int) $mm['id']]);
            $nbDone = count(array_filter($sv, fn($r) => (int) $r['fait'] && isset($p['seances'][$r['seance']])));
            $lastRpe = null;
            foreach ($sv as $r) if ($r['rpe']) { $lastRpe = (int) $r['rpe']; break; } ?>
          <tr>
            <td><a class="on-strong" href="coach.php?m=<?= (int) $mm['id'] ?>"><?= h($mm['prenom']) ?></a> <span class="on-offre"><?= h(ON_OFFRES[$mm['offre']]['court'] ?? $mm['offre']) ?></span><br><span class="on-help"><?= h($mm['email']) ?></span></td>
            <td><span class="on-state is-<?= $cls ?>"><?= h($state) ?></span></td>
            <td class="num"><?= $nbDone ?>/<?= count($p['seances']) ?></td>
            <td class="num"><?= $lastRpe ?? '—' ?></td>
            <td class="num"><?= $sv ? h(on_fmt_date(substr($sv[0]['maj_le'], 0, 10), 'court')) : '—' ?></td>
            <td class="num"><?= h(on_fmt_date($mm['date_fin'], 'court')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($recent): ?>
  <section class="on-sec">
    <h2 class="on-h2">Derniers tests saisis</h2>
    <ul class="on-rows">
      <?php foreach ($recent as $r): $t = $tests[$r['test']] ?? null; if (!$t) continue; ?>
      <li><a class="on-row" href="coach.php?m=<?= (int) $r['membre_id'] ?>#tests">
        <span class="on-row__date"><b><?= h($r['prenom']) ?></b><?= h(on_fmt_date($r['date_test'], 'court')) ?></span>
        <span class="on-row__main"><?= on_tag($t['etiquette'], $t['appareil']) ?><span class="on-row__t"><?= h($t['nom']) ?> · <?= h(on_fmt_value($t, (float) $r['valeur'])) ?></span></span>
        <span class="on-row__st"><?= $r['saisi_par'] === 'membre' ? 'par l’athlète' : 'par ' . h($r['saisi_par']) ?></span>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="on-sec" id="ajout">
    <h2 class="on-h2">Ajouter un membre</h2>
    <form method="post" action="coach.php#ajout" class="on-card on-form">
      <?= on_csrf_field($user) ?>
      <input type="hidden" name="action" value="membre_ajout">
      <div class="on-form__row">
        <label class="on-field"><span>Prénom</span><input type="text" name="prenom" value="<?= h($f['prenom'] ?? '') ?>" maxlength="40" required></label>
        <label class="on-field"><span>E-mail</span><input type="email" name="email" value="<?= h($f['email'] ?? '') ?>" maxlength="120" required></label>
      </div>
      <div class="on-form__row">
        <label class="on-field"><span>Offre</span>
          <select name="offre"><?php foreach (ON_OFFRES as $oid => $o): ?><option value="<?= h($oid) ?>"<?= ($f['offre'] ?? 'pack') === $oid ? ' selected' : '' ?>><?= h($o['nom']) ?></option><?php endforeach; ?></select>
        </label>
        <label class="on-field"><span>Téléphone</span><input type="tel" name="tel" value="<?= h($f['tel'] ?? '') ?>" maxlength="30" autocomplete="off"></label>
      </div>
      <div class="on-form__row">
        <label class="on-field"><span>Début</span><input type="date" name="date_debut" value="<?= h($debut) ?>" required data-on-debut></label>
        <label class="on-field"><span>Fin de l’accès</span><input type="date" name="date_fin" value="<?= h(($f['date_fin'] ?? '') ?: on_date_add($debut, ON_DEFAULT_WEEKS * 7 - 1)) ?>" required data-on-fin data-on-auto></label>
      </div>
      <p class="on-help">Inutile pour un achat sur bsport&nbsp;: l’espace se crée tout seul. Ici, pour un membre ajouté à la main. Le jour 1 du programme est la date de début (un lundi de préférence). Fin proposée&nbsp;: 8 semaines + 2 semaines de marge.</p>
      <button class="on-btn on-btn--grad" type="submit">Créer le membre</button>
    </form>
  </section>

  <section class="on-sec" id="bsport">
    <h2 class="on-h2">Achats bsport</h2>
    <div class="on-card on-webhook">
      <p>À chaque achat d’un produit HYROX Online sur bsport, l’espace du client se crée tout seul&nbsp;: il reçoit le lien par e-mail et choisit sa date de démarrage à sa première connexion. L’équipe (<?= h(ON_MAIL_REPLY) ?> et les coachs) reçoit une alerte pour le rappeler.</p>
      <details>
        <summary>Réglage dans bsport (une seule fois)</summary>
        <ol>
          <li>bsport → Réglages → Webhooks → «&nbsp;Ajouter un webhook&nbsp;».</li>
          <li>Événement&nbsp;: <b>invoice-pay</b>.</li>
          <li>URL&nbsp;: le lien ci-dessous, en entier. Il contient une clé secrète&nbsp;: ne le partagez pas.</li>
          <li>«&nbsp;Valider&nbsp;», puis «&nbsp;Tester&nbsp;»&nbsp;: le dernier appel reçu s’affiche ici (rechargez la page).</li>
        </ol>
        <?php if (on_is_local()): ?><p class="on-note on-note--warn">Copie d’essai sur ordinateur&nbsp;: ce lien ne vaut pas pour le site en ligne. Copiez celui de zero26coachingclub.com/online/coach.php.</p><?php endif; ?>
        <label class="on-field"><span>Lien du webhook</span><input type="text" value="<?= h(on_webhook_url()) ?>" readonly data-on-select></label>
        <p class="on-help">Il commence par <b><?= h(ON_SITE) ?>/online/bsport.php/</b> et se termine par 48 caractères (chiffres et lettres de a à f). Si bsport avait déjà un lien en «&nbsp;?cle=&nbsp;», remplacez-le par celui-ci.</p>
      </details>
      <?php $last = on_webhook_last(); $refus = on_webhook_last_refusal(); ?>
      <p class="on-help">Dernier appel reçu de bsport&nbsp;: <?= $last !== '' ? h($last) : 'aucun pour l’instant' ?>.</p>
      <?php if ($refus !== '' && substr($refus, 0, 16) >= substr($last, 0, 16)): ?><p class="on-note on-note--warn">Dernier appel refusé&nbsp;: <?= h($refus) ?>.</p><?php endif; ?>
      <p class="on-help">Produits reconnus (nom exact dans la boutique bsport)&nbsp;: <?= h(implode(' · ', array_map(fn($o) => $o['produit'], ON_OFFRES))) ?>.</p>
    </div>
  </section>

  <section class="on-sec">
    <h2 class="on-h2">Programme</h2>
    <ul class="on-rows">
      <?php foreach ($progs as $pid => $p): ?>
      <li><a class="on-row" href="coach.php?programme=<?= h($pid) ?>">
        <span class="on-row__date"><b><?= h(ON_PROGRAMMES[$pid]) ?></b>Aperçu</span>
        <span class="on-row__main"><span class="on-row__t"><?= h($p['nom']) ?></span></span>
        <?= $p['ok'] ? '<span class="on-row__st is-done">' . count($p['semaines']) . ' sem. · ' . count($p['seances']) . ' séances</span>' : '<span class="on-row__st is-err">' . count($p['erreurs']) . ' erreur' . (count($p['erreurs']) > 1 ? 's' : '') . '</span>' ?>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php
on_page_end();
