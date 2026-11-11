<?php
/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online : fonctions communes
   Réglages, base de données, connexion par code e-mail, mise en page.
   Utilisé par online/index.php (membres) et online/coach.php (coachs).
   Le programme et le calcul des allures sont dans engine.php.
   ========================================================================== */
declare(strict_types=1);

ini_set('display_errors', '0'); // une alerte PHP ne doit jamais s'afficher aux membres

require_once __DIR__ . '/../superleague.php'; // dossier de stockage (zero26-data), nettoyage des champs
require_once __DIR__ . '/engine.php';

/* ---------- Réglages ---------- */

// Coachs : ils se connectent comme les membres (code par e-mail) et arrivent dans l'espace coach.
// Adresses en minuscules.
const ON_COACHES = [
    'info.thomaslocht@gmail.com' => 'Thomas',
    'alexcoachingplus@gmail.com' => 'Alexis',
];
// Programmes proposés : identifiant => nom affiché. « hyrox » = programmes/hyrox.json ;
// « hyrox-run » = la variante « run » décrite dans hyrox.json (séances RUN seulement).
const ON_PROGRAMMES = ['hyrox' => 'HYROX', 'hyrox-run' => 'HYROX · RUN seul'];

// Offres vendues sur bsport. « produit » = nom exact du produit dans la boutique bsport : le webhook
// le reconnaît dans la facture et crée l'espace du client. « club » : séance test et simulation au club.
const ON_OFFRES = [
    'pack' => ['nom' => 'Pack test + programme + simulation', 'court' => 'Pack', 'programme' => 'hyrox', 'club' => true, 'produit' => '[PACK] PREPA HYROX - TEST + PROGRAMME 8 SEMAINES + SIMULATION'],
    'run' => ['nom' => 'Programme RUN 8 semaines', 'court' => 'RUN', 'programme' => 'hyrox-run', 'club' => false, 'produit' => 'PREPA HYROX - PROGRAMME 8 SEMAINES RUN'],
    'programme' => ['nom' => 'Programme 8 semaines', 'court' => 'Programme', 'programme' => 'hyrox', 'club' => false, 'produit' => 'PREPA HYROX - PROGRAMME 8 SEMAINES'],
];
// Réservation des créneaux au club (pack) : page bsport du club
const ON_RESA_TEST = 'https://backoffice.bsport.io/c/1748/';
const ON_RESA_SIMULATION = 'https://backoffice.bsport.io/c/1748/';

const ON_SITE = 'https://zero26coachingclub.com';
const ON_MAIL_FROM = 'site@zero26coachingclub.com';
const ON_MAIL_REPLY = 'team@zero26coachingclub.com'; // réponses, retours d'erreur d'envoi et alertes « nouvel achat »
const ON_CODE_TTL = 600;        // validité d'un code : 10 minutes
const ON_CODE_TRIES = 5;        // essais par code
const ON_SESSION_DAYS = 60;     // durée de connexion sur un appareil (prolongée à chaque visite)
const ON_DEFAULT_WEEKS = 10;    // accès par défaut : 8 semaines de programme + 2 de marge
const ON_RETENTION_DAYS = 365;  // données d'un membre supprimées un an après sa date de fin
const ON_COOKIE = 'z26on';
const ON_ASSETS_V = '20261117'; // à changer après une modification de online.css ou online.js

/* ---------- Base de données (un fichier SQLite dans zero26-data, hors du site) ---------- */

function on_db(): PDO
{
    static $db = null;
    if ($db) return $db;
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('SQLite n’est pas activé sur le serveur (extension pdo_sqlite).');
    }
    $path = sl_data_dir() . '/online.sqlite';
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA foreign_keys = ON');
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() < 1) {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS membres (
              id INTEGER PRIMARY KEY,
              email TEXT NOT NULL UNIQUE,
              prenom TEXT NOT NULL,
              programme TEXT NOT NULL,
              date_debut TEXT NOT NULL,
              date_fin TEXT NOT NULL,
              actif INTEGER NOT NULL DEFAULT 1,
              cree_le TEXT NOT NULL,
              cree_par TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS tests (
              id INTEGER PRIMARY KEY,
              membre_id INTEGER NOT NULL REFERENCES membres(id) ON DELETE CASCADE,
              test TEXT NOT NULL,
              valeur REAL NOT NULL,
              date_test TEXT NOT NULL,
              statut TEXT NOT NULL,
              saisi_par TEXT NOT NULL,
              saisi_le TEXT NOT NULL,
              valide_par TEXT,
              valide_le TEXT
            );
            CREATE INDEX IF NOT EXISTS tests_membre ON tests (membre_id, test, date_test);
            CREATE TABLE IF NOT EXISTS suivi (
              membre_id INTEGER NOT NULL REFERENCES membres(id) ON DELETE CASCADE,
              seance TEXT NOT NULL,
              fait INTEGER NOT NULL DEFAULT 0,
              rpe INTEGER,
              commentaire TEXT NOT NULL DEFAULT '',
              maj_le TEXT NOT NULL,
              PRIMARY KEY (membre_id, seance)
            );
            CREATE TABLE IF NOT EXISTS codes (
              id INTEGER PRIMARY KEY,
              email TEXT NOT NULL,
              hash TEXT NOT NULL,
              expire INTEGER NOT NULL,
              essais INTEGER NOT NULL DEFAULT 0,
              utilise INTEGER NOT NULL DEFAULT 0,
              cree INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS codes_email ON codes (email);
            CREATE TABLE IF NOT EXISTS sessions (
              hash TEXT PRIMARY KEY,
              email TEXT NOT NULL,
              csrf TEXT NOT NULL,
              cree INTEGER NOT NULL,
              expire INTEGER NOT NULL,
              vu INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS limites (
              cle TEXT NOT NULL,
              t INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS limites_cle ON limites (cle, t);
            PRAGMA user_version = 1;
            SQL);
    }
    // v3 : les résultats saisis par l'athlète sont valables tout de suite (plus de validation par le coach)
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() < 3) {
        $db->exec("UPDATE tests SET statut = 'valide' WHERE statut <> 'valide'");
        $db->exec('PRAGMA user_version = 3');
    }
    // v4 : offre achetée, téléphone, date de début choisie par le membre, achats reçus de bsport
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() < 4) {
        $db->exec(<<<'SQL'
            ALTER TABLE membres ADD COLUMN offre TEXT NOT NULL DEFAULT 'programme';
            ALTER TABLE membres ADD COLUMN tel TEXT NOT NULL DEFAULT '';
            ALTER TABLE membres ADD COLUMN debut_choisi INTEGER NOT NULL DEFAULT 1;
            CREATE TABLE IF NOT EXISTS achats (
              facture TEXT PRIMARY KEY,
              membre_id INTEGER REFERENCES membres(id) ON DELETE SET NULL,
              offre TEXT NOT NULL,
              produit TEXT NOT NULL,
              montant INTEGER NOT NULL DEFAULT 0,
              recu_le TEXT NOT NULL
            );
            PRAGMA user_version = 4;
            SQL);
    }
    @chmod($path, 0600);
    on_daily_backup($db);
    return $db;
}

