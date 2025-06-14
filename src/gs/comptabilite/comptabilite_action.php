<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 * 
 * API pour le traitement lié aux Versements Client
 */

use NAbySy\GS\Client\xClient;
use NAbySy\GS\Comptabilite\xHistoriqueTransaction;
use NAbySy\GS\Comptabilite\xInfosCheque;
use NAbySy\xErreur;
use NAbySy\xNotification;

 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;
 if(isset($_REQUEST['TRACKERID'])){
    $Err->Source = $_REQUEST['TRACKERID'];
 }
 //var_dump($_REQUEST);
 switch ($action){
    case "SAVE_VERSEMENT_CLIENT" ; //Enregistrement d'un versement client
        $IdClient=null;
        $Mt=null;
        $ModeReglement="E"; //(E)spèce ; (C)hèque ; (V)irement / Carte
        $IdTransaction = null;
        $IdCompteB=null;
        $DateVers=null;
        $Trans=new xHistoriqueTransaction($nabysy);
        
        #region Condition
            if(isset($_REQUEST['ID'])){
                if ((int)$_REQUEST['ID']){
                    $IdTransaction = (int)$_REQUEST['ID'];
                }
            }
            if(isset($_REQUEST['IDCLIENT'])){
                if ((int)$_REQUEST['IDCLIENT']){
                    $IdClient = (int)$_REQUEST['IDCLIENT'];
                }
            }
            if(isset($_REQUEST['IdClient'])){
                if ((int)$_REQUEST['IdClient']){
                    $IdClient = (int)$_REQUEST['IdClient'];
                }
            }
            if(isset($_REQUEST['MONTANT'])){
                if ((float)$_REQUEST['MONTANT']){
                    $Mt = (float)$_REQUEST['MONTANT'];
                }
            }
            if(isset($_REQUEST['Montant'])){
                if ((float)$_REQUEST['Montant']){
                    $Mt = (float)$_REQUEST['Montant'];
                }
            }
            if (!isset($IdClient)){
                $Err->TxErreur = "ID Client non définit.";
                echo json_encode($Err);
                exit;
            }
            if (!isset($Mt)){
                $Err->TxErreur = "Montant versé non définit.";
                echo json_encode($Err);
                exit;
            }
            if(isset($_REQUEST['MODEREGLEMENT'])){
                if (trim($_REQUEST['MODEREGLEMENT']!=="")){
                    $ModeReglement = $_REQUEST['MODEREGLEMENT'];
                }
            }
            if (isset($_REQUEST['IdCompteBancaire'])){
                if ((int)$_REQUEST['IdCompteBancaire']>0){
                    $IdCompteB=(int)$_REQUEST['IdCompteBancaire'];                    
                }
            }
            if(isset($_REQUEST['DATEVERS'])){
                if($_REQUEST['DATEVERS']!==""){
                    $dte=new DateTime($_REQUEST['DATEVERS']);
                    if($dte){
                        $DateVers = $dte->format("Y-m-d");
                    }
                }
            }
        #endregion

        $InfosCheq=null;
        if ($ModeReglement =="C" || $ModeReglement =="V"){
            $InfosCheq=new xInfosCheque;
            if($IdCompteB){
                $InfosCheq->IdBanqueReception = $IdCompteB ;
                $InfosCheq->Montant = $Mt;
                if(isset($_REQUEST['Nom'])){
                    $InfosCheq->NomBanque = $_REQUEST['Nom'];
                }               
            }            
        }

        $Client=new xClient($nabysy,$IdClient);
        if ($Client->Id==0){
            $Err->TxErreur = "Id Client No".$IdClient." introuvable !";
            echo json_encode($Err);
            exit;
        }
        $Trans=new xHistoriqueTransaction($nabysy,$IdTransaction);
        $IsNew=true;
        if ($Trans->Id>0){
            $IsNew = false;
        }
        if($IdTransaction > 0 && $IsNew){
            $Err->TxErreur = "Transaction No".$IdTransaction." introuvable !";
            echo json_encode($Err);
            exit;
        }

        $HistTrans=$Trans->EnregistrerNouveauVersementClient($Client,$Mt,null,null,$DateVers,$ModeReglement,$InfosCheq);
        if ($HistTrans){
            if (isset($_REQUEST['MODULE-PAIEMENT'])){
                    
                try {
                    $ListeM=json_decode($_REQUEST['MODULE-PAIEMENT']);
                    //var_dump($ListeM);
                    foreach($ListeM->LISTE_MODULEPAIE as $xMethP){						
                        $TotalReduction +=(int)$xMethP->MONTANT ;
                        $Module=$nabysy->GetModulePaie($xMethP->HANDLE);
                        if (isset($Module)){
                            $ListeMethodePaie[]=$Module ;
                            $ListeMobilePaieCheckOut[]=$xMethP;
                        }						
                    }
                }
                catch (Exception $ex){
                    $Err->TxErreur = $ex ;
                    echo json_encode($Err);
                    exit;
                }

                if (isset($ListeMethodePaie)){
                    foreach($ListeMethodePaie as $MethP){
                        try{
                            $Ind=array_search($MethP,$ListeMethodePaie);
                            if (isset($ListeMobilePaieCheckOut[$Ind])){
                                $CheckOut=$ListeMobilePaieCheckOut[$Ind];
                                $CheckOutArray=$CheckOut;
                                if (is_object($CheckOutArray)){
                                    $CheckOutArray=(array)$CheckOut ;
                                }									
                                //var_dump($CheckOutArray);
                                if ($MethP->UpDateTransaction($HistTrans->Id,$CheckOutArray) == false){
                                    //On annule l'opération ou bien ?
                                    $Err->TxErreur .=$MethP->Nom().": ".$MethP->LastError()."#";
                                }
                            }
                            //exit;
                        }catch(Exception $ex){
                            $Err->TxErreur = $ex ;
                            echo json_encode($Err);
                            exit;
                        }
                        
                    }
                }
            }
            $Reponse->OK=1;
            $Reponse->Contenue = $HistTrans->ToObject();
        }else{
            $Reponse->OK=0;
            $Reponse->TxErreur = "Impossible de valider l'opération. Raison inconnue";
        }
        if(isset($_REQUEST['TRACKERID'])){
            $Reponse->Source = $_REQUEST['TRACKERID'];
        }
        
        echo json_encode($Reponse);
        exit;
        break;

    case "GET_RESUME_VERSEMENT": //Retourne un resumé de tous les versement effectués sur une période
        $DateDu = null;
        $DateAu = null;
        $IdCaissier = null;
        $IdClient = null;
        $IdCompte=null;
        $Trans=new xHistoriqueTransaction($nabysy);
        if(isset($_REQUEST['DATE_DEPART'])){
            if ($_REQUEST['DATE_DEPART'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_DEPART']);
                if($Dte){
                    $DateDu=$Dte;
                }
            }
        }
        if(isset($_REQUEST['DATE_FIN'])){
            if ($_REQUEST['DATE_FIN'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_FIN']);
                if($Dte){
                    $DateAu=$Dte;
                }
            }
        }
        if (!isset($DateDu)){
            $DateDu= new DateTime('now');
        }
        if (!isset($DateAu)){
            $DateAu= new DateTime('now');
        }
        if(isset($_REQUEST['IDCAISSIER'])){
            if((int)$_REQUEST['IDCAISSIER'] > 0 ){
                $IdCaissier = (int)$_REQUEST['IDCAISSIER'];
            }
        }
        if(isset($_REQUEST['IDCLIENT'])){
            if((int)$_REQUEST['IDCLIENT'] > 0 ){
                $IdClient = (int)$_REQUEST['IDCLIENT'];
            }
        }
        if(isset($_REQUEST['IDCOMPTEBANCAIRE'])){
            if((int)$_REQUEST['IDCOMPTEBANCAIRE'] > 0 ){
                $IdCompte = (int)$_REQUEST['IDCOMPTEBANCAIRE'];
            }
        }
        $Critere="DATEOP>='".$DateDu->format("Y-m-d")."' and DATEOP <='".$DateDu->format("Y-m-d")."' " ;
        if(isset($IdCaissier)){
            $Critere .=" and IDOPERATEUR = ".(int)$IdCaissier;
        }
        if(isset($IdClient)){
            $Critere .=" and (IDCLIENT = ".(int)$IdClient." OR IDCLIENT like '".(int)$IdClient."'%) "  ;
        }
        if(isset($IdCompte)){
            $Critere .=" and IdCompteBancaire = ".(int)$IdCompte;
        }
        //Recherche du total des Transactions
        $Retour=$Trans->GetTotalTransacton($DateDu,$DateAu,$IdCaissier,$IdClient, $IdCompte);
        
        echo json_encode($Retour);
        exit;
        break;
        //$Lst=$Trans->ChargeListe($Critere);


    default:
        break;
 }
 //echo __FILE__." Ligne N°:".__LINE__." => Cherchons Action ".$action." dans les modulepaie...</br>";
 //echo "Lecture du fichier ".__DIR__."\modulepaie_action.php</br>";
 //readfile(__DIR__."\modulepaie_action.php");
 
 try {
    include_once "modulepaie_action.php";
 } catch (\Throwable $th) {
    throw $th;
 }


 ?>