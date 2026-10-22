<?php
/**
 * controles.php — contrôles et historique : archers inscrits plusieurs fois,
 * journal des applications, diagnostic complet du garde-fou. Lecture seule.
 */
require_once __DIR__ . '/config.php';

$dups = Equipe_dup_control(Equipe_entries());
$log = array_slice(Equipe_log_read(), 0, 100);
$yes = function ($v) { return $v ? '<b>oui</b>' : 'non'; };

$PAGE_TITLE = 'Équipes — contrôles et historique';
$JS_SCRIPT[] = '<link href="./index.css" rel="stylesheet" type="text/css">';
include('Common/Templates/head.php');
?>
<div id="eq">
    <?= Equipe_page_header('controles') ?>

    <section class="eq-card">
        <h3>Archers inscrits plusieurs fois</h3>
        <p class="eq-muted">Pour les équipes, seul compte le <b>premier score</b> de l'archer : son premier départ (date du départ dans
            Ianseo si elle est saisie, sinon numéro du départ). Sur ses autres inscriptions, toutes les cases équipe doivent être à <b>non</b>.</p>
        <?php if (!$dups['archers']): ?>
            <div class="eq-banner eq-green">Aucun archer n'est inscrit plusieurs fois.</div>
        <?php else: ?>
            <?php if ($dups['bad']): ?>
                <div class="eq-banner eq-orange"><b><?= $dups['bad'] ?> inscription(s) en défaut.</b> Case « Équipe » : composez la catégorie dans
                    l'assistant en mode « classe et division », il la décoche. « Épreuves par équipes » et « Équipes mixtes » : le module n'y
                    touche pas (finales) ; décochez-les dans Ianseo › Participants.</div>
            <?php else: ?>
                <div class="eq-banner eq-green">Toutes les inscriptions qui ne comptent pas ont leurs cases équipe à non.</div>
            <?php endif; ?>
            <div class="eq-scroll">
                <table class="eq-table">
                    <tr><th>Archer</th><th>Départ</th><th>Catégorie</th><th>Compte</th><th>Équipe</th><th>Épreuves par équipes</th><th>Équipes mixtes</th></tr>
                    <?php foreach ($dups['archers'] as $a): foreach ($a['rows'] as $i => $r): ?>
                        <tr<?= $r['bad'] ? ' class="eq-bad"' : '' ?>><?php if (!$i): ?><td rowspan="<?= count($a['rows']) ?>"><?= Equipe_h($a['name']) ?></td><?php endif; ?>
                            <td><?= $r['session'] ?: '?' ?></td><td><?= Equipe_h($r['cat']) ?></td>
                            <td><?= $r['kept'] ? '<span class="b ok">oui</span>' : 'non' ?></td>
                            <td><?= $yes($r['team']) ?></td><td><?= $yes($r['teamf']) ?></td><td><?= $yes($r['teammix']) ?></td></tr>
                    <?php endforeach; endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="eq-card">
        <h3>Journal des applications</h3>
        <?php if (!$log): ?>
            <p class="eq-muted">Journal vide.</p>
        <?php else: ?>
            <div class="eq-scroll">
                <table class="eq-table">
                    <tr><th>Date</th><th>Par</th><th>Action</th><th>Détail</th></tr>
                    <?php foreach ($log as $l): ?>
                        <tr><td><?= Equipe_h($l[0]) ?></td><td><?= Equipe_h($l[1]) ?></td><td><?= Equipe_h($l[2]) ?></td><td><?= Equipe_h($l[3]) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php if (count($log) === 100): ?><p class="eq-muted">Les 100 dernières lignes.</p><?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="eq-card" id="garde-fou">
        <h3>Garde-fou</h3>
        <?php $lvl = ['green' => 'vert', 'orange' => 'orange', 'red' => 'rouge'][$GUARD['level']]; ?>
        <div class="eq-banner <?= ['green' => 'eq-green', 'orange' => 'eq-orange', 'red' => 'eq-red'][$GUARD['level']] ?>">
            <b><?= ucfirst($lvl) ?></b> (Ianseo <?= Equipe_h($GUARD['version']) ?>) : <?= [
                'green' => 'toutes les vérifications passent, le module peut écrire.',
                'orange' => 'version d\'Ianseo non validée, mais le code surveillé est identique : le module reste utilisable.',
                'red' => 'le module est en lecture seule.'][$GUARD['level']] ?></div>
        <details <?= $GUARD['level'] === 'green' ? '' : 'open' ?>>
            <summary>Détail des vérifications</summary>
            <div class="eq-scroll">
                <table class="eq-table">
                    <tr><th>Contrôle</th><th>État</th><th>Détail</th></tr>
                    <?php foreach ($GUARD['checks'] as $c): ?>
                        <tr><td><?= Equipe_h($c['label']) ?></td>
                            <td><span class="b <?= $c['level'] === 'green' ? 'ok' : ($c['level'] === 'red' ? 'red' : 'orange') ?>"><?= Equipe_h($c['level']) ?></span></td>
                            <td><?= Equipe_h($c['detail']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </details>
    </section>

    <?= Equipe_page_footer() ?>
</div>
<?php
include('Common/Templates/tail.php');