// Une copie de la base par jour (dossier sauvegardes), les 30 dernières sont gardées
function on_daily_backup(PDO $db): void
{
    try {
        $dir = sl_data_dir() . '/sauvegardes';
        $file = "$dir/online-" . date('Y-m-d') . '.sqlite';
        if (is_file($file)) return;
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $db->exec('VACUUM INTO ' . $db->quote($file));
        $old = glob("$dir/online-*.sqlite") ?: [];
        sort($old);
        foreach (array_slice($old, 0, max(0, count($old) - 30)) as $f) @unlink($f);
    } catch (Throwable $e) {
        error_log('Online, sauvegarde : ' . $e->getMessage());
    }
}

function on_q(string $sql, array $args = []): PDOStatement
{
    $st = on_db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function on_row(string $sql, array $args = []): ?array
{
    $r = on_q($sql, $args)->fetch();
    return $r === false ? null : $r;
}

function on_all(string $sql, array $args = []): array
{
    return on_q($sql, $args)->fetchAll();
}

function on_now_str(): string
{
    return date('Y-m-d H:i');
}

/* ---------- Membres ---------- */

function on_member(int $id): ?array
{
    return on_row('SELECT * FROM membres WHERE id = ?', [$id]);
}

// Tous les résultats de tests d'un membre, du plus ancien au plus récent
function on_member_tests(int $id): array
{
    return on_all('SELECT * FROM tests WHERE membre_id = ? ORDER BY date_test, id', [$id]);
}

function on_member_suivi(int $id): array
{
    $out = [];
    foreach (on_all('SELECT * FROM suivi WHERE membre_id = ?', [$id]) as $r) $out[$r['seance']] = $r;
    return $out;
}

// Suppression définitive des membres dont l'accès a pris fin depuis plus d'un an
function on_purge_old(): int
{
    $limit = on_date_add(on_today(), -ON_RETENTION_DAYS);
    $old = on_all('SELECT id, email FROM membres WHERE date_fin < ?', [$limit]);
    foreach ($old as $m) on_delete_member((int) $m['id']);
    return count($old);
}

function on_delete_member(int $id): void
{
    $m = on_member($id);
    if (!$m) return;
    on_q('DELETE FROM sessions WHERE email = ?', [$m['email']]);
    on_q('DELETE FROM codes WHERE email = ?', [$m['email']]);
    on_q('DELETE FROM membres WHERE id = ?', [$id]); // tests et suivi suivent (ON DELETE CASCADE)
}

/* ---------- Champs ---------- */

function on_clean_text($v, int $max): string
{
    $v = is_string($v) ? str_replace(["\r\n", "\r"], "\n", $v) : '';
    $v = preg_replace('/[^\P{C}\n]/u', '', $v) ?? '';
    return trim(mb_substr($v, 0, $max));
}

/* ---------- Limitation des tentatives ---------- */

// Vrai si la limite est atteinte ; sinon, compte cette tentative.
function on_limited(string $bucket, string $who, int $max, int $window): bool
{
    $key = $bucket . ':' . hash('sha256', $who . '|zero26-online');
    $now = time();
    if (random_int(1, 40) === 1) on_q('DELETE FROM limites WHERE t < ?', [$now - 86400]);
    $n = (int) on_q('SELECT COUNT(*) FROM limites WHERE cle = ? AND t > ?', [$key, $now - $window])->fetchColumn();
    if ($n >= $max) return true;
    on_q('INSERT INTO limites (cle, t) VALUES (?, ?)', [$key, $now]);
    return false;
}

function on_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* ---------- Codes de connexion ---------- */

// Essais sur un ordinateur (serveur intégré de PHP) : aucun e-mail, le code s'affiche à l'écran
function on_is_local(): bool
{
    return PHP_SAPI === 'cli-server';
}

function on_new_code(string $email): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = time();
    on_q('UPDATE codes SET utilise = 1 WHERE email = ? AND utilise = 0', [$email]); // un seul code valable à la fois
    on_q('INSERT INTO codes (email, hash, expire, cree) VALUES (?, ?, ?, ?)', [$email, password_hash($code, PASSWORD_DEFAULT), $now + ON_CODE_TTL, $now]);
    on_q('DELETE FROM codes WHERE cree < ?', [$now - 86400]);
    return $code;
}

