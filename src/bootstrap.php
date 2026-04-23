<?php
// src/bootstrap.php
// Code exécuté automatiquement dès que le package est chargé par Composer

// ============================================================
//  GARDE : Ne pas s'exécuter dans le contexte Composer
// ============================================================
if (php_sapi_name() === 'cli' && (
    class_exists('Composer\Factory') ||
    getenv('COMPOSER_BINARY') !== false ||
    getenv('COMPOSER') !== false
)) {
    return;
}

use NAbySy\xNAbySyGS;

define('XNABYSY_LOADED', true);

// ============================================================
//  1. LOGGING BOOTSTRAP AUTONOME
//  Disponible avant toute initialisation de N
// ============================================================

function nabysyBootstrapLog(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap.log';
    $line    = date('d/m/Y H:i:s') . " [{$level}] " . $message . PHP_EOL;
    error_log("[NAbySyGS Bootstrap] {$message}");
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Capture les erreurs fatales non attrapables par try/catch
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        nabysyBootstrapLog(
            "[FATAL] {$error['message']} dans {$error['file']} ligne {$error['line']}",
            'FATAL'
        );
        nabysyOpenBootstrapLog();
    }
});

// ============================================================
//  2. AFFICHAGE DES LOGS BOOTSTRAP EN CAS D'ERREUR
// ============================================================

