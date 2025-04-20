<?php
    /**
     * END-POINT NAbySy GS API
     * By Paul Isidore A. NIAMIE
     */

use NAbySy\Lib\ModuleExterne\IModuleExterne;
use NAbySy\RH\Personnel\xEmploye;
use NAbySy\RS\Projet\xListeParticipant;
use NAbySy\RS\Projet\xProjet;

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

    if(!isset($action)){
        $postData=$nabysy::ConvertBodyPostToArray();
        if($postData){
            $_REQUEST=$postData;
            $_POST=$postData ;
            if (is_array($_REQUEST)){
                $PARAM=$_REQUEST;
                if (isset($PARAM[$ChampAction])){
                    $action=$PARAM[$ChampAction] ;
                }
                if (isset($PARAM[strtolower($ChampAction)])){
                    $action=$PARAM[strtolower($ChampAction)] ;
                }
                $nabysy->ReadConfig();
            }
        }
        //echo json_encode($_POST);
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

    $PourClientBonAchat=false;
    if (isset($PARAM['CLIENT_GENERAL'])){
        if ((int)$PARAM['CLIENT_GENERAL']>0){
            if (!isset($PARAM['LOGIN']) && !isset($PARAM['Login']) ){
                $fakeUser=new xUser($nabysy,0);
                $fakeUser->Id=-1 ;
                $fakeUser->Login="BON_ACHAT_CUSTUMER_MANAGER";
                $fakeUser->Password='NAbySyGS#PAULVB@2023';
                $fakeUser->NIVEAUACCES=1;
                $fakeUser->Nom ="GENERAL CUSTUMER";
                $fakeUser->Prenom="MANAGER";

                $Auth=new xAuth($nabysy);
                $Token=$Auth->GetToken($fakeUser);
                $nabysy->UserToken=$Token ;
                $nabysy->User=$fakeUser ;
                $PourClientBonAchat =true;
            }
        }
    }

    if (!$PourClientBonAchat){
        if (!$nabysy->ValideUser()){
            exit;
        }
    }

    //Si nous somme ici alors on pursuivre sur les autres action de l'API
    $rep="./gs/" ;
    $rep=str_replace('\\', DIRECTORY_SEPARATOR, $rep) ;
    //$rep="./main/rh/zoneaffectation" ;
    $dossier_action=[] ;
    if($nabysy::IsDirectory($rep)){
        if($iteration = opendir($rep)){
            while(($dos = readdir($iteration)) !== false)
            {
               if($dos != "." && $dos != ".." && $dos != "Thumbs.db")  
                {
                    $pathfile=$rep.$dos ;
                    if (is_dir($pathfile)){
                        $fichier_action=$pathfile."/".$dos."_action.php" ;
                        //var_dump($fichier_action);
                        if (file_exists($fichier_action)){
                            $dossier_action[]=$fichier_action ;
                        }                                
                    }
                }
            }
            closedir($iteration);  
        }
    }

    //Ajout des APIs des Modules
    $rep="./moduleExterne/" ;
    $rep=str_replace('\\', DIRECTORY_SEPARATOR, $rep) ;
    //$rep="./main/rh/zoneaffectation" ;
    if($nabysy::IsDirectory($rep)){
        if($iteration = opendir($rep)){
            while(($dos = readdir($iteration)) !== false)
            {
               if($dos != "." && $dos != ".." && $dos != "Thumbs.db")  
                {
                    $pathfile=$rep.$dos ;
                    if (is_dir($pathfile)){
                        if($iteration2 = opendir($pathfile)){
                            while(($dos2 = readdir($iteration2)) !== false){
                                if($dos2 != "." && $dos2 != ".." && $dos2 != "Thumbs.db"){
                                    if (!is_dir($dos2)){
                                        //On a un fichier vérifions s'il a un _action.php
                                        $pos = strpos($dos2,"_action.php") ;
                                        //echo "Pos Pos = ".$pos."</br>" ;
                                        if ($pos){
                                            $fichier_action=$pathfile."/".$dos2 ;
                                            //var_dump($fichier_action);
                                            if (file_exists($fichier_action)){
                                                //var_dump($fichier_action);
                                                $dossier_action[]=$fichier_action ;
                                            }
                                        }
                                    }   
                                }
                            }
                        }
                                                 
                    }
                }
            }
            closedir($iteration);  
        }
    }

    //var_dump($dossier_action);
    $Err->TxErreur='Aucun module ne peut traiter votre demande.';
    $Err->Source=__FILE__;
    if(isset($_REQUEST['TRACKERID'])){
        $Err->Source = $_REQUEST['TRACKERID'];
     }
     
    //var_dump($dossier_action);
    foreach ($dossier_action as $fichier_action){
        //echo __FILE__."LINE:".__LINE__." => Chargement du Fichier ".$fichier_action." ...</br>";
        include_once $fichier_action;
        //echo __FILE__."LINE:".__LINE__." =>Action non trouvé Chargement du Fichier suivant ...</br>";
                
    }
    
    //Si on arrive ici c' est qu' aucun module n' a pus g'erer la requete
    if (isset($action)){
        $Err->TxErreur .= " ".$action." n'est pas définit." ;
    }
    echo json_encode($Err);

?>