// 'ok' | 'faux' | 'expire' | 'bloque'
function on_check_code(string $email, string $code): string
{
    $row = on_row('SELECT * FROM codes WHERE email = ? AND utilise = 0 ORDER BY id DESC LIMIT 1', [$email]);
    if (!$row || (int) $row['expire'] < time()) return 'expire';
    if ((int) $row['essais'] >= ON_CODE_TRIES) return 'bloque';
    if (password_verify($code, $row['hash'])) {
        on_q('UPDATE codes SET utilise = 1 WHERE id = ?', [$row['id']]);
        return 'ok';
    }
    on_q('UPDATE codes SET essais = essais + 1 WHERE id = ?', [$row['id']]);
    return (int) $row['essais'] + 1 >= ON_CODE_TRIES ? 'bloque' : 'faux';
}

function on_mail_code(string $email, string $code, string $prenom): bool
{
    if (on_is_local()) @file_put_contents(sl_data_dir() . '/codes-essais.log', date('Y-m-d H:i:s') . " $email $code\n", FILE_APPEND);
    return on_send_mail($email, "$code est votre code ZERO26. Online", "Bonjour $prenom,\n\n"
        . "Voici votre code de connexion à l’espace HYROX Online :\n\n"
        . "    $code\n\n"
        . "Il est valable 10 minutes. Si vous n’avez rien demandé, ignorez simplement cet e-mail.");
}

// E-mail texte depuis ON_MAIL_FROM (serveur du site). L'expéditeur technique (retours d'erreur) est
// ON_MAIL_REPLY : le SPF du domaine doit autoriser Hostinger (include:_spf.mail.hostinger.com).
// En essai sur ordinateur, rien ne part : l'e-mail est écrit dans zero26-data/emails-essais.log.
function on_send_mail(string $to, string $subject, string $body, array $headers = []): bool
{
    $body .= "\n\n--\nZERO26. Coaching Club · 11 rue d’Isle, 02100 Saint-Quentin\n" . ON_SITE . "/online/\n";
    if (on_is_local()) {
        @file_put_contents(sl_data_dir() . '/emails-essais.log', "==== " . date('Y-m-d H:i:s') . "\nÀ : $to\nObjet : $subject\n" . implode('', array_map(fn($k, $v) => "$k : $v\n", array_keys($headers), $headers)) . "\n$body\n\n", FILE_APPEND);
        return true;
    }
    $args = [
        $to,
        mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 9),
        quoted_printable_encode(str_replace("\n", "\r\n", $body)),
        $headers + [
            'From' => 'ZERO26. Coaching Club <' . ON_MAIL_FROM . '>',
            'Reply-To' => ON_MAIL_REPLY,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
            'Auto-Submitted' => 'auto-generated',
        ],
    ];
    try {
        // Expéditeur technique du domaine ; si le serveur le refuse, envoi sans
        return mail(...array_merge($args, ['-f' . ON_MAIL_REPLY])) || mail(...$args);
    } catch (Throwable $e) {
        error_log('Online, envoi d’e-mail : ' . $e->getMessage());
        return false;
    }
}

/* ---------- Connexion ---------- */

// Qui peut se connecter : un coach, ou un membre actif dont l'accès n'est pas terminé
function on_account(string $email): ?array
{
    if (isset(ON_COACHES[$email])) return ['role' => 'coach', 'email' => $email, 'prenom' => ON_COACHES[$email], 'membre' => null];
    $m = on_row('SELECT * FROM membres WHERE email = ?', [$email]);
    if (!$m || !(int) $m['actif'] || $m['date_fin'] < on_today()) return null;
    return ['role' => 'membre', 'email' => $email, 'prenom' => $m['prenom'], 'membre' => $m];
}

