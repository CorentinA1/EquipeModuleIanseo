<?php
/**
 * assistant.php — composer des équipes, en 4 étapes :
 *
 *   1. Mode (classe et division / libre)
 *   2. Catégories : divisions, classes, archers par équipe
 *   3. Composition club par club (proposée puis ajustée)
 *   4. Vérifier et appliquer, puis écran de fin
 *
 * Le contenu est construit par assistant.js à partir de action.php.
 * Préréglage depuis le tableau de bord (« Modifier ces équipes ») :
 * ?mode=classe|libre&divs[]=…&classes[]=…&go=compose ouvre directement la composition.
 */
require_once __DIR__ . '/config.php';

$state = Equipe_state();
$strings = function ($v) { return array_values(array_filter(array_map('strval', (array) $v), function ($s) { return $s !== '' && strlen($s) <= 10; })); };
$JS_SCRIPT[] = '<link href="./index.css" rel="stylesheet" type="text/css">';
$JS_SCRIPT[] = '<script>window.EQUIPE = ' . json_encode([
    'action' => $EQUIPE_URL . 'action.php',
    'print' => $CFG->ROOT_DIR . 'Qualification/PrnTeam.php',
    'home' => $EQUIPE_URL,
    'teamsPdf' => $EQUIPE_URL . 'equipes-pdf.php',
    'export' => $CFG->ROOT_DIR . 'Tournament/TournamentExport.php',
    'token' => Equipe_token(),
    'preset' => [
        'mode' => in_array($_GET['mode'] ?? '', ['classe', 'libre'], true) ? $_GET['mode'] : '',
        'divs' => $strings($_GET['divs'] ?? []),
        'classes' => $strings($_GET['classes'] ?? []),
        'compose' => ($_GET['go'] ?? '') === 'compose',
    ],
]) . ';</script>';
$JS_SCRIPT[] = '<script src="./assistant.js"></script>';
$PAGE_TITLE = 'Équipes — composer';
include('Common/Templates/head.php');
?>
<div id="eq">
    <?= Equipe_page_header('assistant') ?>
    <?php if ($state['locked']): ?>
        <div class="eq-banner eq-red"><b>Liste des participants verrouillée :</b> lecture seule.</div>
    <?php elseif ($state['started']): ?>
        <div class="eq-banner eq-orange"><b>Le tir a commencé</b> (départ<?= count($state['sessions']) > 1 ? 's' : '' ?>
            <?= Equipe_h(implode(', ', array_keys($state['sessions']))) ?>).
            Changer une équipe change le classement : l'étape « Vérifier » le montre ; en mode « classe et division »
            (classement d'Ianseo), une confirmation est demandée.</div>
    <?php endif; ?>
    <div id="eq-app"><p class="eq-muted">Chargement…</p></div>
    <?= Equipe_page_footer() ?>
</div>
<?php
include('Common/Templates/tail.php');
