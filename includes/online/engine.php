<?php
/* ==========================================================================
   ZERO26. Coaching Club — HYROX Online : programme et calculateur
   Tests de référence : tests.json. Programmes : programmes/*.json.
   Mode d'emploi : MODE-EMPLOI.md, dans ce dossier.
   Ce fichier n'affiche rien et ne touche pas à la base de données.
   ========================================================================== */
declare(strict_types=1);

const ON_DIR = __DIR__;

// Vitesse critique (méthode à deux chronos) : seuil 1 vers 80–84 % de la VC, seuil 2 = VC.
// Trois domaines d'intensité : modéré (sous le seuil 1), élevé (entre les seuils), sévère (au-delà de la VC).
const ON_VC_SEUIL1 = 0.82;
const ON_VC_DOMAINES = [
    'modere' => ['nom' => 'Domaine modéré', 'court' => 'Modéré', 'texte' => 'Endurance : marqueurs physiologiques bas et stables. Sorties longues, endurance fondamentale.'],
    'eleve' => ['nom' => 'Domaine élevé', 'court' => 'Élevé', 'texte' => 'Marqueurs élevés mais stables, soutenable longtemps. Tempo, seuil (90 à 100 % de la VC).'],
    'severe' => ['nom' => 'Domaine sévère', 'court' => 'Sévère', 'texte' => 'La fatigue s’accumule jusqu’à l’épuisement : durée limitée. Intervalles (100 à 106 % de la VC).'],
];

/* ---------- Tests de référence ---------- */

// Mesures :
//   temps  : chrono sur une distance → allure (/km) ; avec "watts": true (RowErg, SkiErg), puissance moyenne
//   chrono : chrono seul, sans distance (METCON HYROX, simulation) : une référence à battre au re-test
//   charge : 1RM en kg → charges en %
//   reps   : nombre de répétitions
//   vc     : vitesse critique, calculée à partir de deux tests « temps » (jamais saisie)
function on_tests(): array
{
    static $tests = null;
    if ($tests !== null) return $tests;
    $data = on_read_json(ON_DIR . '/tests.json');
    $list = [];
    foreach ($data as $id => $t) {
        if (is_string($id) && str_starts_with($id, '_')) continue;
        if (!is_string($id) || !preg_match('/^[a-z0-9_]+$/', $id) || !is_array($t)) {
            throw new RuntimeException("tests.json : « $id » n’est pas un identifiant valide (minuscules, chiffres et _).");
        }
        $mesure = $t['mesure'] ?? 'temps';
        if (!in_array($mesure, ['temps', 'chrono', 'charge', 'reps', 'vc'], true)) {
            throw new RuntimeException("tests.json, « $id » : mesure doit valoir \"temps\", \"chrono\", \"charge\", \"reps\" ou \"vc\".");
        }
        if ($mesure === 'temps' && (!is_numeric($t['distance'] ?? null) || !is_numeric($t['allure'] ?? null) || $t['distance'] <= 0 || $t['allure'] <= 0)) {
            throw new RuntimeException("tests.json, « $id » : indiquez la distance du test et la distance de l’allure (en mètres).");
        }
        if ($mesure === 'vc' && (!is_array($t['depuis'] ?? null) || count($t['depuis']) !== 2)) {
            throw new RuntimeException("tests.json, « $id » : « depuis » doit nommer les deux tests de course, par exemple [\"run_1200\", \"run_3600\"].");
        }
        if ($mesure === 'vc' && isset($t['affiner']) && !is_array($t['affiner'])) {
            throw new RuntimeException("tests.json, « $id » : « affiner » doit être une liste de tests de course, par exemple [\"run_5000\"].");
        }
        $list[$id] = [
            'id' => $id,
            'nom' => (string) ($t['nom'] ?? $id),
            'appareil' => (string) ($t['appareil'] ?? ''),
            'etiquette' => (string) ($t['etiquette'] ?? strtoupper((string) ($t['appareil'] ?? ''))),
            'mesure' => $mesure,
            'saisie' => $mesure !== 'vc',
            'watts' => $mesure === 'temps' && !empty($t['watts']),
            'distance' => (float) ($t['distance'] ?? 0),
            'allure' => (float) ($t['allure'] ?? ($mesure === 'vc' ? 1000 : 0)),
            'unite' => (string) ($t['unite'] ?? ($mesure === 'charge' ? 'kg' : ($mesure === 'reps' ? 'reps' : ''))),
            'arrondi' => (float) ($t['arrondi'] ?? 2.5),
            'depuis' => array_values((array) ($t['depuis'] ?? [])),
            'affiner' => array_values((array) ($t['affiner'] ?? [])),
            'note' => (string) ($t['note'] ?? ''),
            'exemple' => (string) ($t['exemple'] ?? ''),
        ];
    }
    foreach ($list as $id => $t) {
        if ($t['mesure'] !== 'vc') continue;
        [$a, $b] = $t['depuis'];
        if (($list[$a]['mesure'] ?? '') !== 'temps' || ($list[$b]['mesure'] ?? '') !== 'temps' || $list[$a]['distance'] >= $list[$b]['distance']) {
            throw new RuntimeException("tests.json, « $id » : « depuis » doit nommer deux tests « temps », le plus court en premier.");
        }
        foreach ($t['affiner'] as $c) {
            if (($list[$c]['mesure'] ?? '') !== 'temps' || in_array($c, $t['depuis'], true)) {
                throw new RuntimeException("tests.json, « $id » : « affiner » doit nommer d’autres tests « temps » (par exemple \"run_5000\").");
            }
        }
    }
    return $tests = $list;
}

