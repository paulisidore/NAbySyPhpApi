<?php

/* 	Chargeur du Module MCP
	Version 1.0
*/
//session_start();
	
include_once 'nabysy.php' ;
 
$db='nabysygs' ;
if(!empty($_SESSION['key'])){
	//Si la variable session existe pas
	//$_SESSION['db'] = $_SESSION['key'] ;
	$db = $_SESSION['key'] ;
}
else {
	//$_SESSION['db']='bd_depot';
	}


//ModuleMCP $Module;
$Module = new ModuleMCP ;
$Module->Nom='NAbySyGS' ;
$nabysy = new xNAbySyGS($xserveur,$xuser,$xpasswd,$Module,$db)  ;

$Article = new xProduit($nabysy) ;
$Panier = new xPanier($nabysy) ;
$Vente = new xVente($nabysy) ;
$Boutique = new xBoutique($nabysy,$nabysy->BoutiqueID) ;



?>