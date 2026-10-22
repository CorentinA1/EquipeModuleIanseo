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
        $ret['MODS']['EQUIPE'][] = MENU_DIVIDER;
        $ret['MODS']['EQUIPE'][] = 'Mise à jour du module|' . $CFG->ROOT_DIR . 'Modules/Custom/' . basename(__DIR__) . '/admin/update.php';
    }
}