function on_read_json(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) throw new RuntimeException(basename($path) . ' est introuvable.');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException(basename($path) . ' n’est pas un JSON valide (virgule, guillemet ou accolade en trop ou manquant ?).');
    }
    return $data;
}

// Test qui donne une allure (course) — les ergomètres donnent des watts
function on_is_pace(array $test): bool
{
    return $test['mesure'] === 'vc' || ($test['mesure'] === 'temps' && !$test['watts']);
}

function on_is_time(array $test): bool
{
    return in_array($test['mesure'], ['temps', 'chrono'], true);
}

// Libellé de l'allure : « /km », « /500 m »…
function on_pace_label(array $test): string
{
    $par = (int) round($test['allure']);
    return $par === 1000 ? '/km' : '/' . number_format($par, 0, ',', "\u{202F}") . "\u{00A0}m";
}

// Allure en secondes (par km…) d'un test de course ou de la vitesse critique
function on_pace(array $test, float $value): float
{
    return $test['mesure'] === 'vc' ? $test['allure'] / $value : $value / $test['distance'] * $test['allure'];
}

// Puissance moyenne Concept2 (RowErg, SkiErg) : P = 2,80 / (secondes par mètre)³
function on_watts(float $seconds, float $meters): float
{
    return $seconds > 0 && $meters > 0 ? 2.80 / (($seconds / $meters) ** 3) : 0.0;
}

/* ---------- Saisie et affichage ---------- */

// « 3:40 », « 1:02:15 », « 3:40,2 », « 47'30 » → secondes ; « 82,5 » → kg ; « 60 » → répétitions
function on_parse_value(array $test, string $raw): ?float
{
    $raw = trim($raw);
    if ($test['mesure'] === 'reps') {
        return preg_match('/^\d{1,4}$/', $raw) && (int) $raw > 0 ? (float) (int) $raw : null;
    }
    if ($test['mesure'] === 'charge') {
        $raw = str_replace([',', ' ', 'kg'], ['.', '', ''], strtolower($raw));
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw)) return null;
        $v = (float) $raw;
        return $v > 0 ? $v : null;
    }
    if (!on_is_time($test)) return null;
    $raw = str_replace([',', "'", '’', '′', 'h', 'min', '"', '″', ' '], ['.', ':', ':', ':', ':', ':', '', '', ''], $raw);
    $raw = rtrim($raw, ':');
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,3}):(\d{1,2}(?:\.\d{1,2})?)$/', $raw, $m)) return null;
    $h = (int) ($m[1] !== '' ? $m[1] : 0);
    $min = (int) $m[2];
    $s = (float) $m[3];
    if ($s >= 60 || ($m[1] !== '' && $min >= 60)) return null;
    $v = $h * 3600 + $min * 60 + $s;
    return $v > 0 ? round($v, 2) : null;
}

// Message d'aide quand un résultat saisi n'est pas compris
function on_value_hint(array $test): string
{
    return match ($test['mesure']) {
        'reps' => 'Indiquez un nombre de répétitions.',
        'charge' => 'Indiquez la charge en kg, par exemple 110 ou 82,5.',
        default => 'Indiquez le temps au format min:s, par exemple 3:40 (ou 1:02:15).',
    };
}

