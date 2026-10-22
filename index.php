<?php
/**
 * index.php — tableau de bord du module : où en sont les équipes, impressions, alertes.
 *
 *  1. Classe et division : équipes du classement natif d'Ianseo, d'après les
 *     cases « Équipe » (avant le tir) et Teams (après) ; résumé par catégorie,
 *     détail (archers, scores, suppression) replié.
 *  2. Libres : équipes gardées par le module, classées par conditions de tir
 *     et par sexe ; même présentation.
 *  3. Dernière application (annulation), alerte des contrôles, accès à l'assistant.
 *
 * Suppression et annulation : tableau.js → action.php (act=delete, act=undo).
 */
require_once __DIR__ . '/config.php';

$entries = Equipe_entries();
$countries = Equipe_countries();
$divisions = Equipe_divisions();
$classes = Equipe_classes();
$state = Equipe_state();
$native = Equipe_native_teams();

/** Division et classe d'une catégorie « CLS1H » : [division, classe] ou [null, null]. */
$splitCat = function ($cat) use ($divisions, $classes) {
    foreach ($divisions as $did => $dn) {
        if (strpos($cat, $did) === 0 && isset($classes[substr($cat, strlen($did))])) return [$did, substr($cat, strlen($did))];
    }
    return [null, null];
};

// ── Classe et division : clubs avec au moins 3 archers cochés, ou équipe classée par Ianseo ──
$byCat = [];
foreach ($entries as $id => $e) {
    if ($e['counted'] && $e['team'] === 1) $byCat[Equipe_cat($e)][$e['club']][] = $id;
}
$catRows = [];
foreach ($byCat as $cat => $clubs) {
    foreach ($clubs as $club => $ids) {
        $n = $native[$cat][$club] ?? null;
        if (count($ids) < 3 && !$n) continue;
        Equipe_sort_by_score($ids, $entries);
        $catRows[$cat][] = [
            'club' => $club, 'name' => $countries[$club]['name'] ?? '?', 'members' => $ids, 'retained' => $n['members'] ?? [],
            'score' => $n ? $n['score'] : null, 'rank' => $n['rank'] ?? null,
        ];
    }
}
foreach ($catRows as &$rows) {
    usort($rows, function ($a, $b) { return [$a['rank'] ?? 9999, $a['name']] <=> [$b['rank'] ?? 9999, $b['name']]; });
}
unset($rows);
// Ordre d'Ianseo : divisions puis classes.
$order = [];
foreach (array_keys($divisions) as $d) foreach (array_keys($classes) as $c) $order[$d . $c] = count($order);
uksort($catRows, function ($a, $b) use ($order) { return ($order[$a] ?? 9999) <=> ($order[$b] ?? 9999); });
$nClass = array_sum(array_map('count', $catRows));

$free = Equipe_free_ranking(Equipe_free_read(), $entries);
$nFree = array_sum(array_map(function ($g) { return count($g['teams']); }, $free));

/** Lien « Modifier ces équipes » : l'assistant ouvert sur la composition, critères déjà cochés. */
$editUrl = function ($mode, array $divs, array $cls) {
    $q = ['mode' => $mode, 'go' => 'compose', 'divs' => array_values(array_unique($divs)), 'classes' => array_values(array_unique($cls))];
    return './assistant.php?' . http_build_query($q);
};
$classDivs = $classCls = [];
foreach (array_keys($catRows) as $cat) {
    [$d, $c] = $splitCat($cat);
    if ($d !== null) { $classDivs[] = $d; $classCls[] = $c; }
}
$freeDivs = $freeCls = [];
foreach ($free as $g) foreach ($g['teams'] as $t) foreach ($t['members'] as $id) { $freeDivs[] = $entries[$id]['div']; $freeCls[] = $entries[$id]['cls']; }

$dups = Equipe_dup_control($entries);
$undo = Equipe_undo_info();

