<?php
// src/bootstrap.php

// Code exécuté automatiquement dès que ton package est chargé
// Tu peux y mettre des hooks, des logs, du code d'initialisation

use NAbySy\xNAbySyGS;

define('XNABYSY_LOADED', true);
// ── Logging bootstrap autonome ───────────────────────────
function nabysyBootstrapLog(string $message, string $level = 'INFO'): void {
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap.log';
    $line    = date('d/m/Y H:i:s') . " [{$level}] " . $message . PHP_EOL;
    error_log("[NAbySyGS] {$message}"); // → error.log Apache/WAMP
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Capture les erreurs fatales non attrapables par try/catch
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        nabysyBootstrapLog("[FATAL] {$error['message']} dans {$error['file']} ligne {$error['line']}", 'FATAL');
    }
});

$base = "";
if (defined('__BASEDIR__') ){
	$base = __BASEDIR__  ;
}

$Rep = $_SERVER['DOCUMENT_ROOT'] ;
if(isset($base) && $base !==''){
	$PrecRep = $Rep ;
	$Rep .= DIRECTORY_SEPARATOR.$base ;
	if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
		//echo "Creation du dossier ".$Rep." dans ". $PrecRep ." !</br>";
		nabysyBootstrapLog("Creation du dossier ".$Rep." dans ". $PrecRep, 'INFO');
		try {
			mkdir($Rep,0777,true);
		} catch (\Throwable $th) {
				nabysyBootstrapLog($th->getMessage(), 'ERROR');
				throw $th;
		}
	}
	if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
		nabysyBootstrapLog("Basedir ".$Rep." introuvable !", 'ERROR');
		throw new Exception("Basedir ".$Rep." introuvable !", 1);
	}
}
$Rep=str_replace('/',DIRECTORY_SEPARATOR,$Rep)  ;
$host_directory = $Rep ;

