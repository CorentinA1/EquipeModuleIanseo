<?php
/**
 * config.php — amorçage des pages HTML du module Equipe.
 *
 * Configuration d'Ianseo, compétition ouverte, droit de lecture des
 * participants, puis garde-fou (avant toute autre action).
 */
require_once dirname(__FILE__, 4) . '/config.php';
CheckTourSession(true);
checkFullACL(AclParticipants, 'pEntries', AclReadOnly);

require_once __DIR__ . '/guard.php';
$GUARD = Equipe_guard();
require_once __DIR__ . '/lib.php';

// Adresse du module (le dossier peut s'appeler EQUIPE ou Equipe).
$EQUIPE_URL = $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/';

/** Jeton anti-CSRF de la session, exigé par action.php pour toute écriture. */
function Equipe_token()
{
    if (empty($_SESSION['EQUIPE_TOKEN'])) $_SESSION['EQUIPE_TOKEN'] = bin2hex(random_bytes(16));
    return $_SESSION['EQUIPE_TOKEN'];
}

/** Bandeau du garde-fou (vide si vert) ; le diagnostic complet est sur la page Contrôles. */
function Equipe_guard_banner($guard)
{
    if ($guard['level'] === 'green') return '';
    $red = $guard['level'] === 'red';
    $bad = array_filter($guard['checks'], function ($c) { return $c['level'] !== 'green'; });
    $html = '<div class="eq-banner ' . ($red ? 'eq-red' : 'eq-orange') . '"><b>'
        . ($red ? 'Garde-fou rouge : module en lecture seule.' : 'Version d\'Ianseo non validée : le code surveillé est identique, le module reste utilisable. Exportez la compétition (.ianseo) avant d\'appliquer.')
        . '</b><ul>';
    foreach ($bad as $c) $html .= '<li>' . Equipe_h($c['label']) . ' : ' . Equipe_h($c['detail']) . '</li>';
    return $html . '</ul><a href="./controles.php#garde-fou">Diagnostic complet</a></div>';
}

/** Adresse de la documentation du module (dépôt public). */
const EQUIPE_DOC = 'https://github.com/CorentinA1/EquipeModuleIanseo/blob/main/docs/';

/**
 * En-tête commun des pages : titre, onglets du module, lien vers le mode d'emploi, bandeau du garde-fou.
 *
 * @param string $cur Onglet en cours : 'index', 'assistant' ou 'controles'.
 */
function Equipe_page_header($cur)
{
    global $GUARD;
    $tabs = ['index' => ['./', 'Tableau de bord'], 'assistant' => ['./assistant.php', 'Composer des équipes'],
             'controles' => ['./controles.php', 'Contrôles et historique']];
    $html = '<div class="eq-head"><h2>Équipes</h2><nav aria-label="Module Équipes">';
    foreach ($tabs as $k => [$href, $label]) {
        $html .= '<a href="' . $href . '"' . ($k === $cur ? ' class="cur" aria-current="page"' : '') . '>' . $label . '</a>';
    }
    $html .= '<a class="eq-help" href="' . EQUIPE_DOC . 'UTILISATION.md" target="_blank" rel="noopener">Mode d\'emploi</a></nav></div>';
    return $html . Equipe_guard_banner($GUARD);
}

/** Pied de page commun : l'avertissement, sur une ligne. */
function Equipe_page_footer()
{
    global $CFG;
    return '<p class="eq-foot">Module indépendant d\'IANSEO, sans garantie ni SAV. <b>Sauvegardez la compétition</b> avant chaque application ('
        . '<a href="' . Equipe_h($CFG->ROOT_DIR . 'Tournament/TournamentExport.php') . '">Compétition › Exports › Export de la compétition</a>). '
        . '<a href="' . EQUIPE_DOC . 'AVERTISSEMENT.md" target="_blank" rel="noopener">À lire avant d\'utiliser le module</a></p>';
}
