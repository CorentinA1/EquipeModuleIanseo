<?php
/**
 * action.php — points d'entrée JSON de l'assistant (?act=…).
 *
 * Paramètres communs : mode (classe|libre), divs[], classes[], settings {size_min, size_max}.
 * Lecture  : init, load (archers listés et composition actuelle), propose, preview, log
 * Écriture : apply, undo — jeton de session, garde-fou vert ou orange, droit
 *            Participants en écriture ; mode classe : participants non verrouillés.
 */
$JSON = ['error' => 1, 'msg' => 'Accès refusé.'];
require_once dirname(__FILE__, 4) . '/config.php';
if (!CheckTourSession() || !hasFullACL(AclParticipants, 'pEntries', AclReadOnly)) JsonOut($JSON);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/lib.php';

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$in += $_REQUEST;
$act = (string) ($in['act'] ?? '');
$mode = ($in['mode'] ?? '') === 'libre' ? 'libre' : 'classe';
$divs = array_map('strval', (array) ($in['divs'] ?? []));
$cls = array_map('strval', (array) ($in['classes'] ?? []));

$guard = Equipe_guard();
$canWrite = hasFullACL(AclParticipants, 'pEntries', AclReadWrite);

/** Refuse une écriture si une condition de sécurité manque. */
function Equipe_require_write($mode)
{
    global $guard, $canWrite, $in;
    $fail = function ($msg) { JsonOut(['error' => 1, 'msg' => $msg]); };
    if (empty($_SESSION['EQUIPE_TOKEN']) || !hash_equals($_SESSION['EQUIPE_TOKEN'], (string) ($in['token'] ?? ''))) $fail('Jeton de sécurité invalide : rechargez la page.');
    if ($guard['level'] === 'red') $fail('Garde-fou rouge : module en lecture seule.');
    if (!$canWrite) $fail('Droit « Participants » en écriture nécessaire.');
    if ($mode === 'classe' && IsBlocked(BIT_BLOCK_PARTICIPANT)) $fail('La liste des participants est verrouillée.');
}

switch ($act) {

    case 'init':
        $state = Equipe_state();
        $classes = [];
        foreach (Equipe_classes() as $id => $c) $classes[] = ['id' => $id, 'name' => $c['name'], 'sex' => $c['sex']];
        $divisions = [];
        foreach (Equipe_divisions() as $id => $name) $divisions[] = ['id' => $id, 'name' => $name];
        // Nombre d'archers par catégorie, pour guider le choix des critères.
        $counts = [];
        $entries = Equipe_entries();
        foreach ($entries as $e) if ($e['counted']) $counts[Equipe_cat($e)] = ($counts[Equipe_cat($e)] ?? 0) + 1;
        $undo = Equipe_undo_info();
        $JSON = [
            'error' => 0,
            'guard' => $guard['level'],
            'state' => $state,
            'canWrite' => $canWrite && $guard['level'] !== 'red',
            'divisions' => $divisions,
            'classes' => $classes,
            'counts' => $counts,
            'dups' => Equipe_dup_control($entries),
            'undo' => $undo ? ['at' => $undo['at'], 'user' => $undo['user'], 'mode' => $undo['mode'] ?? 'classe',
                'n' => ($undo['mode'] ?? 'classe') === 'libre' ? count($undo['teams']) : count($undo['rows'])] : null,
        ];
        break;

    // Archers listés par section et par club, et composition actuelle.
    case 'load':
        $entries = Equipe_entries();
        $sections = Equipe_sections($mode, $divs, $cls, $entries);
        $countries = Equipe_countries();
        $native = $mode === 'classe' ? Equipe_native_teams(array_keys($sections)) : [];
        $out = [];
        foreach ($sections as $key => $s) {
            $clubs = [];
            foreach ($s['clubs'] as $club => $ids) {
                $clubs[] = [
                    'id' => $club, 'code' => $countries[$club]['code'] ?? '', 'name' => $countries[$club]['name'] ?? '?',
                    'native' => isset($native[$key][$club]) ? ['score' => $native[$key][$club]['score'], 'rank' => $native[$key][$club]['rank']] : null,
                    'archers' => array_map(function ($id) use ($entries) {
                        $e = $entries[$id];
                        return ['id' => $id, 'name' => Equipe_person($e), 'cls' => $e['cls'], 'sex' => $e['sex'], 'score' => $e['score']];
                    }, $ids),
                ];
            }
            usort($clubs, function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
            $out[] = ['key' => $key, 'title' => $mode === 'classe' ? $key . ' — ' . $s['label'] : $s['label'],
                'sub' => $mode === 'classe' ? $s['sub'] : 'Classes : ' . implode(', ', $s['classes']), 'known' => $s['known'], 'clubs' => $clubs];
        }
        $JSON = ['error' => 0, 'mode' => $mode, 'sections' => $out, 'teams' => Equipe_current($mode, $sections, $entries)];
        break;

    case 'propose':
        $JSON = ['error' => 0, 'teams' => Equipe_propose($mode, $divs, $cls, $in['settings'] ?? [])];
        break;

    case 'preview':
        $plan = Equipe_plan($mode, $divs, $cls, $in['teams'] ?? [], $in['settings'] ?? []);
        unset($plan['teams']);
        $JSON = ['error' => 0, 'plan' => $plan];
        break;

    case 'apply':
        Equipe_require_write($mode);
        $r = Equipe_apply($mode, $divs, $cls, $in['teams'] ?? [], $in['settings'] ?? [], (string) ($in['plan'] ?? ''), !empty($in['confirm']));
        if (isset($r['plan'])) unset($r['plan']['teams']);
        $JSON = ['error' => $r['ok'] ? 0 : 1, 'msg' => $r['msg'], 'plan' => $r['plan'] ?? null];
        break;

    case 'undo':
        $u = Equipe_undo_info();
        Equipe_require_write($u['mode'] ?? 'classe');
        $r = Equipe_undo(!empty($in['confirm']));
        $JSON = ['error' => $r['ok'] ? 0 : 1, 'msg' => $r['msg']];
        break;

    // Suppression depuis la page « Équipes créées ».
    case 'delete':
        $kind = ($in['kind'] ?? '') === 'classe' ? 'classe' : 'libre';
        Equipe_require_write($kind);
        if ($kind === 'classe') {
            $r = Equipe_delete_class((string) ($in['cat'] ?? ''), intval($in['club'] ?? 0), !empty($in['confirm']));
        } else {
            $r = Equipe_delete_free(!empty($in['all']) ? null : [['club' => intval($in['club'] ?? 0), 'members' => (array) ($in['members'] ?? [])]]);
        }
        $JSON = ['error' => $r['ok'] ? 0 : 1, 'msg' => $r['msg']];
        break;

    case 'log':
        $JSON = ['error' => 0, 'log' => array_slice(Equipe_log_read(), 0, 100)];
        break;

    default:
        $JSON['msg'] = 'Action inconnue.';
}
JsonOut($JSON);