// Secondes → « 4:35 » ou « 1:02:15 » (avec $decimals : « 3:40,2 »)
function on_fmt_time(float $sec, int $decimals = 0): string
{
    $sec = round(max(0, $sec), $decimals);
    $whole = (int) floor($sec + 1e-9);
    $frac = $sec - $whole;
    $h = intdiv($whole, 3600);
    $m = intdiv($whole % 3600, 60);
    $s = $whole % 60;
    $out = $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    if ($decimals > 0 && $frac > 0.00001) $out .= ',' . substr(number_format($frac, $decimals, '.', ''), 2);
    return $out;
}

// 82.5 → « 82,5 » ; 12.0 → « 12 »
function on_fmt_num(float $v, int $decimals = 2): string
{
    $s = number_format($v, $decimals, ',', "\u{202F}");
    return $decimals > 0 ? rtrim(rtrim($s, '0'), ',') : $s;
}

function on_round(float $v, float $step): float
{
    return $step > 0 ? round($v / $step) * $step : $v;
}

// m/s → « 14,4 km/h »
function on_fmt_kmh(float $ms): string
{
    return on_fmt_num($ms * 3.6, 1) . "\u{00A0}km/h";
}

// Résultat d'un test, tel qu'on le saisit (« 3:40 », « 110 kg », « 60 reps ») ; vitesse critique en allure
function on_fmt_value(array $test, float $value): string
{
    return match ($test['mesure']) {
        'temps', 'chrono' => on_fmt_time($value, 1),
        'vc' => on_fmt_time(on_pace($test, $value)) . "\u{00A0}" . on_pace_label($test),
        default => on_fmt_num($value) . "\u{00A0}" . $test['unite'],
    };
}

// Ce que le résultat donne : « 3:40 /km » pour la course, « 263 W » pour un ergomètre
function on_fmt_base(array $test, float $value): string
{
    if ($test['watts']) return on_fmt_num(round(on_watts($value, $test['distance'])), 0) . "\u{00A0}W";
    if (on_is_pace($test)) return on_fmt_time(on_pace($test, $value)) . "\u{00A0}" . on_pace_label($test);
    return on_fmt_value($test, $value);
}

/* ---------- Vitesse critique : seuils et domaines ---------- */

// Vitesse critique (m/s) et réserve anaérobie D′ (m), d'après deux chronos sur deux distances
function on_critical_speed(float $d1, float $t1, float $d2, float $t2): ?array
{
    if ($t2 <= $t1 || $d2 <= $d1) return null;
    $vc = ($d2 - $d1) / ($t2 - $t1);
    return ['vc' => $vc, 'dprime' => $d1 - $vc * $t1];
}

// Même modèle (distance = VC × temps + D′) sur deux chronos ou plus : droite des moindres carrés.
// Avec deux chronos, le résultat est celui de on_critical_speed ; un troisième (5 km) affine la VC.
// $points : [[distance en m, temps en s], …]
function on_critical_speed_fit(array $points): ?array
{
    $n = count($points);
    if ($n < 2) return null;
    if ($n === 2) {
        usort($points, fn($x, $y) => $x[0] <=> $y[0]);
        return on_critical_speed((float) $points[0][0], (float) $points[0][1], (float) $points[1][0], (float) $points[1][1]);
    }
    $md = array_sum(array_column($points, 0)) / $n;
    $mt = array_sum(array_column($points, 1)) / $n;
    $num = 0.0;
    $den = 0.0;
    foreach ($points as [$d, $t]) {
        $num += ($t - $mt) * ($d - $md);
        $den += ($t - $mt) ** 2;
    }
    if ($den <= 0 || $num <= 0) return null;
    $vc = $num / $den;
    return ['vc' => $vc, 'dprime' => $md - $vc * $mt];
}

// Seuils et domaines, en allure /km et en km/h
function on_vc_domains(float $vc): array
{
    $s1 = $vc * ON_VC_SEUIL1;
    return [
        'vc' => $vc,
        's1' => $s1,
        'pace_vc' => 1000 / $vc,
        'pace_s1' => 1000 / $s1,
        'modere' => ['max' => $s1],
        'eleve' => ['min' => $s1, 'max' => $vc],
        'severe' => ['min' => $vc],
    ];
}

// Temps tenable au-delà de la VC : D′ / (vitesse − VC), en secondes
function on_vc_time_limit(float $vc, float $dprime, float $speed): ?float
{
    return $speed > $vc && $dprime > 0 ? $dprime / ($speed - $vc) : null;
}

