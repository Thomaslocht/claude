<?php
/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online : webhook bsport « invoice-pay »
   bsport appelle cette adresse à chaque facture payée du club. Si la facture
   contient un produit HYROX Online (ON_OFFRES), l'espace du client est créé
   (ou réactivé), il reçoit le lien par e-mail et l'équipe est prévenue.
   Toutes les autres factures sont ignorées, sans rien garder.
   Lien à coller dans bsport (Réglages → Webhooks) : affiché dans l'espace coach,
   avec la clé secrète créée sur le serveur (jamais dans le dépôt).
   ========================================================================== */
declare(strict_types=1);

require __DIR__ . '/../includes/online/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function bsport_reply(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => $status < 300, 'resultat' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bsport_reply(405, 'POST attendu');

try {
    $key = (string) ($_GET['cle'] ?? '');
    if ($key === '' || !hash_equals(on_webhook_key(), $key)) {
        if (on_limited('webhook-ip', on_ip(), 30, 3600)) bsport_reply(429, 'trop de tentatives');
        bsport_reply(403, 'clé invalide');
    }

    $raw = (string) file_get_contents('php://input', false, null, 0, 262144);
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        on_webhook_log('refusé : contenu illisible');
        bsport_reply(400, 'JSON attendu');
    }
    $event = (string) ($in['event_type'] ?? '');
    if ($event !== 'invoice-pay') {
        on_webhook_log("ignoré : événement « $event » (seul invoice-pay crée un espace)");
        bsport_reply(200, 'ignoré : événement non suivi');
    }
    $inv = $in['data']['object'] ?? null;
    if (!is_array($inv)) {
        on_webhook_log('refusé : facture absente');
        bsport_reply(400, 'facture absente');
    }

    $result = on_bsport_invoice($inv);
    on_webhook_log($result);
    bsport_reply(200, $result);
} catch (Throwable $e) {
    error_log('Online, webhook bsport : ' . $e->getMessage());
    try { on_webhook_log('erreur du serveur, bsport devra renvoyer la facture'); } catch (Throwable $e2) {}
    bsport_reply(500, 'erreur du serveur');
}
