<?php
    /**
     * END-POINT Gestion et Controle des UTILISATEURS
     * By Paul Isidore A. NIAMIE
     * 
     * API d'Accès pour les clients des Bons d'Achat EXCLUSIVE
     * 
     */

use NAbySy\GS\Client\xClientBonAchat;
use NAbySy\GS\Client\xClientEntreprise;
use NAbySy\Lib\BonAchat\Exclusive\xCarteBonAchatExclusive;
use NAbySy\Lib\BonAchat\xBonAchatManager;
use NAbySy\Lib\BonAchat\xHistoriqueBonAchat;
use NAbySy\Lib\Sms\xMessageSMS;
use NAbySy\Lib\Sms\xSMSOrange;
use NAbySy\ORM\xORMHelper;
use NAbySy\xErreur;

     /**
      * Nous allon auto-logger un Utilisateur systeme pour simplifier la tache aux clients
      */
    /* $fakeUser=new xUser($nabysy,0);
    $fakeUser->Login="BON_ACHAT_CUSTUMER_MANAGER";
    $fakeUser->Password='NAbySyGS#PAULVB@2023';
    $fakeUser->NIVEAUACCES=1;
    $fakeUser->Nom ="GENERAL CUSTUMER";
    $fakeUser->Prenom="MANAGER";

    $Auth=new xAuth($nabysy);
    $Token=$Auth->GetToken($fakeUser);
    $nabysy->UserToken=$Token ;
    $nabysy->User=$fakeUser ; */
	
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
 