/* ---------- Intensités ---------- */

// Une intensité référence un test de tests.json :
//   { "ref": "run_vc", "type": "speed", "value": 105 }          → 105 % de la vitesse critique (plus rapide)
//   { "ref": "run_vc", "type": "domaine", "value": "modere" }   → allures du domaine modéré (élevé, sévère)
//   { "ref": "run_vc", "type": "percent", "value": 120 }        → 120 % du temps au km (plus lent)
//   { "ref": "row_1000", "type": "power", "value": 80 }         → 80 % des watts moyens du 1K
//   { "ref": "back_squat_1rm", "type": "percent", "value": 75 } → 75 % du 1RM, arrondi à 2,5 kg
//   "value": [a, b] → fourchette ; "distance": 800 → ajoute le temps de la répétition.
// Les charges écrites « femme / homme » (@8/12.5) restent dans le texte de la séance.
// Renvoie ['valeurs' => [['', '4:31'], …], 'unite' => '/km', 'extra' => '3:20 le 800 m']
function on_target(array $int, array $test, float $testValue): array
{
    $v = $testValue;

    if ($int['type'] === 'domaine') {
        $d = on_vc_domains($v);
        $p = fn(float $ms) => on_fmt_time(1000 / $ms);
        $vals = match ($int['value']) {
            'modere' => [['plus lent que', $p($d['s1'])]],
            'eleve' => [['', $p($d['vc'])], ['', $p($d['s1'])]],
            default => [['plus vite que', $p($d['vc'])]],
        };
        return ['valeurs' => $vals, 'unite' => '/km', 'extra' => '', 'domaine' => ON_VC_DOMAINES[$int['value']]['nom']];
    }

    if ($int['type'] === 'power') {
        $w = on_watts($v, $test['distance']);
        $out = array_map(fn($x) => ['', on_fmt_num(round($w * (float) $x / 100), 0)], (array) $int['value']);
        return ['valeurs' => $out, 'unite' => 'W', 'extra' => ''];
    }

    $out = [];
    $reps = [];
    $timeLike = on_is_time($test) || $test['mesure'] === 'vc';
    $base = $timeLike ? ($test['mesure'] === 'chrono' ? $v : on_pace($test, $v)) : $v;
    foreach ((array) $int['value'] as $x) {
        $x = (float) $x;
        $t = match ($int['type']) {
            'percent' => $base * $x / 100,
            'speed' => $base / ($x / 100),
            default => $base + $x,
        };
        if ($timeLike) {
            $out[] = ['', on_fmt_time($t)];
            if (!empty($int['distance']) && $test['mesure'] !== 'chrono') $reps[] = on_fmt_time($t * $int['distance'] / $test['allure']);
        } elseif ($test['mesure'] === 'charge') {
            $out[] = ['', on_fmt_num(max(0, on_round($t, $test['arrondi'])))];
        } else {
            $out[] = ['', (string) max(0, (int) round($t))];
        }
    }
    $unit = match (true) {
        $test['mesure'] === 'chrono' => '',
        $timeLike => on_pace_label($test),
        default => $test['unite'],
    };
    $extra = $reps ? implode('–', $reps) . ' le ' . on_fmt_num((float) $int['distance']) . "\u{00A0}m" : '';
    return ['valeurs' => $out, 'unite' => $unit, 'extra' => $extra];
}

// Unité facultative d'un écart : doit correspondre à l'unité du test
function on_unit_matches(string $unit, array $test): bool
{
    $u = strtolower(str_replace([' ', "\u{00A0}"], '', $unit));
    if (!on_is_time($test) && $test['mesure'] !== 'vc') {
        $expected = strtolower($test['unite']);
        return $u === $expected || ($u === 'rep' && $expected === 'reps');
    }
    if ($test['mesure'] === 'chrono') return $u === 's';
    if (!preg_match('/^s\/(km|1km|(\d+)m)$/', $u, $m)) return false;
    $par = ($m[1] === 'km' || $m[1] === '1km') ? 1000 : (int) $m[2];
    return $par === (int) round($test['allure']);
}

function on_unit_example(array $test): string
{
    if ($test['mesure'] === 'chrono') return 'unité attendue : "s"';
    if (!on_is_time($test) && $test['mesure'] !== 'vc') return 'unité attendue : "' . $test['unite'] . '"';
    $par = (int) round($test['allure']);
    return 'unité attendue : "' . ($par === 1000 ? 's/km' : "s/{$par}m") . '"';
}

