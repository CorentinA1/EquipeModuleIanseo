<?php
/**
 * guard-reference.php — références du garde-fou (guard.php).
 *
 * - versions   : versions d'Ianseo validées (garde-fou vert).
 * - functions  : nom => paramètres attendus (début de liste) et empreintes
 *                SHA-1 du code par version. Sans empreinte : signature seule.
 * - columns    : colonnes lues ou écrites par le module, avec leur DATA_TYPE.
 * - constants  : constantes d'Ianseo utilisées.
 *
 * Nouvelle version d'Ianseo : lancer `php dev/empreintes.php`, relire le code
 * des fonctions dont l'empreinte change, puis ajouter la version ici.
 */
return [
    'versions' => ['2026-03-01'],

    'functions' => [
        // Calcul natif du classement par équipes division/classe : code figé.
        'MakeTeams' => [
            'params' => ['Societa', 'Category', 'ToId'],
            'sha1' => [
                '2026-03-01' => '38bea42752d4048b5674aea099ae928c5ea83fe1',
            ],
        ],

        // Fonctions utilitaires : signature seule.
        'getModuleParameter' => ['params' => ['module', 'param', 'defaultValue', 'TourId', 'clear']],
        'setModuleParameter' => ['params' => ['module', 'param', 'value', 'TourId']],
        'CheckTourSession' => ['params' => ['PrintCrack', 'popup']],
        'checkFullACL' => ['params' => ['feature', 'subFeature', 'level', 'redirect', 'TourId']],
        'hasFullACL' => ['params' => ['feature', 'subFeature', 'level', 'TourId']],
        'IsBlocked' => ['params' => ['Bit']],
        'JsonOut' => ['params' => ['JSON']],
        'safe_r_SQL' => ['params' => ['SQL', 'use', 'force']],
        'safe_w_SQL' => ['params' => ['SQL', 'use', 'acc_error']],
        'safe_w_BeginTransaction' => ['params' => []],
        'safe_w_Commit' => ['params' => []],
        'safe_w_Rollback' => ['params' => []],
    ],

    'constants' => [
        'ProgramVersion', 'BIT_BLOCK_PARTICIPANT',
        'AclParticipants', 'AclQualification', 'AclReadOnly', 'AclReadWrite',
    ],

    'columns' => [
        'Entries' => ['EnId' => 'int', 'EnTournament' => 'int', 'EnDivision' => 'varchar', 'EnClass' => 'varchar', 'EnCountry' => 'int', 'EnCountry2' => 'int', 'EnTeamClEvent' => 'tinyint', 'EnTeamFEvent' => 'tinyint', 'EnTeamMixEvent' => 'tinyint', 'EnSex' => 'tinyint', 'EnTargetFace' => 'int', 'EnStatus' => 'tinyint', 'EnAthlete' => 'tinyint', 'EnName' => 'varchar', 'EnFirstName' => 'varchar', 'EnCode' => 'varchar', 'EnTimestamp' => 'timestamp'],
        'Qualifications' => ['QuId' => 'int', 'QuSession' => 'tinyint', 'QuScore' => 'int', 'QuHits' => 'int', 'QuGold' => 'int', 'QuXnine' => 'int', 'QuIrmType' => 'tinyint'],
        'Classes' => ['ClId' => 'varchar', 'ClTournament' => 'int', 'ClSex' => 'tinyint', 'ClDescription' => 'varchar'],
        'Divisions' => ['DivId' => 'varchar', 'DivTournament' => 'int', 'DivDescription' => 'varchar', 'DivAthlete' => 'varchar'],
        'Countries' => ['CoId' => 'int', 'CoTournament' => 'int', 'CoCode' => 'varchar', 'CoName' => 'varchar'],
        'Teams' => ['TeCoId' => 'int', 'TeEvent' => 'varchar', 'TeTournament' => 'int', 'TeFinEvent' => 'tinyint', 'TeScore' => 'smallint', 'TeRank' => 'smallint'],
        'TeamComponent' => ['TcOrder' => 'tinyint', 'TcCoId' => 'int', 'TcEvent' => 'varchar', 'TcTournament' => 'int', 'TcId' => 'int', 'TcFinEvent' => 'tinyint'],
        'TournamentDistances' => ['TdClasses' => 'varchar', 'TdType' => 'smallint', 'TdTournament' => 'int', 'Td1' => 'varchar', 'TdDist1' => 'tinyint', 'TdDist2' => 'tinyint'],
        'Tournament' => ['ToId' => 'int', 'ToType' => 'smallint', 'ToNumDist' => 'tinyint', 'ToElabTeam' => 'tinyint'],
        'TargetFaces' => ['TfId' => 'int', 'TfTournament' => 'int', 'TfName' => 'varchar', 'TfClasses' => 'varchar', 'TfRegExp' => 'varchar', 'TfDefault' => 'varchar', 'TfT1' => 'int', 'TfW1' => 'int'],
        'Session' => ['SesTournament' => 'int', 'SesOrder' => 'tinyint', 'SesType' => 'varchar', 'SesDtStart' => 'datetime'],
        'IrmTypes' => ['IrmId' => 'tinyint', 'IrmShowRank' => 'tinyint'],
        'ModulesParameters' => ['MpModule' => 'varchar', 'MpParameter' => 'varchar', 'MpTournament' => 'int', 'MpValue' => 'mediumtext'],
    ],
];
