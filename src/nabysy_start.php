<?php

/* 	Lanceur du Module NAbySy RH & RS
	Version 1.0
	Auteur: Paul & Aïcha Machinerie
	Date: 20/05/2022
*/

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'mod_ext/rb.php' ;
include_once 'nabysy.php' ;

$db='nabysyrhrs' ;
$masterdb=$db ;

//ModuleMCP $Module;
$Module = new ModuleMCP ;
$Module->Nom='NAbySy RH&RS' ;
$Module->MCP_CLIENT="-PAUL ET AICHA MACHINERIE-" ;
$Module->MCP_ADRESSECLT="Zack Mbao DAKAR/SENEGAL" ;
$Module->MCP_TELCLT="Tel: +221 33 836 14 77" ;

if (isset($NoCSS)){
	$NoCSS=true ;
}else{
	$NoCSS=false ;
}

//On Récupère la configuration dans un fichier s'il existe
$FichierConfig='parametre.json';
if (!file_exists($FichierConfig)){
	//On le crée
	$Config='{
	"Connexion": {
		"Serveur":"'.$xserveur.'",
		"Port":"'.$xport.'",
		"DBUser":"'.$xuser.'",
		"DBPwd":"'.$xpasswd.'",
		"DB":"'.$db.'",
		"MasterDB":"'.$masterdb.'"
		},
	"Module": {
		"Nom":"'.$Module->Nom.'",
		"MCP_CLIENT":"'.$Module->MCP_CLIENT.'",
		"MCP_ADRESSECLT":"'.$Module->MCP_ADRESSECLT.'",
		"MCP_TELCLT":"'.$Module->MCP_TELCLT.'"
		},
	"DebugMode":"true"
	}';
	try {
		$F= fopen($FichierConfig, 'w');			
		$TxT=$Config ;
		$TxT .="\r\n" ;				
		fputs($F, $TxT);
		fclose($F);
	}catch(Exception $e){
		echo 'Erreur création du fichier de configuration: '.$e->getMessage();
	}
}

//On récupere la configuration
$string = file_get_contents($FichierConfig);
$Parametre = json_decode($string, false);

if (isset($Parametre)){
	if (is_object($Parametre)){
		$Module->Nom=$Parametre->Module->Nom;
		$Module->MCP_CLIENT=$Parametre->Module->MCP_CLIENT;
		$Module->MCP_ADRESSECLT=$Parametre->Module->MCP_ADRESSECLT;
		$Module->MCP_TELCLT=$Parametre->Module->MCP_TELCLT;

		$xserveur=$Parametre->Connexion->Serveur ;
		$xuser=$Parametre->Connexion->DBUser ;
		$xpasswd=$Parametre->Connexion->DBPwd ;
		$db=$Parametre->Connexion->DB ;
		$masterdb=$Parametre->Connexion->MasterDB ;		
	}
}

$nabysy = new xNAbySyGS($xserveur,$xuser,$xpasswd,$Module,$db,$masterdb)  ;
if ($nabysy == false){
	$Err=new xErreur();
	$Err->OK=0;
	$Err->TxErreur = "Le module ".$Module->Nom." a rencontré une erreur.";
	echo json_encode($Err) ;
	exit ;
}
$nabysy->ActiveDebug= boolval ($Parametre->DebugMode) ;

if (!class_exists('N')) {
	/**
	 * La Class static N regroupe l'ensemble des fonctions static de l'objet central NAbySyGS.
	 */
	class N extends xNAbySyGS { 
		/**
		 * Module Principal NAbySy GS
		 * @var xNAbySyGS
		 */
		public static xNAbySyGS $Main  ;

		final public function __get($key) {
			$method = 'get' . ucfirst($key);
			if (method_exists($this, $method)) {
			  return $this->$method($this->data[$key]);
			} else {
			  return self::$Main;
			}
		  }
	}
	N::$Main = $nabysy ;
}
$nabysy->AutorisationCORS();




?>