/* ---------- Programmes ---------- */

// Variante d'un programme (« variantes » du fichier) : séances des types choisis, nom, structure,
// thèmes des semaines et tests propres. Exemple : « hyrox-run » = variante « run » de hyrox.json.
function on_apply_variant(array $data, array $v): array
{
    $types = array_values(array_filter((array) ($v['types'] ?? []), 'is_string'));
    foreach ((array) ($data['semaines'] ?? []) as $i => $w) {
        if (!is_array($w) || !is_array($w['seances'] ?? null)) continue;
        if ($types) $data['semaines'][$i]['seances'] = array_values(array_filter($w['seances'], fn($s) => is_array($s) && in_array($s['type'] ?? '', $types, true)));
        if (isset($v['themes'][$i]) && is_string($v['themes'][$i])) $data['semaines'][$i]['theme'] = $v['themes'][$i];
    }
    foreach (['nom', 'note', 'infos', 'structure', 'tests'] as $k) if (array_key_exists($k, $v)) $data[$k] = $v[$k];
    unset($data['variantes']);
    return $data;
}

// Charge et vérifie un programme (programmes/<id>.json, ou variante « <fichier>-<variante> »). Ne lève pas d'erreur :
// ['ok' => bool, 'erreurs' => [...], 'nom', 'note', 'infos', 'structure', 'tests' (tests affichés, vide = tous),
//  'semaines' => [...], 'seances' => [id => séance]]
function on_programme(string $id): array
{
    static $cache = [];
    if (isset($cache[$id])) return $cache[$id];
    $res = ['ok' => false, 'erreurs' => [], 'id' => $id, 'nom' => $id, 'note' => '', 'infos' => '', 'structure' => [], 'tests' => [], 'semaines' => [], 'seances' => []];
    if (!preg_match('/^[a-z0-9_-]+$/', $id)) {
        $res['erreurs'][] = 'Identifiant de programme invalide.';
        return $cache[$id] = $res;
    }
    try {
        $tests = on_tests();
        [$base, $var] = array_pad(explode('-', $id, 2), 2, null);
        if ($var !== null && !is_file(ON_DIR . "/programmes/$id.json")) {
            $data = on_read_json(ON_DIR . "/programmes/$base.json");
            if (!is_array($data['variantes'][$var] ?? null)) throw new RuntimeException("Programme « $id » : la variante « $var » n’existe pas dans $base.json.");
            $data = on_apply_variant($data, $data['variantes'][$var]);
        } else {
            $data = on_read_json(ON_DIR . "/programmes/$id.json");
        }
    } catch (Throwable $e) {
        $res['erreurs'][] = $e->getMessage();
        return $cache[$id] = $res;
    }
    $err = &$res['erreurs'];
    $res['nom'] = is_string($data['nom'] ?? null) ? $data['nom'] : $id;
    $res['note'] = is_string($data['note'] ?? null) ? $data['note'] : '';
    $res['infos'] = is_string($data['infos'] ?? null) ? $data['infos'] : '';
    foreach ((array) ($data['tests'] ?? []) as $tid) {
        if (is_string($tid) && isset($tests[$tid])) $res['tests'][] = $tid;
        else $res['erreurs'][] = '« tests » : « ' . (is_string($tid) ? $tid : '?') . ' » n’est pas un test de tests.json.';
    }
    $weeks = $data['semaines'] ?? null;
    $nbWeeks = is_array($weeks) ? count($weeks) : 0;
    // Structure du programme, par blocs de semaines :
    // [{ "titre": "Bloc 1", "semaines": [1, 4], "seances": [ … ], "tests": [ … ] }] (« tests » facultatif)
    $res['structure'] = [];
    foreach ((array) ($data['structure'] ?? []) as $k => $bl) {
        $range = $bl['semaines'] ?? null;
        $okRange = is_array($range) && count($range) === 2 && is_int($range[0]) && is_int($range[1])
            && $range[0] >= 1 && $range[0] <= $range[1] && $range[1] <= max(1, $nbWeeks);
        if (!is_array($bl) || !is_string($bl['titre'] ?? null) || !is_array($bl['seances'] ?? null) || !$okRange) {
            $err[] = '« structure », bloc ' . ((int) $k + 1) . ' : il faut un « titre », des « semaines » (première et dernière, par exemple [1, 4]) et une liste « seances ».';
            continue;
        }
        $res['structure'][] = [
            'titre' => $bl['titre'],
            'de' => $range[0],
            'a' => $range[1],
            'semaines' => $range[0] === $range[1] ? 'Semaine ' . $range[0] : 'Semaines ' . $range[0] . ' à ' . $range[1],
            'seances' => array_values(array_filter($bl['seances'], 'is_string')),
            'tests' => array_values(array_filter((array) ($bl['tests'] ?? []), 'is_string')),
        ];
    }
    if (!is_array($weeks) || !array_is_list($weeks) || !$weeks) {
        $err[] = '« semaines » doit être une liste [ … ] d’au moins une semaine.';
        return $cache[$id] = $res;
    }

    foreach ($weeks as $wi => $w) {
        $wn = $wi + 1;
        $where = "Semaine $wn";
        if (!is_array($w) || !is_array($w['seances'] ?? null) || !array_is_list($w['seances'])) {
            $err[] = "$where : « seances » doit être une liste [ … ].";
            continue;
        }
        $week = ['numero' => $wn, 'theme' => is_string($w['theme'] ?? null) ? $w['theme'] : '', 'seances' => []];
        foreach ($w['seances'] as $si => $s) {
            $sn = $si + 1;
            $at = "$where, séance $sn";
            if (!is_array($s)) { $err[] = "$at : la séance doit être un objet { … }."; continue; }
            $titre = is_string($s['titre'] ?? null) ? trim($s['titre']) : '';
            if ($titre === '') $err[] = "$at : il manque le « titre ».";
            else $at .= " (« $titre »)";
            $jour = $s['jour'] ?? null;
            if (!is_int($jour) || $jour < 1 || $jour > 7) { $err[] = "$at : « jour » doit être un nombre de 1 à 7 (1 = premier jour de la semaine)."; $jour = 1; }
            $sid = $s['id'] ?? "s$wn-$sn";
            if (!is_string($sid) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $sid)) { $err[] = "$at : « id » ne doit contenir que des lettres, chiffres, - et _."; $sid = "s$wn-$sn"; }
            if (isset($res['seances'][$sid])) $err[] = "$at : l’identifiant « $sid » est déjà utilisé par une autre séance.";
            // Pas de blocs : séance annoncée, contenu à venir
            $blocs = $s['blocs'] ?? [];
            if (!is_array($blocs) || !array_is_list($blocs)) { $err[] = "$at : « blocs » doit être une liste [ … ]."; $blocs = []; }

            $seance = [
                'id' => $sid,
                'semaine' => $wn,
                'numero' => $sn,
                'jour' => $jour,
                'type' => is_string($s['type'] ?? null) ? $s['type'] : '',
                'titre' => $titre,
                'description' => is_string($s['description'] ?? null) ? $s['description'] : '',
                'blocs' => [],
            ];
            foreach ($blocs as $bi => $b) {
                $ab = "$at, bloc " . ($bi + 1);
                if (!is_array($b)) { $err[] = "$ab : le bloc doit être un objet { … }."; continue; }
                $bloc = [
                    'titre' => is_string($b['titre'] ?? null) ? $b['titre'] : '',
                    'note' => is_string($b['note'] ?? null) ? $b['note'] : '',
                    'lignes' => [],
                ];
                // Un bloc simple ({ texte, intensite }) devient un bloc d'une seule ligne
                $lignes = $b['lignes'] ?? null;
                if ($lignes === null) $lignes = (isset($b['texte']) || isset($b['intensite'])) ? [['texte' => $b['texte'] ?? '', 'intensite' => $b['intensite'] ?? null]] : [];
                if (!is_array($lignes) || !array_is_list($lignes)) { $err[] = "$ab : « lignes » doit être une liste [ … ]."; $lignes = []; }
                if ($bloc['titre'] === '' && !$lignes) $err[] = "$ab : un bloc a besoin d’un « titre » ou d’au moins une ligne.";
                foreach ($lignes as $li => $l) {
                    $al = "$ab, ligne " . ($li + 1);
                    if (is_string($l)) $l = ['texte' => $l];
                    if (!is_array($l)) { $err[] = "$al : la ligne doit être un texte ou un objet { … }."; continue; }
                    $ligne = ['texte' => is_string($l['texte'] ?? null) ? $l['texte'] : '', 'intensites' => []];
                    if (isset($l['intensite'])) {
                        // Une intensité, ou une liste d'intensités affichées côte à côte
                        $ints = is_array($l['intensite']) && array_is_list($l['intensite']) ? $l['intensite'] : [$l['intensite']];
                        foreach ($ints as $raw) {
                            $int = on_check_intensity($raw, $tests, $al, $err);
                            if ($int) $ligne['intensites'][] = $int;
                        }
                    }
                    $bloc['lignes'][] = $ligne;
                }
                $seance['blocs'][] = $bloc;
            }
            $week['seances'][] = $seance;
            $res['seances'][$sid] = $seance;
        }
        // Dans l'ordre des jours de la semaine
        usort($week['seances'], fn($a, $b) => [$a['jour'], $a['numero']] <=> [$b['jour'], $b['numero']]);
        $res['semaines'][] = $week;
    }
    $res['ok'] = !$err;
    return $cache[$id] = $res;
}

