<?php
/**
 * index.php — assistant de composition des équipes (classement natif division/classe).
 *
 *   1. Mode (classe et division / libre) et critères (divisions, classes)
 *   2. Composition club par club (proposée puis ajustée)
 *   3. Aperçu, application, puis équipes créées (equipes.php) ou classement
 *      par équipes natif d'Ianseo
 *
 * Le contenu est construit par index.js à partir de action.php.
 */
require_once __DIR__ . '/config.php';

$state = Equipe_state();
$JS_SCRIPT[] = '<link href="./index.css" rel="stylesheet" type="text/css">';
$JS_SCRIPT[] = '<script>window.EQUIPE = ' . json_encode([
    'action' => $EQUIPE_URL . 'action.php',
    'print' => $CFG->ROOT_DIR . 'Qualification/PrnTeam.php',
    'teams' => $EQUIPE_URL . 'equipes.php',
    'teamsPdf' => $EQUIPE_URL . 'equipes-pdf.php',
    'token' => Equipe_token(),
]) . ';</script>';
$JS_SCRIPT[] = '<script src="./index.js"></script>';
$PAGE_TITLE = 'Équipes — assistant';
include('Common/Templates/head.php');
?>
<div id="eq">
    <div class="eq-head">
        <h2>Équipes — assistant de composition</h2>
        <nav><a href="./" class="cur">Assistant</a><a href="./equipes.php">Équipes créées</a></nav>
    </div>
    <?= Equipe_guard_banner($GUARD) ?>
    <div class="eq-banner eq-orange"><b>Module indépendant d'IANSEO, sans garantie ni SAV</b> : il n'est pas développé par
        l'équipe IANSEO et peut contenir des bugs. <b>Sauvegardez la compétition</b> avant de commencer et après chaque
        application (<a href="<?= Equipe_h($CFG->ROOT_DIR . 'Tournament/TournamentExport.php') ?>">Compétition › Exports › Export de la compétition</a> ;
        il contient la case « Équipe » des archers et les équipes libres). Détails :
        <a href="https://github.com/CorentinA1/EquipeModuleIanseo/blob/main/docs/AVERTISSEMENT.md" target="_blank" rel="noopener">À lire avant d'utiliser le module</a>.</div>
    <div class="eq-banner eq-info">📖 <b>Mode d'emploi</b> : les deux modes, l'assistant et la page des équipes sont décrits dans
        l'<a href="https://github.com/CorentinA1/EquipeModuleIanseo/blob/main/docs/UTILISATION.md" target="_blank" rel="noopener">utilisation pas à pas</a>.</div>
    <?php if ($state['locked']): ?>
        <div class="eq-banner eq-red"><b>Liste des participants verrouillée :</b> lecture seule.</div>
    <?php elseif ($state['started']): ?>
        <div class="eq-banner eq-red"><b>Le tir a commencé</b> (départ<?= count($state['sessions']) > 1 ? 's' : '' ?>
            <?= Equipe_h(implode(', ', array_keys($state['sessions']))) ?>).
            Changer une équipe change le classement : l'aperçu le montre ; en mode « classe et division »
            (classement d'Ianseo), une confirmation est demandée.</div>
    <?php endif; ?>
    <div id="eq-app"><p class="eq-muted">Chargement…</p></div>
    <details class="eq-card" id="garde-fou">
        <summary>Garde-fou : <?= Equipe_h(['green' => 'vert', 'orange' => 'orange', 'red' => 'rouge'][$GUARD['level']]) ?> (Ianseo <?= Equipe_h($GUARD['version']) ?>)</summary>
        <table class="eq-table">
            <tr><th>Contrôle</th><th>État</th><th>Détail</th></tr>
            <?php foreach ($GUARD['checks'] as $c): ?>
                <tr><td><?= Equipe_h($c['label']) ?></td>
                    <td><span class="b <?= $c['level'] === 'green' ? 'ok' : ($c['level'] === 'red' ? 'red' : 'orange') ?>"><?= Equipe_h($c['level']) ?></span></td>
                    <td><?= Equipe_h($c['detail']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </details>
</div>
<?php
include('Common/Templates/tail.php');
