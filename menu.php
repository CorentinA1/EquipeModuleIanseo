<?php
// Module Equipe : sous-menu « Équipes » du menu Modules (compétition au règlement FR).
// Inclus par Ianseo sur CHAQUE page (Common/Menu.php) : rien d'autre ici,
// ni require, ni requête, ni fonction du module. Une erreur ici bloquerait tout Ianseo.
if (!empty($on) && ($_SESSION['TourLocRule'] ?? '') === 'FR'
    && subFeatureAcl($acl ?? [], AclParticipants, 'pEntries') >= AclReadOnly) {
    // Le premier élément est le titre du sous-menu.
    $ret['MODS']['EQUIPE'][] = 'Équipes';
    $ret['MODS']['EQUIPE'][] = 'Tableau de bord|' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/';
    $ret['MODS']['EQUIPE'][] = 'Composer des équipes|' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/assistant.php';
    $ret['MODS']['EQUIPE'][] = 'Contrôles et historique|' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/controles.php';
    // Administrateur seulement. Avec un module de comptes (AUTH), AclRoot ne
    // suffit pas : il faut aussi la vue administrateur serveur (AUTH_ROOT).
    if (subFeatureAcl($acl ?? [], AclRoot, '') >= AclReadWrite
        && (empty($_SESSION['AUTH_User']) || !empty($_SESSION['AUTH_ROOT']))) {
        // Pastille « mise à jour disponible » : lit seulement l'état gardé par la dernière
        // vérification (lib/update.php, Equipe_upd_notify_*), jamais GitHub. Délai écoulé :
        // une image invisible lance la vérification en arrière-plan (admin/update-check.php).
        $eqMaj = '';
        $eqReal = realpath(__DIR__);
        $eqState = @json_decode((string) @file_get_contents(sys_get_temp_dir() . '/ianseo-maj-' . md5($eqReal !== false ? $eqReal : __DIR__) . '.json'), true);
        $eqLocal = @json_decode((string) @file_get_contents(__DIR__ . '/version.json'), true);
        $eqHave = is_array($eqLocal) ? (string) ($eqLocal['version'] ?? '') : '';
        $eqLatest = is_array($eqState) ? (string) ($eqState['latest'] ?? '') : '';
        if ($eqHave !== '' && preg_match('/^\d+\.\d+\.\d+$/', $eqLatest) && version_compare($eqLatest, $eqHave, '>')) {
            $eqTip = htmlspecialchars('Mise à jour disponible : ' . $eqHave . ' → ' . $eqLatest, ENT_QUOTES, 'UTF-8');
            $eqMaj = '<span class="maj-dot" title="' . $eqTip . '" style="display:inline-block;min-width:15px;height:15px;line-height:15px;padding:0 3px;border-radius:8px;'
                . 'background:var(--text-danger,#d6283a);color:#fff;font-size:10px;font-weight:bold;text-align:center;margin-left:6px;vertical-align:middle">⬆</span>';
            $eqDot = '<span class="maj-dot" title="' . $eqTip . '" style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                . 'background:var(--text-danger,#d6283a);margin-left:5px;vertical-align:middle"></span>';
            $ret['MODS']['EQUIPE'][0] .= $eqDot;
            // Titre « Modules » de la barre : un seul point, quel que soit le nombre de modules concernés.
            if (isset($ret['MODS'][0]) && strpos($ret['MODS'][0], 'maj-dot') === false) {
                $ret['MODS'][0] .= '<span class="maj-dot" title="Mise à jour disponible pour un module" style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                    . 'background:var(--text-danger,#d6283a);margin-left:4px;vertical-align:super"></span>';
            }
        }
        if (!is_array($eqState) || time() >= (int) ($eqState['next'] ?? 0)) {
            $eqMaj .= '<img src="' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/admin/update-check.php" alt="" width="1" height="1" style="position:absolute;opacity:0">';
        }
        $ret['MODS']['EQUIPE'][] = MENU_DIVIDER;
        $ret['MODS']['EQUIPE'][] = 'Mise à jour du module' . $eqMaj . '|' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/admin/update.php';
    }
}
