<?php

use NAbySy\xAuth;
use NAbySy\xErreur;
use NAbySy\xUser;

    //include_once 'nabysy_start.php';

    $Login='' ;
    $Password='' ;
    $Token=null;
    $nabysy=N::getInstance();
    $Boutique=N::getInstance()->MaBoutique ;
    $Auth=new xAuth($nabysy) ;
    $UserToken=null ;
    $Err=new xErreur;

    if (isset($_REQUEST['Token'])){
        $Token=$_REQUEST['Token'] ;
        //echo "Token a recherche = ".$Token ;
        $UserToken=$Auth->DecodeToken($Token) ;
        //var_dump($UserToken)."</br>" ;
        //var_dump($User) ;
        if (!isset($UserToken)){            
            $Err->TxErreur="La session a expirée." ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }
        if (get_class($UserToken)=='xErreur'){
            $Err->TxErreur="Votre session à expirée." ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }

        $User=new xUser($nabysy,$UserToken->user_id) ;
        //print_r($UserToken) ;
        //echo "Reponse=: ".$UserToken->user_login ; // Marche bien et retourne la bonne infos
        if ($User->BLOQUE=='OUI' || $User->Etat !=='Actif' ){
            $Err->TxErreur="Compte bloqué. vérifiez la validité de votre contrat chez ".$nabysy->MODULE->Nom ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);            
            exit ;
        }
        
		$IdUser=$User->Id;
        $Notif=new xErreur;
        $Notif->TxErreur="Connexion Reussit" ;
        $Notif->Extra=json_encode($User) ;        
        $Notif->OK=1 ;
        echo json_encode($Notif) ;
        exit ;
    }

    if (isset($_REQUEST['Login'])){
        $Login=$_REQUEST['Login'] ;
    }
    if (isset($_REQUEST['User'])){
        $Login=$_REQUEST['User'] ;
    }
    if (isset($_REQUEST['Password'])){
        $Password=$_REQUEST['Password'] ;
    }

    //print_r('IdBoutique='.$Boutique->Id.'  <= ') ;
    //$Auth=new xAuth($nabysy,3600) ; //Version Prod Token valable 1 heure
    $Auth=new xAuth($nabysy,86400) ; //Version Dev Token Valable 24 heures
    $nabysy->SelectDB($nabysy->MaBoutique->DBname);
    $User=new xUser($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$Login) ;
    $Err=new xErreur;
    $Err->Source='auth.php-'.$User->Id.':'.$Login;
    var_dump($User);

    if ($User->Id>0){
        if ($User->BLOQUE=='OUI'  ){
            $Err->TxErreur="Compte bloqué. vérifiez la validité de votre contrat chez ".$nabysy->MODULE->Nom ;
            $Err->OK=0;
            echo json_encode($Err) ;
            http_response_code(419);
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
            $Err->Extra='Token trouvé: '.$Token ;
            var_dump($Token);
        }
    }
    

    if ($Token){
        $Auth->EnteteAPI() ;
        $Notif=new xErreur;
        $Notif->OK=1;
        $Notif->Extra=$Token ;
        $Notif->Autres = $User->ToObject();
        $Notif->Source='auth.php-'.$User->Id.':'.$Login;
        echo json_encode($Notif) ;
        http_response_code(200);
        //var_dump($Auth->DecodeToken($Token)) ;
    }else{
        
        $Err->TxErreur="Vous etes pas authorisé." ;
        $Err->OK=0;
        
        http_response_code(401);
        echo json_encode($Err) ;

    }


?>