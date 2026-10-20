<?php
/**
 * lib/update.php — mise à jour du module « Équipes » depuis GitHub.
 *
 * Même mécanisme que les modules TDM, Greffe, Sections du menu et Aide au développement.
 * Le dépôt public (module.json) se lit sans jeton ; un dépôt privé en demande un :
 *   module.json       d'où vient le module (dépôt, branche, dossier). Configuration
 *                     LOCALE : jamais dans files[], jamais écrasée par une mise à jour.
 *   module.local.php  facultatif, LOCAL aussi : le jeton GitHub d'un dépôt privé,
 *                     return ['github_token' => '…'];  Fichier PHP et non JSON : le
 *                     serveur web l'exécute (il n'affiche rien) au lieu de le servir
 *                     tel quel, alors que module.json se lit dans le navigateur.
 *                     Il peut aussi remplacer les autres réglages de module.json.
 *   version.json  version, notes et liste files[] des fichiers du module. Il
 *                 vient du dépôt : tout ce qu'on y lit est une donnée non fiable.
 *
 * Fonctions sans dépendance à ianseo (testables en ligne de commande) : le
 * dossier du module est toujours passé en paramètre.
 *
 * Règles de sûreté :
 *   - seuls des chemins relatifs simples, à l'intérieur du module, sont acceptés ;
 *   - tous les fichiers sont téléchargés AVANT d'écrire quoi que ce soit : un
 *     seul échec, et rien n'est modifié ;
 *   - chaque fichier est écrit dans un fichier temporaire puis renommé ;
 *     version.json en dernier, pour qu'une mise à jour interrompue reste visible ;
 *   - une installation de développement (dossier .git) est refusée.
 */

/** Configuration par défaut : dépôt à renseigner dans module.json. */
function Equipe_upd_defaults()
{
    return [
        'github_url'    => '',
        'github_branch' => 'main',
        'github_path'   => '',
        'github_token'  => '',
    ];
}

/**
 * Lit module.json, puis module.local.php (qui l'emporte) ; les clés absentes prennent
 * la valeur par défaut.
 *
 * @param string $dir Dossier du module.
 * @return array github_url, github_branch, github_path, github_token.
 */
function Equipe_upd_config($dir)
{
    $cfg = Equipe_upd_defaults();
    $sources = [];
    if (is_file($dir . '/module.json')) $sources[] = json_decode((string) file_get_contents($dir . '/module.json'), true);
    if (is_file($dir . '/module.local.php')) $sources[] = include $dir . '/module.local.php';
    foreach ($sources as $d) {
        if (!is_array($d)) continue;
        foreach ($cfg as $k => $v) {
            if (isset($d[$k]) && is_string($d[$k])) $cfg[$k] = trim($d[$k]);
        }
    }
    return $cfg;
}

/**
 * Adresse d'un fichier du module sur GitHub.
 *
 * Avec un jeton (dépôt privé) : l'API GitHub (api.github.com/…/contents/…?ref=…),
 * qui renvoie le fichier brut grâce à l'en-tête Accept de Equipe_upd_http_get() et
 * n'a pas le cache de 5 minutes des adresses « raw ». Sans jeton : raw.githubusercontent.com.
 *
 * @param string $ref Tag ou branche à lire ; vide = la branche de module.json.
 * @return string Vide si l'adresse du dépôt est inutilisable.
 */
function Equipe_upd_raw_url(array $cfg, $rel, $ref = '')
{
    if (!preg_match('#github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#i', $cfg['github_url'], $m)) return '';
    $branch = preg_match('#^[A-Za-z0-9_./-]+$#', $cfg['github_branch']) ? $cfg['github_branch'] : 'main';
    if ($ref !== '' && preg_match('#^[A-Za-z0-9_.-]+$#', $ref)) $branch = $ref;
    $path = trim($cfg['github_path'], '/');
    if ($path !== '' && !Equipe_upd_safe_path($path)) return '';
    $file = ($path !== '' ? $path . '/' : '') . $rel;
    if ($cfg['github_token'] !== '') {
        return 'https://api.github.com/repos/' . $m[1] . '/' . $m[2] . '/contents/' . $file . '?ref=' . rawurlencode($branch);
    }
    return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $branch . '/' . $file;
}

/**
 * Chemin relatif acceptable pour un fichier du module ?
 *
 * Lettres, chiffres, « _ - . / » seulement ; ni chemin absolu, ni « .. », ni
 * dossier caché, ni module.json / module.local.php (configuration locale).
 */