function on_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function on_set_cookie(string $value, int $expires): void
{
    setcookie(ON_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/online/',
        'secure' => on_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function on_login(string $email): void
{
    $token = bin2hex(random_bytes(32));
    $now = time();
    $exp = $now + ON_SESSION_DAYS * 86400;
    on_q('DELETE FROM sessions WHERE expire < ?', [$now]);
    on_q('INSERT INTO sessions (hash, email, csrf, cree, expire, vu) VALUES (?, ?, ?, ?, ?, ?)', [hash('sha256', $token), $email, bin2hex(random_bytes(16)), $now, $exp, $now]);
    on_set_cookie($token, $exp);
}

function on_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    $token = (string) ($_COOKIE[ON_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $s = on_row('SELECT * FROM sessions WHERE hash = ? AND expire > ?', [$hash, time()]);
    if (!$s) return null;
    $who = on_account($s['email']);
    if (!$who) {
        on_q('DELETE FROM sessions WHERE hash = ?', [$hash]);
        return null;
    }
    // Connexion prolongée à chaque visite (au plus une fois par jour)
    if ((int) $s['vu'] < time() - 86400) {
        $exp = time() + ON_SESSION_DAYS * 86400;
        on_q('UPDATE sessions SET vu = ?, expire = ? WHERE hash = ?', [time(), $exp, $hash]);
        on_set_cookie($token, $exp);
    }
    return $user = $who + ['csrf' => $s['csrf'], 'session' => $hash];
}

function on_logout(?array $user): void
{
    if ($user) on_q('DELETE FROM sessions WHERE hash = ?', [$user['session']]);
    on_set_cookie('', time() - 3600);
}

// Formulaire envoyé depuis le site lui-même, avec le jeton de la session si l'on est connecté
function on_post_ok(?array $user): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    // « null » : origine masquée par le navigateur ; le jeton de session protège alors les formulaires
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') ?? '');
    if ($origin !== '' && $origin !== 'null' && strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host) return false;
    if ($user && !hash_equals($user['csrf'], (string) ($_POST['csrf'] ?? ''))) return false;
    return true;
}

/* ---------- Achats bsport : l'espace se crée au paiement ---------- */

// Prochain lundi (aujourd'hui si l'on est lundi)
function on_next_monday(): string
{
    $t = strtotime(on_today());
    return date('N', $t) === '1' ? on_today() : date('Y-m-d', strtotime('next monday', $t));
}

// Fin d'accès proposée : 8 semaines de programme + 2 semaines de marge
function on_default_end(string $debut): string
{
    return on_date_add($debut, ON_DEFAULT_WEEKS * 7 - 1);
}

// Nom de produit comparable : majuscules, sans accents, tirets et espaces uniformisés
function on_norm_product(string $s): string
{
    $s = strtr($s, ['–' => '-', '—' => '-', '‑' => '-', "\u{00A0}" => ' ']);
    $s = (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
    return trim((string) preg_replace('/\s+/', ' ', strtoupper($s)));
}

// Offre correspondant à une ligne de facture (« description » = nom du produit bsport), ou null
function on_offre_from_product(string $description): ?string
{
    $d = on_norm_product($description);
    foreach (ON_OFFRES as $k => $o) if (str_contains($d, on_norm_product($o['produit']))) return $k; // pack, puis RUN, puis programme
    return null;
}

// Clé secrète du webhook bsport : créée sur le serveur au premier besoin, gardée dans zero26-data
// (jamais dans le dépôt). Le lien complet s'affiche dans l'espace coach.
function on_webhook_key(): string
{
    $file = sl_data_dir() . '/bsport-webhook.key';
    $key = is_file($file) ? trim((string) file_get_contents($file)) : '';
    if (!preg_match('/^[a-f0-9]{48}$/', $key)) {
        $key = bin2hex(random_bytes(24));
        file_put_contents($file, $key, LOCK_EX);
        @chmod($file, 0600);
    }
    return $key;
}

function on_webhook_url(): string
{
    return ON_SITE . '/online/bsport.php?cle=' . on_webhook_key();
}

// Dernier appel reçu de bsport (sans donnée personnelle), affiché dans l'espace coach
function on_webhook_log(string $result): void
{
    @file_put_contents(sl_data_dir() . '/bsport-dernier-appel.txt', on_now_str() . ' · ' . $result, LOCK_EX);
}

function on_webhook_last(): string
{
    $f = sl_data_dir() . '/bsport-dernier-appel.txt';
    return is_file($f) ? trim((string) file_get_contents($f)) : '';
}

// Facture bsport payée ($inv = data.object du webhook « invoice-pay ») : crée ou réactive l'espace
// du client si la facture contient un produit HYROX Online. Renvoie le résultat en quelques mots.
function on_bsport_invoice(array $inv): string
{
    $facture = is_string($inv['id'] ?? null) ? substr($inv['id'], 0, 80) : '';
    if ($facture === '') return 'ignoré : facture sans identifiant';
    $paid = ($inv['status'] ?? '') === 'paid' || (isset($inv['amount_due']) && (int) $inv['amount_due'] === 0);
    if (!$paid) return 'ignoré : facture non payée';

    // Offre achetée (la plus complète si la facture en contient plusieurs)
    $offre = null;
    $produit = '';
    $montant = 0;
    $prio = ['pack' => 0, 'programme' => 1, 'run' => 2]; // le pack d'abord, puis le programme complet
    foreach ((array) ($inv['line_items']['data'] ?? []) as $line) {
        if (!is_array($line) || in_array($line['item_type'] ?? '', ['refund', 'consumer_giftcard'], true)) continue;
        $k = on_offre_from_product((string) ($line['description'] ?? ''));
        if ($k === null || !isset($prio[$k])) continue;
        if ($offre === null || $prio[$k] < $prio[$offre]) {
            $offre = $k;
            $produit = mb_substr((string) $line['description'], 0, 160);
            $montant = (int) ($line['line_total'] ?? $line['tax_included_amount'] ?? 0);
        }
    }
    if ($offre === null) return 'ignoré : pas un produit HYROX Online';

    $c = is_array($inv['customer'] ?? null) ? $inv['customer'] : [];
    $email = sl_clean_email($c['email'] ?? '');
    if ($email === '') return 'à vérifier : achat HYROX Online sans e-mail client';
    if (on_row('SELECT facture FROM achats WHERE facture = ?', [$facture])) return 'déjà traité';

    $prenom = sl_clean_name($c['first_name'] ?? '') ?: sl_clean_name(explode(' ', (string) ($c['name'] ?? ''))[0]) ?: 'Bonjour';
    $tel = mb_substr(preg_replace('/[^0-9+ .()-]/', '', (string) ($c['phone'] ?? '')) ?? '', 0, 30);
    $prog = ON_OFFRES[$offre]['programme'];
    $debut = on_next_monday();
    $m = on_row('SELECT * FROM membres WHERE email = ?', [$email]);
    $coach = isset(ON_COACHES[$email]);

    if ($coach) {
        $result = 'à vérifier : achat avec l’adresse d’un coach';
    } elseif (!$m) {
        on_q('INSERT INTO membres (email, prenom, programme, date_debut, date_fin, actif, cree_le, cree_par, offre, tel, debut_choisi) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 0)',
            [$email, $prenom, $prog, $debut, on_default_end($debut), on_now_str(), 'bsport', $offre, $tel]);
        $m = on_member((int) on_db()->lastInsertId());
        $result = 'espace créé';
    } else {
        // Déjà membre : accès réactivé ; nouveau cycle si l'ancien est terminé. On ne rétrograde pas un pack.
        $ended = $m['date_fin'] < on_today();
        $keep = !$ended && ($prio[$m['offre']] ?? 9) < $prio[$offre];
        $newOffre = $keep ? $m['offre'] : $offre;
        on_q('UPDATE membres SET actif = 1, offre = ?, programme = ?, tel = CASE WHEN tel = \'\' THEN ? ELSE tel END'
            . ($ended ? ', date_debut = ?, date_fin = ?, debut_choisi = 0' : '') . ' WHERE id = ?',
            array_merge([$newOffre, ON_OFFRES[$newOffre]['programme'], $tel], $ended ? [$debut, on_default_end($debut)] : [], [(int) $m['id']]));
        $m = on_member((int) $m['id']);
        $result = $ended ? 'nouveau programme pour un ancien membre' : 'achat d’un membre déjà inscrit';
    }
    on_q('INSERT INTO achats (facture, membre_id, offre, produit, montant, recu_le) VALUES (?, ?, ?, ?, ?, ?)',
        [$facture, $m && !$coach ? (int) $m['id'] : null, $offre, $produit, $montant, on_now_str()]);

    if ($m && !$coach) on_mail_welcome($m);
    on_mail_team_purchase($m, $offre, $produit, $montant, $result, $c);
    return $result;
}

// Bienvenue : l'espace est prêt, première étape = choisir sa date de démarrage
function on_mail_welcome(array $m): bool
{
    $o = ON_OFFRES[$m['offre']] ?? ON_OFFRES['programme'];
    $body = "Bonjour {$m['prenom']},\n\n"
        . "Merci pour votre achat ({$o['nom']}). Votre espace HYROX Online est prêt :\n\n"
        . "    " . ON_SITE . "/online/\n\n"
        . "Connectez-vous avec cette adresse e-mail ({$m['email']}) : vous recevrez un code à 6 chiffres, sans mot de passe.\n\n"
        . "Première étape : choisir la date à laquelle vous démarrez votre programme de 8 semaines.\n"
        . ($o['club'] ? "Depuis votre espace, réservez aussi votre séance test (2 h) et votre simulation (2 h) au club.\n" : '')
        . "\nNotre équipe vous appellera pour valider avec vous la mise en place de votre programme.\n\n"
        . "À très vite,\nL’équipe ZERO26.";
    return on_send_mail($m['email'], 'Votre espace HYROX Online est prêt', $body);
}

// Alerte à l'équipe (team@ et coachs) : nouvel achat, à rappeler
function on_mail_team_purchase(?array $m, string $offre, string $produit, int $montant, string $result, array $c): bool
{
    $who = $m ? $m['prenom'] : (string) ($c['name'] ?? '');
    $body = "Nouvel achat HYROX Online sur bsport : $result.\n\n"
        . "Client : " . trim(((string) ($c['first_name'] ?? '')) . ' ' . ((string) ($c['last_name'] ?? ''))) . "\n"
        . "E-mail : " . ($m['email'] ?? (string) ($c['email'] ?? '')) . "\n"
        . "Téléphone : " . (((string) ($c['phone'] ?? '')) ?: 'non renseigné') . "\n"
        . "Produit : $produit\n"
        . "Montant : " . number_format($montant / 100, 2, ',', ' ') . " €\n\n"
        . ($m ? "Fiche du membre : " . ON_SITE . "/online/coach.php?m=" . (int) $m['id'] . "\n"
            . "Le client a reçu le lien de son espace ; il y choisit sa date de démarrage.\n" : '')
        . "À faire : l’appeler pour valider la mise en place du programme"
        . (ON_OFFRES[$offre]['club'] ? " et ses créneaux au club (séance test, simulation)." : '.');
    return on_send_mail(ON_MAIL_REPLY, "Nouvel achat HYROX Online : $who", $body, ['Cc' => implode(', ', array_keys(ON_COACHES))]);
}

// Date de démarrage choisie par le membre (ou fixée par le coach) : fin d'accès recalculée
function on_set_start(int $id, string $debut): void
{
    on_q('UPDATE membres SET date_debut = ?, date_fin = ?, debut_choisi = 1 WHERE id = ?', [$debut, on_default_end($debut), $id]);
}

/* ---------- Mise en page ---------- */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function on_redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function on_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

function on_csrf_field(?array $user): string
{
    return $user ? '<input type="hidden" name="csrf" value="' . h($user['csrf']) . '">' : '';
}

// Étiquette d'appareil (SKI violet, RUN lavande, ROW bleu ; les autres en neutre), toujours avec son nom
function on_tag(string $label, string $appareil = ''): string
{
    $st = in_array($appareil, ['ski', 'run', 'row'], true) ? ' data-st="' . $appareil . '"' : '';
    return '<span class="on-tag"' . $st . '>' . h($label) . '</span>';
}

function on_page_start(string $title, ?array $user, string $area = 'membre'): void
{
    $home = $area === 'coach' ? 'coach.php' : './';
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#08070d">
  <title><?= h($title) ?> · HYROX Online | ZERO26.</title>
  <link rel="icon" href="/favicon.ico" sizes="48x48">
  <link rel="icon" href="/icon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="preload" href="/assets/fonts/anton-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/css/online.css?v=<?= ON_ASSETS_V ?>">
  <script src="/assets/js/online.js?v=<?= ON_ASSETS_V ?>"></script>
</head>
<body class="on on--<?= h($area) ?>">
<a class="on-skip" href="#contenu">Aller au contenu</a>
<header class="on-top">
  <div class="on-top__in">
    <a class="on-top__brand" href="<?= $home ?>" aria-label="HYROX Online — accueil">
      <img src="/assets/img/logo-zero26.webp" alt="ZERO26." width="480" height="179">
      <span class="on-top__name">HYROX <b>Online</b><?= $area === 'coach' ? ' <i>Coach</i>' : '' ?></span>
    </a>
    <div class="on-top__act">
      <button class="on-theme" type="button" data-on-theme aria-pressed="false" aria-label="Passer en mode jour">
        <span class="on-theme__knob" aria-hidden="true"></span>
        <svg class="on-theme__sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/></svg>
        <svg class="on-theme__moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.5 14.6A7.5 7.5 0 0 1 9.4 4.5a7.5 7.5 0 1 0 10.1 10.1z"/></svg>
      </button>
      <?php if ($user): ?>
      <form method="post" action="./">
        <?= on_csrf_field($user) ?>
        <button class="on-btn on-btn--ghost on-btn--sm" name="action" value="logout">Déconnexion</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="on-main<?= $area === 'coach' ? ' on-main--wide' : '' ?>" id="contenu">
<?php
}

function on_page_end(): void
{
    ?>
</main>
<footer class="on-foot">
  <p>ZERO26. Coaching Club · 11 rue d’Isle, Saint-Quentin · <a href="tel:+33764623085">07 64 62 30 85</a></p>
  <p><a href="/hyrox-online.html">HYROX Online</a> · <a href="/mentions-legales.html#donnees">Données personnelles</a> · <a href="/">zero26coachingclub.com</a></p>
</footer>
</body>
</html>
<?php
}

function on_notice(string $msg, bool $error = false): string
{
    if ($msg === '') return '';
    return '<p class="on-note' . ($error ? ' on-note--err' : '') . '" role="' . ($error ? 'alert' : 'status') . '">' . h($msg) . '</p>';
}

/* ---------- Rendu d'une séance ---------- */

// Une cible calculée : « [VC] 4:31 /km », « [1RM] 82,5 kg », « [ROW] 210 W ».
// Sans résultat de test valide : rien (aucune charge ni allure inventée).
function on_render_intensity(array $int, array $values): string
{
    $t = on_tests()[$int['ref']];
    if (!isset($values[$int['ref']])) return '';
    $target = on_target($int, $t, (float) $values[$int['ref']]['valeur']);
    if (!$target['valeurs']) return '';
    $parts = [];
    foreach ($target['valeurs'] as [$label, $v]) $parts[] = ($label !== '' ? '<em>' . h($label) . '</em> ' : '') . h($v);
    return '<span class="on-pace">' . on_tag($t['etiquette'], $t['appareil'])
        . (!empty($target['domaine']) ? '<small class="on-pace__dom">' . h($target['domaine']) . '</small>' : '')
        . '<b>' . implode('<i>–</i>', $parts) . '</b>'
        . ($target['unite'] !== '' ? '<small>' . h($target['unite']) . '</small>' : '')
        . ($target['extra'] !== '' ? '<small class="on-pace__extra">soit ' . h($target['extra']) . '</small>' : '')
        . '</span>';
}

// Tests attendus par une séance et pas encore saisis
function on_missing_refs(array $s, array $values): array
{
    return array_values(array_filter(on_seance_refs($s), fn($ref) => !isset($values[$ref])));
}

// Blocs d'une séance avec les cibles calculées. $values : test => résultat (on_values_at).
function on_render_blocs(array $s, array $values): string
{
    if (!$s['blocs']) return '<p class="on-empty">Le détail de cette séance arrive bientôt.</p>';
    $out = '';
    foreach ($s['blocs'] as $b) {
        $out .= '<section class="on-bloc"><header class="on-bloc__h">';
        if ($b['titre'] !== '') $out .= '<h3 class="on-bloc__t">' . h($b['titre']) . '</h3>';
        if ($b['note'] !== '') $out .= '<p class="on-bloc__note">' . h($b['note']) . '</p>';
        $out .= '</header>';
        if ($b['lignes']) {
            $out .= '<ul class="on-lignes">';
            foreach ($b['lignes'] as $l) {
                $chips = implode('', array_map(fn($int) => on_render_intensity($int, $values), $l['intensites']));
                $out .= '<li class="on-ligne"><span class="on-ligne__txt">' . h($l['texte']) . '</span>'
                    . ($chips !== '' ? '<span class="on-ligne__chips">' . $chips . '</span>' : '') . '</li>';
            }
            $out .= '</ul>';
        }
        $out .= '</section>';
    }
    return $out;
}

// Tests affichés pour un programme (« tests » de sa variante, sinon tous), dans l'ordre de tests.json
function on_prog_tests(array $prog): array
{
    $all = on_tests();
    return $prog['tests'] ? array_filter($all, fn($id) => in_array($id, $prog['tests'], true), ARRAY_FILTER_USE_KEY) : $all;
}

// Pack : réservation de la séance test et de la simulation au club (créneaux sur bsport)
function on_render_club_booking(): string
{
    return '<section class="on-sec on-club"><h2 class="on-h2">Au club</h2>'
        . '<p class="on-help">Votre pack comprend deux séances de 2&nbsp;h avec votre coach&nbsp;: la séance test, avant la semaine&nbsp;1, et la simulation, en semaine&nbsp;7 (environ 15&nbsp;jours avant votre course). Les créneaux se réservent sur bsport.</p>'
        . '<div class="on-club__btns">'
        . '<a class="on-btn on-btn--grad" href="' . h(ON_RESA_TEST) . '" target="_blank" rel="noopener">Réserver ma séance test<small>2&nbsp;h</small></a>'
        . '<a class="on-btn on-btn--ghost" href="' . h(ON_RESA_SIMULATION) . '" target="_blank" rel="noopener">Réserver ma simulation<small>2&nbsp;h</small></a>'
        . '</div></section>';
}

// Bloc du programme qui contient la semaine $w (structure de hyrox.json), ou null
function on_week_block(array $structure, int $w): ?array
{
    foreach ($structure as $bl) if ($w >= $bl['de'] && $w <= $bl['a']) return $bl;
    return null;
}

// Blocs du programme : semaines, types de séances et tests ; le bloc de la semaine $week est mis en avant.
// Même affichage dans l'espace membre (onglet Programme) et dans l'aperçu coach.
function on_render_structure(array $structure, int $week = 0): string
{
    if (!$structure) return '';
    $list = fn(array $lines, string $cls = '') => '<ul' . ($cls !== '' ? ' class="' . $cls . '"' : '') . '>' . implode('', array_map(fn($l) => '<li>' . h($l) . '</li>', $lines)) . '</ul>';
    $out = '<div class="on-structure">';
    foreach ($structure as $bl) {
        $cur = $week >= $bl['de'] && $week <= $bl['a'];
        $out .= '<article class="on-card on-structure__b' . ($cur ? ' is-current' : '') . '"' . ($cur ? ' aria-current="true"' : '') . '>'
            . '<p class="on-structure__k">' . h($bl['titre']) . ($cur ? '<span class="on-structure__now">Semaine ' . $week . '</span>' : '') . '</p>'
            . '<p class="on-structure__w">' . h($bl['semaines']) . '</p>'
            . $list($bl['seances'])
            . ($bl['tests'] ? '<p class="on-structure__t">Tests</p>' . $list($bl['tests'], 'on-structure__tests') : '')
            . '</article>';
    }
    return $out . '</div>';
}

// « Calculé d'après : Back squat 1RM 110 kg (test du 12 octobre 2026) » ; tests manquants signalés une seule fois
function on_render_sources(array $s, array $values): string
{
    $tests = on_tests();
    $items = [];
    foreach (on_seance_refs($s) as $ref) {
        if (!isset($values[$ref])) continue;
        $r = $values[$ref];
        $items[] = $tests[$ref]['mesure'] === 'vc'
            ? 'vitesse critique <b>' . h(on_fmt_value($tests[$ref], (float) $r['valeur'])) . '</b> (tests du ' . h(on_fmt_date($r['date_test'], 'annee')) . ')'
            : h($tests[$ref]['nom']) . ' <b>' . h(on_fmt_value($tests[$ref], (float) $r['valeur'])) . '</b> (test du ' . h(on_fmt_date($r['date_test'], 'annee')) . ')';
    }
    $out = $items ? '<p class="on-sources">Calculé d’après : ' . implode(' · ', $items) . '.</p>' : '';
    $missing = on_missing_refs($s, $values);
    if ($missing) {
        $names = array_map(fn($ref) => $tests[$ref]['mesure'] === 'vc' ? 'tests 1 200 m et 3 600 m' : $tests[$ref]['nom'], $missing);
        $out .= '<p class="on-sources">Les charges et allures de cette séance s’afficheront dès que ces tests seront saisis&nbsp;: ' . h(implode(', ', $names)) . '.</p>';
    }
    return $out;
}

// Vitesse critique d'un membre : seuils, domaines d'intensité et réserve D′
function on_render_vc_domains(float $vc, float $dprime): string
{
    $d = on_vc_domains($vc);
    $pace = fn(float $ms) => on_fmt_time(1000 / $ms);
    $rows = [
        'modere' => 'plus lent que ' . $pace($d['s1']) . "\u{00A0}/km · moins de " . on_fmt_kmh($d['s1']),
        'eleve' => $pace($d['vc']) . ' à ' . $pace($d['s1']) . "\u{00A0}/km · " . on_fmt_num($d['s1'] * 3.6, 1) . ' à ' . on_fmt_kmh($d['vc']),
        'severe' => 'plus vite que ' . $pace($d['vc']) . "\u{00A0}/km · plus de " . on_fmt_kmh($d['vc']),
    ];
    $out = '<div class="on-vc"><p class="on-vc__seuils"><span><b>Seuil 1</b> ' . h($pace($d['s1'])) . "\u{00A0}/km · " . h(on_fmt_kmh($d['s1'])) . '</span>'
        . '<span><b>Seuil 2 = VC</b> ' . h($pace($d['vc'])) . "\u{00A0}/km · " . h(on_fmt_kmh($d['vc'])) . '</span></p><ul class="on-vc__dom">';
    foreach (ON_VC_DOMAINES as $k => $dom) {
        $out .= '<li data-dom="' . $k . '"><span class="on-vc__n">' . h($dom['nom']) . '</span><b>' . h($rows[$k]) . '</b><span class="on-vc__t">' . h($dom['texte']) . '</span></li>';
    }
    $out .= '</ul>';
    $lim = on_vc_time_limit($vc, $dprime, $vc * 1.05);
    if ($lim) $out .= '<p class="on-help">Réserve D′ ' . h(on_fmt_num(round($dprime), 0)) . "\u{00A0}m : à 105 % de la VC (" . h($pace($vc * 1.05)) . "\u{00A0}/km), environ " . h(on_fmt_time($lim)) . ' avant l’épuisement.</p>';
    return $out . '</div>';
}

// Résultat en vigueur d'un test, selon sa mesure : vitesse critique et domaines, watts d'un ergomètre,
// charges en % du 1RM, progression d'un METCON. $hist : résultats validés ou non de ce test.
function on_render_test_now(array $t, ?array $cur, array $hist, string $missingHint = 'Pas encore de résultat.'): string
{
    $tests = on_tests();
    if ($t['mesure'] === 'vc') {
        $dist = fn($id) => on_fmt_distance($tests[$id]['distance']);
        if (!$cur) {
            return '<p class="on-help">Calculée dès que les tests ' . h(on_fmt_list(array_map($dist, $t['depuis']))) . ' sont saisis'
                . ($t['affiner'] ? ' (le test ' . h(on_fmt_list(array_map($dist, $t['affiner']))) . ' affine ensuite le calcul)' : '')
                . '. Elle donne vos deux seuils et vos trois domaines d’intensité.</p>';
        }
        $vc = (float) $cur['valeur'];
        $src = array_map(fn($r) => on_fmt_value($tests[$r['test']], (float) $r['valeur']) . ' sur ' . $dist($r['test']), $cur['sources']);
        return '<p class="on-test__v"><b>' . h(on_fmt_value($t, $vc)) . '</b><span>' . h(on_fmt_kmh($vc)) . ' · D′ ' . h(on_fmt_num(round($cur['dprime']), 0)) . "\u{00A0}m</span></p>"
            . '<p class="on-help">D’après vos tests ' . h(on_fmt_list($src)) . '.'
            . ($cur['dprime'] < 0 ? ' D′ négatif : les tests semblent incohérents, à vérifier.' : '') . '</p>'
            . on_render_vc_domains($vc, (float) $cur['dprime']);
    }
    if (!$cur) return '<p class="on-help">' . h($missingHint) . '</p>';
    $v = (float) $cur['valeur'];
    $out = '<p class="on-test__v"><b>' . h(on_fmt_value($t, $v)) . '</b>';
    if ($t['mesure'] === 'temps') $out .= '<span>' . h(on_fmt_base($t, $v)) . ($t['watts'] ? ' de moyenne' : '') . '</span>';
    $out .= '</p><p class="on-help">Test du ' . h(on_fmt_date($cur['date_test'], 'annee')) . '</p>';

    if ($t['mesure'] === 'charge') {
        $out .= '<ul class="on-loads" aria-label="Charges calculées">';
        foreach ([50, 60, 70, 75, 80, 90] as $p) $out .= '<li><span>' . $p . "\u{00A0}%</span><b>" . h(on_fmt_num(on_round($v * $p / 100, $t['arrondi']))) . '</b></li>';
        $out .= '</ul>';
    }
    // Progression depuis le premier test validé (METCON, simulation, chronos)
    $valid = array_values(array_filter($hist, fn($r) => $r['statut'] === 'valide'));
    usort($valid, fn($x, $y) => [$x['date_test'], $x['id']] <=> [$y['date_test'], $y['id']]);
    if (on_is_time($t) && count($valid) > 1 && (int) $valid[0]['id'] !== (int) $cur['id']) {
        $diff = $v - (float) $valid[0]['valeur'];
        $since = ' (' . h(on_fmt_date($valid[0]['date_test'], 'court')) . ')';
        $out .= abs($diff) < 0.5
            ? '<p class="on-progress-delta">Même temps qu’au premier test' . $since . '</p>'
            : '<p class="on-progress-delta' . ($diff < 0 ? ' is-better' : '') . '">' . ($diff < 0 ? '−' : '+') . h(on_fmt_time(abs($diff))) . ' depuis le premier test' . $since . '</p>';
    }
    return $out;
}
