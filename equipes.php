<?php
/**
 * equipes.php — équipes créées, avec suppression d'une équipe (equipes.js → action.php?act=delete).
 *
 *  1. Classe et division : équipes du classement natif d'Ianseo, d'après les
 *     cases « Équipe » (avant le tir) et Teams (après), avec le lien vers
 *     l'impression native.
 *  2. Libres : équipes gardées par le module, classées par conditions de tir
 *     et par sexe, avec le PDF.
 */
require_once __DIR__ . '/config.php';

$entries = Equipe_entries();
$countries = Equipe_countries();
$divisions = Equipe_divisions();
$classes = Equipe_classes();
$state = Equipe_state();
$native = Equipe_native_teams();

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
            'score' => $n ? $n['score'] : null, 'gold' => 0, 'xnine' => 0, 'rank' => $n['rank'] ?? null,
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

$free = Equipe_free_ranking(Equipe_free_read(), $entries);

// Inscriptions multiples qui ne comptent pas mais gardent une case équipe : Ianseo les compte tant qu'elles restent cochées.
$dupFlagged = [];
foreach ($entries as $e) {
    if ($e['dup'] && ($e['team'] || $e['teamf'] || $e['teammix'])) {
        $first = $entries[$e['dup']];
        $cases = array_keys(array_filter(['« Équipe »' => $e['team'], '« Épreuves par équipes »' => $e['teamf'], '« Équipes mixtes »' => $e['teammix']]));
        $dupFlagged[] = Equipe_person($e) . ' : ' . Equipe_cat($e) . ' au départ ' . ($e['session'] ?: '?') . ', ' . implode(', ', $cases)
            . ' (compte au départ ' . ($first['session'] ?: '?') . ' en ' . Equipe_cat($first) . ')';
    }
}
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

// Suppression : droits en écriture et garde-fou ; mode classe : participants non verrouillés.
$canWrite = hasFullACL(AclParticipants, 'pEntries', AclReadWrite) && $GUARD['level'] !== 'red';
$canClass = $canWrite && !$state['locked'];

$PAGE_TITLE = 'Équipes créées';
$JS_SCRIPT[] = '<link href="./index.css" rel="stylesheet" type="text/css">';
$JS_SCRIPT[] = '<script>window.EQUIPE = ' . json_encode(['action' => $EQUIPE_URL . 'action.php', 'token' => Equipe_token(), 'started' => $state['started']]) . ';</script>';
$JS_SCRIPT[] = '<script src="./equipes.js"></script>';
include('Common/Templates/head.php');
?>
<div id="eq">
    <div class="eq-head">
        <h2>Équipes créées</h2>
        <nav><a href="./">Assistant</a><a href="./equipes.php" class="cur">Équipes créées</a></nav>
    </div>
    <?= Equipe_guard_banner($GUARD) ?>
    <div id="eq-msg"></div>
    <p class="eq-muted"><?= $state['started']
        ? 'En gras : les 3 meilleurs scores, ceux qui comptent.'
        : 'Le tir n\'a pas commencé : les équipes sont listées, le classement apparaîtra avec les scores.' ?></p>

    <div class="eq-card">
        <h3>Classe et division — classement par équipes d'Ianseo</h3>
        <div class="eq-actions" style="margin:0 0 8px">
            <a class="eq-btn" href="<?= Equipe_h($CFG->ROOT_DIR) ?>Qualification/PrnTeam.php" target="_blank">Éditer le classement par équipes (Ianseo)</a>
        </div>
        <?php if ($dupFlagged): ?>
            <div class="eq-banner eq-orange"><b>Archers inscrits plusieurs fois avec une case équipe cochée sur un départ qui ne compte pas :</b>
                seul le premier score compte pour les équipes, mais Ianseo les compte tant que la case reste cochée. « Équipe » :
                passez leur catégorie dans l'assistant (mode « classe et division »), il la décoche. « Épreuves par équipes »,
                « Équipes mixtes » : à décocher dans Ianseo › Participants (le module ne touche pas aux finales).<ul><?php foreach ($dupFlagged as $d): ?><li><?= Equipe_h($d) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!$catRows): ?>
            <p class="eq-muted">Aucune équipe : aucun club n'a 3 archers cochés « Équipe » dans une même catégorie.</p>
        <?php endif; ?>
        <?php foreach ($catRows as $cat => $rows): [$d, $c] = [null, null];
            foreach ($divisions as $did => $dn) if (strpos($cat, $did) === 0 && isset($classes[substr($cat, strlen($did))])) { $d = $dn; $c = $classes[substr($cat, strlen($did))]['name']; } ?>
            <h4><?= Equipe_h($cat . ($d ? " — $d — $c" : '')) ?></h4>
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
    </div>

    <div class="eq-card">
        <h3>Équipes libres — classement du module</h3>
        <div class="eq-actions" style="margin:0 0 8px">
            <a class="eq-btn" href="./equipes-pdf.php" target="_blank">Classement des équipes libres (PDF)</a>
            <?php if ($free): ?><span class="eq-grow"></span>
            <button class="ghost danger-ghost" data-del="libre-all" <?= $canWrite ? '' : 'disabled' ?>>Supprimer toutes les équipes libres</button><?php endif; ?>
        </div>
        <?php if (!$free): ?>
            <p class="eq-muted">Aucune équipe libre : utilisez le mode « Libre » de l'assistant.</p>
        <?php endif; ?>
        <?php foreach ($free as $g): ?>
            <h4><?= Equipe_h($g['label']) ?> <span class="b">Classes : <?= Equipe_h(implode(', ', $g['classes'])) ?></span></h4>
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
    </div>
</div>
<?php
include('Common/Templates/tail.php');
