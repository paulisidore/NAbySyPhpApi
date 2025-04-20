<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 */

use NAbySy\Lib\ModuleExterne\OilStation\xCuveStockageCarburant;
use NAbySy\Lib\ModuleExterne\OilStation\xILot;
use NAbySy\Lib\ModuleExterne\OilStation\xPompe;

 include_once 'nabysy_start.php';
 
 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;
 if(isset($_REQUEST['TRACKERID'])){
    $Err->Source = $_REQUEST['TRACKERID'];
 }
 
 switch ($action){
    case "STATION_GET_PISTON": //Retourne la liste des Pistons ou Pompes de Station
        $Id=null;
        $Critere=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDILOT'])){
            if ((int)$_REQUEST['IDILOT']>0){
                $Critere .=" AND p.IDILOT =".(int)$_REQUEST['IDILOT'];
            }
        }

        $Pompe=new xPompe($nabysy,$Id);
        $Reponse->OK=1;
        $Reponse->Extra = "Liste des pistons pour Station d'Essence";
        $Reponse->Contenue=[];
        if (!$Pompe->TableIsEmpty()){
            $Cuve = new xCuveStockageCarburant($Pompe->Main);
            $iLot=new xILot($Pompe->Main);
            $TxSQL="select  p.IDILOT, p.*, c.NOM as 'CARBURANT', i.NOM as 'ILOT' from ".$Pompe->Table." p 
            left outer join ".$Cuve->Table." c on c.ID = p.IdCarburant 
            left outer join ".$iLot->Table." i on i.ID = p.IdIlot ";
            $TxSQL .=" Where p.Id>0 ".$Critere;
            $Lst=$Pompe->ExecSQL($TxSQL);
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
    
    case "STATION_SAVE_PISTON": //Enregistre un Piston / Pompes
        $Id=null;
        $Nom=null;
        $IgnoreL=[];
        $IdILot=null;
        $IdCarburant=null;

        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
                $IgnoreL[]=$Id;
            }
        }
        if(isset($_REQUEST['IDILOT'])){
            if ((int)$_REQUEST['IDILOT']>0){
                $IdILot = (int)$_REQUEST['IDILOT'];
            }
        }
        if(isset($_REQUEST['IdILot'])){
            if ((int)$_REQUEST['IdILot']>0){
                $IdILot = (int)$_REQUEST['IdILot'];
            }
        }
        
        $xILot=new xILot($nabysy,$IdILot);
        if ($xILot->Id ==0){
            $Err->TxErreur = "ILot introuvable.";
            echo json_encode($Err);
            exit;
        }

        $Pompe=new xPompe($nabysy,$Id);
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
        if(isset($_REQUEST['IDCARBURANT'])){
            if ((int)$_REQUEST['IDCARBURANT']){
                $IdCarburant = (int)$_REQUEST['IDCARBURANT'];
                $Pompe->IdCarburant=$IdCarburant ;
            }
        }
        if(isset($_REQUEST['IdCarburant'])){
            if ((int)$_REQUEST['IdCarburant']){
                $IdCarburant = (int)$_REQUEST['IdCarburant'];
                $Pompe->IdCarburant=$IdCarburant ;
            }
        }
        if(!isset($Nom)){
            $Err->TxErreur = "Le nom est obligatoire.";
            echo json_encode($Err);
            exit;
        }
        
        $Pompe->Nom = $Nom ;
        if ($xILot->AjoutPompe($Pompe)){
            $Reponse->Extra = $Pompe->Id ;
            $Reponse->Contenue = $Pompe->ToObject();
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;
        
    case "STATION_SUPPRIME_PISTON": //Supprime un Piston
        $Id=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        $Pompe=new xPompe($nabysy,$Id);
        if ($Pompe->Id ==0){
            $Err->TxErreur = "Impossible de supprimer. Object inexistant.";
            echo json_encode($Err);
            exit;
        }
        if ($Pompe->Supprimer()){
            $Reponse->Autres = "Piston Id ".$Pompe->Id." supprimé correctement." ;
            $Reponse->Extra=$Pompe->Id;
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