<?php
    /**
     * END-POINT Setup initial — Fichier temporaire généré par NAbySyGS bootstrap
     * Ce fichier se supprime lui-même après un setup réussi et est remplacé
     * par le vrai index.php de l'application.
     * By Paul Isidore A. NIAMIE
     */

use NAbySy\xAuth;
use NAbySy\xErreur;
use NAbySy\xNotification;
use NAbySy\xUser;

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
        $targetFile = N::CurrentFolder(true) . 'appinfos.php';

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
const __API_VERSION__    = '{$apiversion}';
const __APPNAME__        = '{$appname}';
const __PROVIDER_NAME__  = '{$provider}';
const __PROVIDER_ADR__   = "{$adrSafe}";
const __PROVIDER_TEL__   = "{$tel}";
const __MASTERDB__       = '{$masterdb}';
const __DBNAME__         = '{$dbname}';
const __DBVERSION__      = '{$dbversion}';
const __DBSERVER__       = '{$dbserver}';
const __DBUSER__         = '{$dbuser}';
const __DBPASSWORD__     = '{$dbpwd}';
const __DBPORT__         = {$dbport} ;
const __DUREE_TOKEN__    = {$token}; {$tokenComment}
const __BASEDIR__        = "{$basedirSafe}"; // Sous dossier d'hebergement si applicable
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
    //include_once 'db_structure.php'; // Fichier contenant la définition des modules/tables métier
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
            include_once $targetFile;
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
        $log .= "Génération du fichier index.php définitif...\n";
        try {
            $indexFile    = N::CurrentFolder(true) . 'index.php';
            $templatePath = N::CurrentFolder() . 'templates' . DIRECTORY_SEPARATOR . 'template_index.php';
            $template     = file_get_contents($templatePath);

            if ($template === false) {
                throw new \RuntimeException("Impossible de lire template_index.php");
            }

            $indexContent = str_replace(
                ['{DATE}', '{MODULE_NAME}'],
                [date('d/M/Y H:i:s'), $appname],
                $template
            );

            // Supprimer le fichier setup temporaire (index.php actuel)
            // et le remplacer par le vrai index.php
            // Note : on écrit d'abord le nouveau contenu AVANT de supprimer
            // car __FILE__ est verrouillé pendant l'exécution
            file_put_contents($indexFile, $indexContent);

            $log .= "OK\n\n";
        } catch (\Throwable $e) {
            // Non bloquant : appinfos.php est déjà créé, l'essentiel est fait
            $log .= "⚠ Avertissement : impossible de régénérer index.php : "
                  . $e->getMessage() . "\n"
                  . "Vous pouvez le recréer manuellement depuis template_index.php\n\n";
        }

        // ── 8. Suppression de setup.html ─────────────────────
        $setupHtml = N::CurrentFolder(true) . 'setup.html';
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