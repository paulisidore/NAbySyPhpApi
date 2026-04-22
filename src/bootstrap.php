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
//echo "Repertoir de Travail ".__FILE__." = ". $host_directory."</br>" ;

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
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/>';
            echo '<title>NAbySyGS — Bootstrap Log</title>';
            echo '<style>
                body { background:#0a0f0d; color:#e8f0eb; font-family:monospace; padding:32px; }
                h2   { color:#f5a623; margin-bottom:16px; }
                .log { background:#111a15; border:1px solid #1f3026; border-radius:8px; padding:16px; }
                .line { padding:4px 0; border-bottom:1px solid #1f3026; font-size:0.85rem; }
                .line:last-child { border-bottom:none; }
                .INFO  { color:#e8f0eb; }
                .ERROR { color:#ff5252; }
                .FATAL { color:#ff5252; font-weight:bold; }
                .WARN  { color:#f5a623; }
            </style></head><body>';
            echo '<h2>🦅 NAbySyGS — Bootstrap Log</h2>';
            echo '<div class="log">';
            foreach ($lines as $line) {
                // Détecter le niveau pour la colorisation
                $level = 'INFO';
                if (str_contains($line, '[FATAL]')) $level = 'FATAL';
                elseif (str_contains($line, '[ERROR]')) $level = 'ERROR';
                elseif (str_contains($line, '[WARN]'))  $level = 'WARN';
                echo '<div class="line ' . $level . '">' . htmlspecialchars($line) . '</div>';
            }
            echo '</div>';
            echo '<p style="margin-top:16px;color:#6b8c74;font-size:0.75rem;">';
            echo 'Fichier log : ' . htmlspecialchars($bootstrapLog);
            echo '</p></body></html>';
            exit; // Stopper l'exécution pour que le HTML soit lisible
        }
    }
}

