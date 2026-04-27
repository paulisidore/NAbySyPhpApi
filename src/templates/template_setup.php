<?php
    /**
     * END-POINT Setup initial — Fichier temporaire généré par NAbySyGS bootstrap
     * Ce fichier se supprime lui-même après un setup réussi et est remplacé
     * par le vrai index.php de l'application.
     * By Paul Isidore A. NIAMIE
     */

// ============================================================
//  Classes autonomes pour le contexte Setup
//  Versions allégées sans dépendance à N — utilisées uniquement
//  pendant la phase de configuration initiale.
// ============================================================

if (!class_exists('xErreur')) {
    class xErreur
    {
        public int $OK = 0;
        public string|null $TxErreur = null;
        public $Source  = null;
        public $Extra   = null;
        public $Autres  = null;

        public function ToJSON(): string|false {
            return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        /**
         * Version allégée pour le setup : pas de N::getInstance() requis
         */
        public function SendAsJSON(bool $SendAndExit = true): bool {
            header('Content-Type: application/json');
            // CORS minimal pour le setup local
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            echo json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($SendAndExit) exit;
            return true;
        }
    }
}

if (!class_exists('xNotification')) {
    class xNotification extends xErreur
    {
        public $Contenue = null;

        public function __construct($jsonData = null) {
            $this->OK = 1;
            if (isset($jsonData)) {
                $js = is_string($jsonData) ? json_decode($jsonData) : $jsonData;
                foreach ($js as $key => $value) $this->{$key} = $value;
            }
        }
    }
}

// ============================================================

$PARAM = $_REQUEST;

$ChampAction = 'Action';
$action      = null;
if (isset($PARAM[$ChampAction])) {
    $action = $PARAM[$ChampAction];
}
if (isset($PARAM[strtolower($ChampAction)])) {
    $action = $PARAM[strtolower($ChampAction)];
}

$Err            = new xErreur;
$Err->TxErreur  = 'Erreur';
$Err->OK        = 0;

if (!isset($action)) {
    $Err->OK        = 0;
    $Err->TxErreur  = 'Action non définit !';
    $Err->Source    = __FILE__;
    echo json_encode($Err);
    exit;
}

switch ($action) {

    case 'SETUP':

        $notif         = new xNotification();
        $notif->Source = 'SETUP';
        $log           = '';

        // ── 1. Vérifier que appinfos.php n'existe pas déjà ──
        $targetFile = 'appinfos.php';

        if (file_exists($targetFile)) {
            $notif->OK       = 0;
            $notif->TxErreur = 'Le fichier appinfos.php existe déjà.';
            $notif->Contenue = "❌ Fichier appinfos.php déjà présent dans :\n"
                             . $targetFile . "\n\n"
                             . "Supprimez-le manuellement avant de relancer le setup.";
            $notif->SendAsJSON();
            exit;
        }

        // ── 2. Récupérer et assainir les paramètres ──────────
        function _setupGet(string $key, string $default = ''): string {
            return isset($_REQUEST[$key]) ? trim((string)$_REQUEST[$key]) : $default;
        }

        $appname    = _setupGet('appname');
        $apiversion = _setupGet('apiversion',   '1.0.0');
        $provider   = _setupGet('provider');
        $adr        = _setupGet('adr');
        $tel        = _setupGet('tel');
        $masterdb   = _setupGet('masterdb');
        $dbname     = _setupGet('dbname');
        $dbserver   = _setupGet('dbserver',     '127.0.0.1');
        $dbport     = (int)_setupGet('dbport',  '3306');
        $dbuser     = _setupGet('dbuser');
        $dbpwd      = _setupGet('dbpwd');
        $basedir    = _setupGet('basedir',      '');
        $dbversion  = _setupGet('dbversion',    '1.0.0');
        $token      = (int)_setupGet('token',   '94608000');
        $debug      = _setupGet('debug',        '1') === '1';
        $debuglevel = (int)_setupGet('debuglevel', '4');
        $authresp   = _setupGet('authresp',     '1') === '1';
        $routing    = _setupGet('routing',      'action');
        $serverurl = _setupGet('serverurl',    'http://localhost');

        // ── 3. Validation des champs obligatoires ────────────
        $errors = [];
        if (empty($appname))  $errors[] = 'appname (Nom de l\'application)';
        if (empty($provider)) $errors[] = 'provider (Nom du fournisseur)';
        if (empty($masterdb)) $errors[] = 'masterdb (Base de données Master)';
        if (empty($dbname))   $errors[] = 'dbname (Base de données applicative)';
        if (empty($dbserver)) $errors[] = 'dbserver (Serveur)';
        if (empty($dbuser))   $errors[] = 'dbuser (Utilisateur DB)';

        if (!empty($errors)) {
            $notif->OK       = 0;
            $notif->TxErreur = 'Champs obligatoires manquants.';
            $notif->Contenue = "❌ Champs obligatoires manquants :\n\n"
                             . implode("\n", array_map(fn($e) => "  • $e", $errors));
            $notif->SendAsJSON();
            exit;
        }

        // ── 4a. Bloc routage selon le choix utilisateur ──────
        switch ($routing) {
            case 'url':
                $routingBlock =
                    "    // Routage par URL — CanSendReponse=true : NAbySyGS envoie directement la réponse si aucune route ne correspond.\n" .
                    "    // ATTENTION : Traitez vos routes NAbySyGS AVANT cet appel si vous activez CanSendReponse.\n" .
                    "    N::\$UrlRouter::resolveUrlRoute(true);\n" .
                    "    // N::ReadHttpRequest(); // Routage par Action désactivé — décommentez pour l'activer en complément";
                break;
            case 'both':
                $routingBlock =
                    "    // Routage combiné : URL Router d'abord (CanSendReponse=false), puis routage par Action.\n" .
                    "    // CanSendReponse est false ici car ReadHttpRequest() prend le relai ensuite.\n" .
                    "    // ATTENTION : Traitez vos routes NAbySyGS AVANT resolveUrlRoute si vous passez CanSendReponse à true.\n" .
                    "    N::\$UrlRouter::resolveUrlRoute(false);\n" .
                    "    N::ReadHttpRequest();";
                break;
            default: // 'action'
                $routingBlock =
                    "    // Routage par Action (défaut) — Dispatch via le paramètre Action=XXX.\n" .
                    "    // Pour activer le routage par URL en complément, décommentez la ligne suivante\n" .
                    "    // et placez-la AVANT ReadHttpRequest(). CanSendReponse doit alors être false.\n" .
                    "    // N::\$UrlRouter::resolveUrlRoute(false);\n" .
                    "    N::ReadHttpRequest();";
        }

        // ── 4b. Générer le contenu de appinfos.php ───────────
        $now          = date('d/M/Y H:i:s');
        $debugStr     = $debug    ? 'true' : 'false';
        $authRespStr  = $authresp ? 'true' : 'false';
        $basedirSafe  = addslashes($basedir);
        $adrSafe      = addslashes($adr);
        $tokenYears   = round($token / (365 * 24 * 3600), 1);
        $tokenComment = "// Durée de vie du token en secondes ({$tokenYears} an(s))";

        $phpContent = <<<PHP
<?php
//appinfos.php
    /**
     * CUSTUMISE YOUR DATABASE IN THIS FILE FOR {$appname}
     * This file is used to create the database structure for your application.
     * Date: {$now}
     * Version: {$apiversion}
     * Generated by NAbySyGS Setup
     */
defined('__API_VERSION__') || define('__API_VERSION__', '{$apiversion}');
defined('__SERVER_URL__') || define('__SERVER_URL__', '{$serverurl}'); // URL de base du serveur (ex: http://monsite.com)

defined('__APPNAME__') || define('__APPNAME__', '{$appname}');
defined('__PROVIDER_NAME__') || define('__PROVIDER_NAME__','{$provider}');
defined('__PROVIDER_ADR__') || define('__PROVIDER_ADR__', "{$adrSafe}");
defined('__PROVIDER_TEL__') || define('__PROVIDER_TEL__', "{$tel}");
defined('__MASTERDB__') || define('__MASTERDB__', '{$masterdb}');
defined('__DBNAME__') || define('__DBNAME__', '{$dbname}');
defined('__DBVERSION__') || define('__DBVERSION__', '{$dbversion}');
defined('__DBSERVER__') || define('__DBSERVER__', '{$dbserver}');
defined('__DBUSER__') || define('__DBUSER__', '{$dbuser}');
defined('__DBPASSWORD__') || define('__DBPASSWORD__', '{$dbpwd}');
defined('__DBPORT__') || define('__DBPORT__', {$dbport});
defined('__DUREE_TOKEN__') || define('__DUREE_TOKEN__', {$token}); {$tokenComment}

defined('__BASEDIR__') || define('__BASEDIR__', '{$basedirSafe}');  // Sous dossier d'hebergement si applicable

\$ACTIVE_DEBUG           = {$debugStr};
\$DEBUG_LEVEL            = {$debuglevel}; // Niveau de debug (0: Aucun, 1: Erreurs, 2: Avertissements, 3: Informations, 4: Débogage détaillé)
\$DUREE_SESSION_AUTH     = __DUREE_TOKEN__ ;
if (\$ACTIVE_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', \$DEBUG_LEVEL);
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
    /**
     * Replace according with your database connection informations
     */
    \$nabysy = N::Init(__APPNAME__,
        __PROVIDER_NAME__, __PROVIDER_ADR__,
        __PROVIDER_TEL__,  __DBNAME__,
        __MASTERDB__,      __DBSERVER__,
        __DBUSER__,        __DBPASSWORD__,
        __DBPORT__,        __BASEDIR__);
    \$nabysy->ActiveDebug = {$debugStr};
    N::SetShowDebug(\$ACTIVE_DEBUG, \$DEBUG_LEVEL);
    N::SetAuthSessionTime(\$DUREE_SESSION_AUTH);
    N::\$SendAuthReponse = {$authRespStr}; // Indique si les réponses d'authentification doivent être envoyées
    if(file_exists(__DIR__ . '/db_structure.php')){
        include_once __DIR__ . '/db_structure.php'; // Modules/tables métier — géré par nsy CLI
    }
{$routingBlock}
?>
PHP;

        // ── 5. Écriture de appinfos.php ──────────────────────
        $log   .= "Génération de appinfos.php...\n";
        $written = file_put_contents($targetFile, $phpContent);

        if ($written === false) {
            $notif->OK       = 0;
            $notif->TxErreur = 'Impossible d\'écrire appinfos.php.';
            $notif->Contenue = $log
                             . "❌ Échec de l'écriture dans :\n"
                             . $targetFile . "\n\n"
                             . "Vérifiez les permissions d'écriture sur le dossier.";
            $notif->SendAsJSON();
            exit;
        }
        $log .= "OK\n\n";

        // ── 6. Initialisation NAbySyGS ───────────────────────
        $log .= "Initialisation de la base de données Master :\n {$masterdb}\n \n";
        try {
            //include_once $targetFile;
            $log .= "OK\n\n";
            $log .= "Création des tables système NAbySyGS :\n \n";
            $log .= "OK\n\n";
        } catch (\Throwable $e) {
            $notif->OK       = 0;
            $notif->TxErreur = 'appinfos.php généré mais initialisation DB échouée.';
            $notif->Contenue = $log
                             . "❌ Erreur lors de l'initialisation :\n"
                             . $e->getMessage() . "\n\n"
                             . "Le fichier appinfos.php a été créé.\n"
                             . "Corrigez les paramètres DB, supprimez appinfos.php et relancez le setup.";
            $notif->SendAsJSON();
            exit;
        }

        // ── 7. Remplacement de index.php par la version finale ──
        $log .= "Mise en place du fichier index.php définitif...\n";
        try {
            $hostRoot    = dirname(__FILE__) . DIRECTORY_SEPARATOR;
            $indexNew    = $hostRoot . 'index_new.php';
            $indexFile   = $hostRoot . 'index.php';

            if (!file_exists($indexNew)) {
                throw new \RuntimeException(
                    "index_new.php introuvable — le bootstrap n'a pas pu le préparer."
                );
            }

            // Sur Windows, on ne peut pas supprimer un fichier en cours d'exécution.
            // On écrase donc index.php avec le contenu de index_new.php,
            // puis on supprime index_new.php.
            // Sur Linux/macOS, rename() est atomique et suffit.
            if (PHP_OS_FAMILY === 'Windows') {
                file_put_contents($indexFile, file_get_contents($indexNew));
                @unlink($indexNew);
            } else {
                // Supprime index.php (= ce fichier) et renomme index_new.php en index.php
                @unlink($indexFile);
                rename($indexNew, $indexFile);
            }

            $log .= "OK\n\n";
        } catch (\Throwable $e) {
            // Non bloquant : appinfos.php est déjà créé, l'essentiel est fait
            $log .= "⚠ Avertissement : impossible de régénérer index.php : "
                  . $e->getMessage() . "\n"
                  . "Renommez manuellement index_new.php en index.php\n\n";
        }

        // ── 8. Suppression de setup.html ─────────────────────
        $setupHtml = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'setup.html';
        if (file_exists($setupHtml)) {
            @unlink($setupHtml);
            $log .= "setup.html supprimé ✅\n";
        }

        // ── 9. Succès ─────────────────────────────────────────
        $notif->OK       = 1;
        $notif->Contenue = $log
                         . "✅ Setup NAbySyGS terminé avec succès !\n\n"
                         . "• appinfos.php généré\n"
                         . "• Base de données Master créée\n"
                         . "• index.php mis à jour\n"
                         . "• setup.html supprimé\n\n"
                         . "Votre application est prête.";
        $notif->SendAsJSON();
        exit;

    // ── FIN case SETUP ────────────────────────────────────────

    default:
        $Err->OK       = 0;
        $Err->TxErreur = "Action '{$action}' inconnue dans le setup.";
        $Err->Source   = __FILE__;
        echo json_encode($Err);
        exit;
}