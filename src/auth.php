<?php

require __DIR__.'/vendor/autoload.php';

use NAbySy\xAuth;
use NAbySy\xErreur;
use NAbySy\xNotification;
use NAbySy\xUser;

    $Login=null ;
    $Password='' ;
    $Token=null;
    $ConnectByToken=false ;

    $nabysy=N::getInstance();
    $BoutId=null;
    if(isset($_REQUEST['IDBOUTIQUE']) && (int)$_REQUEST['IDBOUTIQUE']>0 ){
        $BoutId=(int)$_REQUEST['IDBOUTIQUE'];
    }
    if(isset($_REQUEST['IdBoutique']) && (int)$_REQUEST['IdBoutique']>0 ){
        $BoutId=(int)$_REQUEST['IdBoutique'];
    }
    if(isset($_REQUEST['idboutique']) && (int)$_REQUEST['idboutique']>0 ){
        $BoutId=(int)$_REQUEST['idboutique'];
    }

    if(isset($BoutId) && $BoutId !== $nabysy->MaBoutique->Id){
        $BoutiqueCible=N::GetBoutique($BoutId);
        if($BoutiqueCible && $BoutiqueCible->Id>0){
            N::getInstance()->SelectBoutique($BoutiqueCible->Id);
        }
    }

    //echo __FILE__." Je lance l'authentification dans auth.php ... </br>" ;
    if(isset($nabysy->User)){
        //var_dump("Utilisateur déjà connecté: ".$nabysy->User->Login);
    }

    if(isset(N::$AUTH_BOUTIQUE_ID)){
        if(N::$AUTH_BOUTIQUE_ID >0 ){
            $BoutiqueAuth = N::GetBoutiqueByID(N::$AUTH_BOUTIQUE_ID);
            if($BoutiqueAuth){
                $nabysy->MaBoutique = $BoutiqueAuth ;
                N::SelectDefautBoutique(N::$AUTH_BOUTIQUE_ID);
                $nabysy->SelectBoutique(N::$AUTH_BOUTIQUE_ID);
            }
        }
    }

    if(!isset($nabysy->MaBoutique)){
        $nabysy->ChargeInfos();
    }
    
    $Auth=new xAuth($nabysy, N::$AUTH_DUREE_SESSION) ;
    $UserToken=null ;
    $Err=new xErreur;

    
    if (isset($_REQUEST['Token'])){
        $Token=$_REQUEST['Token'] ;
        //var_dump( __FILE__." Je lis le Token dans auth.php ... </br>" );
        //echo "Token a recherche = ".$Token ; //exit;
        $UserToken=$Auth->DecodeToken($Token) ;
        //var_dump($UserToken)."</br>" ;
        //var_dump(__FILE__." L".__LINE__." Je suis maintenant ici avec UserId = ".$UserToken->user_id);
        if (!isset($UserToken)){            
            $Err->TxErreur="La session a expirée." ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }
        if (get_class($UserToken)=='xErreur' || get_class($UserToken)=='NAbySy\xErreur'){
            $Err->TxErreur="Votre session à expirée." ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }
        
        $User=new xUser($nabysy,$UserToken->user_id) ;
        //var_dump(__FILE__." L".__LINE__." Je suis maintenant ici avec User = ".$User->Login) ;

        //print_r($UserToken) ;
        //exit;
        //echo "Reponse=: ".$UserToken->user_login ; // Marche bien et retourne la bonne infos
        if ($User->BLOQUE=='OUI' || ($User->ETAT !=='Actif' && $User->ETAT !=='A') ){
            $Err->TxErreur="Compte utilisateur ".$User->Login." bloqué. vérifiez la validité de votre contrat chez ".$nabysy->MODULE->Nom ;
            $Err->OK=0;
            $Err->Source=$User->DataBase ;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }
        
		$IdUser=$User->Id;
        $Notif=new xErreur;
        $Notif->TxErreur="Connexion Reussit" ;
        $Notif->Extra=json_encode($User) ;        
        $Notif->OK=1 ;
        $nabysy->User=$User ;
        $ConnectByToken=true ;
        //echo "Utilisateur connecté par Token  ".$nabysy->User->Login."</br>" ;
        
        //Si le token est fournit et valable on ne retourne pas de confirmation sauf si demandé
         /* if(isset($_REQUEST['AUTH']) && ((int)$_REQUEST['AUTH']>0) ){
            http_response_code(200);
            echo json_encode($Notif) ;
            exit;
        }
        exit; */
    }

    //s'il ne s'agit pas d'une requette vers l'API des Utilisateurs on cherche les parametres Login et Password
    //echo __FILE__." SubStr: ".strtoupper(substr($_REQUEST['Action'],0,strlen("USERAPI_") )) ;exit;

    if(!isset($_REQUEST['Action']) || (isset($_REQUEST['Action']) && strtoupper(substr($_REQUEST['Action'],0,strlen("USERAPI_") )) !='USERAPI_') ){
        //var_dump("Je ne dois pas aller dans userapi") ;
        if (isset($_REQUEST['Login'])){
            $Login=$_REQUEST['Login'] ;
        }elseif (isset($_REQUEST['LOGIN'])){
            $Login=$_REQUEST['LOGIN'] ;
        }elseif (isset($_REQUEST['User'])){
            $Login=$_REQUEST['User'] ;
        }
        if (isset($_REQUEST['Password'])){
            $Password=$_REQUEST['Password'] ;
        } if (isset($_REQUEST['PASSWORD'])){
            $Password=$_REQUEST['PASSWORD'] ;
        }
    }

    //var_dump( __FILE__." On verifie que User est bien ici dans auth.php: ".$nabysy->User->Login." </br>") ;

    //$Auth=new xAuth($nabysy,3600) ; //Version Prod Token valable 1 heure
    if(!isset($Auth)){
        $Auth=new xAuth($nabysy,N::$Main::$AUTH_DUREE_SESSION) ; //Version Dev Token Valable 24 heures
    }
    //$nabysy->SelectDB($nabysy->DataBase);
    if(isset($Login)){
        $Login=trim($Login) ;
        $User=new xUser($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$Login) ;
    }elseif($ConnectByToken){
        $User=$nabysy->User ;
    }elseif(!isset($User)){
        //echo "Initi User par défaut !!!";
        //echo __FILE__." je suis ici NAbySyYser: ".$nabysy->User->Login."</br>" ;exit;
        $User=new xUser($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE) ;
    }
    $Err=new xErreur;
    $Err->Source='auth-'.$User->Id.':'.$Login;
    if ($User->Id>0 && !$ConnectByToken){
        if ($User->BLOQUE=='OUI'  ){
            $Err->TxErreur="Compte bloqué. vérifiez la validité de votre contrat chez ".$nabysy->MODULE->Nom ;
            $Err->OK=0;
            if($User->Main::$SendAuthReponse){
                echo json_encode($Err) ;
                http_response_code(419);
            }
            exit ;
        }
        if(isset($_REQUEST['IsModuleConnexion'])){
            if ((int)$_REQUEST['IsModuleConnexion']>0){
                if ($Password==""){
                    $Password = $User->Password ;
                }
                $nabysy->NomPosteClient="";
                $nabysy->IdPosteClient=0;
            }
        }
        $Err->Source='auth.php: CheckPassword';
        if ($User->CheckPassword($Password)){
            $Err->Source='auth.php: GetToken';
            $Token=$User->GetToken();
        }
    }

    $UserToken = $Token;
    if(isset($_REQUEST['fordocumentation']) && (int)$_REQUEST['fordocumentation']>0){
        $Liste = N::getInstance()::$UrlRouter->getRegistredRoute();
        $Rep=new xNotification();
        $Rep->OK=1;
        $Rep->Contenue = $Liste ;
        echo N::getInstance()::$UrlRouter::generateRoutesDocumentationPage($Rep->ToJSON());
        exit;
    }

    if ($Token){
        $Auth->EnteteAPI() ;
        $Notif=new xErreur;
        $Notif->OK=1;
        $Notif->Extra=$Token ;
        $vUserStr =  $User->ToJSON(false, xAuth::$ColonneToIgnore);
        $vUser = json_decode($vUserStr);
        $Notif->Autres = $vUser ; //$User->ToObject();
        
        $Notif->Source='auth-'.$User->Id.':'.$Login;
        $nabysy->User=$User ;
         if($User->Main::$SendAuthReponse && !$ConnectByToken){
            http_response_code(200);
            echo json_encode($Notif) ;
            exit ;
        }else{
            if(isset($_REQUEST['AUTH']) && ((int)$_REQUEST['AUTH']>0) ){
                http_response_code(200);
                echo json_encode($Notif) ;
                exit ;
            }
        }
        
    }else{
        if(!N::$NO_AUTH){
            //Authentification a échouée
            //var_dump(__FILE__. " "." L".__LINE__." Token = ". $Token);
            $Auth->EnteteAPI() ;
            $Err->TxErreur="L".__LINE__." Vous etes pas authorisé." ;
            $Err->OK=0;
            //$nabysy->User=null ;
            if($User->Main::$SendAuthReponse){
                http_response_code(401);
                //echo json_encode($Err) ;
            }elseif(isset($_REQUEST['AUTH']) && ((int)$_REQUEST['AUTH']>0) ){
                http_response_code(401);
            // echo json_encode($Err) ;            
            }
            $Err->SendAsJSON();
            exit;
        }
        //Sinon on continue sans authentification
    }


?>