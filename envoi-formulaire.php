<?php
/* ==========================================================================
   ZERO26. Coaching Club — envoi des formulaires par e-mail
   POST (JSON) → envoie la demande à l'équipe depuis le serveur du site et en
   garde une copie à côté des inscriptions SuperLeague (zero26-data).
   Utilisé par le formulaire de contact (main.js) et par la copie des
   inscriptions SuperLeague (superleague.js).
   Réponse : { "success": "true" } ou { "success": "false", "message": "…" }.
   En cas d'échec, le navigateur ouvre la messagerie du visiteur avec sa demande.
   ========================================================================== */
declare(strict_types=1);

ini_set('display_errors', '0'); // Une alerte PHP ne doit pas casser la réponse JSON

require __DIR__ . '/includes/superleague.php';

const FORM_TO = 'team@zero26coachingclub.com';
const FORM_FROM = 'site@zero26coachingclub.com';
const FORM_CSV_NAME = 'formulaires-demandes.csv';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function form_fail(int $code, string $message): void
{
    sl_json($code, ['success' => 'false', 'message' => $message]);
}

// Retire les caractères de contrôle ; $multiline garde les retours à la ligne d'un message
function form_clean(string $v, int $max, bool $multiline = false): string
{
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = $multiline
        ? preg_replace('/[^\P{C}\n]/u', '', $v)
        : preg_replace('/\p{C}/u', ' ', $v);
    return trim(mb_substr($v ?? '', 0, $max));
}

function form_store(string $subject, string $replyTo, string $body, bool $sent): void
{
    $path = sl_data_dir() . '/' . FORM_CSV_NAME;
    sl_locked(function () use ($path, $subject, $replyTo, $body, $sent) {
        $isNew = !is_file($path);
        $h = fopen($path, 'a');
        if ($h === false) throw new RuntimeException('Écriture impossible.');
        if ($isNew) {
            fwrite($h, "\xEF\xBB\xBF"); // accents corrects à l'ouverture dans Excel
            fputcsv($h, ['date', 'email_envoye', 'objet', 'repondre_a', 'contenu'], ',', '"', '');
        }
        // Jamais de « = + - @ » en tête : un tableur l'interpréterait comme une formule
        $safe = fn(string $c): string => preg_match('/^[=+\-@]/', $c) ? "'$c" : $c;
        fputcsv($h, [date('Y-m-d H:i'), $sent ? 'oui' : 'NON', $safe($subject), $safe($replyTo), $safe($body)], ',', '"', '');
        fclose($h);
    });
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        form_fail(405, 'Méthode non autorisée.');
    }

    // Uniquement depuis le site lui-même
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') ?? '');
    if ($origin === '' || strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host) {
        form_fail(403, 'Envoi refusé.');
    }

    $in = json_decode((string) file_get_contents('php://input', false, null, 0, 16384), true);
    if (!is_array($in)) form_fail(400, 'Demande illisible.');

    // Champ piège invisible : rempli uniquement par les robots (on fait comme si tout allait bien)
    if (!empty($in['_honey'])) sl_json(200, ['success' => 'true']);

    $replyTo = sl_clean_email($in['_replyto'] ?? '');
    if ($replyTo === '') form_fail(422, 'Adresse e-mail invalide.');

    try {
        if (sl_rate_limited('formulaire', 20, 3600)) {
            form_fail(429, 'Trop d’envois depuis cette connexion. Réessayez dans un moment, ou appelez-nous au 07 64 62 30 85.');
        }
    } catch (Throwable $e) {
        error_log('Formulaire, limite d’envois : ' . $e->getMessage()); // l'e-mail part quand même
    }

    $subject = form_clean(is_string($in['_subject'] ?? null) ? $in['_subject'] : '', 200);
    if ($subject === '') $subject = 'Site ZERO26 — Nouvelle demande';

    // Les champs commençant par « _ » sont des réglages, pas du contenu
    $lines = [];
    foreach ($in as $label => $value) {
        if (!is_string($label) || str_starts_with($label, '_') || !is_string($value) || count($lines) >= 20) continue;
        $lines[] = form_clean($label, 60) . ' : ' . form_clean($value, 2000, true);
    }
    if (!$lines) form_fail(422, 'Demande vide.');
    $body = implode("\n", $lines);

    $sent = false;
    try {
        $args = [
            FORM_TO,
            mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 9),
            quoted_printable_encode(str_replace("\n", "\r\n", $body . "\n\n--\nEnvoyé depuis le site zero26coachingclub.com. Pour répondre, utilisez simplement « Répondre ».")),
            [
                'From' => 'Site ZERO26 <' . FORM_FROM . '>',
                'Reply-To' => $replyTo,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Transfer-Encoding' => 'quoted-printable',
            ],
        ];
        // Expéditeur technique du domaine (vérifié par le SPF, qui doit inclure Hostinger) ; repli sans
        $sent = mail(...array_merge($args, ['-f' . FORM_TO])) || mail(...$args);
    } catch (Throwable $e) {
        error_log('Formulaire, envoi : ' . $e->getMessage());
    }

    // Copie gardée dans tous les cas : aucune demande perdue si l'e-mail n'arrive pas
    try {
        form_store($subject, $replyTo, $body, $sent);
    } catch (Throwable $e) {
        error_log('Formulaire, copie : ' . $e->getMessage());
    }

    if (!$sent) form_fail(502, 'L’e-mail n’a pas pu partir.');
    sl_json(200, ['success' => 'true']);
} catch (Throwable $e) {
    error_log('Formulaire : ' . $e->getMessage());
    form_fail(500, 'Envoi impossible pour le moment.');
}
