<?php
    /**
     * END-POINT Gestion et Controle des UTILISATEURS
     * By Paul Isidore A. NIAMIE
     */
use NAbySy\xErreur;
use NAbySy\xNotification;
use NAbySy\xUser;

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

    if (!$nabysy->ValideUser()){
        exit;
    }

    switch ($action){
        case "USERAPI_GETUSER": //Retourne la liste des utilisateurs
            $IdU=null;
            $Critere="Id>0 ";
            $Reponse=new xErreur;
            $Reponse->OK=0;
            $Reponse->TxErreur="Impossible de valider l'opération.";
            
            if(isset($_REQUEST['IDUSER'])){
                $IdU=(int)$_REQUEST['IDUSER'] ;
                $Critere .=" AND Id=".$IdU ;
            }
            if(isset($_REQUEST['LOGIN'])){
                $Login=$_REQUEST['LOGIN'] ;
                $Critere .=" AND LOGIN like '%".$Login."%' " ;
            }
            if(isset($_REQUEST['TEL'])){
                $Critere .=" AND TEL like '%".$_REQUEST['TEL']."%' " ;
            }
            if(isset($_REQUEST['PRENOM'])){
                $Critere .=" AND PRENOM like '%".$_REQUEST['PRENOM']."%' " ;
            }
            if(isset($_REQUEST['NOM'])){
                $Critere .=" AND NOM like '%".$_REQUEST['NOM']."%' " ;
            }
            if(isset($_REQUEST['ETAT'])){
                $Critere .=" AND ETAT like '%".$_REQUEST['ETAT']."%' " ;
            }
            if(isset($_REQUEST['NIVEAUACCES'])){
                $Critere .=" AND NIVEAUACCES like '%".$_REQUEST['NIVEAUACCES']."%' " ;
            }
            $Utilisateur=new xUser($nabysy,$IdU,N::GLOBAL_AUTO_CREATE_DBTABLE);

            $Lst=$Utilisateur->ChargeListe($Critere);
            $Reponse=new xNotification();
            $Reponse->Autres="Liste des Utilisateurs";
            $Reponse->Contenue=$nabysy->SQLToJSON($Lst);
            echo json_encode($Reponse);            
            
            exit;
        case "USERAPI_CREATEUSER": //Créer un utilisateur
            if ($nabysy->User->NiveauAcces < 4){
                $Err->TxErreur = "Niveau d'accès insuffisant pour cette opération.";
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }

            $newUser=new xUser($nabysy);
            if (!isset($PARAM['LOGIN'])){
                $Err->TxErreur = "Login du nouvel utilisateur absent.impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            //On vérfie si le login est disponible
            $RepU=$newUser->ChargeListe("LOGIN like '".$PARAM['LOGIN']."' ");
            if ($RepU->num_rows){
                $Err->TxErreur = "Ce Login existe. Impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $newUser->Login=$PARAM['LOGIN'] ;

            if (!isset($PARAM['PASSWORD'])){
                $Err->TxErreur = "Mot de  passe du nouvel utilisateur absent.impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $newUser->Password=$PARAM['PASSWORD'] ;

            if (!isset($PARAM['NOM'])){
                $Err->TxErreur = "Nom du nouvel utilisateur absent.impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            if (trim($PARAM['NOM'])==''){
                $Err->TxErreur = "Le nom ne peut pas être vide. Impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }

            $newUser->NOM=$PARAM['NOM'] ;

            if (isset($PARAM['PRENOM'])){
                $newUser->Prenom=$PARAM['PRENOM'] ;
            }
            if (isset($PARAM['NIVEAUACCES'])){
                $newUser->NiveauAcces=(int)$PARAM['NIVEAUACCES'] ;
            }else{
                $newUser->NiveauAcces=2;
            }

            $newUser->DateCreation = Date("Y-m-d");
            $newUser->HeureCreation = Date ("H:i:s");
            $newUser->Profile = 'Utilisateur';
            $newUser->Etat ='Actif';
            $newUser->BLOQUE='NON';
            if ($newUser->NiveauAcces>3){
                $newUser->Profile="Administrateur" ;
            }
            if ($newUser->Enregistrer()){
                $Tache="CREATION UTILISATEUR";
                $Note=$nabysy->User->Login." a crée un nouvel utilisateur: Login=".$newUser->Login. " (IdU-".$newUser->Id.") ";
                $newUser->AddToJournal($Tache,$Note);
                $Notif=new xNotification();
                $Notif->OK=1;
                $Notif->Extra=$newUser->Id;
                $Notif->Contenue=$newUser->ToObject();
                $Notif->Source="Utilisateur crée correctement";
                echo json_encode($Notif) ;
                exit;
            }

            $Err->TxErreur = "Erreur non spécifiée.";
            echo json_encode($Err) ;
            exit;
            break;

        case "USERAPI_SAVEUSER": //Modifier un utilisateur
            if ($nabysy->User->NiveauAcces < 4){
                $Err->TxErreur = "Niveau d'accès insuffisant pour cette opération.";
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }
            $IdU=null;
            if (!isset($PARAM['IDUSER'])){
                $Err->TxErreur = "Id utilisateur absent impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IdU=(int)$PARAM['IDUSER'] ;
            if ($IdU<1){
                $Err->TxErreur = "Id-U non définit correctement.";
                echo json_encode($Err) ;
                exit;
            }

            $eUser=new xUser($nabysy,$IdU);
            if ($eUser->Id<=0){
                $Err->TxErreur = "Utilisateur introuvable.";
                echo json_encode($Err) ;
                exit;
            }

            $CanModif=false;
            if (isset($PARAM['NOM'])){
                if ($PARAM['NOM'] !== $eUser->Nom ){
                    $eUser->Nom = $PARAM['NOM'];
                    $CanModif=true;
                }                
            }
            if (isset($PARAM['PRENOM'])){
                if ($PARAM['PRENOM'] !== $eUser->Prenom ){
                    $eUser->Prenom = $PARAM['PRENOM'];
                    $CanModif=true;
                }                
            }
            if (isset($PARAM['NIVEAUACCES'])){
                if ((int)$PARAM['NIVEAUACCES'] !== (int)$eUser->NiveauAcces ){
                    $eUser->NiveauAcces = (int)$PARAM['NIVEAUACCES'];
                    $CanModif=true;
                    if ($eUser->NiveauAcces>3){
                        $eUser->Profile="Administrateur" ;
                    }else{
                        $eUser->Profile="Utilisateur" ;
                    }        
                }                
            }

            if (isset($PARAM['LOGIN'])){
                if ($PARAM['LOGIN'] !== $eUser->Login ){
                    //On vérifie d'abord le login
                    $UEx=$eUser->ChargeListe(" Login like '".$PARAM['LOGIN']."' ");
                    if ($UEx->num_rows>0){
                        $Err->TxErreur = "Le Login existe déjà. Impossible de continuer l'opération.";
                        echo json_encode($Err) ;
                        exit;
                    }
                    $eUser->Login = $PARAM['LOGIN'];
                    $CanModif=true;
                }                
            }

            if (isset($PARAM['PASSWORD'])){
                if ($PARAM['PASSWORD'] !== $eUser->Password ){
                    $eUser->Password = $PARAM['PASSWORD'];
                    $CanModif=true;
                }             
            }

            if ($CanModif){
                if ($eUser->Enregistrer()){
                    $Tache="MODIFICATION UTILISATEUR";
                    $Note=$nabysy->User->Login." a modifié les données de l'utilisateur ".$eUser->Login. " (IdU-".$eUser->Id.") ";
                    $eUser->AddToJournal($Tache,$Note);
                    $Notif=new xErreur;
                    $Notif->OK=1;
                    $Notif->Extra=$eUser->Id;
                    $Notif->Source="Utilisateur modifié correctement.";
                    echo json_encode($Notif) ;
                    exit;
                }else{
                    $Err->TxErreur = "Erreur non spécifiée.";
                    echo json_encode($Err) ;
                    exit;
                }
            }else{
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra=$eUser->Id;
                $Notif->Source="Aucune modification trouvée.";
                echo json_encode($Notif) ;
                exit;
            }           
            break;

        case "USERAPI_DELETEUSER": //Supprime un utilisateur
            if ($nabysy->User->NiveauAcces < 4){
                $Err->TxErreur = "Niveau d'accès insuffisant pour cette opération.";
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }
            $IdU=null;
            if (!isset($PARAM['IDUSER'])){
                $Err->TxErreur = "Id utilisateur absent. impossible de continuer.";
                echo json_encode($Err) ;
                exit;
            }
            $IdU=(int)$PARAM['IDUSER'] ;
            $sUser=new  xUser($nabysy,$IdU);
            if ($sUser->Id>0){
                $Login=$sUser->Login ;
                $Tache="SUPPRESSION UTILISATEUR";
                $Note=$nabysy->User->Login." a supprimé l'utilisateur ".$Login. " (IdU-".$IdU.") ";
                if ($sUser->Supprimer()){
                    $sUser->AddToJournal($Tache,$Note);
                    $Notif=new xErreur;
                    $Notif->OK=1;
                    $Notif->Extra=$IdU;
                    $Notif->Source=$Login. " (IdU-".$IdU.") a été supprimé correctement";
                    echo json_encode($Notif) ;
                    exit;
                }else{
                    $Err->TxErreur = "Erreur inconnue. impossible de continuer.";
                    echo json_encode($Err) ;
                    exit;
                }
            }else{
                $Err->TxErreur = "Id utilisateur introuvable ou déjà supprimé.";
                echo json_encode($Err) ;
                exit;
            }

            break;
        default:

    }
    

?>