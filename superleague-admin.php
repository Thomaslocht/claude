<?php
/* ==========================================================================
   ZERO26. Coaching Club — SuperLeague : page organisateur
   Liste des inscrits, téléchargement du CSV pour l'app du jour J,
   suppression d'une inscription. Accès protégé par mot de passe.
   ========================================================================== */
declare(strict_types=1);

require __DIR__ . '/includes/superleague.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

session_name('z26sl');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$self = strtok($_SERVER['REQUEST_URI'] ?? 'superleague-admin.php', '?');
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$error = '';
$notice = '';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrfOk = fn() => hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));
$logged = !empty($_SESSION['sl_admin']);

try {
    $action = $_POST['action'] ?? '';

    if ($action === 'login' && !$logged) {
        if (sl_rate_limited('connexion', 8, 900)) {
            $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), SL_ADMIN_HASH)) {
            session_regenerate_id(true);
            $_SESSION['sl_admin'] = true;
            header('Location: ' . $self, true, 303);
            exit;
        } else {
            $error = 'Mot de passe incorrect.';
        }
    }

    if ($logged && $action === 'logout' && $csrfOk()) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $self, true, 303);
        exit;
    }

    if ($logged && $action === 'delete' && $csrfOk()) {
        $email = strtolower((string) ($_POST['email'] ?? ''));
        $removed = sl_locked(function () use ($email) {
            $rows = sl_read_all();
            $kept = array_values(array_filter($rows, fn($r) => strtolower($r['email'] ?? '') !== $email));
            if (count($kept) === count($rows)) return false;
            sl_save_all($kept);
            return true;
        });
        $_SESSION['notice'] = $removed ? 'Inscription supprimée.' : 'Inscription introuvable.';
        header('Location: ' . $self, true, 303);
        exit;
    }

    if ($logged && ($_GET['export'] ?? '') === 'csv') {
        $path = sl_csv_path();
        $csv = is_file($path) ? (string) file_get_contents($path) : "\xEF\xBB\xBF" . implode(',', SL_COLUMNS) . "\n";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="superleague-inscriptions-' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    $rows = $logged ? sl_read_all() : [];
} catch (Throwable $e) {
    error_log('SuperLeague admin : ' . $e->getMessage());
    $rows = [];
    $error = 'Les inscriptions sont inaccessibles pour le moment (' . $e->getMessage() . ').';
}

if (!empty($_SESSION['notice'])) {
    $notice = $_SESSION['notice'];
    unset($_SESSION['notice']);
}

