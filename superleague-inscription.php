<?php
/* ==========================================================================
   ZERO26. Coaching Club — SuperLeague : réception des inscriptions
   GET  → état des inscriptions (ouvertes, clôturées, complet, places restantes)
   POST → enregistre une inscription (JSON : prenom, nom, sexe, email, tel)
   Les inscriptions s'ajoutent au fichier CSV lu par superleague-admin.php.
   ========================================================================== */
declare(strict_types=1);

require __DIR__ . '/includes/superleague.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $s = sl_status();
        sl_json(200, ['ok' => true, 'open' => $s['open'], 'reason' => $s['reason'], 'remaining' => $s['remaining']]);
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        sl_json(405, ['ok' => false, 'error' => 'method', 'message' => 'Méthode non autorisée.']);
    }

    // Uniquement depuis le site lui-même
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') ?? '');
    if ($origin !== '' && strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host) {
        sl_json(403, ['ok' => false, 'error' => 'origin', 'message' => 'Envoi refusé.']);
    }

    $raw = (string) file_get_contents('php://input', false, null, 0, 8192);
    $in = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;

    // Champ piège invisible : rempli uniquement par les robots (on fait comme si tout allait bien)
    if (!empty($in['site'])) sl_json(200, ['ok' => true, 'prenom' => '']);

    $d = [
        'prenom' => sl_clean_name($in['prenom'] ?? ''),
        'nom' => sl_clean_name($in['nom'] ?? ''),
        'sexe' => sl_clean_sexe($in['sexe'] ?? ''),
        'email' => sl_clean_email($in['email'] ?? ''),
        'tel' => sl_clean_tel($in['tel'] ?? ''),
    ];
    $labels = ['prenom' => 'prénom', 'nom' => 'nom', 'sexe' => 'sexe', 'email' => 'e-mail', 'tel' => 'téléphone'];
    $bad = array_keys(array_filter($d, fn($v) => $v === ''));
    if ($bad) {
        sl_json(422, [
            'ok' => false,
            'error' => 'fields',
            'fields' => $bad,
            'message' => 'Merci de vérifier : ' . implode(', ', array_map(fn($k) => $labels[$k], $bad)) . '.',
        ]);
    }

    if (sl_rate_limited('inscription', 20, 3600)) {
        sl_json(429, ['ok' => false, 'error' => 'rate', 'message' => 'Trop d’inscriptions depuis cette connexion. Réessayez dans un moment, ou appelez-nous au 07 64 62 30 85.']);
    }

    $result = sl_locked(function () use ($d) {
        $rows = sl_read_all();
        $status = sl_status(count($rows));
        if (!$status['open']) return ['code' => 410, 'error' => $status['reason']];
        foreach ($rows as $r) {
            if (strtolower($r['email'] ?? '') === $d['email']) return ['code' => 409, 'error' => 'duplicate'];
        }
        $d['inscrit_le'] = date('Y-m-d H:i');
        $rows[] = $d;
        sl_save_all($rows);
        $left = sl_status(count($rows))['remaining'];
        return ['code' => 200, 'remaining' => $left];
    });

    if ($result['code'] === 409) {
        sl_json(409, ['ok' => false, 'error' => 'duplicate', 'message' => 'Cette adresse e-mail est déjà inscrite. Une modification ? Écrivez-nous à team@zero26coachingclub.com.']);
    }
    if ($result['code'] === 410) {
        $msg = $result['error'] === 'full' ? 'La SuperLeague est complète.' : 'Les inscriptions sont clôturées.';
        sl_json(410, ['ok' => false, 'error' => $result['error'], 'message' => $msg]);
    }
    sl_json(200, ['ok' => true, 'prenom' => $d['prenom'], 'remaining' => $result['remaining']]);
} catch (Throwable $e) {
    error_log('SuperLeague inscription : ' . $e->getMessage());
    sl_json(500, ['ok' => false, 'error' => 'server', 'message' => 'Enregistrement impossible pour le moment.']);
}
