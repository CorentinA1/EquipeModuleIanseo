<?php
/**
 * guard.php — garde-fou du module Equipe.
 *
 * Exécuté en premier sur chaque page du module (jamais dans menu.php).
 * Lecture seule. Vérifie que l'Ianseo installé se comporte comme celui pour
 * lequel le module a été écrit : version, fonctions (signatures + empreintes
 * SHA-1 du code), colonnes, constantes, absence de surcharge de MakeTeams,
 * calcul des équipes standard (ToElabTeam=0).
 *
 *   green  : version validée, tout conforme            → tout autorisé
 *   orange : version inconnue, mais tout est identique → autorisé après avertissement
 *   red    : un écart quelconque                       → lecture seule
 *
 * Références : guard-reference.php (et dev/empreintes.php pour les recalculer).
 */

/** Charge les fichiers d'Ianseo qui définissent les fonctions surveillées. */
function Equipe_guard_load_ianseo()
{
    foreach ([
        'Qualification/Fun_Qualification.local.inc.php',
    ] as $file) {
        require_once $file;
    }
}

/**
 * Empreinte d'une fonction : SHA-1 de son code source (retours chariot
 * retirés), lignes et noms des paramètres.
 */
function Equipe_guard_fingerprint($name)
{
    $rf = new ReflectionFunction($name);
    $lines = file($rf->getFileName());
    $src = implode('', array_slice($lines, $rf->getStartLine() - 1, $rf->getEndLine() - $rf->getStartLine() + 1));
    $params = [];
    foreach ($rf->getParameters() as $p) $params[] = $p->getName();
    return [
        'sha1' => sha1(str_replace("\r", '', $src)),
        'start' => $rf->getStartLine(),
        'end' => $rf->getEndLine(),
        'params' => $params,
    ];
}

/**
 * Résultat du garde-fou (calculé une fois par requête).
 *
 * @return array ['level' => 'green|orange|red', 'version' => …, 'checks' => [[label, level, detail], …]]
 */