function Equipe_upd_safe_path($rel)
{
    $rel = (string) $rel;
    if ($rel === '' || strlen($rel) > 120 || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*$#', $rel)) return false;
    foreach (explode('/', $rel) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || $part[0] === '.') return false;
    }
    foreach (['module.json', 'module.local.php'] as $local) {
        if (strcasecmp($rel, $local) === 0) return false;
    }
    return true;
}

/**
 * Télécharge un fichier (https). curl si disponible, sinon file_get_contents.
 *
 * @return string|false Contenu, ou false en cas d'échec (y compris HTTP ≠ 200).
 */
function Equipe_upd_http_get($url, $token = '')
{
    $headers = ['User-Agent: ianseo-equipe-updater'];
    if ($token !== '') {
        // Dépôt privé, lu par l'API : le jeton, et le fichier brut plutôt qu'une fiche JSON.
        $headers[] = 'Authorization: token ' . $token;
        $headers[] = 'Accept: application/vnd.github.raw';
    }

    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return ($body !== false && $code === 200) ? $body : false;
    }

    $ctx = stream_context_create(['http' => [
        'header'        => implode("\r\n", $headers) . "\r\n",
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = isset($http_response_header[0]) ? $http_response_header[0] : '';
    return ($body !== false && preg_match('#\s200\s#', $status . ' ')) ? $body : false;
}

/** version.json local, ou null. */
function Equipe_upd_local($dir)
{
    $f = $dir . '/version.json';
    $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    return is_array($d) ? $d : null;
}

/**
 * version.json du dépôt, validé.
 *
 * @return array Manifeste, ou ['_error' => message].
 */
function Equipe_upd_remote(array $cfg)
{
    $url = Equipe_upd_raw_url($cfg, 'version.json');
    if ($cfg['github_url'] === '') return ['_error' => 'Aucun dépôt renseigné dans module.json : mise à jour indisponible pour le moment.'];
    if ($url === '') return ['_error' => 'Adresse du dépôt GitHub invalide dans module.json.'];
    $raw = Equipe_upd_http_get($url . (strpos($url, '?') === false ? '?' : '&') . 't=' . time(), $cfg['github_token']);
    if ($raw === false || $raw === '') {
        return ['_error' => $cfg['github_token'] === ''
            ? 'GitHub injoignable, ou version.json introuvable sur le dépôt (dépôt privé : jeton GitHub dans module.local.php).'
            : 'GitHub injoignable, version.json introuvable, ou jeton GitHub refusé (expiré, ou sans accès en lecture au dépôt).'];
    }
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['version']) || !preg_match('/^\d+\.\d+\.\d+$/', (string) $d['version'])) {
        return ['_error' => 'version.json du dépôt illisible.'];
    }
    $files = is_array($d['files'] ?? null) ? $d['files'] : [];
    $d['files'] = array_values(array_filter($files, 'Equipe_upd_safe_path'));
    if (!$d['files'] || count($d['files']) !== count($files)) {
        return ['_error' => 'Liste de fichiers du dépôt vide ou contenant des chemins refusés.'];
    }
    if (!in_array('version.json', $d['files'], true)) $d['files'][] = 'version.json';
    return $d;
}

/**
 * Nouveautés d'une seule version, extraites des notes de version.json. Les notes
 * enchaînent toutes les versions, la plus récente d'abord : « 1.3.2 — … 1.3.1 — … ».
 * Fonction pure.
 *
 * @return string La partie de $version (sans le numéro), sinon la première ; tout le
 *                texte s'il n'a pas ce format.
 */
function Equipe_upd_version_notes($notes, $version)
{
    $notes = trim((string) $notes);
    if (!preg_match_all('/(?:^|\s)(\d+\.\d+\.\d+) — /u', $notes, $m, PREG_OFFSET_CAPTURE)) return $notes;
    $parts = [];
    foreach ($m[1] as $i => [$v, $pos]) {
        $start = $pos + strlen($v . ' — ');
        $end = isset($m[1][$i + 1]) ? $m[1][$i + 1][1] : strlen($notes);
        $parts[$v] = trim(substr($notes, $start, $end - $start));
    }
    return $parts[(string) $version] ?? reset($parts);
}