$cfg = sl_config();
$nbF = count(array_filter($rows, fn($r) => ($r['sexe'] ?? '') === 'Femme'));
$nbH = count($rows) - $nbF;
$status = $logged ? sl_status(count($rows)) : null;
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>SuperLeague — Inscriptions (organisateur) | ZERO26.</title>
  <link rel="icon" href="favicon.ico" sizes="48x48">
  <style>
    @font-face { font-family: 'Inter'; font-weight: 300 700; font-display: swap; src: url('assets/fonts/inter-latin.woff2') format('woff2'); }
    @font-face { font-family: 'Anton'; font-weight: 100 900; font-display: swap; src: url('assets/fonts/anton-latin.woff2') format('woff2'); }
    :root { --bg: #08070d; --card: #12111a; --line: #2e2b3e; --text: #e4e3ee; --muted: #9693ab; --white: #fff; --accent: #b39dff; --error: #f0918a; --grad: linear-gradient(100deg, #7b3ff2 0%, #5653f1 50%, #2f6bf0 100%); }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    .wrap { max-width: 1040px; margin: 0 auto; padding: 40px 16px 64px; }
    .top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 32px; }
    .top img { height: 30px; width: auto; display: block; }
    h1 { margin: 0 0 8px; font: 400 clamp(48px, 9vw, 96px)/.9 'Anton', 'Impact', sans-serif; text-transform: uppercase; color: var(--white); letter-spacing: .005em; }
    h1 span { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .sub { margin: 0 0 32px; color: var(--muted); }
    .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 16px; }
    .stat { padding: 18px 20px; border-radius: 14px; background: var(--card); }
    .stat b { display: block; font: 400 48px/1 'Anton', 'Impact', sans-serif; color: var(--white); }
    .stat span { font-size: 13px; color: var(--muted); }
    .meta { margin: 0 0 24px; font-size: 14px; color: var(--muted); }
    .meta strong { color: var(--text); font-weight: 600; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
    .btn { display: inline-flex; align-items: center; height: 44px; padding: 0 22px; border: 0; border-radius: 9999px; font: 500 14px 'Inter', sans-serif; cursor: pointer; text-decoration: none; }
    .btn--grad { background: var(--grad); color: var(--white); }
    .btn--ghost { background: transparent; color: var(--white); box-shadow: inset 0 0 0 1px var(--line); }
    .btn--ghost:hover { box-shadow: inset 0 0 0 1px var(--white); }
    .note { margin: 0 0 24px; padding: 14px 18px; border-radius: 14px; background: var(--card); font-size: 14px; }
    .note.err { color: var(--error); }
    .table { overflow-x: auto; border-radius: 14px; background: var(--card); }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 12px 16px; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--line); }
    th { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
    tr:last-child td { border-bottom: 0; }
    td.num { color: var(--muted); font-variant-numeric: tabular-nums; }
    .tag { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #2a2540; color: var(--accent); }
    .tag--h { background: #1e2a45; color: #9dbaff; }
    .del { padding: 6px 12px; border: 0; border-radius: 9999px; background: transparent; color: var(--muted); font: 13px 'Inter', sans-serif; cursor: pointer; box-shadow: inset 0 0 0 1px var(--line); }
    .del:hover { color: var(--error); box-shadow: inset 0 0 0 1px var(--error); }
    .empty { padding: 40px 20px; text-align: center; color: var(--muted); }
    .login { max-width: 380px; padding: 28px; border-radius: 14px; background: var(--card); }
    .login label { display: block; margin-bottom: 8px; font-size: 13px; color: var(--muted); }
    .login input { width: 100%; height: 48px; margin-bottom: 16px; padding: 0 18px; border: 0; border-radius: 9999px; background: var(--bg); color: var(--white); font: 15px 'Inter', sans-serif; box-shadow: inset 0 0 0 1px var(--line); }
    .login input:focus { outline: none; box-shadow: inset 0 0 0 1px var(--white); }
    :focus-visible { outline: 2px solid var(--white); outline-offset: 3px; }
    .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
    @media (max-width: 560px) { .stats { grid-template-columns: 1fr 1fr; } .stat:first-child { grid-column: 1 / -1; } }
  </style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <a href="superleague.html"><img src="assets/img/logo-zero26.webp" alt="ZERO26." width="480" height="179"></a>
    <?php if ($logged): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <button class="btn btn--ghost" name="action" value="logout">Se déconnecter</button>
    </form>
    <?php endif; ?>
  </div>

  <h1>Super<span>League</span></h1>
  <p class="sub">Espace organisateur · inscriptions à la SuperLeague Concept2 Triathlon</p>

  <?php if ($error): ?><p class="note err" role="alert"><?= $h($error) ?></p><?php endif; ?>
  <?php if ($notice): ?><p class="note" role="status"><?= $h($notice) ?></p><?php endif; ?>

  <?php if (!$logged): ?>
  <form class="login" method="post">
    <input type="hidden" name="action" value="login">
    <label for="pw">Mot de passe organisateur</label>
    <input id="pw" type="password" name="password" autocomplete="current-password" required autofocus>
    <button class="btn btn--grad" type="submit">Se connecter</button>
  </form>
  <?php else: ?>
  <div class="stats">
    <div class="stat"><b><?= count($rows) ?></b><span>inscrit<?= count($rows) > 1 ? 's' : '' ?><?= (int) ($cfg['placesMax'] ?? 0) > 0 ? ' sur ' . (int) $cfg['placesMax'] . ' places' : '' ?></span></div>
    <div class="stat"><b><?= $nbH ?></b><span>homme<?= $nbH > 1 ? 's' : '' ?></span></div>
    <div class="stat"><b><?= $nbF ?></b><span>femme<?= $nbF > 1 ? 's' : '' ?></span></div>
  </div>
  <p class="meta">
    Inscriptions <strong><?= $status['open'] ? 'ouvertes' : ($status['reason'] === 'full' ? 'complètes' : 'clôturées') ?></strong>
    · Épreuve : <strong><?= $h(($cfg['dateLabel'] ?? '') ?: 'date à venir') ?></strong>
    · Clôture : <strong><?= $h(($cfg['clotureLabel'] ?? '') ?: 'à venir') ?></strong>
    <?php if (sl_data_is_public_dir()): ?><br>Stockage de secours dans le dossier du site (fermé aux visiteurs).<?php endif; ?>
  </p>
  <div class="actions">
    <a class="btn btn--grad" href="?export=csv">Télécharger le CSV (pour l’app du jour J)</a>
  </div>

  <div class="table">
    <?php if (!$rows): ?>
    <p class="empty">Aucune inscription pour l’instant.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Prénom</th><th>Nom</th><th>Sexe</th><th>E-mail</th><th>Téléphone</th><th>Inscrit le</th><th><span class="sr">Action</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td class="num"><?= $i + 1 ?></td>
          <td><?= $h($r['prenom'] ?? '') ?></td>
          <td><?= $h($r['nom'] ?? '') ?></td>
          <td><span class="tag<?= ($r['sexe'] ?? '') === 'Homme' ? ' tag--h' : '' ?>"><?= $h($r['sexe'] ?? '') ?></span></td>
          <td><a href="mailto:<?= $h($r['email'] ?? '') ?>" style="color:inherit"><?= $h($r['email'] ?? '') ?></a></td>
          <td><a href="tel:<?= $h(str_replace(' ', '', $r['tel'] ?? '')) ?>" style="color:inherit"><?= $h($r['tel'] ?? '') ?></a></td>
          <td class="num"><?= $h($r['inscrit_le'] ?? '') ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Supprimer l’inscription de <?= $h(addslashes(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''))) ?> ?')">
              <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
              <input type="hidden" name="email" value="<?= $h($r['email'] ?? '') ?>">
              <button class="del" name="action" value="delete">Supprimer</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