function Equipe_guard()
{
    static $result = null;
    if ($result !== null) return $result;

    global $CFG;
    $ref = require __DIR__ . '/guard-reference.php';
    $checks = [];
    $add = function ($label, $level, $detail = '') use (&$checks) {
        $checks[] = ['label' => $label, 'level' => $level, 'detail' => $detail];
    };

    // ── Version ──────────────────────────────────────────────────────────────
    $version = defined('ProgramVersion') ? ProgramVersion : '';
    $known = in_array($version, $ref['versions'], true);
    $add('Version d\'Ianseo', $known ? 'green' : 'orange',
        $known ? $version . ' (validée)' : ($version ?: '?') . ' : version non validée (validées : ' . implode(', ', $ref['versions']) . ')');

    // ── Constantes ───────────────────────────────────────────────────────────
    $missing = array_values(array_filter($ref['constants'], function ($c) { return !defined($c); }));
    $add('Constantes', $missing ? 'red' : 'green', $missing ? 'absentes : ' . implode(', ', $missing) : count($ref['constants']) . ' présentes');

    // ── Fonctions : présence, paramètres, empreintes ─────────────────────────
    try {
        Equipe_guard_load_ianseo();
    } catch (Throwable $e) {
        $add('Chargement des fonctions', 'red', $e->getMessage());
    }
    foreach ($ref['functions'] as $name => $def) {
        if (!function_exists($name)) {
            $add("Fonction $name", 'red', 'absente');
            continue;
        }
        try {
            $f = Equipe_guard_fingerprint($name);
        } catch (Throwable $e) {
            $add("Fonction $name", 'red', $e->getMessage());
            continue;
        }
        // Les paramètres attendus doivent ouvrir la liste (Ianseo ajoute parfois un paramètre optionnel à la fin).
        if (array_slice($f['params'], 0, count($def['params'])) !== $def['params']) {
            $add("Fonction $name", 'red', 'paramètres changés : (' . implode(', ', $f['params']) . '), attendus : (' . implode(', ', $def['params']) . ')');
            continue;
        }
        if (empty($def['sha1'])) {
            $add("Fonction $name", 'green', 'signature conforme');
            continue;
        }
        $expected = $def['sha1'][$version] ?? null;
        if ($expected !== null) {
            $ok = hash_equals($expected, $f['sha1']);
        } else {
            // Version inconnue : le code doit être identique à celui d'une version validée.
            $ok = in_array($f['sha1'], $def['sha1'], true);
        }
        $add("Fonction $name", $ok ? 'green' : 'red',
            ($ok ? 'code identique à la référence' : 'code modifié (empreinte ' . substr($f['sha1'], 0, 12) . '…)')
            . " — l.{$f['start']}-{$f['end']}");
    }

    // ── Surcharge de MakeTeams par le règlement (mêmes noms que ceux cherchés par MakeTeams) ──
    $rule = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_SESSION['TourLocRule'] ?? ''));
    $over = [];
    if ($rule !== '') {
        $dir = $CFG->DOCUMENT_PATH . 'Modules/Sets/' . $rule . '/Functions/';
        $over = array_merge(glob($dir . 'MakeTeams.php') ?: [], glob($dir . 'MakeTeams-*.php') ?: []);
    }
    $add('Surcharge de MakeTeams', $over ? 'red' : 'green',
        $over ? 'fichier(s) : ' . implode(', ', array_map('basename', $over)) : 'aucune (règlement ' . ($rule ?: '?') . ')');

    // ── Calcul des équipes de la compétition : standard (ToElabTeam=0) ───────
    $rs = safe_r_SQL("SELECT ToElabTeam FROM Tournament WHERE ToId=" . intval($_SESSION['TourId'] ?? 0), false, true);
    $elab = ($rs && ($r = safe_fetch($rs))) ? intval($r->ToElabTeam) : -1;
    $add('Type de classement par équipes', $elab === 0 ? 'green' : 'red',
        $elab === 0 ? 'standard : 3 meilleurs scores par club et par division/classe' : "non géré par le module (ToElabTeam=$elab)");

    // ── Colonnes ─────────────────────────────────────────────────────────────
    // Noms de tables comparés en minuscules : avec lower_case_table_names=1 (MySQL sous Windows),
    // information_schema renvoie « entries » et non « Entries ».
    $types = [];
    $rs = safe_r_SQL("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=" . StrSafe_DB($CFG->DB_NAME) . "
        AND LOWER(TABLE_NAME) IN (" . implode(',', array_map(function ($t) { return StrSafe_DB(strtolower($t)); }, array_keys($ref['columns']))) . ")", false, true);
    if ($rs === false) {
        $add('Colonnes', 'red', 'lecture de information_schema impossible');
    } else {
        while ($r = safe_fetch($rs)) $types[strtolower($r->TABLE_NAME)][$r->COLUMN_NAME] = strtolower($r->DATA_TYPE);
        $bad = [];
        $n = 0;
        foreach ($ref['columns'] as $table => $cols) {
            foreach ($cols as $col => $type) {
                $n++;
                $found = $types[strtolower($table)][$col] ?? null;
                if ($found === null) $bad[] = "$table.$col absente";
                elseif ($found !== $type) $bad[] = "$table.$col : $found au lieu de $type";
            }
        }
        $add('Colonnes', $bad ? 'red' : 'green', $bad ? implode(' ; ', $bad) : "$n colonnes conformes");
    }

    $level = 'green';
    foreach ($checks as $c) {
        if ($c['level'] === 'red') { $level = 'red'; break; }
        if ($c['level'] === 'orange') $level = 'orange';
    }
    return $result = ['level' => $level, 'version' => $version, 'checks' => $checks];
}

/** Vrai si le garde-fou autorise les écritures (vert ou orange). */
function Equipe_guard_writable()
{
    return Equipe_guard()['level'] !== 'red';
}
