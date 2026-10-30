<?php
/* ==========================================================================
   ZERO26. Coaching Club — SuperLeague Concept2 Triathlon
   Fonctions communes aux inscriptions (formulaire + page organisateur).
   Ce fichier n'affiche rien : il est seulement inclus par
   superleague-inscription.php et superleague-admin.php.
   ========================================================================== */
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

// Date, clôture et nombre de places : modifiables dans assets/data/superleague.json
const SL_CONFIG_FILE = __DIR__ . '/../assets/data/superleague.json';

// Mot de passe de la page organisateur (superleague-admin.php), enregistré haché :
// le mot de passe lui-même n'est écrit nulle part dans le site. Pour le changer :
//   php -r 'echo password_hash("nouveau-mot-de-passe", PASSWORD_BCRYPT, ["cost" => 12]), "\n";'
const SL_ADMIN_HASH = '$2y$12$ma3yrIrECFUjwKYZ3zeDUucBh60QACACYjU.XAAvHtYh3UgeS.2Pu';

// Colonnes du fichier exporté : l'app du jour J lit « prenom », « nom » et « sexe »
const SL_COLUMNS = ['prenom', 'nom', 'sexe', 'email', 'tel', 'inscrit_le'];
const SL_CSV_NAME = 'superleague-inscriptions.csv';

function sl_config(): array
{
    $raw = @file_get_contents(SL_CONFIG_FILE);
    $cfg = $raw !== false ? json_decode($raw, true) : null;
    return is_array($cfg) ? $cfg : [];
}

/* ---------- Stockage ---------- */

// Les inscriptions sont rangées hors du dossier public du site (à côté de public_html) :
// elles ne sont jamais accessibles par une adresse web et la publication depuis GitHub
// n'y touche pas. Si ce dossier n'est pas accessible, repli sur public_html/.data,
// fermé aux visiteurs.
function sl_data_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;

    $site = dirname(__DIR__);
    foreach ([dirname($site) . '/zero26-data', $site . '/.data'] as $candidate) {
        if (!@is_dir($candidate)) @mkdir($candidate, 0700, true);
        if (@is_dir($candidate) && @is_writable($candidate)) {
            if (str_starts_with($candidate, $site . '/')) sl_protect_dir($candidate);
            return $dir = $candidate;
        }
    }
    throw new RuntimeException('Aucun dossier de stockage accessible en écriture.');
}

function sl_data_is_public_dir(): bool
{
    return str_starts_with(sl_data_dir(), dirname(__DIR__) . '/');
}

function sl_protect_dir(string $dir): void
{
    $rules = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
    if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", $rules);
    if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
}

function sl_csv_path(): string
{
    return sl_data_dir() . '/' . SL_CSV_NAME;
}

// Verrou exclusif : deux inscriptions simultanées ne peuvent pas s'écraser
function sl_locked(callable $fn)
{
    $h = fopen(sl_data_dir() . '/.lock', 'c');
    if ($h === false) throw new RuntimeException('Verrou indisponible.');
    flock($h, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

function sl_read_all(): array
{
    $path = sl_csv_path();
    if (!is_file($path)) return [];
    $h = fopen($path, 'r');
    if ($h === false) throw new RuntimeException('Lecture impossible.');
    $rows = [];
    $head = null;
    while (($line = fgetcsv($h, null, ',', '"', '')) !== false) {
        if ($line === [null]) continue;
        if ($head === null) {
            $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]);
            $head = $line;
            continue;
        }
        $row = [];
        foreach ($head as $i => $col) $row[$col] = (string) ($line[$i] ?? '');
        $rows[] = $row;
    }
    fclose($h);
    return $rows;
}

