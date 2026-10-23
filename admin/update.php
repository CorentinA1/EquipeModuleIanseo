<?php
/**
 * admin/update.php — Équipes : mise à jour du module depuis GitHub.
 *
 * Réservé à l'administrateur : AclRoot en écriture et, si un module de comptes
 * (AUTH) est actif, la vue « administrateur serveur » (AUTH_ROOT), car AUTH
 * accorde AclRoot à tout organisateur connecté hors compétition.
 * Ne demande pas de compétition ouverte. Ne passe pas par config.php (pas de
 * garde-fou ici : la mise à jour doit rester possible même si le garde-fou est rouge).
 */
require_once dirname(__DIR__, 4) . '/config.php';
require_once dirname(__DIR__) . '/lib/update.php';

checkFullACL(AclRoot, '', AclReadWrite);
if (trim((string) ($_SESSION['AUTH_User'] ?? '')) !== '' && empty($_SESSION['AUTH_ROOT'])) {
    CD_redirect($CFG->ROOT_DIR . 'noAccess.php');
    die();
}

$dir = dirname(__DIR__);
$h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
if (empty($_SESSION['EQUIPE_UPD_TOKEN'])) $_SESSION['EQUIPE_UPD_TOKEN'] = bin2hex(random_bytes(16));
$token = $_SESSION['EQUIPE_UPD_TOKEN'];

$check = null;
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        http_response_code(403);
        exit('Jeton de sécurité invalide : rechargez la page.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update' || $action === 'force') $result = Equipe_upd_apply($dir, $action === 'force');
    $check = Equipe_upd_check($dir);   // après une mise à jour, relit la version installée
} elseif (Equipe_upd_notify_pending($dir) !== null) {
    // Pastille du menu affichée : vérifie tout de suite, pour montrer les nouveautés et le bouton « Mettre à jour ».
    $check = Equipe_upd_check($dir);
}

$cfg = Equipe_upd_config($dir);
$local = Equipe_upd_local($dir);
$isDev = is_dir($dir . '/.git');
$field = '<input type="hidden" name="token" value="' . $h($token) . '">';
$url = $CFG->ROOT_DIR . 'Modules/Custom/' . basename($dir) . '/';
$repo = rtrim(preg_replace('#\.git$#', '', $cfg['github_url']), '/');

$PAGE_TITLE = 'Équipes - Mise à jour du module';
$JS_SCRIPT[] = '<link href="' . $h($url) . 'index.css" rel="stylesheet" type="text/css">';
include $CFG->DOCUMENT_PATH . 'Common/Templates/head.php';
?>
<div id="eq" style="max-width:820px">
    <div class="eq-head">
        <h2>Équipes — mise à jour du module</h2>
        <nav><a href="<?= $h($url) ?>">Assistant</a></nav>
    </div>

    <?php if ($result): ?>
        <div class="eq-banner <?= $result['ok'] ? 'eq-green' : 'eq-red' ?>"><?= $h($result['message']) ?></div>
    <?php endif; ?>
    <?php if ($isDev): ?>
        <div class="eq-banner eq-orange">Cette installation est une copie de développement (dossier <code>.git</code>) :
            la mise à jour depuis cette page est désactivée. Utilisez Git.</div>
    <?php endif; ?>
    <div class="eq-banner eq-orange"><b>Avant de mettre à jour</b> : sauvegardez vos compétitions
        (<a href="<?= $h($CFG->ROOT_DIR . 'Tournament/TournamentExport.php') ?>">Compétition › Exports › Export de la compétition</a>).
        Le module ne touche pas aux données pendant la mise à jour, mais la nouvelle version peut se comporter autrement.</div>

    <div class="eq-card">
        <h3>Version installée : <?= $h($local['version'] ?? 'inconnue') ?></h3>
        <div class="eq-muted">Source : <?= $repo !== '' ? '<a href="' . $h($repo) . '" target="_blank" rel="noopener">' . $h($repo) . '</a>' : 'pas encore renseignée' ?>
            (branche <?= $h($cfg['github_branch']) ?>) — fichier <code>module.json</code>.
            <?php if ($cfg['github_token'] !== ''): ?><br>Jeton GitHub (fichier <code>module.local.php</code>) : renseigné.<?php endif; ?></div>
        <form method="post" class="eq-actions">
            <?= $field ?><input type="hidden" name="action" value="check">
            <button type="submit" class="ghost">Vérifier les mises à jour</button>
        </form>

        <?php if ($check): ?>
            <?php if ($check['error']): ?>
                <div class="eq-banner eq-red"><?= $h($check['error']) ?></div>
            <?php else: ?>
                <p>Version disponible : <b><?= $h($check['remote']) ?></b>
                    <?= $check['date'] !== '' ? '<span class="eq-muted">(' . $h($check['date']) . ')</span>' : '' ?>
                    <span class="b <?= $check['update'] ? 'orange' : 'ok' ?>"><?= $check['update'] ? 'Mise à jour disponible' : '✓ À jour' ?></span></p>
                <?php if ($check['notes'] !== ''): ?>
                    <div class="eq-banner eq-info" style="white-space:pre-line"><?= $h($check['notes']) ?></div>
                    <?php if ($repo !== ''): ?><p><a href="<?= $h($repo . '/blob/main/docs/NOUVEAUTES.md') ?>" target="_blank" rel="noopener">Toutes les nouveautés</a></p><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($check && !$check['error'] && !$isDev): ?>
    <div class="eq-card">
        <?php if ($check['update']): ?>
            <h3>Installer la version <?= $h($check['remote']) ?></h3>
            <form method="post" onsubmit="return confirm('Mettre à jour le module Équipes en version <?= $h($check['remote']) ?> ?');">
                <?= $field ?><input type="hidden" name="action" value="update">
                <button type="submit">Mettre à jour</button>
            </form>
        <?php else: ?>
            <details><summary>Forcer la réinstallation de la version <?= $h($check['remote']) ?></summary>
                <form method="post" class="eq-actions" onsubmit="return confirm('Réinstaller tous les fichiers du module ?');">
                    <?= $field ?><input type="hidden" name="action" value="force">
                    <button type="submit" class="ghost">Réinstaller</button>
                </form>
            </details>
        <?php endif; ?>
        <p class="eq-muted">Tous les fichiers sont téléchargés avant d'en remplacer un seul : en cas d'échec, rien n'est
            modifié. <code>module.json</code> et <code>module.local.php</code> sont gardés. Le serveur web doit pouvoir écrire
            dans le dossier du module.</p>
    </div>
    <?php endif; ?>

    <div class="eq-card"><h3>Désinstaller</h3>
        <p class="eq-muted">Supprimer le dossier <code><?= $h(basename($dir)) ?></code> dans Modules/Custom/ suffit : le module
            ne crée aucune table. La case « Équipe » des archers reste telle que le module l'a laissée (donnée normale
            d'IANSEO). Les réglages du module (équipes libres, journal, dernière annulation) restent dans
            <code>ModulesParameters</code> ; facultatif : <code>DELETE FROM ModulesParameters WHERE MpModule='Equipe';</code></p></div>
</div>
<?php include $CFG->DOCUMENT_PATH . 'Common/Templates/tail.php'; ?>