/**
 * État de la mise à jour.
 *
 * @return array local (version|null), remote (version|null), notes (de la version
 *               disponible seulement), date,
 *               update (bool), dev (installation Git), error (message|null).
 */
function Equipe_upd_check($dir)
{
    $local = Equipe_upd_local($dir);
    $remote = Equipe_upd_remote(Equipe_upd_config($dir));
    $lv = $local['version'] ?? null;
    if (isset($remote['_error'])) {
        return ['local' => $lv, 'remote' => null, 'notes' => '', 'date' => '', 'update' => false,
                'dev' => is_dir($dir . '/.git'), 'error' => $remote['_error']];
    }
    return [
        'local'  => $lv,
        'remote' => $remote['version'],
        'notes'  => Equipe_upd_version_notes($remote['notes'] ?? '', $remote['version']),   // la dernière version seulement
        'date'   => (string) ($remote['date'] ?? ''),
        'update' => $lv === null || version_compare($remote['version'], $lv, '>'),
        'dev'    => is_dir($dir . '/.git'),
        'error'  => null,
    ];
}

/**
 * Installe la version du dépôt.
 *
 * @param string $dir Dossier du module.
 * @param bool $force Réinstaller même si la version est identique.
 * @return array ['ok' => bool, 'message' => texte, 'files' => nombre écrit].
 */
function Equipe_upd_apply($dir, $force = false)
{
    if (is_dir($dir . '/.git')) {
        return ['ok' => false, 'files' => 0, 'message' => 'Installation de développement (dossier .git) : mettez-la à jour avec Git, pas depuis cette page.'];
    }
    $cfg = Equipe_upd_config($dir);
    $remote = Equipe_upd_remote($cfg);
    if (isset($remote['_error'])) return ['ok' => false, 'files' => 0, 'message' => $remote['_error']];

    $lv = Equipe_upd_local($dir)['version'] ?? null;
    if (!$force && $lv !== null && !version_compare($remote['version'], $lv, '>')) {
        return ['ok' => true, 'files' => 0, 'message' => 'Le module est déjà à jour (' . $lv . ').'];
    }

    // 1. Tout télécharger d'abord, depuis le tag de la version (« v1.0.2 ») : la branche est
    // gardée en cache 5 minutes par GitHub, qui servait des fichiers de l'ancienne version juste
    // après une publication. Sans tag publié, on lit la branche.
    $content = [];
    $ref = 'v' . $remote['version'];
    if (Equipe_upd_http_get(Equipe_upd_raw_url($cfg, 'version.json', $ref), $cfg['github_token']) === false) $ref = '';
    foreach ($remote['files'] as $rel) {
        $body = Equipe_upd_http_get(Equipe_upd_raw_url($cfg, $rel, $ref), $cfg['github_token']);
        if ($body === false || $body === '') {
            return ['ok' => false, 'files' => 0, 'message' => 'Téléchargement impossible : ' . $rel . '. Aucun fichier n\'a été modifié.'];
        }
        $content[$rel] = $body;
    }

    // 2. Écrire, version.json en dernier.
    $order = array_keys($content);
    usort($order, function ($a, $b) { return ($a === 'version.json') <=> ($b === 'version.json'); });
    $n = 0;
    foreach ($order as $rel) {
        $dest = $dir . '/' . $rel;
        if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) {
            return ['ok' => false, 'files' => $n, 'message' => 'Dossier impossible à créer : ' . dirname($rel) . ' (droits d\'écriture ?).'];
        }
        $tmp = $dest . '.tmp-' . bin2hex(random_bytes(4));
        // Windows refuse de remplacer par renommage un fichier ouvert, dont la page qui fait la mise à
        // jour (admin/update.php) : on écrit alors directement dans le fichier.
        $written = @file_put_contents($tmp, $content[$rel]) !== false
            && (@rename($tmp, $dest) || @file_put_contents($dest, $content[$rel]) !== false);
        if (is_file($tmp)) @unlink($tmp);
        if (!$written) {
            return ['ok' => false, 'files' => $n, 'message' => 'Écriture impossible : ' . $rel . ' (droits d\'écriture sur le dossier du module ?). '
                . $n . ' fichier(s) déjà remplacé(s) : relancez la mise à jour.'];
        }
        $n++;
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    return ['ok' => true, 'files' => $n, 'message' => 'Module mis à jour en version ' . $remote['version'] . ' (' . $n . ' fichiers).'];
}