function sl_save_all(array $rows): void
{
    $path = sl_csv_path();
    // Copie de sécurité du fichier précédent, une par jour
    if (is_file($path)) {
        $bdir = sl_data_dir() . '/sauvegardes';
        if (!is_dir($bdir)) @mkdir($bdir, 0700, true);
        $backup = $bdir . '/inscriptions-' . date('Y-m-d') . '.csv';
        if (!is_file($backup)) @copy($path, $backup);
    }
    $tmp = $path . '.tmp';
    $h = fopen($tmp, 'w');
    if ($h === false) throw new RuntimeException('Écriture impossible.');
    fwrite($h, "\xEF\xBB\xBF"); // accents corrects à l'ouverture dans Excel
    fputcsv($h, SL_COLUMNS, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($h, array_map(fn($c) => (string) ($r[$c] ?? ''), SL_COLUMNS), ',', '"', '');
    }
    fclose($h);
    if (!rename($tmp, $path)) throw new RuntimeException('Écriture impossible.');
}

/* ---------- État des inscriptions ---------- */

function sl_status(?int $count = null): array
{
    $cfg = sl_config();
    $cloture = trim((string) ($cfg['cloture'] ?? ''));
    if ($cloture !== '') {
        $t = strtotime($cloture);
        if ($t !== false && time() > $t) return ['open' => false, 'reason' => 'closed', 'remaining' => null];
    }
    $max = (int) ($cfg['placesMax'] ?? 0);
    if ($max > 0) {
        $count ??= count(sl_read_all());
        $left = max(0, $max - $count);
        return ['open' => $left > 0, 'reason' => $left > 0 ? null : 'full', 'remaining' => $left];
    }
    return ['open' => true, 'reason' => null, 'remaining' => null];
}

/* ---------- Nettoyage des champs ---------- */

function sl_clean_name($v): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[^\p{L}\p{M}\' .\-]/u', '', $v) ?? '';
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    // Jamais de « = + - @ » en tête : un tableur l'interpréterait comme une formule
    $v = trim($v, " .-'");
    return mb_substr($v, 0, 40);
}

function sl_clean_sexe($v): string
{
    $v = mb_strtolower(trim(is_string($v) ? $v : ''));
    if (in_array($v, ['femme', 'f', 'women'], true)) return 'Femme';
    if (in_array($v, ['homme', 'h', 'men'], true)) return 'Homme';
    return '';
}

function sl_clean_email($v): string
{
    $v = mb_strtolower(trim(is_string($v) ? $v : ''));
    return (mb_strlen($v) <= 120 && filter_var($v, FILTER_VALIDATE_EMAIL)) ? $v : '';
}

function sl_clean_tel($v): string
{
    $v = trim(is_string($v) ? $v : '');
    $digits = preg_replace('/\D/', '', $v) ?? '';
    // Numéros français : toujours au format 06 12 34 56 78
    if (preg_match('/^(?:33|0033)([1-9]\d{8})$/', $digits, $m) && preg_match('/^\s*(\+|00)/', $v)) $digits = '0' . $m[1];
    if (preg_match('/^0[1-9]\d{8}$/', $digits)) return trim(chunk_split($digits, 2, ' '));
    if (strlen($digits) >= 8 && strlen($digits) <= 15 && preg_match('/^\s*(\+|00)/', $v)) return '+' . ltrim($digits, '0');
    return '';
}

/* ---------- Limitation des envois (anti-robots) ---------- */

function sl_client_key(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|zero26-superleague');
}

// Vrai si la limite est atteinte ; sinon, compte cette tentative.
// Ne pas appeler à l'intérieur de sl_locked() : le verrou est le même.
function sl_rate_limited(string $bucket, int $max, int $window): bool
{
    return sl_locked(function () use ($bucket, $max, $window) {
        $file = sl_data_dir() . '/limites.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (!is_array($data)) $data = [];
        $now = time();
        foreach ($data as $k => $hits) {
            $data[$k] = array_values(array_filter((array) $hits, fn($t) => is_int($t) && $t > $now - 86400));
            if (!$data[$k]) unset($data[$k]);
        }
        $key = $bucket . ':' . sl_client_key();
        $recent = array_filter($data[$key] ?? [], fn($t) => $t > $now - $window);
        $limited = count($recent) >= $max;
        if (!$limited) $data[$key][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return $limited;
    });
}

// Réponse JSON, puis fin du script
function sl_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
