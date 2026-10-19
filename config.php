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

/** Bandeau du garde-fou (vide si vert). */
function Equipe_guard_banner($guard)
{
    if ($guard['level'] === 'green') return '';
    $red = $guard['level'] === 'red';
    $bad = array_filter($guard['checks'], function ($c) { return $c['level'] !== 'green'; });
    $html = '<div class="eq-banner ' . ($red ? 'eq-red' : 'eq-orange') . '"><b>'
        . ($red ? 'Garde-fou rouge : module en lecture seule.' : 'Version d\'Ianseo non validée : le code surveillé est identique, le module reste utilisable. Exportez la compétition (.ianseo) avant d\'appliquer.')
        . '</b><ul>';
    foreach ($bad as $c) $html .= '<li>' . Equipe_h($c['label']) . ' : ' . Equipe_h($c['detail']) . '</li>';
    return $html . '</ul><a href="#garde-fou">Diagnostic complet en bas de page</a></div>';
}
