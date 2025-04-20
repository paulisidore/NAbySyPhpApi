<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 */

use NAbySy\Lib\ModuleExterne\OilStation\xCuveStockageCarburant;

 include_once 'nabysy_start.php';
 
 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;
 if(isset($_REQUEST['TRACKERID'])){
    $Err->Source = $_REQUEST['TRACKERID'];
 }
 
 switch ($action){
    case "STATION_GET_CUVE": //Retourne la liste des CUVEs de stockage
        $Id=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        $Cuve=new xCuveStockageCarburant($nabysy,$Id);
        $Reponse->OK=1;
        $Reponse->Extra = "Liste des cuves pour Station d'Essence";
        $Reponse->Contenue=[];
        if (!$Cuve->TableIsEmpty()){
            $Lst=$Cuve->ChargeListe();
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
    
    case "STATION_SAVE_CUVE": //Enregistre une cuve
        $Id=null;
        $Nom=null;
        $IgnoreL=[];

        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
                $IgnoreL[]=$Id;
            }
        }
        
        $xCuve=new xCuveStockageCarburant($nabysy,$Id);
        if ($xCuve->Id==0){
            //Is New Cuve
            $xCuve->DateJaugeB=date("Y-m-d H:i:s");
            $xCuve->Stock=0;
        }
        if(isset($_REQUEST['NOM'])){
            if (trim($_REQUEST['NOM']) !==""){
                $Nom = trim($_REQUEST['NOM']);
            }
        }
        if(!isset($Nom)){
            $Err->TxErreur = "Le nom est obligatoire.";
            echo json_encode($Err);
            exit;
        }
        if ($xCuve->Existe($Nom,$IgnoreL)){
            $Err->TxErreur = "Le nom de Cuve ".$Nom." existe déjà.";
            echo json_encode($Err);
            exit;
        }

        $xCuve->Nom = $Nom ;
        if(isset($_REQUEST['UNITE_MESURE'])){
            if (trim($_REQUEST['UNITE_MESURE']) !==""){
                $xCuve->UniteMesure = trim($_REQUEST['UNITE_MESURE']);
            }
        }
        if(isset($_REQUEST['TYPECARBURANT'])){
            if (trim($_REQUEST['TYPECARBURANT']) !==""){
                $vTypeN=$xCuve::GetTypeCarburant(trim($_REQUEST['TYPECARBURANT']));
                if ($vTypeN !==""){
                    $xCuve->TypeCarburant= $vTypeN ;
                }else{
                    $Err->TxErreur = "Type de carburant introuvable. ".$_REQUEST['TYPECARBURANT']." n'existe déjà.";
                    echo json_encode($Err);
                    exit;
                }                
            }else{
                $Err->TxErreur = "Type de carburant incorrect.";
                echo json_encode($Err);
                exit;
            }
        }else{
            if ($xCuve->Id ==0){
                $Err->TxErreur = "Absence du type de carburant.";
                echo json_encode($Err);
                exit;
            }
        }

        if ($xCuve->Enregistrer()){
            $Reponse->Extra = $xCuve->Id ;
            $Reponse->Contenue = $xCuve->ToObject();
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;
        
    case "STATION_SUPPRIME_CUVE": //Supprime un cuve
        $Id=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        $Cuve=new xCuveStockageCarburant($nabysy,$Id);
        if ($Cuve->Id ==0){
            $Err->TxErreur = "Impossible de supprimer. Object inexistant.";
            echo json_encode($Err);
            exit;
        }
        if ($Cuve->Supprimer()){
            $Reponse->Autres = "Cuve Id ".$Cuve->Id." supprimée correctement." ;
            $Reponse->Extra=$Cuve->Id;
            echo json_encode($Reponse);
            exit;
        }
        $Err->TxErreur = "Err. API";
        echo json_encode($Err);
        exit;
        break;
    
    case "STATION_LISTE_TYPECARBURANT":// Retourne les type de carburant pris en charge
        $Liste=[];
        $Cuve=new xCuveStockageCarburant($nabysy);
        $Liste[]=$Cuve::CARBURANT_ESSENCE ;
        $Liste[]=$Cuve::CARBURANT_GASOIL ;
        $Liste[]=$Cuve::CARBURANT_GAZ ;
        $Liste[]=$Cuve::CARBURANT_KEROZENE_JET_A1 ;
        $Reponse->OK=1;
        $Reponse->Extra="Carburant pris en charge.";
        $Reponse->Contenue=$Liste ;
        echo json_encode($Reponse);
        exit;
        break;

    default:
        break;
 }

 ?>