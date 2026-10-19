<?php
/**
 * lib.php — socle du module Equipe.
 *
 * Deux modes de composition :
 *
 *  - « classe » : classement par équipes natif d'Ianseo (division/classe).
 *    Ianseo forme une équipe par club et par catégorie (division + classe)
 *    avec les 3 meilleurs scores des archers cochés « Équipe »
 *    (Entries.EnTeamClEvent). Le module ne fait que cocher ou décocher cette
 *    case, puis lance le recalcul natif MakeTeams().
 *
 *  - « libre » : équipes mêlant des classes d'une même arme qui tirent dans
 *    les mêmes conditions (mêmes distances, même blason). Ianseo ne sait pas
 *    les classer : le module les garde dans ses paramètres (ModulesParameters
 *    'Libres') et calcule leur classement (3 meilleurs scores, 10, 9).
 *    Rien n'est écrit dans les tables d'Ianseo.
 */

const EQUIPE_MODULE = 'Equipe';
const EQUIPE_LOG_MAX = 500;

/**
 * Types de blason (table Targets d'Ianseo) considérés comme les mêmes
 * conditions de tir, à taille égale : le trispot (zones 6 à 10) compte comme
 * le blason unique de la même famille. 2 TrgIndSmall → 1 TrgIndComplete
 * (classique, arc nu) ; 4 TrgCOIndSmall → 3 TrgCOIndComplete (poulies).
 */
const EQUIPE_FACE_SAME = [2 => 1, 4 => 3];

/** Libellés des états de la compétition. */
const EQUIPE_STATES = [
    'BEFORE' => 'Avant le tir',
    'STARTED' => 'Tir commencé',
    'LOCKED' => 'Compétition verrouillée',
];

// ── Utilitaires ──────────────────────────────────────────────────────────────

function Equipe_h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function Equipe_tour_id()
{
    return intval($_SESSION['TourId'] ?? 0);
}

/** Identité de l'utilisateur pour le journal. */
function Equipe_user()
{
    $u = $_SESSION['AUTH_User'] ?? ($_SESSION['AUTH_Name'] ?? '');
    if (is_array($u) || is_object($u)) $u = '';
    return trim((string) $u) !== '' ? (string) $u : 'poste ' . ($_SERVER['REMOTE_ADDR'] ?? '?');
}

/** Réglages reçus du navigateur : archers par équipe, bornés. */
function Equipe_settings($s)
{
    $s = is_array($s) ? $s : [];
    $min = max(3, min(8, intval($s['size_min'] ?? 3)));
    $max = max($min, min(8, intval($s['size_max'] ?? 4)));
    return ['size_min' => $min, 'size_max' => $max];
}

// ── Lecture ──────────────────────────────────────────────────────────────────

function Equipe_tournament()
{
    static $t = null;
    if ($t !== null) return $t;
    $rs = safe_r_SQL("SELECT ToId, ToCode, ToName, ToType, ToNumDist FROM Tournament WHERE ToId=" . Equipe_tour_id());
    return $t = (safe_fetch($rs) ?: null);
}

/** Divisions d'archers (arme), dans l'ordre d'Ianseo : id => nom. */
function Equipe_divisions()
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $rs = safe_r_SQL("SELECT DivId, DivDescription FROM Divisions
        WHERE DivTournament=" . Equipe_tour_id() . " AND DivAthlete='1' ORDER BY DivViewOrder, DivId");
    while ($r = safe_fetch($rs)) $out[$r->DivId] = $r->DivDescription;
    return $out;
}

/** Classes d'archers : id => [nom, sexe (0 H, 1 F, -1 les deux)]. */
function Equipe_classes()
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $rs = safe_r_SQL("SELECT ClId, ClDescription, ClSex FROM Classes
        WHERE ClTournament=" . Equipe_tour_id() . " AND ClAthlete='1' ORDER BY ClViewOrder, ClId");
    while ($r = safe_fetch($rs)) $out[$r->ClId] = ['name' => $r->ClDescription, 'sex' => intval($r->ClSex)];
    return $out;
}

/** Clubs : CoId => [code, nom]. */
function Equipe_countries()
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $rs = safe_r_SQL("SELECT CoId, CoCode, CoName FROM Countries WHERE CoTournament=" . Equipe_tour_id());
    while ($r = safe_fetch($rs)) $out[intval($r->CoId)] = ['code' => $r->CoCode, 'name' => $r->CoName];
    return $out;
}

/**
 * Inscrits de la compétition avec leur score de qualification.
 *
 * Un archer inscrit plusieurs fois (même licence, départs différents) ne compte
 * pour les équipes que sur son PREMIER départ, celui où il tire son premier
 * score : date de début du départ dans Ianseo quand ses départs en ont une,
 * sinon numéro du départ (à départ égal : la première inscription ; sans
 * départ : après les autres). Ses autres inscriptions ont 'dup' = EnId de
 * l'inscription retenue et ne sont jamais comptées ; leurs cases équipe
 * (team, teamf, teammix) doivent être à 0 (voir Equipe_dup_control()).
 *
 * @return array EnId => [id, code, name, first, div, cls, club, team, teamf, teammix, sex, face, session, sesdt, dup, counted,
 *                        score, gold, xnine, hits]
 *         club    : club retenu par Ianseo pour les équipes (EnCountry2 sinon EnCountry)
 *         team    : case « Équipe » (EnTeamClEvent) ; teamf, teammix : épreuves par équipes et mixtes (lecture seule)
 *         counted : archer qu'Ianseo peut classer (athlète, statut ≤ 1, IRM classé, club),
 *                   et inscription retenue s'il est inscrit plusieurs fois
 */