function nabysyOpenBootstrapLog(): void
{
    $logFile     = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap.log';
    $logHtmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap_log.html';

    if (!file_exists($logFile)) return;

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return;

    // Générer le HTML des logs
    $html  = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/>';
    $html .= '<title>NAbySyGS — Bootstrap Log</title>';
    $html .= '<style>
        body  { background:#0a0f0d; color:#e8f0eb; font-family:monospace; padding:32px; }
        h2    { color:#f5a623; margin-bottom:16px; }
        .log  { background:#111a15; border:1px solid #1f3026; border-radius:8px; padding:16px; }
        .line { padding:4px 0; border-bottom:1px solid #1f3026; font-size:0.85rem; }
        .line:last-child { border-bottom:none; }
        .INFO  { color:#e8f0eb; }
        .ERROR { color:#ff5252; }
        .FATAL { color:#ff5252; font-weight:bold; }
        .WARN  { color:#f5a623; }
    </style></head><body>';
    $html .= '<h2>🦅 NAbySyGS — Bootstrap Log</h2><div class="log">';

    foreach ($lines as $line) {
        $level = 'INFO';
        if (str_contains($line, '[FATAL]')) $level = 'FATAL';
        elseif (str_contains($line, '[ERROR]')) $level = 'ERROR';
        elseif (str_contains($line, '[WARN]'))  $level = 'WARN';
        $html .= '<div class="line ' . $level . '">' . htmlspecialchars($line) . '</div>';
    }

    $html .= '</div><p style="margin-top:16px;color:#6b8c74;font-size:0.75rem;">';
    $html .= 'Fichier log : ' . htmlspecialchars($logFile);
    $html .= '</p></body></html>';

    @file_put_contents($logHtmlFile, $html);

    // Ouvrir dans le navigateur selon l'OS
    $url = 'file:///' . str_replace('\\', '/', realpath($logHtmlFile) ?: $logHtmlFile);
    if (PHP_OS_FAMILY === 'Windows') {
        $safeUrl = str_replace(['"', '^', '&', '<', '>', '|'], '', $url);
        @exec('cmd /c start "" "' . $safeUrl . '" > NUL 2>&1');
    } elseif (PHP_OS_FAMILY === 'Darwin') {
        @exec('open ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
    } else {
        @exec('xdg-open ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
    }
}

// ============================================================
//  3. DÉTECTION DYNAMIQUE DE LA RACINE DU PROJET HÔTE
//  Remonte les dossiers depuis __DIR__ jusqu'à trouver
//  un dossier contenant vendor/ + composer.json
// ============================================================

function nabysyFindHostRoot(string $startDir, int $maxLevels = 10): ?string
{
    $current = $startDir;
    for ($i = 0; $i < $maxLevels; $i++) {
        $parent = dirname($current);

        // Sécurité : atteint la racine du système de fichiers
        if ($parent === $current) {
            nabysyBootstrapLog("Racine projet hôte introuvable depuis {$startDir}", 'ERROR');
            return null;
        }

        // Un dossier Composer valide contient vendor/ ET composer.json
        if (is_dir($parent . DIRECTORY_SEPARATOR . 'vendor')
            && file_exists($parent . DIRECTORY_SEPARATOR . 'composer.json')
        ) {
            return rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        $current = $parent;
    }

    nabysyBootstrapLog("Racine projet hôte introuvable après {$maxLevels} niveaux", 'ERROR');
    return null;
}

// ============================================================
//  4. DÉTECTION DU DOSSIER HÔTE SELON LE CONTEXTE
// ============================================================

$base = '';
if (defined('__BASEDIR__')) {
    $base = __BASEDIR__;
}

if (php_sapi_name() === 'cli') {
    // ── Contexte CLI (Composer post-install) ──
    $host_directory = nabysyFindHostRoot(__DIR__);
    if ($host_directory === null) {
        nabysyBootstrapLog("Impossible de détecter la racine du projet hôte en CLI", 'FATAL');
        return;
    }
    nabysyBootstrapLog("Racine projet hôte détectée : {$host_directory}", 'INFO');
} else {
    // ── Contexte HTTP normal ──
    $Rep = $_SERVER['DOCUMENT_ROOT'];
    if (isset($base) && $base !== '') {
        $PrecRep = $Rep;
        $Rep    .= DIRECTORY_SEPARATOR . $base;
        if (!is_dir(str_replace('/', DIRECTORY_SEPARATOR, $Rep))) {
            nabysyBootstrapLog("Création du dossier {$Rep} dans {$PrecRep}", 'INFO');
            try {
                mkdir($Rep, 0777, true);
            } catch (\Throwable $th) {
                nabysyBootstrapLog("Erreur création dossier {$Rep} : " . $th->getMessage(), 'ERROR');
                throw $th;
            }
        }
        if (!is_dir(str_replace('/', DIRECTORY_SEPARATOR, $Rep))) {
            throw new Exception("Basedir {$Rep} introuvable !", 1);
        }
    }
    $host_directory = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $Rep), DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR;
}

// ============================================================
//  5. CHARGEMENT DU FRAMEWORK
// ============================================================

include_once 'nabysy.php';

if (!class_exists('N')) {
    /**
     * La Class static N regroupe l'ensemble des fonctions static
     * de l'objet central NAbySyGS.
     */
    class N extends xNAbySyGS
    {
        final public function __get($key)
        {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                return $this->$method($this->data[$key]);
            } else {
                return parent::getInstance();
            }
        }
    }
}

N::$BASEDIR = $base;

// ============================================================
//  6. COPIE DES FICHIERS DE DÉMARRAGE VERS LA RACINE HÔTE
//  Effectuée avant l'inclusion d'appinfos.php car ces fichiers
//  sont nécessaires même si appinfos.php n'existe pas encore.
// ============================================================

$templateDir = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;

// ── index.php ──────────────────────────────────────────────
// Au premier démarrage, on copie template_setup.php comme index.php temporaire.
// Ce fichier gère le setup initial (?Action=SETUP) puis se remplace lui-même
// par le vrai template_index.php une fois appinfos.php généré.
$main_entry_file = $host_directory . 'index.php';
if (!file_exists($main_entry_file)) {
    nabysyBootstrapLog("Génération de index.php (setup temporaire) dans {$host_directory}", 'INFO');
    try {
        $templateSetup = $templateDir . 'template_setup.php';
        if (!file_exists($templateSetup)) {
            throw new \RuntimeException("template_setup.php introuvable dans {$templateDir}");
        }
        copy($templateSetup, $main_entry_file);
        nabysyBootstrapLog("index.php (setup) généré avec succès", 'INFO');
    } catch (\Throwable $th) {
        nabysyBootstrapLog("Erreur génération index.php : " . $th->getMessage(), 'ERROR');
    }
}

// ── index_new.php ───────────────────────────────────────────
// Copie anticipée du vrai template_index.php en index_new.php.
// template_setup.php (chargé en index.php) le renommera en index.php
// après un setup réussi, sans dépendance à N::CurrentFolder().
$index_new_file = $host_directory . 'index_new.php';
if (!file_exists($index_new_file) && !file_exists($host_directory . 'appinfos.php')) {
    nabysyBootstrapLog("Copie de index_new.php dans {$host_directory}", 'INFO');
    try {
        $templateIndex = $templateDir . 'template_index.php';
        if (!file_exists($templateIndex)) {
            throw new \RuntimeException("template_index.php introuvable dans {$templateDir}");
        }
        $template = file_get_contents($templateIndex);
        $updated  = str_replace(
            ['{DATE}', '{MODULE_NAME}'],
            [date('d/M/Y H:i:s'), 'Mon Application NAbySyGS'],
            $template
        );
        file_put_contents($index_new_file, $updated);
        nabysyBootstrapLog("index_new.php copié avec succès", 'INFO');
    } catch (\Throwable $th) {
        nabysyBootstrapLog("Erreur copie index_new.php : " . $th->getMessage(), 'ERROR');
    }
}

// ── setup.html ─────────────────────────────────────────────
// Copié uniquement si appinfos.php n'existe pas encore
$appinfos_file = $host_directory . 'appinfos.php';
$setup_dest    = $host_directory . 'setup.html';
$setup_src     = __DIR__ . DIRECTORY_SEPARATOR . 'setup.html';

if (!file_exists($appinfos_file) && !file_exists($setup_dest)) {
    nabysyBootstrapLog("Copie de setup.html dans {$host_directory}", 'INFO');
    try {
        if (!file_exists($setup_src)) {
            throw new \RuntimeException("setup.html introuvable dans {$setup_src}");
        }
        copy($setup_src, $setup_dest);
        nabysyBootstrapLog("setup.html copié avec succès", 'INFO');
    } catch (\Throwable $th) {
        nabysyBootstrapLog("Erreur copie setup.html : " . $th->getMessage(), 'ERROR');
    }
}

// ── .htaccess racine ───────────────────────────────────────
$htaccess_file = $host_directory . '.htaccess';
if (!file_exists($htaccess_file)) {
    $templatePath = $templateDir . 'template_htaccess';
    try {
        $template = file_get_contents($templatePath);
        if ($template !== false && strlen($template) > 0) {
            $updated = str_replace('{NABYSYROOT}', $host_directory, $template);
            file_put_contents($htaccess_file, $updated);
            nabysyBootstrapLog(".htaccess généré avec succès", 'INFO');
        }
    } catch (\Throwable $th) {
        nabysyBootstrapLog("Erreur génération .htaccess : " . $th->getMessage(), 'ERROR');
    }
}

// ── Dossier tmp/ et son .htaccess ──────────────────────────
$tmpDir = $host_directory . 'tmp' . DIRECTORY_SEPARATOR;
try {
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
        nabysyBootstrapLog("Dossier tmp/ créé : {$tmpDir}", 'INFO');
    }
} catch (\Throwable $th) {
    nabysyBootstrapLog("Erreur création tmp/ : " . $th->getMessage(), 'ERROR');
}

$htaccess_tmpfile = $tmpDir . '.htaccess';
if (!file_exists($htaccess_tmpfile) && is_dir($tmpDir)) {
    $templatePath = $templateDir . 'templateimagetmp_htaccess';
    try {
        $template = file_get_contents($templatePath);
        if ($template !== false && strlen($template) > 0) {
            $updated = str_replace('{NABYSYROOT}', $host_directory, $template);
            file_put_contents($htaccess_tmpfile, $updated);
            nabysyBootstrapLog("tmp/.htaccess généré avec succès", 'INFO');
        }
    } catch (\Throwable $th) {
        nabysyBootstrapLog("Erreur génération tmp/.htaccess : " . $th->getMessage(), 'ERROR');
    }
}

// ============================================================
//  7. CHARGEMENT D'APPINFOS.PHP
// ============================================================

if (file_exists($appinfos_file)) {
    include $appinfos_file;
} else {
    nabysyBootstrapLog(
        "appinfos.php absent — setup requis via {$setup_dest}",
        'WARN'
    );
    // Le setup sera effectué via setup.html copié à la racine.
    // On ne lève pas d'exception ici pour ne pas bloquer Composer.
}

// ============================================================
//  8. TRANSFERT DES LOGS BOOTSTRAP VERS N::$Log
//  Une fois N disponible et initialisé
// ============================================================

$bootstrapLog = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap.log';

if (class_exists('N') && isset(N::$Log)) {
    // N est disponible : transfert vers le système de log NAbySyGS
    if (file_exists($bootstrapLog)) {
        $lines = file($bootstrapLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!empty($lines)) {
            foreach ($lines as $line) {
                N::$Log->AddToLog("[Bootstrap] " . $line);
            }
            @unlink($bootstrapLog); // Nettoyer après transfert
        }
    }
} else {
    // N non disponible : ouvrir les logs dans le navigateur si erreurs présentes
    if (file_exists($bootstrapLog)) {
        $lines = file($bootstrapLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $hasErrors = !empty(array_filter($lines, fn($l) =>
            str_contains($l, '[ERROR]') || str_contains($l, '[FATAL]')
        ));
        if ($hasErrors) {
            nabysyOpenBootstrapLog();
        }
    }
}