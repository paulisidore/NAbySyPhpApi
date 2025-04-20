<?php
/**
 * Module de gestion des Modes de Paiement.
 * Cet API réside sur le serveur local NAbySy GS afin de servir de proxy entre les caisses NAbySyGS et 
 * les différentes Plate-Forme de paiement telaue NAbySy-Wave
 */

use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;

 include_once 'nabysy_start.php';
	
	$PARAM=$_REQUEST;
	
    $ChampAction='Action';
	$action=null ;
	if (isset($PARAM[$ChampAction])){
		$action=$PARAM[$ChampAction] ;
	}
    if (isset($PARAM[strtolower($ChampAction)])){
		$action=$PARAM[strtolower($ChampAction)] ;
	}
   	
    $Err=new xErreur ;
    $Err->TxErreur='Erreur';
    $Err->OK=0 ;
    if (!isset($action)){		
        //Il n'y a pas d'action, on retourne a la page précedente
        $Err->OK=0;
        $Err->TxErreur='Action non définit !' ;
        $Err->Source= __FUNCTION__ ;
        $reponse=json_encode($Err) ;
        echo $reponse ;
        exit;	
	}

    //echo __LINE__ ;
    //var_dump($nabysy::$ListeModulePaiement);

    if (!$nabysy->ValideUser()){
        exit;
    }
    //var_dump($_REQUEST);

    switch ($action){
        case "MODULEPAIE_LISTE": //Retourne la liste des Modules de paiement enregistré sur la plate-forme
            $Liste=[];
            
            if (count($nabysy::$ListeModulePaiement)>0){
                foreach($nabysy::$ListeModulePaiement as $Mod){
                    //var_dump(get_class($Mod));
                    try{
                        if ($Mod instanceof IModulePaieManager){
                            $Index=array_search($Mod,$nabysy::$ListeModulePaiement);
                            $vMd['ID']=$Index ;
                            $vMd['Nom'] = $Mod->Nom();
                            $vMd['Description'] = $Mod->Description();
                            $vMd['HandleName'] = $Mod->HandleModuleName() ;
                            $vMd['Alias'] = $Mod->UIName();
                            $vMd['Logo'] = $Mod->LogoURL();
                            $Liste[]=$vMd ;
                        }
                    }
                    catch (Exception $ex){

                    }
                }
            }
            echo json_encode($Liste) ;
        exit;

        case "MODULEPAIE_NEW_CHECKOUT":   //Retoune les infos (url) a Afficher sur le poste caisse afin d'etre validé sur le mobile du client
            $Montant=null;
            $RefPanier=null;
            $Caisse=null;
            $Caissier =null;
            $HandleModName=null;
            
            if (isset($PARAM['HANDLENAME'])){
                $HandleModName=$PARAM['HANDLENAME'] ;
            }else{
                $Err->TxErreur='Handle du module absent !' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            if (isset($PARAM['MONTANT'])){
                $Montant=$PARAM['MONTANT'] ;
            }else{
                $Err->TxErreur='Aucun montant définit' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            if (isset($PARAM['REFPANIER'])){
                $RefPanier=$PARAM['REFPANIER'] ;
            }else{
                $Err->TxErreur='Aucune reference de panier définit. Impossible de valider le panier.' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            if (isset($PARAM['CAISSE'])){
                $Caisse=$PARAM['CAISSE'] ;
            }else{
                $Err->TxErreur='Aucune caisse ou poste de saisie définit. Impossible de valider le panier.' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            if (isset($PARAM['CAISSIER'])){
                $Caissier=$PARAM['CAISSIER'] ;
            }else{
                $Err->TxErreur='Aucun CAISSIER ou poste de saisie définit. Impossible de valider le panier.' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            
            $Module=$nabysy->GetModulePaie($HandleModName);
            
            if (!isset($Module)){
                $Err->TxErreur='Le Module avec le Handle '.$HandleModName." est absent ou indisponible pour le moment." ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }

            $RefCheckOut['REFFACTURE']=$RefPanier ;
            $RefCheckOut['MONTANT']=$Montant ;
            $RefCheckOut['CAISSE']=$Caisse ;
            $RefCheckOut['CAISSIER']=$Caissier;

            if (isset($PARAM['IDCAISSE'])){
                $RefCheckOut['IDCAISSE']=$PARAM['IDCAISSE'];
            }
            if (isset($PARAM['IDCAISSIER'])){
                $RefCheckOut['IDCAISSIER']=$PARAM['IDCAISSIER'];
            }
            
            $Reponse=$Module->GetCheckOut($Montant,$RefCheckOut);
            //var_dump($Reponse);
            echo json_encode($Reponse);
            exit;

        case "MODULEPAIE_CHECK_VALIDATION":   //Demande l'etat de validation d'une facture

            $Reponse=new xErreur;
            $Reponse->OK=1;
            $Reponse->Extra=1 ; //Ref du paiement effectuer

            $IdDemande =null;
            if (isset($PARAM['IDDEMANDE'])){
                $IdDemande=$PARAM['IDDEMANDE'];
            }
            $Etat=new xNotification ;
            $Etat->OK=0;
            if (!isset($CheckOutInfo['IDDEMANDE'])){            
                $Etat->TxErreur="Id de la demande absent !";
                echo json_encode($Etat) ;
                exit;
            }

            $HandleModName=null;
            if (isset($PARAM['HANDLENAME'])){
                $HandleModName=$PARAM['HANDLENAME'] ;
            }else{
                $Err->TxErreur='Handle du module absent !' ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }
            $Module=$nabysy->GetModulePaie($HandleModName);
            if (!isset($Module)){
                $Err->TxErreur='Le Module avec le Handle '.$HandleModName." est absent ou indisponible pour le moment." ;
                $Err->Source=$action ;
                echo json_encode($Err) ;
                exit;
            }

            $IdDemande=$CheckOutInfo['IDDEMANDE'];
            $Demande=new  xCheckOutParam($this->Main,$IdDemande);
            $Reponse=$Module->GetEtatCheckOut($Demande);
            echo json_encode($Reponse);
            exit;
        case "MODULEPAIE_CANCEL_CHECKOUT":  //Annule une demande de paiement

            exit;
        case "MODULEPAIE_REFUND":   //Effectue un remboursement sur un paiement selon le ou les modes de règlements trouvés

            exit;

        case "MODEPAIE_GET_RAPPORT":    //Retourne l'historique des paiements selon la période et autres critere
            $DateDebut=date("Y-m-d");
            $DateFin=$DateDebut ;
            $NomMethode =null;

        default:

            exit;

    }

?>