<?php
/**
 * Module de gestion des Modes de Paiement.
 * Cet API réside sur le serveur local NAbySy GS afin de servir de proxy entre les caisses NAbySyGS et 
 * les différentes Plate-Forme de paiement telaue NAbySy-Wave
 */

use NAbySy\GS\Comptabilite\xCompteBancaire;
use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;
use NAbySy\MethodePaiement\xMethodePaie;
use NAbySy\xErreur;
use NAbySy\xNotification;

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
        //var_dump($_REQUEST);
        exit;
    }
    //var_dump($_REQUEST);

    switch ($action){
        case "MODULEPAIE_LISTE": //Retourne la liste des Modules de paiement enregistré sur la plate-forme
            $Liste=[];
            $Ind=0;
            if (isset($_REQUEST['INCLUDE_STATIC'])){
                if ((int)$_REQUEST['INCLUDE_STATIC']){
                    //Espece
                    $Ind++;
                    //$vMd['INDEX']=$Ind;
                    $vMd['ID']=$Ind ;
                    $vMd['Nom'] = "ESPECE";
                    $vMd['Description'] = "Paiement Espèce et Cash";
                    $vMd['HandleName'] = "" ;
                    $vMd['Alias'] = "ESPECE";
                    $vMd['Logo'] = "";
                    $Liste[]=$vMd ;
                    
                }
            }
            
            $CompteB=new xCompteBancaire($nabysy);

            if (count(N::$ListeModulePaiement)>0){
                foreach(N::$ListeModulePaiement as $Mod){
                    //var_dump(get_class($Mod));
                    try{
                        if ($Mod instanceof IModulePaieManager){
                            $Index=array_search($Mod,N::$ListeModulePaiement)+1 ;
                            if ($Ind>0){
                                $Index=$Ind+1 ;
                                //$vMd['INDEX']=$Index;
                                $Ind++;
                            }
                            
                            $vMd['ID']=$Index ;
                            $vMd['Nom'] = $Mod->Nom();
                            $vMd['Description'] = $Mod->Description();
                            $vMd['HandleName'] = $Mod->HandleModuleName() ;
                            $vMd['Alias'] = $Mod->UIName();
                            $vMd['Logo'] = $Mod->LogoURL();
                            $vMd['IdCompteBancaire']=0;

                            $vMd['API_DISPONIBLE'] = $Mod->Api_Disponible() ;
                            $vMd['CLIENT_ID'] = 0; //$Mod->CLIENT_ID ;
                            $vMd['API_TOKEN'] = $Mod->Api_Token() ;
                            $vMd['API_ENDPOINT'] = $Mod->Api_EndPoint() ;
                            $vMd['API_AUTH'] = $Mod->Api_Auth() ;
                            $vMd['API_AUTH_USER'] = $Mod->Api_Auth_User() ;
                            $vMd['API_AUTH_PWD'] = $Mod->Api_Auth_Pwd() ;
                            $vMd['WAIT_API_RESPONSE'] = $Mod->Wait_Api_Response() ;
                            $vMd['API_REFCLIENT'] = $Mod->Api_RefClient() ;
                            //Ajout de l'IdCompte Bancaire correspondant
                            $CpteBanc=$CompteB->GetCompteBancaireByName($Mod->Nom());
                            if(isset($CpteBanc)){
                                if($CpteBanc->Id){
                                    $vMd['IdCompteBancaire']= $CpteBanc->Id;
                                }
                            }
                            

                            $Liste[]=$vMd ;
                        }
                    }
                    catch (Exception $ex){

                    }
                }
            }
            $Reponse=new xNotification();
            $Reponse->OK=1;
            $Reponse->Autres = "Liste des Méthodes de Paiements";
            $Reponse->Contenue = $Liste ;
            echo json_encode($Reponse) ;
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
            if(isset($_REQUEST['TRACKERID'])){
                $Reponse->Source = $_REQUEST['TRACKERID'];
            }
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
            if(isset($_REQUEST['TRACKERID'])){
                $Reponse->Source = $_REQUEST['TRACKERID'];
            }
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

            exit;            
        case 'METHODE_GET': //Retourne la liste des Méthodes disponible
            $IdMeth=null;
            $NomMeth=null;
            $MethodePaie = new xMethodePaie(N::getInstance());

            $Crit="Id>0 ";
            if(isset($_REQUEST['IDMETHODE'])){
                if ((int)$_REQUEST['IDMETHODE']>0){
                    $IdMeth = (int)$_REQUEST['IDMETHODE'];
                    $Crit .=" and ID=".$IdMeth;
                }
            }
            if(isset($_REQUEST['NOM'])){
                if ($_REQUEST['NOM'] !== ""){
                    $NomMeth = $_REQUEST['NOM'];
                    $NoStrict="";
                    if (isset($_REQUEST['NOT_STRICT'])){
                        $NoStrict="%";
                    }
                    $Crit .=" and NOM  Like '".$NoStrict.$NomMeth.$NoStrict."'" ;
                }
            }

            if(isset($_REQUEST['HANDLEMODULE'])){
                if (trim($_REQUEST['HANDLEMODULE']) !== ""){
                    if($MethodePaie->ChampsExisteInTable('ModulePaiementHandle')){
                        $Crit .=" and ModulePaiementHandle like '".$_REQUEST['HANDLEMODULE']."' " ;
                    }
                }
            }

            if(isset($_REQUEST['CRITERE'])){
                if ($_REQUEST['CRITERE'] !== ""){
                    $Crit .=" and ".$_REQUEST['CRITERE'] ;
                }
            }
            $Rep = new xNotification();
            $Rep->OK=1;
            $Rep->Contenue = [];
            if ($MethodePaie->TableIsEmpty()){
                $Rep->SendAsJSON();
            }
            $Lst=$MethodePaie->ChargeListe($Crit);
            $Rep->Contenue = N::EncodeReponseSQL($Lst);
            $Rep->SendAsJSON();
            break;

        case 'METHODE_ADD': //Ajoute une nouvelle méthode
            $Nom=null;
            if (isset($PARAM['NOM'])){
                $Nom=$PARAM['NOM'];
            }
            if (isset($PARAM['Nom'])){
                $Nom=$PARAM['Nom'];
            }

            if (!isset($Nom)){
                $Err->TxErreur="Nom de la méthode absent. Impossible d'ajouter.";
                echo json_encode($Err);
                exit;
            }
            //On vérifie si le nom existe déja
            $MethodePaie = new xMethodePaie(N::getInstance());
            if ($MethodePaie->MethodeExiste($Nom)){
                $Err->TxErreur=$Nom." existe déjà comme méthode de paiement. Impossible de continuer.";
                echo json_encode($Err);
                exit;
            }

            if(N::getInstance()->User->NiveauAcces<4){
                $Err->TxErreur="Niveau d'accès insuffisant pour cette opération.";
                $Err->SendAsJSON();
            }

            $MethodePaie->Nom=$Nom;
            if ($MethodePaie->Enregistrer()){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra=$MethodePaie->Id;
                echo json_encode($Notif);
                exit;
            }
            $Err->TxErreur="Impossible d'enregistrer ".$Nom." comme méthode de paiement. Erreur systeme.";
            echo json_encode($Err);
            break;

        case 'METHODE_SAVE': //Modifie une méthode
            $Id=null;
            if (isset($PARAM['ID'])){
                $Id=(int)$PARAM['ID'];
            }
            if (isset($PARAM['Id'])){
                $Id=(int)$PARAM['Id'];
            }
            if (!isset($Id)){
                $Err->TxErreur="Id de la méthode non définit";
                echo json_encode($Err);
                exit;
            }
            $MethodePaie=new xMethodePaie($nabysy,$Id,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE);
            if ($MethodePaie->Id==0){
                $Err->TxErreur="Impossible de trouver la Méthode avec ID=".$Id;
                echo json_encode($Err);
                exit;
            }

            if (isset($PARAM['NOM'])){
                if($PARAM['NOM'] !== '' ){
                    if(N::getInstance()->User->NiveauAcces<4){
                        $Err->TxErreur="Niveau d'accès insuffisant pour cette opération.";
                        $Err->SendAsJSON();
                    }
                    if ($MethodePaie->Nom !== $PARAM['NOM']){
                        if (!$MethodePaie->MethodeExiste($PARAM['NOM'])){
                            $MethodePaie->Nom=$PARAM['NOM'];
                            $MethodePaie->Enregistrer();
                        }else{
                            $Err->TxErreur=$PARAM['NOM']." existe déjà comme méthode de paiement. Impossible de modifier la méthode.";
                            echo json_encode($Err);
                            exit;
                        }
                    }
                }
            }

            $Notif=new xErreur;
            $Notif->OK=1;
            $Notif->Extra=$MethodePaie->Id;
            echo json_encode($Notif);
            break;

        case 'METHODE_SUPP': //Supprime une méthode
            if(N::getInstance()->User->NiveauAcces<4){
                $Err->TxErreur="Niveau d'accès insuffisant pour cette opération.";
                $Err->SendAsJSON();
            }
            $Id=null;
            if (isset($PARAM['ID'])){
                $Id=(int)$PARAM['ID'];
            }
            if (isset($PARAM['Id'])){
                $Id=(int)$PARAM['Id'];
            }
            if (!isset($Id)){
                $Err->TxErreur="Id de la méthode non définit";
                echo json_encode($Err);
                exit;
            }
            $MethodePaie=new xMethodePaie($nabysy,$Id,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE);
            if ($MethodePaie->Id==0){
                $Err->TxErreur="Impossible de trouver la Méthode avec ID=".$Id;
                echo json_encode($Err);
                exit;
            }

            if ($MethodePaie->Supprimer()){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra=$MethodePaie->Nom. " a été supprimé de la base de donnée.";
                echo json_encode($Notif);
                exit;
            }
            $Err->TxErreur="Impossible de supprimer ".$MethodePaie->Nom." . Erreur systeme.";
            echo json_encode($Err);
            break;

        default:
            break;

    }

?>