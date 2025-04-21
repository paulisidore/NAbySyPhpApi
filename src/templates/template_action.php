<?php
/**
 * @file {CATEGORIE}_action.php
 * Contains Generic NAbySyGS API Action for {CATEGORIE}
 * Author: 
 * Mail: 
 * Date: {DATE}
 * Version: 1.0.0
 */

use NAbySy\xErreur;
use NAbySy\xNotification;

$PARAM=$_REQUEST;
$action=null ;
if (isset($PARAM['Action'])){
    $action=$PARAM['Action'] ;
}
if (isset($PARAM['action'])){
    $action=$PARAM['action'] ;
}
$Err=new  xErreur ;
$Err->TxErreur='Erreur';
$Err->OK=0 ;

$Reponse = new xNotification();

if (!isset($action)){		
    //Il n'y a pas d'action, on retourne a la page précedente
    $Err->OK=0;
    $Err->TxErreur='Aucune définit !' ;
    $Err->Source= __FILE__ ;
    $reponse=json_encode($Err) ;
    echo $reponse ;
    exit;	
}

/**
 * Routing et Traitement éventuelle des actions liées aux requettes HTTP
 */
switch ($action) {
    case '[CATEGORIE]_GET': //Lecture: {CATEGORIE}...
        $Reponse->OK=0;
        $Reponse->TxErreur="Action $action non effectuée. Absence de critère" ;
        echo $Reponse->ToJSON();
		break;
    case '[CATEGORIE]_CREATE': //Création: {CATEGORIE}...
        $Reponse->OK=0;
        $Reponse->TxErreur="Action $action non effectuée. Absence de critère" ;
        echo $Reponse->ToJSON();
		break;
    case '[CATEGORIE]_SAVE': //Enregistrement: {CATEGORIE}...
        $Reponse->OK=0;
        $Reponse->TxErreur="Action $action non effectuée. Absence de critère" ;
        echo $Reponse->ToJSON();
        break;
    case '[CATEGORIE]_DELETE': //Suppression: {CATEGORIE}...
        $Reponse->OK=0;
        $Reponse->TxErreur="Action $action non effectuée. Absence de critère" ;
        echo $Reponse->ToJSON();
        break;
    default:
        break;
}

?>