function Equipe_entries()
{
    $out = [];
    $rs = safe_r_SQL("SELECT EnId, EnCode, EnName, EnFirstName, EnDivision, EnClass,
            IF(EnCountry2=0, EnCountry, EnCountry2) AS Club, EnTeamClEvent, EnTeamFEvent, EnTeamMixEvent, EnSex, EnTargetFace, EnStatus, EnAthlete,
            IFNULL(QuSession,0) AS QuSession, IF(IFNULL(SesDtStart,'0000-00-00 00:00:00')<'1971', '', SesDtStart) AS SesDt,
            IFNULL(QuScore,0) AS QuScore, IFNULL(QuGold,0) AS QuGold, IFNULL(QuXnine,0) AS QuXnine,
            IFNULL(QuHits,0) AS QuHits, IFNULL(IrmShowRank,1) AS IrmShowRank
        FROM Entries
        LEFT JOIN Qualifications ON QuId=EnId
        LEFT JOIN IrmTypes ON IrmId=QuIrmType
        LEFT JOIN Session ON SesTournament=EnTournament AND SesType='Q' AND SesOrder=QuSession
        WHERE EnTournament=" . Equipe_tour_id() . "
        ORDER BY EnId");
    $byCode = [];
    while ($r = safe_fetch($rs)) {
        $id = intval($r->EnId);
        $out[$id] = [
            'id' => $id, 'code' => $r->EnCode, 'name' => $r->EnName, 'first' => $r->EnFirstName,
            'div' => $r->EnDivision, 'cls' => $r->EnClass,
            'club' => intval($r->Club), 'team' => intval($r->EnTeamClEvent),
            'teamf' => intval($r->EnTeamFEvent), 'teammix' => intval($r->EnTeamMixEvent), 'sex' => intval($r->EnSex),
            'face' => intval($r->EnTargetFace), 'session' => intval($r->QuSession), 'sesdt' => (string) $r->SesDt, 'dup' => 0,
            'counted' => intval($r->EnAthlete) === 1 && intval($r->EnStatus) <= 1 && intval($r->Club) !== 0 && intval($r->IrmShowRank) === 1,
            'score' => intval($r->QuScore), 'gold' => intval($r->QuGold), 'xnine' => intval($r->QuXnine), 'hits' => intval($r->QuHits),
        ];
        $code = strtoupper(trim((string) $r->EnCode));
        if ($code !== '' && intval($r->EnAthlete) === 1) $byCode[$code][] = $id;
    }
    // Inscriptions multiples : seule la première (départ tiré le plus tôt) compte.
    foreach ($byCode as $ids) {
        if (count($ids) < 2) continue;
        // Dates de départ comparées seulement si tous ses départs en ont une.
        $dated = !array_filter($ids, function ($id) use ($out) { return $out[$id]['sesdt'] === ''; });
        usort($ids, function ($a, $b) use ($out, $dated) {
            $sa = $out[$a]['session'] ?: PHP_INT_MAX;
            $sb = $out[$b]['session'] ?: PHP_INT_MAX;
            $da = $dated ? $out[$a]['sesdt'] : '';
            $db = $dated ? $out[$b]['sesdt'] : '';
            return [$da, $sa, $a] <=> [$db, $sb, $b];
        });
        $first = array_shift($ids);
        foreach ($ids as $id) {
            $out[$id]['dup'] = $first;
            $out[$id]['counted'] = false;
        }
    }
    return $out;
}

/** Catégorie d'équipe d'Ianseo : division + classe (ex. « CLS1H »). */
function Equipe_cat($e)
{
    return $e['div'] . $e['cls'];
}

/** Nom affiché d'un archer. */
function Equipe_person($e)
{
    return trim(mb_strtoupper($e['name'], 'UTF-8') . ' ' . $e['first']);
}

/**
 * Contrôle des archers inscrits plusieurs fois, sur toute la compétition.
 *
 * Règle : pour les équipes, seul compte le premier score de l'archer (son
 * premier départ). Ses autres inscriptions doivent donc avoir toutes leurs
 * cases équipe à 0 : « Équipe » (EnTeamClEvent, classement division/classe),
 * épreuves par équipes (EnTeamFEvent) et équipes mixtes (EnTeamMixEvent).
 * Lecture seule : le module ne corrige que la case « Équipe », quand la
 * catégorie passe dans l'assistant en mode « classe et division ».
 *
 * @return array [archers => [[name, code, rows => [[id, session, cat, team, teamf, teammix, kept, bad]]]], bad (nombre d'inscriptions en défaut)]
 */
function Equipe_dup_control(array $entries)
{
    $byFirst = [];
    foreach ($entries as $id => $e) if ($e['dup']) $byFirst[$e['dup']][] = $id;
    $archers = [];
    $bad = 0;
    foreach ($byFirst as $first => $others) {
        $rows = [];
        foreach (array_merge([$first], $others) as $id) {
            $e = $entries[$id];
            $kept = $id === $first;
            $isBad = !$kept && ($e['team'] || $e['teamf'] || $e['teammix']);
            if ($isBad) $bad++;
            $rows[] = ['id' => $id, 'session' => $e['session'], 'cat' => Equipe_cat($e), 'team' => $e['team'], 'teamf' => $e['teamf'],
                'teammix' => $e['teammix'], 'kept' => $kept, 'bad' => $isBad];
        }
        $archers[] = ['name' => Equipe_person($entries[$first]), 'code' => $entries[$first]['code'], 'rows' => $rows];
    }
    usort($archers, function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
    return ['archers' => $archers, 'bad' => $bad];
}

/** Tri d'Ianseo : score, 10, 9, puis EnId. */
function Equipe_sort_by_score(array &$ids, array $entries)
{
    usort($ids, function ($a, $b) use ($entries) {
        $x = $entries[$a];
        $y = $entries[$b];
        return [$y['score'], $y['gold'], $y['xnine'], $x['id']] <=> [$x['score'], $x['gold'], $x['xnine'], $y['id']];
    });
}

/** Motif LIKE de MySQL (% et _) en expression régulière, insensible à la casse. */
function Equipe_like_re($pattern)
{
    $re = '';
    foreach (str_split((string) $pattern) as $ch) $re .= $ch === '%' ? '.*' : ($ch === '_' ? '.' : preg_quote($ch, '/'));
    return '/^' . $re . '$/i';
}

// ── Conditions de tir : distances et blason ──────────────────────────────────

/**
 * Distances d'un couple division + classe (TournamentDistances, motif LIKE le
 * plus précis) : une valeur par distance de la compétition.
 *
 * @return string[]|null ex. ['70m', '70m'] ; null si aucune ligne ne correspond
 */
function Equipe_distances($div, $cls)
{
    static $rows = null, $cache = [];
    if ($rows === null) {
        $rows = [];
        $t = Equipe_tournament();
        $n = max(1, min(8, intval($t->ToNumDist ?? 1)));
        $rs = safe_r_SQL("SELECT * FROM TournamentDistances
            WHERE TdTournament=" . Equipe_tour_id() . " AND TdType=" . intval($t->ToType ?? 0));
        while ($r = safe_fetch($rs)) {
            $d = [];
            for ($i = 1; $i <= $n; $i++) {
                $m = intval($r->{"TdDist$i"} ?? 0);
                $d[] = $m ? $m . 'm' : preg_replace('/-\d+$/', '', (string) ($r->{"Td$i"} ?? ''));
            }
            $rows[] = ['re' => Equipe_like_re($r->TdClasses), 'len' => strlen($r->TdClasses), 'd' => $d];
        }
        usort($rows, function ($a, $b) { return $b['len'] <=> $a['len']; });
    }
    $key = $div . $cls;
    if (array_key_exists($key, $cache)) return $cache[$key];
    foreach ($rows as $r) if (preg_match($r['re'], $key)) return $cache[$key] = $r['d'];
    return $cache[$key] = null;
}

/** Libellé court des distances : « 70m » ou « 50m / 30m ». */
function Equipe_distance_label($d)
{
    return $d ? implode(' / ', array_unique($d)) : '';
}

/** Blasons de la compétition : TfId => [name, re, def (types et tailles par distance), default]. */
function Equipe_faces()
{
    static $faces = null;
    if ($faces !== null) return $faces;
    $faces = [];
    $n = max(1, min(8, intval(Equipe_tournament()->ToNumDist ?? 1)));
    $rs = safe_r_SQL("SELECT * FROM TargetFaces WHERE TfTournament=" . Equipe_tour_id() . " ORDER BY TfId");
    while ($r = safe_fetch($rs)) {
        $def = [];
        for ($i = 1; $i <= $n; $i++) {
            $type = intval($r->{"TfT$i"});
            $def[] = (EQUIPE_FACE_SAME[$type] ?? $type) . ':' . intval($r->{"TfW$i"});
        }
        $faces[intval($r->TfId)] = [
            'name' => $r->TfName,
            're' => $r->TfRegExp !== '' ? '/' . str_replace('/', '\/', $r->TfRegExp) . '/i' : Equipe_like_re($r->TfClasses),
            'def' => implode(',', $def),
            'default' => (string) $r->TfDefault === '1',
        ];
    }
    return $faces;
}

/**
 * Blason d'un archer : celui que lui a donné Ianseo (EnTargetFace), sinon le
 * blason par défaut de sa catégorie. null si inconnu.
 */
function Equipe_face($e)
{
    $faces = Equipe_faces();
    if (isset($faces[$e['face']])) return $faces[$e['face']];
    static $cache = [];
    $key = Equipe_cat($e);
    if (array_key_exists($key, $cache)) return $cache[$key];
    foreach ($faces as $f) if ($f['default'] && @preg_match($f['re'], $key)) return $cache[$key] = $f;
    return $cache[$key] = null;
}

/**
 * Conditions de tir d'un archer (mode libre) : même arme, mêmes distances,
 * même blason (trispot et blason unique de même taille confondus, voir
 * EQUIPE_FACE_SAME). Deux archers ne peuvent être dans la même équipe libre
 * que si leurs clés sont égales.
 *
 * @return array [key, base (arme — distances), face (nom du blason), known]
 *         known=false si distance ou blason inconnu
 */
function Equipe_conditions($e)
{
    $d = Equipe_distances($e['div'], $e['cls']);
    $f = Equipe_face($e);
    $divs = Equipe_divisions();
    return [
        'key' => $e['div'] . '|' . ($d ? implode(',', $d) : '?' . $e['cls']) . '|' . ($f ? $f['def'] : '?' . $e['cls']),
        'base' => ($divs[$e['div']] ?? $e['div']) . ' — ' . ($d ? Equipe_distance_label($d) : 'distance inconnue'),
        'face' => $f ? $f['name'] : 'blason inconnu',
        'known' => $d !== null && $f !== null,
    ];
}

/** Libellé d'un groupe de conditions : « Arc Classique — 18m — Blason Unique 40cm / Trispot … ». */
function Equipe_conditions_label($base, array $faces)
{
    return $base . ' — ' . implode(' / ', array_keys($faces));
}

// ── État ─────────────────────────────────────────────────────────────────────

/** État de la compétition : départs avec des scores, verrou des participants. */
function Equipe_state()
{
    static $s = null;
    if ($s !== null) return $s;
    $sessions = [];
    $rs = safe_r_SQL("SELECT QuSession, COUNT(*) AS n FROM Qualifications
        INNER JOIN Entries ON EnId=QuId AND EnTournament=" . Equipe_tour_id() . "
        WHERE QuHits>0 OR QuScore>0 GROUP BY QuSession ORDER BY QuSession");
    while ($r = safe_fetch($rs)) $sessions[intval($r->QuSession)] = intval($r->n);
    $locked = (bool) IsBlocked(BIT_BLOCK_PARTICIPANT);
    return $s = [
        'code' => $locked ? 'LOCKED' : ($sessions ? 'STARTED' : 'BEFORE'),
        'started' => !empty($sessions),
        'sessions' => $sessions,
        'locked' => $locked,
    ];
}

// ── Scores et classements ────────────────────────────────────────────────────

/**
 * Score d'équipe comme MakeTeams() : les 3 meilleurs scores (score, 10, 9)
 * des membres ayant un score. null si moins de 3 archers ont tiré.
 */
function Equipe_team_score(array $members, array $entries)
{
    $ids = array_values(array_filter($members, function ($id) use ($entries) { return $entries[$id]['score'] > 0; }));
    if (count($ids) < 3) return null;
    Equipe_sort_by_score($ids, $entries);
    $best = array_slice($ids, 0, 3);
    $sum = function ($k) use ($best, $entries) { return array_sum(array_map(function ($id) use ($entries, $k) { return $entries[$id][$k]; }, $best)); };
    return ['score' => $sum('score'), 'gold' => $sum('gold'), 'xnine' => $sum('xnine'), 'members' => $best];
}

/**
 * Classe des équipes : score, 10, 9 ; ex aequo = même rang. Les équipes sans
 * score (moins de 3 archers ayant tiré) restent en fin, sans rang.
 *
 * @param array $rows chacune avec 'score' (null possible), 'gold', 'xnine', 'name'
 */
function Equipe_rank(array $rows)
{
    usort($rows, function ($a, $b) {
        return [$b['score'] ?? -1, $b['gold'], $b['xnine'], $a['name']] <=> [$a['score'] ?? -1, $a['gold'], $a['xnine'], $b['name']];
    });
    $prev = null;
    foreach ($rows as $i => &$r) {
        if ($r['score'] === null) { $r['rank'] = null; continue; }
        $key = [$r['score'], $r['gold'], $r['xnine']];
        $r['rank'] = ($prev !== null && $prev[0] === $key) ? $prev[1] : $i + 1;
        $prev = [$key, $r['rank']];
    }
    unset($r);
    return $rows;
}

/**
 * Classement natif actuel (Teams, TeFinEvent=0), toutes catégories ou celles demandées.
 *
 * @return array cat => club => [score, rank, members => [EnId…]]
 */
function Equipe_native_teams(array $cats = null)
{
    $out = [];
    $tour = Equipe_tour_id();
    $filter = $cats === null ? '' : ($cats ? ' AND TeEvent IN (' . implode(',', array_map('StrSafe_DB', $cats)) . ')' : ' AND 0');
    $rs = safe_r_SQL("SELECT TeEvent, TeCoId, TeScore, TeRank FROM Teams WHERE TeTournament=$tour AND TeFinEvent=0$filter");
    while ($r = safe_fetch($rs)) $out[$r->TeEvent][intval($r->TeCoId)] = ['score' => intval($r->TeScore), 'rank' => intval($r->TeRank), 'members' => []];
    $filter = str_replace('TeEvent', 'TcEvent', $filter);
    $rs = safe_r_SQL("SELECT TcEvent, TcCoId, TcId FROM TeamComponent WHERE TcTournament=$tour AND TcFinEvent=0$filter ORDER BY TcOrder");
    while ($r = safe_fetch($rs)) {
        if (isset($out[$r->TcEvent][intval($r->TcCoId)])) $out[$r->TcEvent][intval($r->TcCoId)]['members'][] = intval($r->TcId);
    }
    return $out;
}

/** Effectif d'une équipe : alertes (non bloquantes). */
function Equipe_check_size(array $members, array $settings)
{
    $n = count($members);
    if ($n < 3) return ["$n archer(s) : il faut au moins 3 archers ayant tiré pour classer l'équipe"];
    if ($n < $settings['size_min']) return ["Effectif $n, minimum {$settings['size_min']}"];
    if ($n > $settings['size_max']) return ["Effectif $n, maximum {$settings['size_max']} (les 3 meilleurs scores comptent)"];
    return [];
}

// ── Périmètre choisi (critères) ──────────────────────────────────────────────

/**
 * Archers retenus par les critères, rangés par section puis par club.
 *
 *   mode classe : une section par catégorie (division + classe)
 *   mode libre  : une section par conditions de tir (arme, distances, blason)
 *
 * @return array clé => [key, label, sub, classes[], known, clubs => club => [EnId…]]
 */
function Equipe_sections($mode, array $divs, array $cls, array $entries)
{
    $divisions = Equipe_divisions();
    $classes = Equipe_classes();
    $divs = array_flip(array_intersect(array_keys($divisions), $divs));
    $cls = array_flip(array_intersect(array_keys($classes), $cls));
    $out = [];
    foreach ($entries as $id => $e) {
        if (!$e['counted'] || !isset($divs[$e['div']], $cls[$e['cls']])) continue;
        if ($mode === 'libre') {
            $c = Equipe_conditions($e);
            $key = $c['key'];
            if (!isset($out[$key])) $out[$key] = ['key' => $key, 'div' => $e['div'], 'base' => $c['base'], 'faces' => [], 'sub' => '', 'classes' => [], 'known' => $c['known'], 'clubs' => []];
            $out[$key]['faces'][$c['face']] = true;
            $out[$key]['known'] = $out[$key]['known'] && $c['known'];
        } else {
            $key = Equipe_cat($e);
            if (!isset($out[$key])) {
                $out[$key] = ['key' => $key, 'div' => $e['div'], 'label' => $divisions[$e['div']] . ' — ' . $classes[$e['cls']]['name'],
                    'sub' => Equipe_distance_label(Equipe_distances($e['div'], $e['cls'])), 'classes' => [$e['cls']], 'known' => true, 'clubs' => []];
            }
        }
        $out[$key]['classes'][$e['cls']] = $e['cls'];
        $out[$key]['clubs'][$e['club']][] = $id;
    }
    // Ordre : divisions puis classes dans l'ordre d'Ianseo.
    $order = array_flip(array_keys($classes));
    $divOrder = array_flip(array_keys($divisions));
    foreach ($out as &$s) {
        if (isset($s['faces'])) $s['label'] = Equipe_conditions_label($s['base'], $s['faces']);
        $s['classes'] = array_values($s['classes']);
        usort($s['classes'], function ($a, $b) use ($order) { return ($order[$a] ?? 0) <=> ($order[$b] ?? 0); });
    }
    unset($s);
    uasort($out, function ($a, $b) use ($order, $divOrder) {
        return [$divOrder[$a['div']] ?? 0, $order[$a['classes'][0]] ?? 0, $a['key']] <=> [$divOrder[$b['div']] ?? 0, $order[$b['classes'][0]] ?? 0, $b['key']];
    });
    return $out;
}

/** Section d'un archer selon le mode. */
function Equipe_section_key($mode, $e)
{
    return $mode === 'libre' ? Equipe_conditions($e)['key'] : Equipe_cat($e);
}

// ── Équipes libres (paramètres du module) ────────────────────────────────────

/** Équipes libres enregistrées : [[club, members => [EnId…]], …]. */
/**
 * Identité stable d'un inscrit : licence, arme, classe, nom, prénom. Sert à
 * retrouver les archers des équipes libres quand leur EnId a changé : à
 * l'import d'une compétition, Ianseo renumérote les inscrits dans ses tables,
 * mais pas dans les paramètres de module.
 */
function Equipe_member_key($e)
{
    return mb_strtoupper(trim($e['code']) . '|' . $e['div'] . '|' . $e['cls'] . '|' . trim($e['name']) . '|' . trim($e['first']), 'UTF-8');
}

/**
 * Équipes libres enregistrées : [[club, members => [EnId…]], …], archers
 * retrouvés par leur EnId, ou par leur identité si l'EnId ne correspond plus.
 * Le club est relu sur les archers (les CoId changent aussi à l'import).
 */
function Equipe_free_read()
{
    $v = getModuleParameter(EQUIPE_MODULE, 'Libres', [], 0, true);
    if (!is_array($v)) return [];
    $entries = Equipe_entries();
    $byKey = [];
    foreach ($entries as $id => $e) if ($e['code'] !== '' && !$e['dup']) $byKey[Equipe_member_key($e)] = $id;
    $out = [];
    foreach ($v as $t) {
        $members = [];
        foreach ((array) ($t['members'] ?? []) as $m) {
            if (!is_array($m)) { $members[] = intval($m); continue; } // ancien format : EnId seul
            $id = intval($m['id'] ?? 0);
            if (isset($entries[$id]) && Equipe_member_key($entries[$id]) === ($m['key'] ?? '')) $members[] = $id;
            elseif (isset($byKey[$m['key'] ?? ''])) $members[] = $byKey[$m['key']];
        }
        if (!$members) continue;
        $club = isset($entries[$members[0]]) ? $entries[$members[0]]['club'] : intval($t['club'] ?? 0);
        $out[] = ['club' => $club, 'members' => $members];
    }
    return $out;
}

/** Enregistre les équipes libres : chaque archer avec son EnId et son identité stable. */
function Equipe_free_write(array $teams)
{
    $entries = Equipe_entries();
    $store = [];
    foreach ($teams as $t) {
        $members = [];
        foreach ((array) $t['members'] as $id) {
            $id = intval($id);
            if (isset($entries[$id])) $members[] = ['id' => $id, 'key' => Equipe_member_key($entries[$id])];
        }
        if ($members) $store[] = ['club' => intval($t['club']), 'members' => $members];
    }
    setModuleParameter(EQUIPE_MODULE, 'Libres', $store);
}

/**
 * Classement des équipes libres, par conditions de tir et par sexe
 * (Hommes, Femmes, Mixte).
 *
 * @return array [[label, classes, teams => [[rank, name, club, members, retained, score, gold, xnine, alerts]]], …]
 */
function Equipe_free_ranking(array $teams, array $entries, array $settings = ['size_min' => 3, 'size_max' => 4])
{
    $countries = Equipe_countries();
    $groups = [];
    $perClub = [];
    foreach ($teams as $t) {
        $members = array_values(array_filter(array_map('intval', $t['members']), function ($id) use ($entries) { return isset($entries[$id]); }));
        if (!$members) continue;
        // Inscription d'un archer inscrit plusieurs fois qui n'est pas son premier départ : ne compte pas.
        $ignored = array_values(array_filter($members, function ($id) use ($entries) { return $entries[$id]['dup'] > 0; }));
        $counted = array_values(array_diff($members, $ignored));
        $c = Equipe_conditions($entries[$members[0]]);
        $sexes = array_unique(array_map(function ($id) use ($entries) { return $entries[$id]['sex']; }, $members));
        $sex = count($sexes) > 1 ? 'Mixte' : (reset($sexes) === 1 ? 'Femmes' : 'Hommes');
        $gk = $c['key'] . '|' . $sex;
        if (!isset($groups[$gk])) $groups[$gk] = ['base' => $c['base'], 'sex' => $sex, 'faces' => [], 'classes' => [], 'teams' => []];
        foreach ($members as $id) {
            $groups[$gk]['classes'][$entries[$id]['cls']] = true;
            $groups[$gk]['faces'][Equipe_conditions($entries[$id])['face']] = true;
        }
        $club = intval($t['club']);
        $perClub[$gk][$club] = ($perClub[$gk][$club] ?? 0) + 1;
        $sc = Equipe_team_score($counted, $entries);
        $alerts = Equipe_check_size($counted, $settings);
        foreach ($ignored as $id) $alerts[] = Equipe_person($entries[$id]) . ' ignoré : inscrit plusieurs fois, seul son premier départ compte';
        $groups[$gk]['teams'][] = [
            'club' => $club, 'n' => $perClub[$gk][$club], 'clubName' => $countries[$club]['name'] ?? '?', 'clubCode' => $countries[$club]['code'] ?? '',
            'members' => $members, 'raw' => array_map('intval', (array) $t['members']), 'retained' => $sc['members'] ?? [],
            'score' => $sc['score'] ?? null, 'gold' => $sc['gold'] ?? 0, 'xnine' => $sc['xnine'] ?? 0,
            'alerts' => $alerts,
        ];
    }
    foreach ($groups as $gk => &$g) {
        // « Club 2 » seulement si le club a plusieurs équipes dans ce classement.
        foreach ($g['teams'] as &$t) $t['name'] = $t['clubName'] . ($perClub[$gk][$t['club']] > 1 ? ' ' . $t['n'] : '');
        unset($t);
        $g['teams'] = Equipe_rank($g['teams']);
        $g['classes'] = array_keys($g['classes']);
        $g['label'] = Equipe_conditions_label($g['base'], $g['faces']) . ' — ' . $g['sex'];
    }
    unset($g);
    ksort($groups);
    return array_values($groups);
}

// ── Plan d'une composition ───────────────────────────────────────────────────

/**
 * Calcule ce que l'application d'une composition changerait, sans rien écrire.
 *
 * @param string $mode    'classe' ou 'libre'
 * @param array  $teamsIn [[sec, club, members => [EnId…]], …]
 * @return array [ok, mode, alerts, sections, changes, replaced, token, state]
 */
function Equipe_plan($mode, array $divs, array $cls, $teamsIn, $settingsIn)
{
    $mode = $mode === 'libre' ? 'libre' : 'classe';
    $entries = Equipe_entries();
    $sections = Equipe_sections($mode, $divs, $cls, $entries);
    $settings = Equipe_settings($settingsIn);
    $countries = Equipe_countries();
    $state = Equipe_state();
    $alerts = [];
    $err = function ($m) use (&$alerts) { $alerts[] = ['error', $m]; };
    if ($mode === 'classe' && $state['locked']) $err('Liste des participants verrouillée : lecture seule.');
    if (!$sections) $err('Aucun archer dans les catégories choisies.');

    // Validation des équipes reçues
    $teams = [];
    $placed = [];
    foreach ((array) $teamsIn as $t) {
        $club = intval($t['club'] ?? 0);
        $members = [];
        $sec = null;
        foreach ((array) ($t['members'] ?? []) as $id) {
            $id = intval($id);
            $e = $entries[$id] ?? null;
            if (!$e) { $err("Archer inconnu (n° $id)."); continue; }
            $k = Equipe_section_key($mode, $e);
            if ($e['dup']) {
                $first = $entries[$e['dup']];
                $err(Equipe_person($e) . ' (' . Equipe_cat($e) . ', départ ' . ($e['session'] ?: '?') . ') est inscrit plusieurs fois : '
                    . 'seul son premier départ compte (départ ' . ($first['session'] ?: '?') . ', ' . Equipe_cat($first) . ').');
                continue;
            }
            if (!$e['counted'] || !isset($sections[$k]) || !isset($sections[$k]['clubs'][$club]) || $e['club'] !== $club) {
                $err(Equipe_person($e) . ' ne fait pas partie des archers listés pour ce club.');
                continue;
            }
            if ($sec !== null && $k !== $sec) {
                $err(Equipe_person($e) . ' ne tire pas dans les mêmes conditions (arme, distance, blason) que ses équipiers.');
                continue;
            }
            if (isset($placed[$id])) { $err(Equipe_person($e) . ' est placé deux fois.'); continue; }
            $sec = $k;
            $placed[$id] = true;
            $members[] = $id;
        }
        if (!$members) continue;
        if ($mode === 'classe' && isset($teams[$sec . '#' . $club])) {
            $err('Deux équipes pour ' . ($countries[$club]['name'] ?? '?') . " en $sec : Ianseo n'en forme qu'une par club et par catégorie.");
            continue;
        }
        $teams[$sec . '#' . $club . '#' . count($teams)] = ['sec' => $sec, 'club' => $club, 'members' => $members];
        if ($mode === 'classe') $teams[$sec . '#' . $club] = true;
    }
    $teams = array_values(array_filter($teams, 'is_array'));
    foreach ($sections as $s) {
        if ($mode === 'libre' && !$s['known']) $alerts[] = ['warn', "Conditions de tir incomplètes pour {$s['label']} : vérifiez distances et blasons dans Ianseo."];
    }

    $changes = [];
    $replaced = [];
    $secOut = [];
    if ($mode === 'classe') {
        // Nouvelles valeurs de la case « Équipe » des archers listés
        foreach ($sections as $s) {
            foreach ($s['clubs'] as $club => $ids) {
                foreach ($ids as $id) {
                    $e = $entries[$id];
                    $new = isset($placed[$id]) ? 1 : 0;
                    if ($new === $e['team']) continue;
                    $changes[] = ['id' => $id, 'name' => Equipe_person($e), 'club' => $countries[$club]['name'] ?? '', 'cat' => Equipe_cat($e), 'old' => $e['team'], 'new' => $new];
                }
            }
        }
        // Inscriptions multiples des catégories choisies : seule la première compte, les autres sont décochées
        // (sinon MakeTeams() les compterait).
        $dupNames = [];
        foreach ($entries as $id => $e) {
            if (!$e['dup'] || $e['team'] !== 1 || !in_array($e['div'], $divs, true) || !in_array($e['cls'], $cls, true)) continue;
            $first = $entries[$e['dup']];
            $changes[] = ['id' => $id, 'name' => Equipe_person($e), 'club' => $countries[$e['club']]['name'] ?? '', 'cat' => Equipe_cat($e),
                'old' => 1, 'new' => 0, 'why' => 'inscrit plusieurs fois : compte au départ ' . ($first['session'] ?: '?') . ' en ' . Equipe_cat($first)];
            $dupNames[] = Equipe_person($e) . ' (' . Equipe_cat($e) . ', départ ' . ($e['session'] ?: '?') . ')';
        }
        if ($dupNames) {
            $alerts[] = ['info', 'Inscrits plusieurs fois : seul le premier départ compte pour les équipes. Case « Équipe » décochée pour '
                . implode(', ', $dupNames) . '.'];
        }
        // Classement prévu par catégorie (comme MakeTeams) et classement natif actuel
        $native = Equipe_native_teams(array_keys($sections));
        foreach ($sections as $key => $s) {
            $rows = [];
            foreach ($teams as $t) {
                if ($t['sec'] !== $key) continue;
                $sc = Equipe_team_score($t['members'], $entries);
                $rows[] = [
                    'name' => $countries[$t['club']]['name'] ?? '?', 'members' => $t['members'], 'retained' => $sc['members'] ?? [],
                    'score' => $sc['score'] ?? null, 'gold' => $sc['gold'] ?? 0, 'xnine' => $sc['xnine'] ?? 0,
                    'before' => $native[$key][$t['club']]['score'] ?? null,
                    'alerts' => Equipe_check_size($t['members'], $settings),
                ];
            }
            $gone = [];
            foreach ($native[$key] ?? [] as $club => $n) {
                $still = array_filter($teams, function ($t) use ($key, $club) { return $t['sec'] === $key && $t['club'] === $club; });
                if (!$still) $gone[] = $countries[$club]['name'] ?? '?';
            }
            $secOut[] = ['label' => $key . ' — ' . $s['label'], 'sub' => $s['sub'], 'teams' => Equipe_rank($rows), 'gone' => $gone];
        }
    } else {
        // Inscriptions multiples des catégories choisies : ignorées (seul le premier départ compte)
        $dupNames = [];
        foreach ($entries as $e) {
            if ($e['dup'] && in_array($e['div'], $divs, true) && in_array($e['cls'], $cls, true)) {
                $dupNames[] = Equipe_person($e) . ' (' . Equipe_cat($e) . ', départ ' . ($e['session'] ?: '?') . ')';
            }
        }
        if ($dupNames) $alerts[] = ['info', 'Inscrits plusieurs fois : seul le premier départ compte, inscriptions ignorées : ' . implode(', ', $dupNames) . '.'];
        // Équipes libres enregistrées remplacées : celles qui comptent un archer listé
        $listed = [];
        foreach ($sections as $s) foreach ($s['clubs'] as $ids) foreach ($ids as $id) $listed[$id] = true;
        foreach (Equipe_free_read() as $t) {
            $hit = array_filter((array) $t['members'], function ($id) use ($listed) { return isset($listed[intval($id)]); });
            if ($hit) $replaced[] = ($countries[intval($t['club'])]['name'] ?? '?') . ' : '
                . implode(', ', array_map(function ($id) use ($entries) { return isset($entries[$id]) ? Equipe_person($entries[$id]) : "n°$id"; }, $t['members']));
        }
        foreach (Equipe_free_ranking($teams, $entries, $settings) as $g) {
            $secOut[] = ['label' => $g['label'], 'sub' => 'Classes : ' . implode(', ', $g['classes']), 'teams' => $g['teams'], 'gone' => []];
        }
    }
    // Noms des archers pour l'affichage
    foreach ($secOut as &$s) {
        foreach ($s['teams'] as &$t) {
            $names = function ($ids) use ($entries) { return array_map(function ($id) use ($entries) { return Equipe_person($entries[$id]) . ' (' . $entries[$id]['cls'] . ')'; }, $ids); };
            $t['retained'] = $names($t['retained']);
            $t['members'] = $names($t['members']);
        }
        unset($t);
    }
    unset($s);

    $blocking = (bool) array_filter($alerts, function ($a) { return $a[0] === 'error'; });
    $token = sha1(json_encode([$mode, array_map(function ($c) { return [$c['id'], $c['new']]; }, $changes),
        array_map(function ($t) { return [$t['club'], $t['members']]; }, $teams)]));
    return [
        'ok' => !$blocking, 'mode' => $mode, 'alerts' => $alerts, 'sections' => $secOut, 'changes' => $changes,
        'replaced' => $replaced, 'token' => $token, 'state' => $state['code'],
        'teams' => array_map(function ($t) { return ['club' => $t['club'], 'members' => $t['members']]; }, $teams),
    ];
}

// ── Écriture ─────────────────────────────────────────────────────────────────

/**
 * Écrit la case « Équipe » (EnTeamClEvent) dans une transaction, relit et
 * compare, puis lance le recalcul natif MakeTeams().
 *
 * safe_w_SQL() fait exit en cas d'erreur SQL : la connexion se ferme et MySQL
 * annule la transaction non validée. MakeTeams() est appelé APRÈS le commit
 * (il lit par la connexion de lecture).
 *
 * @param array $rows EnId => 0|1
 * @return string '' si tout va bien, sinon le message d'erreur
 */
function Equipe_write_flags(array $rows)
{
    $tour = Equipe_tour_id();
    if ($rows) {
        safe_w_BeginTransaction();
        foreach ($rows as $id => $v) {
            safe_w_SQL("UPDATE Entries SET EnTimestamp=EnTimestamp, EnTeamClEvent=" . ($v ? 1 : 0)
                . " WHERE EnId=" . intval($id) . " AND EnTournament=$tour");
        }
        $check = [];
        $rs = safe_w_SQL("SELECT EnId, EnTeamClEvent FROM Entries
            WHERE EnTournament=$tour AND EnId IN (" . implode(',', array_map('intval', array_keys($rows))) . ")");
        while ($r = safe_fetch($rs)) $check[intval($r->EnId)] = intval($r->EnTeamClEvent);
        foreach ($rows as $id => $v) {
            if (($check[$id] ?? null) !== ($v ? 1 : 0)) {
                safe_w_Rollback();
                return 'Relecture différente pour l\'archer n°' . intval($id) . ' : rien n\'a été modifié.';
            }
        }
        safe_w_Commit();
    }
    require_once 'Qualification/Fun_Qualification.local.inc.php';
    MakeTeams(null, null);
    return '';
}

/** Ajoute une ligne au journal du module (ModulesParameters 'Log', borné). */
function Equipe_log($action, $detail)
{
    $log = getModuleParameter(EQUIPE_MODULE, 'Log', [], 0, true);
    if (!is_array($log)) $log = [];
    $log[] = [date('Y-m-d H:i:s'), Equipe_user(), $action, $detail];
    if (count($log) > EQUIPE_LOG_MAX) $log = array_slice($log, -EQUIPE_LOG_MAX);
    setModuleParameter(EQUIPE_MODULE, 'Log', $log);
}

/** Journal, du plus récent au plus ancien. */
function Equipe_log_read()
{
    $log = getModuleParameter(EQUIPE_MODULE, 'Log', [], 0, true);
    return is_array($log) ? array_reverse($log) : [];
}

/**
 * Applique une composition : plan recalculé côté serveur, contrôles,
 * sauvegarde pour annulation, écriture, journal.
 */
function Equipe_apply($mode, array $divs, array $cls, $teamsIn, $settingsIn, $token, $confirm)
{
    $plan = Equipe_plan($mode, $divs, $cls, $teamsIn, $settingsIn);
    if (!$plan['ok']) return ['ok' => false, 'msg' => 'La composition contient des erreurs : relancez l\'aperçu.', 'plan' => $plan];
    if (!hash_equals($plan['token'], (string) $token)) return ['ok' => false, 'msg' => 'Les inscriptions ont changé depuis l\'aperçu : relancez-le.', 'plan' => $plan];
    $summary = [];
    foreach ($plan['sections'] as $s) foreach ($s['teams'] as $t) $summary[] = $t['name'] . ' : ' . implode(', ', $t['members']);

    if ($plan['mode'] === 'libre') {
        $entries = Equipe_entries();
        $listed = [];
        foreach (Equipe_sections('libre', $divs, $cls, $entries) as $s) foreach ($s['clubs'] as $ids) foreach ($ids as $id) $listed[$id] = true;
        $old = Equipe_free_read();
        $keep = array_filter($old, function ($t) use ($listed) {
            foreach ((array) $t['members'] as $id) if (isset($listed[intval($id)])) return false;
            return true;
        });
        setModuleParameter(EQUIPE_MODULE, 'Undo', ['mode' => 'libre', 'at' => date('Y-m-d H:i:s'), 'user' => Equipe_user(), 'teams' => $old]);
        Equipe_free_write(array_merge(array_values($keep), $plan['teams']));
        Equipe_log('Équipes libres', count($plan['teams']) . ' équipe(s) enregistrée(s), ' . count($plan['replaced']) . ' remplacée(s)'
            . ($summary ? ' — ' . implode(' | ', $summary) : ''));
        return ['ok' => true, 'msg' => count($plan['teams']) . ' équipe(s) libre(s) enregistrée(s).'];
    }

    if ($plan['state'] === 'STARTED' && !$confirm) return ['ok' => false, 'msg' => 'Le tir a commencé : cochez « Je confirme ».'];
    $rows = [];
    $undo = [];
    foreach ($plan['changes'] as $c) {
        $rows[$c['id']] = $c['new'];
        $undo[$c['id']] = ['old' => $c['old'], 'new' => $c['new'], 'name' => $c['name']];
    }
    if ($rows) setModuleParameter(EQUIPE_MODULE, 'Undo', ['mode' => 'classe', 'at' => date('Y-m-d H:i:s'), 'user' => Equipe_user(), 'rows' => $undo]);
    $error = Equipe_write_flags($rows);
    if ($error !== '') {
        setModuleParameter(EQUIPE_MODULE, 'Undo', '');
        return ['ok' => false, 'msg' => $error];
    }
    Equipe_log('Classe et division', count($rows) . ' archer(s) modifié(s)' . ($plan['state'] === 'STARTED' ? ' — tir commencé, confirmé' : '')
        . ($summary ? ' — ' . implode(' | ', $summary) : ''));
    return ['ok' => true, 'msg' => (count($rows) ? count($rows) . ' archer(s) modifié(s)' : 'Rien à changer') . ', classement par équipes recalculé par Ianseo.'];
}

/** Dernière application annulable, ou null. */
function Equipe_undo_info()
{
    $u = getModuleParameter(EQUIPE_MODULE, 'Undo', '', 0, true);
    if (!is_array($u)) return null;
    if (($u['mode'] ?? 'classe') === 'libre') return array_key_exists('teams', $u) ? $u : null;
    return !empty($u['rows']) ? $u : null;
}

/** Annule la dernière application. */
function Equipe_undo($confirm)
{
    $u = Equipe_undo_info();
    if (!$u) return ['ok' => false, 'msg' => 'Rien à annuler.'];
    if (($u['mode'] ?? 'classe') === 'libre') {
        Equipe_free_write((array) $u['teams']);
        setModuleParameter(EQUIPE_MODULE, 'Undo', '');
        Equipe_log('Annulation', 'Équipes libres remises comme avant le ' . $u['at'] . '.');
        return ['ok' => true, 'msg' => 'Équipes libres remises comme avant.'];
    }
    $state = Equipe_state();
    if ($state['locked']) return ['ok' => false, 'msg' => 'Liste des participants verrouillée.'];
    if ($state['started'] && !$confirm) return ['ok' => false, 'msg' => 'Le tir a commencé : cochez « Je confirme ».'];
    $entries = Equipe_entries();
    $rows = [];
    $skipped = [];
    foreach ($u['rows'] as $id => $r) {
        if (!isset($entries[$id])) continue;
        if ($entries[$id]['team'] !== intval($r['new'])) { $skipped[] = $r['name']; continue; }
        $rows[$id] = intval($r['old']);
    }
    $error = Equipe_write_flags($rows);
    if ($error !== '') return ['ok' => false, 'msg' => $error];
    setModuleParameter(EQUIPE_MODULE, 'Undo', '');
    $skip = $skipped ? ' Modifiés depuis, laissés tels quels : ' . implode(', ', $skipped) . '.' : '';
    Equipe_log('Annulation', count($rows) . ' archer(s) remis comme avant le ' . $u['at'] . '.' . $skip);
    return ['ok' => true, 'msg' => count($rows) . ' archer(s) remis comme avant.' . $skip];
}

// ── Suppression d'équipes (page « Équipes créées ») ──────────────────────────

/** Même équipe libre : même club, mêmes archers (ordre indifférent). */
function Equipe_free_same($t, $club, array $members)
{
    $a = array_map('intval', (array) $t['members']);
    $b = array_map('intval', $members);
    sort($a);
    sort($b);
    return intval($t['club']) === intval($club) && $a === $b;
}

/**
 * Supprime des équipes libres (paramètres du module seulement : aucun effet
 * sur Ianseo). $which = [[club, members], …] ; null = toutes.
 */
function Equipe_delete_free($which)
{
    $old = Equipe_free_read();
    if (!$old) return ['ok' => false, 'msg' => 'Aucune équipe libre.'];
    $keep = [];
    $gone = 0;
    foreach ($old as $t) {
        $hit = $which === null;
        foreach ((array) $which as $w) if (!$hit && Equipe_free_same($t, $w['club'] ?? 0, (array) ($w['members'] ?? []))) $hit = true;
        if ($hit) $gone++; else $keep[] = $t;
    }
    if (!$gone) return ['ok' => false, 'msg' => 'Équipe introuvable : la page n\'est plus à jour, rechargez-la.'];
    setModuleParameter(EQUIPE_MODULE, 'Undo', ['mode' => 'libre', 'at' => date('Y-m-d H:i:s'), 'user' => Equipe_user(), 'teams' => $old]);
    Equipe_free_write($keep);
    Equipe_log('Suppression', "$gone équipe(s) libre(s) supprimée(s)");
    return ['ok' => true, 'msg' => "$gone équipe(s) libre(s) supprimée(s). Classement d'Ianseo inchangé."];
}

/**
 * Supprime une équipe du classement par équipes natif (division/classe) :
 * décoche la case « Équipe » (EnTeamClEvent) des archers du club dans cette
 * catégorie, puis recalcul natif MakeTeams(). EnTeamClEvent ne sert, dans les
 * calculs d'Ianseo, qu'à ce classement : qualifications individuelles
 * (EnIndClEvent), duels et finales (EnIndFEvent, EnTeamFEvent,
 * EnTeamMixEvent) ne sont pas touchés.
 */
function Equipe_delete_class($cat, $club, $confirm)
{
    $state = Equipe_state();
    if ($state['locked']) return ['ok' => false, 'msg' => 'Liste des participants verrouillée.'];
    if ($state['started'] && !$confirm) return ['ok' => false, 'msg' => 'Le tir a commencé : confirmation nécessaire.'];
    $rows = [];
    $undo = [];
    foreach (Equipe_entries() as $id => $e) {
        if ($e['team'] !== 1 || $e['club'] !== intval($club) || Equipe_cat($e) !== (string) $cat) continue;
        $rows[$id] = 0;
        $undo[$id] = ['old' => 1, 'new' => 0, 'name' => Equipe_person($e)];
    }
    if (!$rows) return ['ok' => false, 'msg' => 'Équipe introuvable : la page n\'est plus à jour, rechargez-la.'];
    setModuleParameter(EQUIPE_MODULE, 'Undo', ['mode' => 'classe', 'at' => date('Y-m-d H:i:s'), 'user' => Equipe_user(), 'rows' => $undo]);
    $error = Equipe_write_flags($rows);
    if ($error !== '') {
        setModuleParameter(EQUIPE_MODULE, 'Undo', '');
        return ['ok' => false, 'msg' => $error];
    }
    $clubName = Equipe_countries()[intval($club)]['name'] ?? '?';
    Equipe_log('Suppression', "Équipe $clubName en $cat : case « Équipe » décochée pour " . implode(', ', array_column($undo, 'name')));
    return ['ok' => true, 'msg' => "Équipe $clubName ($cat) retirée du classement par équipes. Qualifications et duels inchangés."];
}

// ── Composition actuelle et proposition ──────────────────────────────────────

/**
 * Composition actuelle des archers listés.
 *   classe : archers cochés « Équipe », si le club en a au moins 3 dans la
 *            catégorie (sinon ils sont montrés non retenus) ;
 *   libre  : équipes libres enregistrées dont tous les archers sont listés.
 */
function Equipe_current($mode, array $sections, array $entries)
{
    $teams = [];
    if ($mode === 'libre') {
        $where = [];
        foreach ($sections as $key => $s) foreach ($s['clubs'] as $club => $ids) foreach ($ids as $id) $where[$id] = [$key, $club];
        foreach (Equipe_free_read() as $t) {
            $members = array_map('intval', (array) $t['members']);
            $loc = $members && isset($where[$members[0]]) ? $where[$members[0]] : null;
            if (!$loc) continue;
            foreach ($members as $id) if (($where[$id] ?? null) !== $loc) continue 2;
            $teams[] = ['sec' => $loc[0], 'club' => $loc[1], 'members' => $members];
        }
        return $teams;
    }
    foreach ($sections as $key => $s) {
        foreach ($s['clubs'] as $club => $ids) {
            $members = array_values(array_filter($ids, function ($id) use ($entries) { return $entries[$id]['team'] === 1; }));
            if (count($members) >= 3) $teams[] = ['sec' => $key, 'club' => $club, 'members' => $members];
        }
    }
    return $teams;
}

/**
 * Proposition automatique, pour chaque club et chaque section : archers dans
 * l'ordre des scores (tir commencé) ou d'inscription, découpés en équipes de 3
 * (le reste devient remplaçant jusqu'à l'effectif maximum, ou non retenu).
 *   classe : une équipe au plus par club et par catégorie ;
 *   libre  : plusieurs équipes possibles, hommes et femmes séparés.
 */
function Equipe_propose($mode, array $divs, array $cls, $settingsIn)
{
    $mode = $mode === 'libre' ? 'libre' : 'classe';
    $entries = Equipe_entries();
    $settings = Equipe_settings($settingsIn);
    $byScore = Equipe_state()['started'];
    $teams = [];
    foreach (Equipe_sections($mode, $divs, $cls, $entries) as $key => $s) {
        foreach ($s['clubs'] as $club => $ids) {
            if ($byScore) Equipe_sort_by_score($ids, $entries);
            if ($mode === 'classe') {
                if (count($ids) >= $settings['size_min']) $teams[] = ['sec' => $key, 'club' => $club, 'members' => array_slice($ids, 0, $settings['size_max'])];
                continue;
            }
            $bySex = [];
            foreach ($ids as $id) $bySex[$entries[$id]['sex']][] = $id;
            foreach ($bySex as $list) {
                $chunks = array_chunk($list, 3);
                $rest = count(end($chunks)) < 3 ? array_pop($chunks) : [];
                foreach ($chunks as &$c) while ($rest && count($c) < $settings['size_max']) $c[] = array_shift($rest);
                unset($c);
                foreach ($chunks as $c) $teams[] = ['sec' => $key, 'club' => $club, 'members' => $c];
            }
        }
    }
    return $teams;
}
