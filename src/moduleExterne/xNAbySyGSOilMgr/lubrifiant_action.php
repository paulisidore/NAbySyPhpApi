<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 * 
 * API pour le traitement lié au Ubrifiants
 */

use NAbySy\Lib\ModuleExterne\OilStation\xLubrifiant;

 include_once 'nabysy_start.php';
 
 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;

 switch ($action){
    case "LUBRIFIANT_LISTE": //Retourne la liste de tous les articles de la famille des lubrifiants
        $Nom=null;
        $IdPdt=null;
        if (isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']){
                $IdPdt=(int)$_REQUEST['ID'];
            }
        }
        $Lubrifiant = new xLubrifiant($nabysy,$IdPdt);
        if (isset($IdPdt) && $Lubrifiant->Id>0){
            if ((int)$Lubrifiant->IDFAMILLE !== $Lubrifiant::$FamilleLubrifiant->Id){
                $Err->TxErreur=$Lubrifiant->Designation." n'est pas un lubrifiant !";
                echo json_encode($Err);
                exit;
            }
            $Reponse->Contenue = $Lubrifiant->ToObject();
            $Reponse->Autres = "LUBRIFIANT";
        }else{
            if (isset($_REQUEST['DESIGNATION'])){
                if ($_REQUEST['DESIGNATION'] !==""){
                    $Nom = "%".$_REQUEST['DESIGNATION']."%";
                }
            }
            $Lst = $Lubrifiant::GetListeLubrifiant($Nom);
            $Reponse->Contenue=[];
            if (count($Lst)){
                $Liste=[];
                foreach($Lst as $Lubr){
                    $Liste[] = $Lubr->ToObject();
                }
                $Reponse->Contenue = $Liste ;
            }
            $Reponse->Autres = "LISTE DES LUBRIFIANTS";
        }
       
        echo json_encode($Reponse);
        exit;
        break;

    case "LUBRIFIANT_STATISTIQUE": //Retourne les statistiques de vente
        $Lubrifiant = new xLubrifiant($nabysy);
        $DteD=null;
        $DteF=null;
        $GroupBy=null;
        if (isset($_REQUEST['DATE_DEPART'])){
            $Dte = new DateTime($_REQUEST['DATE_DEPART']);
            if ($Dte){
                $DteD=$Dte->format("Y-m-d");
            }
        }
        if (isset($_REQUEST['DATE_FIN'])){
            $Dte = new DateTime($_REQUEST['DATE_FIN']);
            if ($Dte){
                $DteF=$Dte->format("Y-m-d");
            }
        }
        if (isset($_REQUEST['GROUPE'])){
            if ($_REQUEST['GROUPE'] !==""){
                $GroupBy = $_REQUEST['GROUPE'];
            }
        }
        $Stat=$Lubrifiant->GetStatistiqueVente($DteD,$DteF,$GroupBy);
        $Reponse->Contenue=$Stat ;
        $Reponse->Autres = "STATISTIQUE DU STOCK DE LUBRIFIANT";
        echo json_encode($Reponse);
        exit;
        break;
    
    default:
    break;
 }

 ?>