// En haut de bootstrap.php — détection du dossier hôte compatible CLI et HTTP
if (php_sapi_name() === 'cli') {
    // Contexte CLI (Composer) : remonter depuis vendor/nabysyphpapi/xnabysygs/src/
    // __DIR__ = .../vendor/nabysyphpapi/xnabysygs/src
    // On remonte 3 niveaux pour atteindre la racine du projet hôte
    $host_directory = dirname(__DIR__, 3);
} else {
    // Contexte HTTP normal : utiliser DOCUMENT_ROOT comme avant
    $Rep = $_SERVER['DOCUMENT_ROOT'];
    if (isset($base) && $base !== '') {
        // ... ta logique existante
    }
    $host_directory = str_replace('/', DIRECTORY_SEPARATOR, $Rep);
}
$host_directory = rtrim($host_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;


include_once 'nabysy.php' ;

//define('__BASEDIR__', $base) ;
//echo "Maitenant __BASEDIR__ = ". $base."</br>" ;

if (!class_exists('N')) {
	/**
	 * La Class static N regroupe l'ensemble des fonctions static de l'objet central NAbySyGS.
	 */
	class N extends xNAbySyGS { 
		/**
		 * Module Principal NAbySy GS
		 * @var xNAbySyGS
		 */
				
		final public function __get($key) {
			$method = 'get' . ucfirst($key);
			if (method_exists($this, $method)) {
			  return $this->$method($this->data[$key]);
			} else {
			  return parent::getInstance(); // self::$Main;
			}
		}
	}
}
N::$BASEDIR = $base ;
//echo "__BASEDIR__ = ".N::$BASEDIR."</br>" ; exit;

// $fichierStart = N::CurrentFolder(true).'appinfos.php';
// $outputDir =  N::CurrentFolder(true) ;
// $fichier_sortie = $outputDir . 'appinfos.php';

$fichierStart    = $host_directory . DIRECTORY_SEPARATOR . 'appinfos.php';
$outputDir       = $host_directory . DIRECTORY_SEPARATOR;
$fichier_sortie  = $outputDir . 'appinfos.php';


if (file_exists($fichierStart)) {
	include $fichierStart;
} else {
	/**
	 * On laisse l'UI et le script post installation de composer faire le travail d'installation et de configuration du fichier appinfos.php, mais on s'assure que si jamais il n'est pas là, on en crée un par défaut pour éviter les erreurs fatales et permettre à l'utilisateur de configurer manuellement si besoin.
	 */
	$templatePath = N::CurrentFolder().'templates/template_appinfos.php';
	/* try {
		$template = file_get_contents($templatePath);
		// Remplacer dynamiquement des morceaux
		$updated = str_replace([
			'{DATE}',
		], [
			date('d/M/Y H:i:s'),
		], $template);

		// Créer le dossier si nécessaire
		if (!is_dir($outputDir)) {
			mkdir($outputDir, 0777, true);
		}

		try {
			// Écrire dans un nouveau fichier
			file_put_contents($fichier_sortie, $updated);

		} catch (\Throwable $th) {
			N::$Log->AddToLog("ERREUR d'ecriture das le dossier ".$outputDir,4);
			N::$Log->AddToLog("Repertoir racine: ".N::CurrentFolder(true) );
			throw $th;
		}
		include $fichierStart;
	 } catch (\Throwable $th) {
		throw $th;
	 } */
	
}

//$htaccess_file = N::CurrentFolder(true).'.htaccess' ;
$htaccess_file = $host_directory . DIRECTORY_SEPARATOR . '.htaccess';
if(!file_exists($htaccess_file)){
	//Création du fichier htaccess afin de rediriger les chemin inconnus vers le gestionnaire des appels api
	$templatePath = N::CurrentFolder().'templates/template_htaccess';
	try {
		$template = file_get_contents($templatePath);
		if(strlen($template)>0){
			//{NABYSYROOT}
			// Remplacer dynamiquement des morceaux
			$updated = str_replace([
				'{NABYSYROOT}',
			], [
				N::CurrentFolder(true),
			], $template);

			//copy($templatePath, $htaccess_file);
			try {
				// Écrire dans un nouveau fichier
				file_put_contents($htaccess_file, $updated);
			} catch (\Throwable $th) {
				nabysyBootstrapLog("Error on writing new .htaccess file: ".$th->getMessage(), 'ERROR');
				//throw $th;
			}
		}
	} catch (\Throwable $th) {
		//throw $th;
		nabysyBootstrapLog("Error on reading .htaccess template file: ".$th->getMessage(), 'ERROR');
		
	}
}

try {
	//$tmpDir = N::CurrentFolder(true).'tmp/';
	$tmpDir = $host_directory . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
	if(!scandir($tmpDir)){
		mkdir($tmpDir, 0777, true);
	}
} catch (\Throwable $th) {
	nabysyBootstrapLog("Error on creating tmp directory: ".$th->getMessage(), 'ERROR');
	
}

//$htaccess_tmpfile = N::CurrentFolder(true).'tmp/.htaccess' ;
$htaccess_tmpfile = $host_directory . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . '.htaccess';
if(!file_exists($htaccess_tmpfile)){
	//Création du fichier htaccess afin de rediriger les chemin inconnus vers le gestionnaire des appels api
	$templatePath = N::CurrentFolder().'templates/templateimagetmp_htaccess';
	try {
		$template = file_get_contents($templatePath);
		if(strlen($template)>0){
			//{NABYSYROOT}
			// Remplacer dynamiquement des morceaux
			$updated = str_replace([
				'{NABYSYROOT}',
			], [
				N::CurrentFolder(true),
			], $template);

			//copy($templatePath, $htaccess_tmpfile);
			try {
				// Écrire dans un nouveau fichier
				file_put_contents($htaccess_tmpfile, $updated);
			} catch (\Throwable $th) {
				nabysyBootstrapLog("Error on writing to ".$htaccess_tmpfile." file: ".$th->getMessage(), 'ERROR');
				//throw $th;
			}
		}
	} catch (\Throwable $th) {
		nabysyBootstrapLog("Error on reading ".$templatePath." file: ".$th->getMessage(), 'ERROR');
		throw $th;
		//N::$Log->AddToLog("Error on reading ".$templatePath." file: ".$th->getMessage());
	}
}

/**
 * Installation du 1er fichier index.php
 */
$main_entry_file = $outputDir . 'index.php';
if (!file_exists($main_entry_file)) {
	$templatePath = N::CurrentFolder().'templates/template_index.php';
	$fichier_sortie = $main_entry_file ;
	try {
		$template = file_get_contents($templatePath);
		// Remplacer dynamiquement des morceaux
		$updated = str_replace([
			'{DATE}',
		], [
			date('d/M/Y H:i:s'),
		], $template);

		// Créer le dossier si nécessaire
		if (!is_dir($outputDir)) {
			mkdir($outputDir, 0777, true);
		}
		try {
			// Écrire dans un nouveau fichier
			file_put_contents($fichier_sortie, $updated);

		} catch (\Throwable $th) {
			//N::$Log->AddToLog("ERREUR d'ecriture das le dossier ".$outputDir,4);
			//N::$Log->AddToLog("Repertoir racine: ".N::CurrentFolder(true) );
			throw $th;
		}
	 } catch (\Throwable $th) {
		nabysyBootstrapLog($th->getMessage(), 'ERROR');
		throw $th;
	 }
	
}

$bootstrapLog = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap.log';

if (class_exists('N')  && isset(N::$Log)) {
    // ── N est disponible : transfert vers le système de log NAbySyGS ──
    if (file_exists($bootstrapLog)) {
        $lines = file($bootstrapLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            N::$Log->AddToLog("[Bootstrap] " . $line);
        }
        @unlink($bootstrapLog); // Nettoyer après transfert
    }
} else {
    // ── N non disponible : affichage HTML des logs dans le navigateur ──
    if (file_exists($bootstrapLog)) {
        $lines = file($bootstrapLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!empty($lines)) {
            // Écrire le HTML dans un fichier temporaire
            $logHtmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'nabysygs_bootstrap_log.html';
            $html = '<!DOCTYPE html>...'; // ton HTML existant
            file_put_contents($logHtmlFile, $html);

            // Ouvrir dans le navigateur selon l'OS
            $url = 'file:///' . str_replace('\\', '/', $logHtmlFile);
            if (PHP_OS_FAMILY === 'Windows') {
                $safeUrl = str_replace(['"', '^', '&', '<', '>', '|'], '', $url);
                @exec('cmd /c start "" "' . $safeUrl . '" > NUL 2>&1');
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                @exec('open ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
            } else {
                @exec('xdg-open ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
            }
        }
    }
}

