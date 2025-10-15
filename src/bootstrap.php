<?php
// src/bootstrap.php

// Code exécuté automatiquement dès que ton package est chargé
// Tu peux y mettre des hooks, des logs, du code d'initialisation

use NAbySy\xNAbySyGS;

define('XNABYSY_LOADED', true);

include_once 'nabysy.php' ;

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

$fichierStart = N::CurrentFolder(true).'appinfos.php';
$outputDir =  N::CurrentFolder(true) ;
$fichier_sortie = $outputDir . 'appinfos.php';

if (file_exists($fichierStart)) {
	include $fichierStart;
} else {
	//Copie du fichier de démarrage par défaut
	//echo "<br>Le fichier de démarrage ".$fichierStart." n'existe pas !<br>";
	$templatePath = N::CurrentFolder().'templates/template_appinfos.php';
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
			throw $th;
		}
		include $fichierStart;
	 } catch (\Throwable $th) {
		throw $th;
	 }
	
}

$htaccess_file = N::CurrentFolder(true).'.htaccess' ;
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
				N::$Log->AddToLog("Error on writing new .htaccess file: ".$th->getMessage());
				throw $th;
			}
		}
	} catch (\Throwable $th) {
		//throw $th;
		N::$Log->AddToLog("Error on reading .htaccess template file: ".$th->getMessage());
	}
}