/*      if (!$nabysy->ValideUser()){
         exit;
     } */
 
     switch ($action){
        case "CLIENTBONACHAT_CREATE": //Créer un nouveau client pour les Bons d'Achat
            $TEL=null;
            $INDICATIF="221";
            

            if (!isset($PARAM['TEL'])){
                $Err->TxErreur = "Téléphone absent. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $TEL=$PARAM['TEL'];
            $TEL=trim($TEL);
            if (isset($PARAM['INDICATIF'])){
                $INDICATIF=trim($PARAM['INDICATIF']);
            }

            if ($TEL==''){
                $Err->TxErreur = "Aucun numéro de téléphone fournit. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $vInd=null ;
            if (substr($INDICATIF,0,1)=='+'){
                $vInd = substr($INDICATIF,1);
                $INDICATIF=$vInd;
                $vInd=null;
            }
            if (strlen($INDICATIF)>3 ){               
                if(substr($INDICATIF,0,2)=='00'){
                    $vInd = substr($INDICATIF,2);
                }                
            }
            if ($vInd){
                $INDICATIF=$vInd ;
            }
            $vTEL=$INDICATIF.$TEL;
            $Clt=new xClientBonAchat($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$vTEL);

            $isAnClient=false;
            $SendOTP=true;
            if ($Clt->Id>0){
                //On retourne les infos du client
                $isAnClient=true;

                //On vérifie si le code OTP a été envoyé il y a moins de 5mn pour authoriser un nouvel envoie
                //$Clt->AddToJournal("DEBUG","Date Prec OTP: ".$Clt->DATE_ENVOIEOTP);
                $start_date = new DateTime($Clt->DATE_ENVOIEOTP);
                $dateAct=new DateTime();
                $since_start = $start_date->diff($dateAct);
                $minutes = $since_start->days * 24 * 60;
                $minutes += $since_start->h * 60;
                $minutes += $since_start->i;
                //$Clt->AddToJournal("DEBUG","Durée précédent OTP: ".$minutes);
                if ($minutes < 5){
                    $SendOTP=false; //L'ancien OTP reste valide
                }

            }else{
                $Clt->ETAT = $Clt::ETAT_ACTIF;
                $Clt->DATECREATION=date("Y-m-d H:i:s");
            }
            
            if ($SendOTP){
                $CODEPIN=mt_rand(1000,9999); //Génère un code automatiquement;
                $Clt->PINCODE = $CODEPIN ;
            }            
            
            $Clt->TEL=$vTEL ;
            $Clt->INDICATIF = $INDICATIF;
            $Clt->TelephneSansIndic=$TEL ;           
            
            if (isset($PARAM['NOM'])){
                $Clt->NOM=$PARAM['NOM'] ;
            }
            if (isset($PARAM['PRENOM'])){
                $Clt->Prenom=$PARAM['PRENOM'] ;
            }
            $Clt->IS_AUTH =0 ;            

            if ($Clt->Enregistrer()){
                $Msg="Bienvenue dans le programme des Bons d'Achat HYPERMARCHE EXCLUSIVE. Votre CODE PIN: ".$Clt->PINCODE." ";
                if ($isAnClient){
                    $Msg="Bon retour dans le programme des Bons d'Achat HYPERMARCHE EXCLUSIVE. Votre CODE PIN: ".$Clt->PINCODE." ";
                }
                $Msg=trim($Msg);

                //$Clt->AddToJournal("DEBUG","Envoie du Code OTP: ".$SendOTP);
                //Si un code précédent a été envoyé il y a plus de 5mn alor on envoie un nouveau
                if ($SendOTP){
                    
                    $sms=new xMessageSMS($nabysy,'','EXCLUSIVE',$Clt->TEL,$Msg) ;
                    $OrangeSMS=new xSMSOrange($nabysy);
                    $TelFoSMS="+".$Clt->TEL ;
                    $Clt->DATE_ENVOIEOTP=date("Y-m-d H:i:s");
                    if (!$OrangeSMS->EnvoieSms($TelFoSMS,$Msg)){
                        //Erreur d'envoie du SMS / On peux utiliser WhatsApp ?
                        if (!$OrangeSMS->Ready){
                            //Les clients n'auront pas besoins d' activer leurs compte ou bien ?
                            //$Clt->IS_AUTH =1 ;
                        }
                    } 
                    $Clt->Enregistrer();
                }
                               

                $Tache="CREATION COMPTE CLIENT BON ACHAT";
                $Note="Abonnement de ".$Clt->TEL." (IdU-".$Clt->Id.") : CodePin attendu: ".$Clt->PINCODE;
                if ($isAnClient){
                    $Tache="REABONNEMENT DU COMPTE CLIENT BON ACHAT";
                    $Note="Re-Abonnement de ".$Clt->TEL." (IdU-".$Clt->Id.") : CodePin attendu: ".$Clt->PINCODE;
                }
                $Clt->AddToJournal($Tache,$Note);
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra=$Clt->Id;
                $Notif->Source="WAIT FOR AUTH";
                echo json_encode($Notif) ;
                exit;
            }

            $Err->TxErreur = "Erreur non spécifiée.";
            echo json_encode($Err) ;
            exit;
            break;
        
        case "CLIENTBONACHAT_AUTHCLIENT": //Termine l'authentification d'un compte client
            $CODEPIN=null; //Génère un code automatiquement;
            $IDCLIENT=null;
            if (!isset($PARAM['CODEPIN'])){
                $Err->TxErreur = "Absence du CODE PIN de validation. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $CODEPIN=$PARAM['CODEPIN'];
            $IDCLIENT=$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $TxDejaAuth="" ;
            if ($Clt->IS_AUTH>0){
                $TxDejaAuth=" précédemment authentifié" ;
            }

            if ($CODEPIN == $Clt->PINCODE){
                $Clt->IS_AUTH=1;
                $Clt->DATE_AUTH=date("Y-m-d H:i:s");
                if ($Clt->Enregistrer()){
                    $Tache='AUTH_COMPTE_CLIENT' ;
                    $Note=$Clt->TEL.$TxDejaAuth." s'est authentifié correctement.";
                    $Clt->AddToJournal($Tache,$Note);
                    $Notif=new xErreur();
                    $Notif->OK=1;
                    $Notif->Extra=$Clt->Id;
                    $Notif->Source="Authentification validée.";
                    echo json_encode($Notif) ;
                    exit;
                }else{
                    $Err->TxErreur = "code OTP non valide. Recommencez plus tards svp. impossible de continuer.";
                    echo json_encode($Err) ;
                    exit;
                }
            }else{
                $Err->TxErreur = "code OTP non valide. Erreur du CODE OTP.";
                echo json_encode($Err) ;
                exit;
            }
            break;


        case "CLIENTBONACHAT_AJOUTCARTE": //Ajouter un Bon d'Achat dans le portefeuille d'un client
            $IDCLIENT=null;
            $Carte=null;
            $NomCarte=null;

            if (!isset($PARAM['IDCARTE'])){
                $Err->TxErreur = "Absence de l'identifiant de la carte. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IDCARTE=$PARAM['IDCARTE'];
            $IDCLIENT=$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ((int)$Clt->IS_AUTH == 0){
                $Err->TxErreur = "Votre compte est en attente d'authentification. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $Carte=new xCarteBonAchatExclusive($nabysy,$IDCARTE);
            if ($Carte->Id==0){
                $Err->TxErreur = "Carte inexistante. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ($Carte->REFCARTE == ''){
                $Err->TxErreur = "Carte non initialisée contactez le service client svp. ETAT: NO PAIRING. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ($Carte->ETAT == $Carte::CARTE_BLOQUEE || $Carte->ETAT == $Carte::CARTE_SUSPENDUE){
                $Err->TxErreur = "Cette carte n'est pas activée (".$Carte->Id."). ETAT: ".$Carte->ETAT.". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if (isset($_REQUEST['LIBELLE'])){
                $NomCarte=trim($_REQUEST['LIBELLE']);
                if ($NomCarte !=''){
                    if ($Carte->LibeleClient !== $NomCarte){
                        $Carte->LibeleClient =$NomCarte ;
                        $Carte->Enregistrer();
                    }
                }
            }

            if ($Clt->AjoutCarte($Carte)){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra="Carte Num.".$Carte->Id." ajoutée correctement.";
                echo json_encode($Notif) ;
                exit;
            }else{
                $Err->TxErreur = "La carte n'a pas été ajoutée. elle existe déjà dans la collection du client. Impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            break;
        case "CLIENTBONACHAT_DESINSCRIRE": //Désinscrire le client
            $IDCLIENT=null;
            $CODEPIN=null;

            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if (!isset($PARAM['CODEPIN'])){
                $Err->TxErreur = "Absence du code PIN. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            
            $IDCLIENT=$PARAM['IDCLIENT'];
            $CODEPIN=$PARAM['CODEPIN'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ($Clt->PINCODE !== $CODEPIN){
                $Err->TxErreur = "Code PIN incorrecte. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            foreach ($Clt->Cartes as $Carte){
                $Clt->RemoveCarte($Carte->Id);
            }

            if ($Clt->Supprimer()){
                $Tache="DESINSCRIPTION CLIENT BON ACHAT";
                $Note="Le client ".$Clt->TEL." a supprimé son compte.";
                $Clt->AddToJournal($Tache,$Note);
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra="Vous avez été supprimé du programme des bons d'achats Exclusive correctement.";
                echo json_encode($Notif) ;
                exit;
            }else{
                $Err->TxErreur = "Erreur de suppression de votre copmpte. Impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            break;
        case "CLIENTBONACHAT_DELETECARTE": //Supprimer un Bon d'Achat du portefeuille d'un client
            $IDCLIENT=null;
            $Carte=null;
            if (!isset($PARAM['IDCARTE'])){
                $Err->TxErreur = "Absence de l'identifiant de la carte. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IDCARTE=$PARAM['IDCARTE'];
            $IDCLIENT=$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $Carte=new xCarteBonAchatExclusive($nabysy,$IDCARTE);
            if ($Carte->Id==0){
                $Err->TxErreur = "Carte inexistante. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }            

            if ($Clt->RemoveCarte($Carte->Id)){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra="Carte ".$Carte->Id." supprimée correctement.";
                echo json_encode($Notif) ;
                exit;
            }else{
                $Err->TxErreur = "La carte n'a pas été supprimée pour une raison inconnue.";
                echo json_encode($Err) ;
                exit;
            }
            break;
        
        case "CLIENTBONACHAT_LISTECARTE": //Liste des Cartes du Client
            $IDCLIENT=null;
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IDCLIENT=(int)$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $Lst=$Clt->GetListeCarte();
            $Liste=[];
            while($rw=$Lst->fetch_assoc()){
                $CarteClient=new xORMHelper($Clt->Main,(int)$rw['ID'],false,$Clt->CartesClient->Table);
                if ($CarteClient->Id>0){
                    $Carte=new xCarteBonAchatExclusive($nabysy,(int)$rw['IDCARTE']);
                    $Entreprise=new xClientEntreprise($Clt->Main,(int)$Carte->IDENTREPRISE);
                    $CarteClient->IdEntreprise=$Carte->IDENTREPRISE;
                    $CarteClient->Etat = $Carte->Etat ;
                    $CarteClient->Solde = $Carte->Solde ;                    
                    $CarteClient->Entreprise = $Entreprise->Nom ;
                    $CarteClient->NomEntreprise =$Entreprise->Nom ;
                    $CarteClient->NomClient = $Carte->NomClient ;
                    $CarteClient->PrenomClient = $Carte->PrenomClient ;
                    $CarteClient->Tel = $Carte->Tel ;
                    $Liste[]=json_decode($CarteClient->ToJSON()) ;
                    //var_dump($Liste);
                    //exit;
                }                
            }
            $ListeC=json_encode( $Liste );
            echo $ListeC ;
            exit;
            break;

        case "CLIENTBONACHAT_HISTORIQUE": //Renvoie l'historique des transactions
            $IDCLIENT=null;
            $IdCarte=null;
            $Montant=null;
            $IdFacture=null;
            $DateD=null;
            $DateF=null;
            $HaveDate=false;
            $Critere="ID>0 ";

            if (!isset($PARAM['IDCARTE'])){
                $Err->TxErreur = "Absence de l'identifiant de la carte. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IdCarte=$PARAM['IDCARTE'];
            $IDCLIENT=$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            

            $Carte=new xCarteBonAchatExclusive($nabysy,$IdCarte);
            if ($Carte->Id==0){
                $Err->TxErreur = "Carte inexistante. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $Historique = new xHistoriqueBonAchat($nabysy);

            if(isset($_REQUEST['DATEDEBUT'])){
                $Dte = new DateTime($_REQUEST['DATEDEBUT']) ;
                if ($Dte !==false){
                    $HaveDate=true;
                    $DateD=$Dte->format('Y-m-d');
                    $TxDate=" AND DATEOP ='".$DateD."' ";
                    if (isset($_REQUEST['DATEFIN'])){
                        $Dte = new DateTime($_REQUEST['DATEFIN']) ;
                        if ($Dte !==false){
                            $DateF=$Dte->format('Y-m-d');
                            $TxDate=" AND DATEOP >='".$DateD."' and DATEOP<='".$DateF."' ";
                        }
                    }
                    $Critere .=$TxDate;
                }
            }

            if(!$HaveDate){
                $Dte = new DateTime();
                $Annee=(int)$Dte->format('Y');
                $Annee -= 1;
                $DateLimit=$Annee."-".$Dte->format('m-01');
                //On va limiter le nombre de ligne a envoyer
                $TxDate=" AND DATEOP >='".$DateLimit."' ";
                $Critere .=$TxDate;
            }

            if(isset($_REQUEST['IDENTREPRISE'])){
                $Critere .=" and IDENTREPRISE=".(int)$_REQUEST['IDENTREPRISE'] ;
            }
            if(isset($_REQUEST['NOMENTREPRISE'])){
                $Critere .=" and NOMENTREPRISE like '%".$_REQUEST['NOMENTREPRISE']."%' " ;
            }
            if(isset($_REQUEST['IDCARTE'])){
                $Critere .=" and IDCARTE = '".(int)$IdCarte."' " ;
            }
            if(isset($_REQUEST['REFCARTE'])){
                $Carte=new xCarteBonAchatExclusive($nabysy,null,N::GLOBAL_AUTO_CREATE_DBTABLE,"",$_REQUEST['REFCARTE']);
                if ($Carte->Id>0){
                    $Critere .=" and IDCARTE = '".$Carte->Id."' " ;
                }else{
                    $Critere .=" and IDCARTE = '0' " ;
                }
                
            }
            if(isset($_REQUEST['SURCARTE'])){
                $Critere .=" and SURCARTE = '".(int)$_REQUEST['SURCARTE']."' " ;
            }
            if(isset($_REQUEST['OPERATION'])){
                $Critere .=" and OPERATION like '%".$_REQUEST['OPERATION']."%' " ;
            }
            if(isset($_REQUEST['CREDIT'])){
                if ((int)$_REQUEST['CREDIT']>0){
                    $Critere .=" and IsCredit = 1 " ;
                }
                if ((int)$_REQUEST['CREDIT'] == 0){
                    $Critere .=" and IsCredit = 0 " ;
                }                
            }
            if(isset($_REQUEST['DEDIT'])){
                if ((int)$_REQUEST['DEDIT']>0){
                    $Critere .=" and IsCredit = 0 " ;
                }
                if ((int)$_REQUEST['DEDIT'] == 0){
                    $Critere .=" and IsCredit = 1 " ;
                }                
            }
            if(isset($_REQUEST['LIBELLE'])){
                $Critere .=" and LIBELLE like '%".$_REQUEST['LIBELLE']."%' " ;
            }
            if(isset($_REQUEST['MONTANT'])){
                $Critere .=" and MONTANT = '".(int)$_REQUEST['MONTANT']."' " ;
            }
            if(isset($_REQUEST['IDUTILISATEUR'])){
                $Critere .=" and IDUTILISATEUR = '".(int)$_REQUEST['IDUTILISATEUR']."' " ;
            }
            if(isset($_REQUEST['LOGIN'])){
                $Critere .=" and LOGIN like '%".$_REQUEST['LOGIN']."%' " ;
            }
            if(isset($_REQUEST['POSTESAISIE'])){
                $Critere .=" and POSTESAISIE like '%".$_REQUEST['POSTESAISIE']."%' " ;
            }
            if(isset($_REQUEST['IDPOSTESAISIE'])){
                $Critere .=" and IDPOSTESAISIE = '".(int)$_REQUEST['IDPOSTESAISIE']."' " ;
            }
            if(isset($_REQUEST['IDFACTURE'])){
                if ($Historique->MySQL->ChampsExiste($Historique->Table,'IdFacture')){
                    $Critere .=" and IDFACTURE = '".(int)$_REQUEST['IDFACTURE']."' " ;
                }                
            }

            $Ordre=" ID DESC ";
            if(isset($_REQUEST['ORDRE'])){
                if($_REQUEST['ORDRE'] ==! ""){
                    $Ordre =$_REQUEST['ORDRE'] ;
                }                
            }
            $Lst=$Historique->ChargeListe($Critere,$Ordre);
            $Reponse=$Historique->EncodeReponseSQLToJSON($Lst);
            //var_dump($Reponse);
            echo $Reponse;
            exit;



        case "CLIENTBONACHAT_UPDATECARTE": //Modifier les informations d'identification de la carte
            $IDCLIENT=null;
            $Carte=null;
            $NomCarte=null;

            if (!isset($PARAM['IDCARTE'])){
                $Err->TxErreur = "Absence de l'identifiant de la carte. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (!isset($PARAM['IDCLIENT'])){
                $Err->TxErreur = "Absence du Code client. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IDCARTE=$PARAM['IDCARTE'];
            $IDCLIENT=$PARAM['IDCLIENT'];
            $Clt=new xClientBonAchat($nabysy,$IDCLIENT);
            if ($Clt->Id==0){
                $Err->TxErreur = "Compte Client introuvable. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if ($Clt->ETAT !== $Clt::ETAT_ACTIF){
                $Err->TxErreur = "Ce compte client est marqué ".$Clt->ETAT. ". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ((int)$Clt->IS_AUTH == 0){
                $Err->TxErreur = "Votre compte est en attente d'authentification. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $Carte=new xCarteBonAchatExclusive($nabysy,$IDCARTE);
            if ($Carte->Id==0){
                $Err->TxErreur = "Carte inexistante. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ($Carte->REFCARTE == ''){
                $Err->TxErreur = "Carte non initialisée contactez le service client svp. ETAT: NO PAIRING. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if ($Carte->ETAT == $Carte::CARTE_BLOQUEE || $Carte->ETAT == $Carte::CARTE_SUSPENDUE){
                $Err->TxErreur = "Cette carte n'est pas activée (".$Carte->Id."). ETAT: ".$Carte->ETAT.". impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            if (isset($_REQUEST['LIBELLE'])){
                $NomCarte=trim($_REQUEST['LIBELLE']);                
            }else{
                $Err->TxErreur = "Aucune information à modifier.";
                echo json_encode($Err) ;
                exit;
            }

            if ($NomCarte !==''){
                if ($Clt->UpdateInfoCarte($Carte,$NomCarte)){
                    $Notif=new xErreur;
                    $Notif->OK=1;
                    $Notif->Extra="Carte Num.".$Carte->Id." modifiée correctement.";
                    echo json_encode($Notif) ;
                    exit;
                }
            }

            $Notif=new xErreur;
            $Notif->OK=1;
            $Notif->Extra="";
            echo json_encode($Notif) ;
            exit;
            
            break;


        default:

        
     }


?>