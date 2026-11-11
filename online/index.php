<?php
/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online : espace membre
   Connexion par code e-mail, semaine en cours, séance du jour avec les allures
   calculées, suivi (faite, RPE, commentaire) et résultats de tests.
   Un coach peut ouvrir l'espace d'un membre en lecture seule (?voir=ID).
   ========================================================================== */
declare(strict_types=1);

require __DIR__ . '/../includes/online/app.php';

on_headers();

try {
    $user = on_user();
} catch (Throwable $e) {
    error_log('Online : ' . $e->getMessage());
    http_response_code(503);
    on_page_start('Indisponible', null);
    echo '<section class="on-hero"><h1 class="on-h1">Un <span class="on-grad">instant</span></h1></section>';
    echo on_notice('L’espace est indisponible pour le moment. Réessayez dans quelques minutes, ou appelez-nous au 07 64 62 30 85.', true);
    on_page_end();
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$error = '';
if ($action !== '' && !on_post_ok($user)) {
    $error = 'La page a expiré. Réessayez.';
    $action = '';
}

if ($action === 'logout') {
    on_logout($user);
    on_redirect('./');
}

/* ======================================================================
   Connexion
   ====================================================================== */
if (!$user) {
    $step = 'email';
    $email = '';
    $testCode = null;
    try {
        if ($action === 'send_code') {
            $email = sl_clean_email($_POST['email'] ?? '');
            if ($email === '') {
                $error = 'Cette adresse e-mail n’est pas valide.';
            } elseif (on_limited('envoi-ip', on_ip(), 10, 3600) || on_limited('envoi-mail', $email, 3, 900)) {
                $error = 'Trop de demandes de code. Réessayez dans 15 minutes.';
                $step = 'code';
            } else {
                // Même réponse que l'adresse soit inscrite ou non
                $acc = on_account($email);
                if ($acc) {
                    $code = on_new_code($email);
                    if (!on_mail_code($email, $code, $acc['prenom'])) error_log("Online : le code pour $email n’a pas pu partir.");
                    if (on_is_local()) $testCode = $code;
                }
                $step = 'code';
            }
        } elseif ($action === 'verify') {
            $email = sl_clean_email($_POST['email'] ?? '');
            $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
            $step = $email === '' ? 'email' : 'code';
            if (on_limited('verif-ip', on_ip(), 20, 900)) {
                $error = 'Trop d’essais. Réessayez dans 15 minutes.';
            } elseif ($email === '' || strlen($code) !== 6) {
                $error = 'Le code contient 6 chiffres.';
            } else {
                $res = on_check_code($email, $code);
                $acc = $res === 'ok' ? on_account($email) : null;
                if ($acc) {
                    on_login($email);
                    on_redirect($acc['role'] === 'coach' ? 'coach.php' : './');
                }
                $error = match ($res) {
                    'faux' => 'Code incorrect. Vérifiez les 6 chiffres reçus.',
                    'bloque' => 'Trop d’essais pour ce code. Demandez-en un nouveau.',
                    default => 'Ce code n’est plus valable. Demandez-en un nouveau.',
                };
            }
        }
    } catch (Throwable $e) {
        error_log('Online, connexion : ' . $e->getMessage());
        $error = 'La connexion est indisponible pour le moment. Réessayez dans quelques minutes.';
    }

    on_page_start('Connexion', null, 'connexion');
    ?>
  <section class="on-hero on-hero--login">
    <h1 class="on-h1">Mon <span class="on-grad">programme</span></h1>
    <p class="on-lead">Vos 8 semaines de préparation, avec vos allures calculées à partir de vos propres tests.</p>
  </section>

  <div class="on-card on-login">
    <?= on_notice($error, true) ?>
    <?php if ($step === 'email'): ?>
    <form method="post" action="./" class="on-form">
      <input type="hidden" name="action" value="send_code">
      <label class="on-field">
        <span>Votre adresse e-mail</span>
        <input type="email" name="email" value="<?= h($email) ?>" autocomplete="email" inputmode="email" maxlength="120" required autofocus>
      </label>
      <button class="on-btn on-btn--grad on-btn--block" type="submit">Recevoir mon code</button>
      <p class="on-help">Pas de mot de passe&nbsp;: vous recevez un code à 6 chiffres par e-mail. Utilisez l’adresse de votre achat sur bsport.</p>
    </form>
    <?php else: ?>
    <?php if ($testCode !== null): ?>
    <p class="on-note on-note--test">Essai sur ordinateur&nbsp;: aucun e-mail n’est envoyé. Le code est <b><?= h($testCode) ?></b>.</p>
    <?php endif; ?>
    <form method="post" action="./" class="on-form">
      <input type="hidden" name="action" value="verify">
      <input type="hidden" name="email" value="<?= h($email) ?>">
      <p class="on-help on-help--top">Si <b><?= h($email) ?></b> est inscrite, un code vient de partir. Il est valable 10&nbsp;minutes.</p>
      <label class="on-field">
        <span>Code reçu par e-mail</span>
        <input class="on-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="000000" required autofocus>
      </label>
      <button class="on-btn on-btn--grad on-btn--block" type="submit">Me connecter</button>
    </form>
    <div class="on-login__alt">
      <form method="post" action="./">
        <input type="hidden" name="action" value="send_code">
        <input type="hidden" name="email" value="<?= h($email) ?>">
        <button class="on-link" type="submit">Renvoyer un code</button>
      </form>
      <a class="on-link" href="./">Changer d’adresse</a>
    </div>
    <p class="on-help">Rien reçu&nbsp;? Regardez dans les spams, ou demandez un code à votre coach.</p>
    <?php endif; ?>
  </div>

  <p class="on-legal">Cet espace enregistre votre prénom, votre e-mail, votre téléphone, l’offre achetée, vos résultats de tests et le suivi de vos séances, uniquement pour votre coaching chez ZERO26. Ils sont supprimés un an après la fin de votre accès. <a href="/mentions-legales.html#online">Données personnelles</a></p>
  <p class="on-legal">Pas encore inscrit&nbsp;? <a href="/hyrox-online.html">Découvrir HYROX Online</a></p>
<?php
    on_page_end();
    exit;
}

/* ======================================================================
   Espace membre (ou aperçu par un coach)
   ====================================================================== */
$readonly = false;
if ($user['role'] === 'coach') {
    $m = on_member((int) ($_GET['voir'] ?? 0));
    if (!$m) on_redirect('coach.php');
    $readonly = true;
} else {
    $m = $user['membre'];
}
$mid = (int) $m['id'];
$prog = on_programme($m["programme"]);
$tests = on_tests();

$link = function (array $p = []) use ($readonly, $mid): string {
    if ($readonly) $p = ['voir' => $mid] + $p;
    return './' . ($p ? '?' . http_build_query($p) : '');
};

/* ---------- Enregistrements ---------- */
try {
    if ($action !== '' && $readonly) {
        $error = 'Aperçu coach : rien n’est enregistré depuis cette page.';
    } elseif ($action === 'suivi') {
        $sid = (string) ($_POST['seance'] ?? '');
        if (!isset($prog['seances'][$sid])) {
            $error = 'Séance introuvable.';
        } else {
            $rpe = (int) ($_POST['rpe'] ?? 0);
            on_q(
                'INSERT INTO suivi (membre_id, seance, fait, rpe, commentaire, maj_le) VALUES (?, ?, ?, ?, ?, ?)
                 ON CONFLICT (membre_id, seance) DO UPDATE SET fait = excluded.fait, rpe = excluded.rpe, commentaire = excluded.commentaire, maj_le = excluded.maj_le',
                [$mid, $sid, empty($_POST['fait']) ? 0 : 1, $rpe >= 1 && $rpe <= 10 ? $rpe : null, on_clean_text($_POST['commentaire'] ?? '', 1000), on_now_str()]
            );
            on_redirect($link(['seance' => $sid, 'ok' => 'suivi']) . '#suivi');
        }
    } elseif ($action === 'test') {
        $tid = (string) ($_POST['test'] ?? '');
        $date = on_valid_date($_POST['date'] ?? '');
        $value = isset($tests[$tid]) ? on_parse_value($tests[$tid], (string) ($_POST['valeur'] ?? '')) : null;
        if (!isset($tests[$tid]) || !$tests[$tid]["saisie"]) $error = "Choisissez un test.";
        elseif ($value === null) $error = on_value_hint($tests[$tid]);
        elseif ($date === '' || $date > on_today()) $error = 'La date du test doit être aujourd’hui ou avant.';
        elseif (on_limited('test-membre', (string) $mid, 20, 86400)) $error = 'Trop de résultats envoyés aujourd’hui. Réessayez demain.';
        else {
            on_q('INSERT INTO tests (membre_id, test, valeur, date_test, statut, saisi_par, saisi_le) VALUES (?, ?, ?, ?, ?, ?, ?)', [$mid, $tid, $value, $date, 'valide', 'membre', on_now_str()]);
            on_redirect($link(['vue' => 'tests', 'ok' => 'test']));
        }
    } elseif ($action === 'test_suppr') {
        on_q('DELETE FROM tests WHERE id = ? AND membre_id = ? AND saisi_par = ?', [(int) ($_POST['id'] ?? 0), $mid, 'membre']);
        on_redirect($link(['vue' => 'tests', 'ok' => 'annule']));
    } elseif ($action === 'debut' && !(int) $m['debut_choisi']) {
        // Première connexion après un achat : le membre choisit le jour 1 de son programme
        $debut = on_valid_date($_POST['date_debut'] ?? '');
        if ($debut === '' || $debut < on_today() || $debut > on_date_add(on_today(), 180)) {
            $error = 'Choisissez une date entre aujourd’hui et les 6 prochains mois.';
        } else {
            on_set_start($mid, $debut);
            on_redirect($link(['ok' => 'debut']));
        }
    }
} catch (Throwable $e) {
    error_log('Online, enregistrement : ' . $e->getMessage());
    $error = 'L’enregistrement n’a pas fonctionné. Réessayez dans un instant.';
}

$notice = match ($_GET['ok'] ?? '') {
    'suivi' => 'Séance enregistrée.',
    'test' => 'Résultat enregistré : vos cibles sont recalculées.',
    'annule' => 'Résultat supprimé.',
    'debut' => 'C’est noté : votre programme démarre le ' . on_fmt_date($m['date_debut'], 'annee') . '.',
    default => '',
};

/* ---------- Première connexion après un achat : date de démarrage ---------- */
if (!$readonly && !(int) $m['debut_choisi']):
    $offre = ON_OFFRES[$m['offre']] ?? ON_OFFRES['programme'];
    $proposed = on_valid_date($_POST['date_debut'] ?? '') ?: on_next_monday();
    on_page_start('Bienvenue', $user);
    ?>
  <section class="on-hero">
    <p class="on-k">Bienvenue <?= h($m['prenom']) ?> · <?= h($offre['nom']) ?></p>
    <h1 class="on-h1">Votre <span class="on-grad">départ</span></h1>
    <p class="on-lead">Quand voulez-vous démarrer vos 8 semaines&nbsp;? Ce jour devient le jour 1 de votre programme&nbsp;: chaque séance a ensuite sa date.</p>
  </section>
  <?= on_notice($error, true) ?>
  <form method="post" action="<?= h($link()) ?>" class="on-card on-form on-start">
    <?= on_csrf_field($user) ?>
    <input type="hidden" name="action" value="debut">
    <label class="on-field">
      <span>Date de démarrage</span>
      <input type="date" name="date_debut" value="<?= h($proposed) ?>" min="<?= h(on_today()) ?>" max="<?= h(on_date_add(on_today(), 180)) ?>" required>
    </label>
    <p class="on-help">Un lundi de préférence&nbsp;: la semaine 1 est la semaine des tests. Votre accès dure 10 semaines à partir de cette date (8 semaines de programme et 2 de marge).</p>
    <button class="on-btn on-btn--grad on-btn--block" type="submit">Valider ma date de démarrage</button>
  </form>
  <?php if ($offre['club']): ?>
  <?= on_render_club_booking() ?>
  <?php endif; ?>
  <p class="on-help on-start__call">Notre équipe vous appelle pour valider avec vous la mise en place de votre programme.</p>
<?php
    on_page_end();
    exit;
endif;

/* ---------- Données ---------- */
$rows = on_member_tests($mid);
$suivi = on_member_suivi($mid);
$today = on_today();
$nbW = count($prog['semaines']);
$wNow = on_week_now($m['date_debut'], $nbW);
$wShown = max(1, min($nbW, $wNow));
$total = count($prog['seances']);
$done = count(array_filter($suivi, fn($r) => (int) $r['fait'] && isset($prog['seances'][$r['seance']])));
$order = array_keys($prog['seances']);
usort($order, fn($a, $b) => [on_seance_date($m['date_debut'], $prog['seances'][$a]), $prog['seances'][$a]['numero']] <=> [on_seance_date($m['date_debut'], $prog['seances'][$b]), $prog['seances'][$b]['numero']]);

$view = 'accueil';
if (isset($_GET['seance']) && isset($prog['seances'][(string) $_GET['seance']])) $view = 'seance';
elseif (isset($_GET['semaine'])) $view = 'semaine';
elseif (($_GET['vue'] ?? '') === 'tests') $view = 'tests';

// Ligne de séance (listes de la semaine)
$seanceRow = function (array $s) use ($m, $suivi, $today, $link): string {
    $date = on_seance_date($m['date_debut'], $s);
    $st = $suivi[$s['id']] ?? null;
    $state = '';
    if ($st && (int) $st['fait']) {
        $state = '<span class="on-row__st is-done"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>Faite' . ($st['rpe'] ? ' · RPE ' . (int) $st['rpe'] : '') . '</span>';
    } elseif ($date === $today) {
        $state = '<span class="on-row__st is-today">Aujourd’hui</span>';
    }
    $d = explode(' ', on_fmt_date($date, 'court'), 2);
    return '<li><a class="on-row' . ($date === $today ? ' is-today' : '') . '" href="' . h($link(['seance' => $s['id']])) . '">'
        . '<span class="on-row__date"><b>' . h($d[0]) . '</b>' . h($d[1] ?? '') . '</span>'
        . '<span class="on-row__main"><span class="on-type">' . h(mb_strtoupper($s['type'])) . '</span><span class="on-row__t">' . h($s['titre']) . '</span></span>'
        . $state . '</a></li>';
};

// Tests utilisés par le programme, sans résultat validé
$nowValues = on_values_at($rows, $today);
$missing = [];
foreach ($prog["seances"] as $s) foreach (on_seance_refs($s) as $ref) {
    if (!isset($nowValues[$ref])) $missing[$ref] = true;
}

on_page_start($view === 'tests' ? 'Mes tests' : ($view === 'seance' ? $prog['seances'][(string) $_GET['seance']]['titre'] : 'Mon programme'), $user);

if ($readonly): ?>
  <p class="on-note on-note--coach">Aperçu coach&nbsp;: l’espace de <b><?= h($m['prenom']) ?></b>, tel qu’il le voit. <a href="coach.php?m=<?= $mid ?>">Retour à sa fiche</a></p>
<?php endif; ?>

  <nav class="on-tabs" aria-label="Mon espace">
    <a href="<?= h($link()) ?>"<?= $view === 'accueil' ? ' aria-current="page"' : '' ?>>Aujourd’hui</a>
    <a href="<?= h($link(['semaine' => $wShown])) ?>"<?= in_array($view, ['semaine', 'seance'], true) ? ' aria-current="page"' : '' ?>>Programme</a>
    <a href="<?= h($link(['vue' => 'tests'])) ?>"<?= $view === 'tests' ? ' aria-current="page"' : '' ?>>Mes tests<?= $missing ? ' <i class="on-dot" aria-label="à compléter"></i>' : '' ?></a>
  </nav>

  <?= on_notice($error, true) ?>
  <?= on_notice($notice) ?>

<?php if (!$prog['ok']): ?>
  <section class="on-hero"><h1 class="on-h1">Mise à <span class="on-grad">jour</span></h1></section>
  <?= on_notice('Votre programme est en cours de mise à jour par votre coach. Revenez dans quelques minutes.', true) ?>
  <?php if ($readonly): ?><p class="on-help">Le fichier du programme contient des erreurs&nbsp;: <a href="coach.php?programme=<?= h($m['programme']) ?>">voir le détail</a>.</p><?php endif; ?>
<?php
    on_page_end();
    exit;
endif;

/* ======================================================================
   Aujourd'hui
   ====================================================================== */
if ($view === 'accueil'):
    $todays = array_filter($prog['seances'], fn($s) => on_seance_date($m['date_debut'], $s) === $today);
    $next = null;
    foreach ($order as $sid) {
        if (on_seance_date($m['date_debut'], $prog['seances'][$sid]) > $today) { $next = $prog['seances'][$sid]; break; }
    }
    $week = $prog['semaines'][$wShown - 1];
    ?>
  <section class="on-hero">
    <p class="on-k">Bonjour <?= h($m['prenom']) ?> · <?= h($prog['nom']) ?></p>
    <?php if ($wNow === 0): $j = on_days_between($today, $m['date_debut']); ?>
    <h1 class="on-h1">J<span class="on-grad">−<?= $j ?></span></h1>
    <p class="on-lead">Votre programme commence <?= $j === 1 ? 'demain' : 'le ' . h(on_fmt_date($m['date_debut'])) ?>. Vous pouvez déjà découvrir la première semaine.</p>
    <?php elseif ($wNow > $nbW): ?>
    <h1 class="on-h1">Programme <span class="on-grad">terminé</span></h1>
    <p class="on-lead">Bravo&nbsp;! Vos séances restent consultables jusqu’au <?= h(on_fmt_date($m['date_fin'], 'annee')) ?>.</p>
    <?php else: ?>
    <h1 class="on-h1">Semaine <span class="on-grad"><?= $wNow ?></span><small>/<?= $nbW ?></small></h1>
    <?php $bl = on_week_block($prog['structure'], $wNow); $lead = array_filter([$bl['titre'] ?? '', $week['theme'] !== '' ? $week['theme'] : ($bl['semaines'] ?? '')]); ?>
    <?php if ($lead): ?><p class="on-lead"><?= h(implode(' · ', $lead)) ?></p><?php endif; ?>
    <?php endif; ?>
    <div class="on-progress" role="img" aria-label="<?= $done ?> séances faites sur <?= $total ?>"><span style="width:<?= $total ? round($done / $total * 100, 1) : 0 ?>%"></span></div>
    <p class="on-progress__t"><b><?= $done ?></b> séance<?= $done > 1 ? 's' : '' ?> faite<?= $done > 1 ? 's' : '' ?> sur <?= $total ?></p>
  </section>

  <?php if ($missing): ?>
  <p class="on-note on-note--warn">Certaines allures attendent vos tests&nbsp;: <?= h(implode(', ', array_map(fn($r) => $tests[$r]['nom'], array_keys($missing)))) ?>. <a href="<?= h($link(['vue' => 'tests'])) ?>">Mes tests</a></p>
  <?php endif; ?>

  <?php if ((ON_OFFRES[$m['offre']]['club'] ?? false) && $wNow <= $nbW) echo on_render_club_booking(); ?>

  <?php if ($wNow >= 1 && $wNow <= $nbW): ?>
  <section class="on-sec">
    <h2 class="on-h2">Séance du jour</h2>
    <?php if ($todays): foreach ($todays as $s):
        $values = on_values_at($rows, $today);
        $st = $suivi[$s['id']] ?? null; ?>
    <article class="on-card on-today">
      <div class="on-today__head">
        <span class="on-type"><?= h(mb_strtoupper($s['type'])) ?></span>
        <?php if ($st && (int) $st['fait']): ?><span class="on-row__st is-done">Faite<?= $st['rpe'] ? ' · RPE ' . (int) $st['rpe'] : '' ?></span><?php endif; ?>
      </div>
      <h3 class="on-today__t"><?= h($s['titre']) ?></h3>
      <?php if ($s['description'] !== ''): ?><p class="on-desc"><?= h($s['description']) ?></p><?php endif; ?>
      <?= on_render_blocs($s, $values) ?>
      <a class="on-btn on-btn--grad on-btn--block" href="<?= h($link(['seance' => $s['id']])) ?>#suivi"><?= $st && (int) $st['fait'] ? 'Modifier mon suivi' : 'J’ai fait ma séance' ?></a>
    </article>
    <?php endforeach; else: ?>
    <div class="on-card on-rest">
      <p class="on-rest__t">Récupération</p>
      <p class="on-help">Pas de séance prévue aujourd’hui.<?= $next ? ' Prochaine séance&nbsp;: ' . h(on_fmt_date(on_seance_date($m['date_debut'], $next))) . '.' : '' ?></p>
    </div>
    <?php if ($next): ?><ul class="on-rows"><?= $seanceRow($next) ?></ul><?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="on-sec">
    <h2 class="on-h2"><?= $wNow >= 1 && $wNow <= $nbW ? 'Cette semaine' : 'Semaine ' . $wShown ?></h2>
    <ul class="on-rows"><?php foreach ($week['seances'] as $s) echo $seanceRow($s); ?></ul>
    <a class="on-more" href="<?= h($link(['semaine' => $wShown])) ?>">Tout le programme<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M14 6l6 6-6 6"/></svg></a>
  </section>
<?php

/* ======================================================================
   Programme : une semaine
   ====================================================================== */
elseif ($view === 'semaine'):
    $w = max(1, min($nbW, (int) $_GET['semaine']));
    $week = $prog['semaines'][$w - 1];
    $from = on_date_add($m['date_debut'], ($w - 1) * 7);
    ?>
  <nav class="on-weeks" aria-label="Semaines du programme">
    <?php for ($i = 1; $i <= $nbW; $i++): ?>
    <a href="<?= h($link(['semaine' => $i])) ?>"<?= $i === $w ? ' aria-current="page"' : '' ?><?= $i === $wNow ? ' class="is-now"' : '' ?>><span class="on-sr">Semaine </span><?= $i ?></a>
    <?php endfor; ?>
  </nav>

  <section class="on-hero on-hero--sm">
    <p class="on-k"><?= $w === $wNow ? 'Semaine en cours' : ($w < $wNow ? 'Semaine passée' : 'Semaine à venir') ?> · du <?= h(on_fmt_date($from, 'court')) ?> au <?= h(on_fmt_date(on_date_add($from, 6), 'court')) ?></p>
    <h1 class="on-h1">Semaine <span class="on-grad"><?= $w ?></span><small>/<?= $nbW ?></small></h1>
    <?php if ($week['theme'] !== ''): ?><p class="on-lead"><?= h($week['theme']) ?></p><?php endif; ?>
  </section>

  <?= on_render_structure($prog['structure'], $w) ?>

  <ul class="on-rows"><?php foreach ($week['seances'] as $s) echo $seanceRow($s); ?></ul>

  <?php if ($prog['infos'] !== ''): ?><p class="on-note on-note--info"><?= h($prog['infos']) ?></p><?php endif; ?>

  <div class="on-pager">
    <?php if ($w > 1): ?><a class="on-btn on-btn--ghost" href="<?= h($link(['semaine' => $w - 1])) ?>">Semaine <?= $w - 1 ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($w < $nbW): ?><a class="on-btn on-btn--ghost" href="<?= h($link(['semaine' => $w + 1])) ?>">Semaine <?= $w + 1 ?></a><?php endif; ?>
  </div>
<?php

/* ======================================================================
   Une séance
   ====================================================================== */
elseif ($view === 'seance'):
    $s = $prog['seances'][(string) $_GET['seance']];
    $date = on_seance_date($m['date_debut'], $s);
    $values = on_values_at($rows, $date);
    $st = $suivi[$s['id']] ?? null;
    $pos = array_search($s['id'], $order, true);
    $prev = $pos > 0 ? $prog['seances'][$order[$pos - 1]] : null;
    $nextS = $pos < count($order) - 1 ? $prog['seances'][$order[$pos + 1]] : null;
    ?>
  <a class="on-back" href="<?= h($link(['semaine' => $s['semaine']])) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>Semaine <?= $s['semaine'] ?></a>

  <section class="on-hero on-hero--sm">
    <p class="on-k"><?= $date === $today ? 'Aujourd’hui' : h(ucfirst(on_fmt_date($date))) ?> · semaine <?= $s['semaine'] ?></p>
    <h1 class="on-h1 on-h1--seance"><?= h($s['titre']) ?></h1>
    <p class="on-hero__type"><span class="on-type"><?= h(mb_strtoupper($s['type'])) ?></span></p>
    <?php if ($s['description'] !== ''): ?><p class="on-lead"><?= h($s['description']) ?></p><?php endif; ?>
  </section>

  <div class="on-card on-seance">
    <?= on_render_blocs($s, $values) ?>
    <?= on_render_sources($s, $values) ?>
  </div>

  <section class="on-sec" id="suivi">
    <h2 class="on-h2">Mon suivi</h2>
    <?php if ($readonly): ?>
    <div class="on-card">
      <?php if ($st && (int) $st['fait']): ?>
      <p><b>Faite</b><?= $st['rpe'] ? ' · RPE ' . (int) $st['rpe'] . '/10' : '' ?> <span class="on-help">(le <?= h(on_fmt_date(substr($st['maj_le'], 0, 10), 'court')) ?>)</span></p>
      <?php if ($st['commentaire'] !== ''): ?><p class="on-quote"><?= nl2br(h($st['commentaire'])) ?></p><?php endif; ?>
      <?php else: ?><p class="on-help">Pas encore indiquée comme faite.</p><?php endif; ?>
    </div>
    <?php else: ?>
    <form method="post" action="<?= h($link(['seance' => $s['id']])) ?>" class="on-card on-form on-suivi">
      <?= on_csrf_field($user) ?>
      <input type="hidden" name="action" value="suivi">
      <input type="hidden" name="seance" value="<?= h($s['id']) ?>">
      <label class="on-check">
        <input type="checkbox" name="fait" value="1"<?= !$st || (int) $st['fait'] ? ' checked' : '' ?>>
        <span>Séance faite</span>
      </label>
      <fieldset class="on-rpe">
        <legend>Ressenti (RPE)</legend>
        <div class="on-rpe__scale">
          <?php for ($i = 1; $i <= 10; $i++): ?>
          <label><input type="radio" name="rpe" value="<?= $i ?>"<?= $st && (int) $st['rpe'] === $i ? ' checked' : '' ?>><span><?= $i ?></span></label>
          <?php endfor; ?>
        </div>
        <p class="on-rpe__hint"><span>1 · très facile</span><span>10 · effort maximal</span></p>
      </fieldset>
      <label class="on-field">
        <span>Commentaire <i>(facultatif)</i></span>
        <textarea name="commentaire" rows="3" maxlength="1000" placeholder="Sensations, douleurs, ce qui a été modifié…"><?= h($st['commentaire'] ?? '') ?></textarea>
      </label>
      <button class="on-btn on-btn--grad on-btn--block" type="submit">Enregistrer</button>
      <?php if ($st): ?><p class="on-help">Dernier enregistrement le <?= h(on_fmt_date(substr($st['maj_le'], 0, 10), 'court')) ?>.</p><?php endif; ?>
    </form>
    <?php endif; ?>
  </section>

  <div class="on-pager">
    <?php if ($prev): ?><a class="on-btn on-btn--ghost" href="<?= h($link(['seance' => $prev['id']])) ?>" aria-label="Séance précédente"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>Précédente</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($nextS): ?><a class="on-btn on-btn--ghost" href="<?= h($link(['seance' => $nextS['id']])) ?>" aria-label="Séance suivante">Suivante<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M14 6l6 6-6 6"/></svg></a><?php endif; ?>
  </div>
<?php

/* ======================================================================
   Mes tests
   ====================================================================== */
else:
    $now = on_values_at($rows, $today);
    $byTest = [];
    foreach ($rows as $r) $byTest[$r['test']][] = $r;
    ?>
  <section class="on-hero on-hero--sm">
    <p class="on-k">Vos références</p>
    <h1 class="on-h1">Mes <span class="on-grad">tests</span></h1>
    <p class="on-lead">Vos allures et vos charges sont calculées à partir de ces résultats. Un nouveau test remplace l’ancien pour les séances ultérieures.</p>
  </section>

  <div class="on-tests">
    <?php foreach (on_prog_tests($prog) as $tid => $t):
        $cur = $now[$tid] ?? null;
        $hist = array_reverse($byTest[$tid] ?? []); ?>
    <article class="on-card on-test<?= $t['mesure'] === 'vc' ? ' on-test--vc' : '' ?>">
      <header class="on-test__head"><?= on_tag($t['etiquette'], $t['appareil']) ?><h2><?= h($t['nom']) ?></h2></header>
      <?php if ($t['note'] !== ''): ?><p class="on-test__note"><?= h($t['note']) ?></p><?php endif; ?>
      <?= on_render_test_now($t, $cur, $byTest[$tid] ?? [], isset($missing[$tid]) ? 'Pas encore de résultat : les cibles liées à ce test s’afficheront ensuite.' : 'Pas encore de résultat.') ?>
      <?php if ($hist): ?>
      <details class="on-hist">
        <summary>Historique (<?= count($hist) ?>)</summary>
        <ul>
          <?php foreach ($hist as $r): ?>
          <li>
            <span><?= h(on_fmt_date($r['date_test'], 'annee')) ?></span><b><?= h(on_fmt_value($t, (float) $r['valeur'])) ?></b>
            <?php if (!$readonly && $r['saisi_par'] === 'membre'): ?>
            <form method="post" action="<?= h($link(['vue' => 'tests'])) ?>" data-confirm="Supprimer ce résultat (<?= h(on_fmt_value($t, (float) $r['valeur'])) ?>) ?">
              <?= on_csrf_field($user) ?><input type="hidden" name="action" value="test_suppr"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="on-link" type="submit">Supprimer</button>
            </form>
            <?php else: ?><i><?= $r['saisi_par'] === 'membre' ? 'saisi par vous' : 'saisi par ' . h($r['saisi_par']) ?></i><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if (!$readonly): ?>
  <section class="on-sec">
    <h2 class="on-h2">Ajouter un résultat</h2>
    <form method="post" action="<?= h($link(['vue' => 'tests'])) ?>" class="on-card on-form">
      <?= on_csrf_field($user) ?>
      <input type="hidden" name="action" value="test">
      <label class="on-field">
        <span>Test</span>
        <select name="test" required>
          <?php foreach (on_prog_tests($prog) as $tid => $t): if (!$t['saisie']) continue; ?><option value="<?= h($tid) ?>"<?= ($_POST['test'] ?? '') === $tid ? ' selected' : '' ?>><?= h($t['nom']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="on-form__row">
        <label class="on-field">
          <span>Résultat</span>
          <input type="text" name="valeur" value="<?= h($_POST['valeur'] ?? '') ?>" placeholder="4:24 ou 110" inputmode="text" maxlength="12" required>
        </label>
        <label class="on-field">
          <span>Date du test</span>
          <input type="date" name="date" value="<?= h(on_valid_date($_POST['date'] ?? '') ?: $today) ?>" max="<?= $today ?>" required>
        </label>
      </div>
      <p class="on-help">Temps en min:s (4:24) ou h:min:s (1:02:15) ; 1RM en kg (110 ou 82,5). Le résultat sert tout de suite au calcul de vos cibles.</p>
      <button class="on-btn on-btn--grad on-btn--block" type="submit">Enregistrer le résultat</button>
    </form>
  </section>
  <?php endif; ?>
<?php
endif;

on_page_end();