function on_check_intensity($int, array $tests, string $at, array &$err): ?array
{
    if (!is_array($int)) { $err[] = "$at : « intensite » doit être un objet { \"ref\": …, \"type\": …, \"value\": … }."; return null; }
    $type = $int['type'] ?? null;

    $ref = $int['ref'] ?? null;
    if ($ref === null) {
        $err[] = "$at : il manque « ref », le test de référence (connus : " . implode(', ', array_keys($tests)) . '). Une charge femme / homme s’écrit dans le texte, par exemple @8/12,5.';
        return null;
    }
    if (!is_string($ref) || !isset($tests[$ref])) {
        $err[] = "$at : le test « " . (is_scalar($ref) ? $ref : '?') . ' » n’existe pas dans tests.json (connus : ' . implode(', ', array_keys($tests)) . ').';
        return null;
    }
    $test = $tests[$ref];

    if ($type === 'domaine') {
        if ($test['mesure'] !== 'vc' || !isset(ON_VC_DOMAINES[$int['value'] ?? ''])) {
            $err[] = "$at : « domaine » s’utilise avec la vitesse critique et vaut \"modere\", \"eleve\" ou \"severe\".";
            return null;
        }
        return ['ref' => $ref, 'type' => 'domaine', 'value' => $int['value'], 'distance' => null];
    }
    if (!in_array($type, ['offset', 'percent', 'speed', 'power'], true)) {
        $err[] = "$at : « type » doit valoir \"offset\", \"percent\", \"speed\", \"domaine\" ou \"power\".";
        return null;
    }
    if ($type === 'speed' && !on_is_pace($test)) { $err[] = "$at : « speed » s’applique à la course et à la vitesse critique."; return null; }
    if ($type === 'power' && !$test['watts']) { $err[] = "$at : « power » s’applique aux tests ergomètres (RowErg, SkiErg)."; return null; }
    $value = $int['value'] ?? null;
    $values = is_array($value) ? $value : [$value];
    if (!$values || count($values) > 2 || array_filter($values, fn($v) => !is_int($v) && !is_float($v))) {
        $err[] = "$at : « value » doit être un nombre, ou deux nombres [a, b] pour une fourchette.";
        return null;
    }
    if ($type !== 'offset' && array_filter($values, fn($v) => $v <= 0)) { $err[] = "$at : un pourcentage doit être supérieur à 0."; return null; }
    if (isset($int['unit']) && (!is_string($int['unit']) || !on_unit_matches($int['unit'], $test))) {
        $err[] = "$at : l’unité « " . (is_string($int['unit']) ? $int['unit'] : '?') . " » ne correspond pas au test « $ref » (" . on_unit_example($test) . ').';
        return null;
    }
    $distance = $int['distance'] ?? null;
    if ($distance !== null && (!on_is_pace($test) || !is_int($distance) || $distance <= 0)) {
        $err[] = "$at : « distance » (en mètres, nombre entier) ne s’applique qu’à une allure de course.";
        $distance = null;
    }
    return ['ref' => $ref, 'type' => $type, 'value' => is_array($value) ? array_values($value) : $value, 'distance' => $distance];
}

