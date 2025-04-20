<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 */

use NAbySy\Lib\ModuleExterne\OilStation\xILot;
use NAbySy\Lib\ModuleExterne\OilStation\xPompe;

 include_once 'nabysy_start.php';
 
 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;

 switch ($action){
    case "STATION_GET_LOT": //Retourne la liste des iLot de Station
        $Id=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        $iLot=new xILot($nabysy,$Id);
        $Reponse->OK=1;
        $Reponse->Extra = "Liste des iLots pour Station d'Essence";
        $Reponse->Contenue=[];
        if (!$iLot->TableIsEmpty()){
            $Lst=$iLot->ChargeListe();
            if ($Lst){
                if($Lst->num_rows){
                    $Ret=N::EncodeReponseSQL($Lst);
                    $Reponse->Contenue = $Ret ;
                }
            }
        }
        echo json_encode($Reponse);
        exit;
        break;
    
    case "STATION_SAVE_LOT": //Enregistre un Lot
        $Id=null;
        $Nom=null;
        $IgnoreL=[];
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
                $IgnoreL[]=$Id;
            }
        }
        $iLot=new xILot($nabysy,$Id);
        if(isset($_REQUEST['NOM'])){
            if (trim($_REQUEST['NOM']) !==""){
                $Nom = trim($_REQUEST['NOM']);
            }
        }
        if(isset($_REQUEST['Nom'])){
            if (trim($_REQUEST['Nom']) !==""){
                $Nom = trim($_REQUEST['Nom']);
            }
        }
        if(!isset($Nom)){
            $Err->TxErreur = "Le nom est obligatoire.";
            echo json_encode($Err);
            exit;
        }
        if ($iLot->Existe($Nom,$IgnoreL)){
            $Err->TxErreur = "Un iLot portant le nom ".$Nom." existe déjà.";
            echo json_encode($Err);
            exit;
        }
        $iLot->Nom = $Nom ;
        if ($iLot->Enregistrer()){
            $Reponse->Extra = $iLot->Id ;
            $Reponse->Contenue = $iLot->ToObject();
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;
        
    case "STATION_SUPPRIME_LOT": //Supprime un Lot
        $Id=null;
        $Nom=null;
        $IgnoreL=[];
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        $iLot=new xILot($nabysy,$Id);
        if ($iLot->Id ==0){
            $Err->TxErreur = "Impossible de supprimer. Object inexistant.";
            echo json_encode($Err);
            exit;
        }
        if ($iLot->Supprimer()){
            $Reponse->Autres = "iLot Id ".$iLot->Id." supprimé correctement." ;
            $Reponse->Extra=$iLot->Id;
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;   

    case "STATION_ADD_PISTON": //Enregistre un Piston dans un iLot
        $Id=null;
        $IdLot=null;
        $Nom=null;
        $IdCarburant=null;
        $IgnoreL=[];
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
                $IgnoreL[]=$Id;
            }
        }
        if(isset($_REQUEST['IDILOT'])){
            if ((int)$_REQUEST['IDILOT']>0){
                $IdiLot = (int)$_REQUEST['IDILOT'];
            }
        }
        $iLot=new xILot($nabysy,$IdiLot);
        if ($iLot->Id==0){
            $Err->TxErreur =  "iLot introuvable !";
            echo json_encode($Err);
            exit;
        }
        if(isset($_REQUEST['NOM'])){
            if (trim($_REQUEST['NOM']) !==""){
                $Nom = trim($_REQUEST['NOM']);
            }
        }
        if(isset($_REQUEST['IDCARBURANT'])){
            if ((int)$_REQUEST['IDCARBURANT']){
                $IdCarburant = (int)$_REQUEST['IDCARBURANT'];
            }
        }
        if(!isset($Nom)){
            $Err->TxErreur = "Le nom est obligatoire.";
            echo json_encode($Err);
            exit;
        }        
        if ($iLot->PompeExiste($Nom)){
            $Err->TxErreur = "Un piston portant le nom ".$Nom." existe déjà dans l'Ilot ".$iLot->Nom;
            echo json_encode($Err);
            exit;
        }

        $Pompe=new xPompe($iLot->Main,$Id);
        $IsOK=false;
        if ($Pompe->Id>0){
            $IsOK=$iLot->AjoutPompe($Pompe);
        }else{
            $IsOK=$iLot->AjoutPompeByName($Nom,$IdCarburant);
        }
        if ($IsOK){
            $Reponse->Extra = "Piston ajouté correctement." ;
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;

    default:
        break;
 }

 ?>