$names = function (array $ids, array $retained, $withClass) use ($entries, $state) {
    $out = [];
    foreach ($ids as $id) {
        if (!isset($entries[$id])) continue;
        $e = $entries[$id];
        $txt = Equipe_h(Equipe_person($e)) . ($withClass ? ' <span class="b">' . Equipe_h($e['cls']) . '</span>' : '')
            . ($state['started'] ? ' <span class="eq-muted">' . $e['score'] . '</span>' : '');
        $out[] = in_array($id, $retained, true) ? "<b>$txt</b>" : $txt;
    }
    return implode('<br>', $out);
};

// Écriture : droits en écriture et garde-fou ; mode classe : participants non verrouillés.
$canWrite = hasFullACL(AclParticipants, 'pEntries', AclReadWrite) && $GUARD['level'] !== 'red';
$canClass = $canWrite && !$state['locked'];

$PAGE_TITLE = 'Équipes — tableau de bord';
$JS_SCRIPT[] = '<link href="./index.css" rel="stylesheet" type="text/css">';
$JS_SCRIPT[] = '<script>window.EQUIPE = ' . json_encode(['action' => $EQUIPE_URL . 'action.php', 'token' => Equipe_token(), 'started' => $state['started']]) . ';</script>';
$JS_SCRIPT[] = '<script src="./tableau.js"></script>';
include('Common/Templates/head.php');
?>
<div id="eq">
    <?= Equipe_page_header('index') ?>
    <div id="eq-msg"></div>
    <?php if ($dups['bad']): ?>
        <div class="eq-banner eq-orange eq-row"><span><b><?= $dups['bad'] ?> inscription(s) en défaut</b> : des archers inscrits plusieurs fois ont une
            case équipe cochée sur un départ qui ne compte pas.</span><a href="./controles.php">Voir les contrôles</a></div>
    <?php endif; ?>

    <div class="eq-grid2">
        <section class="eq-card">
            <h3>Par classe et division</h3>
            <p class="eq-muted">Classement par équipes d'Ianseo · <b><?= $nClass ?> équipe(s)</b><?= $catRows ? ' dans ' . count($catRows) . ' catégorie(s)' : '' ?></p>
            <?php if (!$catRows): ?>
                <p class="eq-muted">Aucune équipe : aucun club n'a 3 archers cochés « Équipe » dans une même catégorie.</p>
            <?php else: ?>
                <table class="eq-table">
                    <tr><th>Catégorie</th><th>Équipes</th><th class="num">Nb</th></tr>
                    <?php foreach ($catRows as $cat => $rows): ?>
                        <tr><td><?= Equipe_h($cat) ?></td><td><?= Equipe_h(implode(' · ', array_column($rows, 'name'))) ?></td><td class="num"><?= count($rows) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            <div class="eq-actions">
                <?php if ($catRows): ?><a class="eq-btn ghost" href="<?= Equipe_h($editUrl('classe', $classDivs, $classCls)) ?>">Modifier ces équipes</a><?php endif; ?>
                <a class="eq-btn ghost" href="<?= Equipe_h($CFG->ROOT_DIR) ?>Qualification/PrnTeam.php" target="_blank">Imprimer le classement (Ianseo)</a>
            </div>
        </section>

        <section class="eq-card">
            <h3>Équipes libres</h3>
            <p class="eq-muted">Classement fait par le module · <b><?= $nFree ?> équipe(s)</b></p>
            <?php if (!$free): ?>
                <p class="eq-muted">Aucune équipe libre : choisissez le mode « Libre » dans « Composer des équipes ».</p>
            <?php else: ?>
                <table class="eq-table">
                    <tr><th>Classement</th><th>Équipes</th><th class="num">Nb</th></tr>
                    <?php foreach ($free as $g): ?>
                        <tr><td><?= Equipe_h($g['label']) ?></td><td><?= Equipe_h(implode(' · ', array_column($g['teams'], 'name'))) ?></td><td class="num"><?= count($g['teams']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            <div class="eq-actions">
                <?php if ($free): ?><a class="eq-btn ghost" href="<?= Equipe_h($editUrl('libre', $freeDivs, $freeCls)) ?>">Modifier ces équipes</a><?php endif; ?>
                <a class="eq-btn ghost" href="./equipes-pdf.php" target="_blank">Imprimer le classement (PDF)</a>
            </div>
        </section>
    </div>

    <?php if ($catRows || $free): ?>
        <details class="eq-card">
            <summary>Détail des équipes : archers, scores, suppression</summary>
            <p class="eq-muted"><?= $state['started']
                ? 'En gras : les 3 meilleurs scores, ceux qui comptent.'
                : 'Le tir n\'a pas commencé : les équipes sont listées, le classement apparaîtra avec les scores.' ?></p>
            <?php foreach ($catRows as $cat => $rows): [$d, $c] = $splitCat($cat); ?>
                <h4><?= Equipe_h($cat . ($d !== null ? ' — ' . $divisions[$d] . ' — ' . $classes[$c]['name'] : '')) ?></h4>
                <table class="eq-table eq-rank">
                    <tr><th>Rang</th><th>Club</th><th>Archers cochés « Équipe »</th><th>Score</th><th></th></tr>
                    <?php foreach ($rows as $r): ?>
                        <tr><td class="r"><?= $r['rank'] ?: '—' ?></td><td><?= Equipe_h($r['name']) ?></td>
                            <td><?= $names($r['members'], $r['retained'], false) ?></td><td class="s"><?= $r['score'] ?? '—' ?></td>
                            <td class="d"><button class="ghost small danger-ghost" data-del="classe" data-cat="<?= Equipe_h($cat) ?>" data-club="<?= $r['club'] ?>"
                                data-name="<?= Equipe_h($r['name'] . ' (' . $cat . ')') ?>" <?= $canClass ? '' : 'disabled' ?>>Supprimer</button></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endforeach; ?>
            <?php foreach ($free as $g): ?>
                <h4>Libre : <?= Equipe_h($g['label']) ?> <span class="b">Classes : <?= Equipe_h(implode(', ', $g['classes'])) ?></span></h4>
                <table class="eq-table eq-rank">
                    <tr><th>Rang</th><th>Équipe</th><th>Archers</th><th>Score</th><th></th></tr>
                    <?php foreach ($g['teams'] as $t): ?>
                        <tr><td class="r"><?= $t['rank'] ?? '—' ?></td><td><?= Equipe_h($t['name']) ?>
                            <?php foreach ($t['alerts'] as $a): ?><br><span class="b orange"><?= Equipe_h($a) ?></span><?php endforeach; ?></td>
                            <td><?= $names($t['members'], $t['retained'], true) ?></td><td class="s"><?= $t['score'] ?? '—' ?></td>
                            <td class="d"><button class="ghost small danger-ghost" data-del="libre" data-club="<?= $t['club'] ?>" data-members="<?= Equipe_h(json_encode($t['raw'])) ?>"
                                data-name="<?= Equipe_h($t['name']) ?>" <?= $canWrite ? '' : 'disabled' ?>>Supprimer</button></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endforeach; ?>
            <?php if ($free): ?>
                <div class="eq-actions"><button class="ghost danger-ghost" data-del="libre-all" <?= $canWrite ? '' : 'disabled' ?>>Supprimer toutes les équipes libres</button></div>
            <?php endif; ?>
        </details>
    <?php endif; ?>

    <?php if ($undo): $uMode = $undo['mode'] ?? 'classe'; ?>
        <div class="eq-card eq-row">
            <span><b>Dernière application</b> : <?= $uMode === 'libre'
                ? 'équipes libres (' . count($undo['teams']) . ' avant l\'application)'
                : 'par classe et division, ' . count($undo['rows']) . ' archer(s)' ?>, le <?= Equipe_h($undo['at']) ?> (<?= Equipe_h($undo['user']) ?>)</span>
            <button class="ghost" data-undo="<?= $uMode ?>" <?= ($uMode === 'libre' ? $canWrite : $canClass) ? '' : 'disabled' ?>>Annuler cette application</button>
        </div>
    <?php endif; ?>

    <div class="eq-cta"><a class="eq-btn big" href="./assistant.php">Composer des équipes</a></div>

    <?= Equipe_page_footer() ?>
</div>
<?php
include('Common/Templates/tail.php');