/* ---------- Calendrier ---------- */

function on_today(): string
{
    return date('Y-m-d');
}

function on_valid_date($v): string
{
    $v = is_string($v) ? trim($v) : '';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : '';
}

function on_date_add(string $ymd, int $days): string
{
    return (new DateTimeImmutable($ymd))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function on_days_between(string $from, string $to): int
{
    return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a');
}

// Date d'une séance : « jour » 1 = date de début du membre, 8 = semaine suivante…
function on_seance_date(string $debut, array $s): string
{
    return on_date_add($debut, ($s['semaine'] - 1) * 7 + $s['jour'] - 1);
}

// Semaine en cours : 0 avant le début, nombre de semaines + 1 une fois le programme fini
function on_week_now(string $debut, int $nbWeeks, ?string $today = null): int
{
    $d = on_days_between($debut, $today ?? on_today());
    if ($d < 0) return 0;
    return min($nbWeeks + 1, intdiv($d, 7) + 1);
}

const ON_JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const ON_MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const ON_MOIS_COURT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

// « lundi 9 novembre » (long), « lun. 9 nov. » (court), « 9 nov. 2026 » (annee)
function on_fmt_date(string $ymd, string $style = 'long'): string
{
    $d = new DateTimeImmutable($ymd);
    $w = (int) $d->format('w');
    $j = (int) $d->format('j');
    $m = (int) $d->format('n') - 1;
    $num = $j === 1 ? '1er' : (string) $j;
    return match ($style) {
        'court' => mb_substr(ON_JOURS[$w], 0, 3) . ". $j " . ON_MOIS_COURT[$m],
        'annee' => "$num " . ON_MOIS[$m] . ' ' . $d->format('Y'),
        default => ON_JOURS[$w] . " $num " . ON_MOIS[$m],
    };
}

/* ---------- Résultats de tests d'un membre ---------- */

// Pour chaque test : le dernier résultat validé à la date donnée. Un re-test remplace donc
// l'ancien résultat pour les séances ultérieures, pas pour celles d'avant.
// Si aucun résultat n'est antérieur à la date, le plus ancien sert de référence.
// La vitesse critique est calculée à partir des tests de course retenus (1 200 et 3 600 m) ;
// un test « affiner » (5 km) s'y ajoute pour les séances ultérieures, jamais avant.
// Deux chronos suffisent : si l'un des deux tests de base manque, le 5 km peut le remplacer.
// $rows : résultats du membre, triés par date de test puis par ordre de saisie.
function on_values_at(array $rows, string $date): array
{
    $tests = on_tests();
    $at = [];
    $first = [];
    foreach ($rows as $r) {
        if ($r['statut'] !== 'valide' || !isset($tests[$r['test']])) continue;
        $first[$r['test']] ??= $r;
        if ($r['date_test'] <= $date) $at[$r['test']] = $r;
    }
    $values = $at + $first;
    foreach ($tests as $id => $t) {
        if ($t['mesure'] !== 'vc') continue;
        $src = [];
        foreach ($t['depuis'] as $c) if (isset($values[$c])) $src[] = $values[$c];
        foreach ($t['affiner'] as $c) if (isset($at[$c])) $src[] = $at[$c];
        if (count($src) < 2) continue;
        usort($src, fn($x, $y) => $tests[$x['test']]['distance'] <=> $tests[$y['test']]['distance']);
        $vc = on_critical_speed_fit(array_map(fn($r) => [$tests[$r['test']]['distance'], (float) $r['valeur']], $src));
        if (!$vc) continue;
        $values[$id] = [
            'test' => $id,
            'valeur' => $vc['vc'],
            'dprime' => $vc['dprime'],
            'date_test' => max(array_column($src, 'date_test')),
            'statut' => 'valide',
            'sources' => $src,
        ];
    }
    return $values;
}

// « 1 200 m », « 5 km »
function on_fmt_distance(float $m): string
{
    return $m >= 1000 && fmod($m, 1000) == 0 ? on_fmt_num($m / 1000, 0) . "\u{00A0}km" : on_fmt_num($m, 0) . "\u{00A0}m";
}

// « 1 200 m et 3 600 m » ; « 1 200 m, 3 600 m et 5 km »
function on_fmt_list(array $items): string
{
    $last = array_pop($items);
    return $items ? implode(', ', $items) . ' et ' . $last : (string) $last;
}

// Tests utilisés par une séance
function on_seance_refs(array $s): array
{
    $refs = [];
    foreach ($s['blocs'] as $b) {
        foreach ($b['lignes'] as $l) {
            foreach ($l['intensites'] as $int) {
                if ($int['ref'] !== null) $refs[$int['ref']] = true;
            }
        }
    }
    return array_keys($refs);
}
