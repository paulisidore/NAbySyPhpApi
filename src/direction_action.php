<?php

use NAbySy\RH\ZoneAffectation;
use NAbySy\RH\ZoneAffectation\xDirection;

	include_once 'nabysy_start.php';
	
	$PARAM=$_REQUEST;
	

	$action=null ;

	if (isset($PARAM['Action'])){
		$action=$PARAM['Action'] ;
	}
	if (isset($PARAM['action'])){
		$action=$PARAM['action'] ;
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

	$Employee=new \NAbySy\RH\Personnel\xEmploye($nabysy) ;

	switch ($action){	
		case 'GET_DIRECTION' :
            if (isset($nabysy->UserToken)){
                if ($nabysy->User){
                    //var_dump($nabysy->User);
                }
            }
            $IdDirection=null;
            $ListeReponse=[];
            $Direction=new xDirection($nabysy);
            if(isset($_REQUEST['IdDirection'] )){
                if ((int)$_REQUEST['IdDirection']>0){
                    $IdDirection= (int)$_REQUEST['IdDirection'] ;
                }                
            }
            if (isset($IdDirection)){
                $Lst=$Direction->ChargeListe("Id=".$IdDirection) ;
            }else if(isset($_REQUEST['IdDirectionParent'])){
                if ((int)$_REQUEST['IdDirectionParent']>0){
                    $Lst=$Direction->ChargeListe("IdDirectionParent=".(int)$_REQUEST['IdDirectionParent']) ;
                }else if ((int)$_REQUEST['IdDirectionParent']==0){
                    $Lst=$Direction->ChargeListe("IdDirectionParent=".(int)$_REQUEST['IdDirectionParent']) ;
                }          
            }
            else{
                $Lst=$Direction->ChargeListe() ;
            }

            if ($Lst->num_rows>0){
                while ($row = $Lst->fetch_assoc()){
                    $ListeReponse[]=$row ;
                }
            }

            $reponse=json_encode($ListeReponse);
			echo $reponse;
			break;
        case 'SAVE_DIRECTION' :
            $IdDirection=null ;
            $IdSiege=null;
            $Nom=null;
            $Adresse=null ;
            $Tel=null;

            if(isset($_REQUEST['IdDirection'] )){
                $IdDirection= $_REQUEST['IdDirection'] ;
            }
            $Direction=new xDirection($nabysy,$IdDirection);
            if(isset($_REQUEST['Nom'] )){
                $Nom= $_REQUEST['Nom'] ;
                $Direction->Nom=$Nom ;
            }else{
                $Err->TxErreur="Impossible de continuer sans un nom valide";
                echo json_encode($Err);
                exit ;
            }

            if(isset($_REQUEST['IdSiege'] )){
                $IdSiege= (int)$_REQUEST['IdSiege'] ;
                $Direction->IdSiege=$IdSiege ;
            }

            if(isset($_REQUEST['Adresse'] )){
                $Adresse= $_REQUEST['Adresse'] ;
                $Direction->Adresse=$Adresse ;
            }
            if(isset($_REQUEST['Tel'] )){
                $Tel= $_REQUEST['Tel'] ;
                $Direction->Tel=$Tel ;
            }

            if(isset($_REQUEST['IdDirectionParent'] )){
                if ((int)$_REQUEST['IdDirectionParent']>0){
                    $Direction->IdDirectionParent=(int)$_REQUEST['IdDirectionParent'] ;
                }
            }

            if(isset($_REQUEST['IdResponsable'] )){
                if ((int)$_REQUEST['IdResponsable']>0){
                    $Direction->IdResponsable=(int)$_REQUEST['IdResponsable'] ;
                }
            }
            

            $Direction->Enregistrer();

            $Reponse=new xErreur ;
            $Reponse->OK=1 ;
            $Reponse->Extra=$Direction->Id ;
            $Reponse->Source=$action;

            $reponse=json_encode($Reponse);
            echo $reponse;
            break;

        case 'SUPPRIME_DIRECTION' :
            $IdDirection=null ;

            if(isset($_REQUEST['IdDirection'] )){
                $IdDirection= $_REQUEST['IdDirection'] ;
            }
            $Direction=new xDirection($nabysy,$IdDirection);
            if(isset($nabysy->User )){
                if ($nabysy->User->Acces>=4){
                    if ($Direction->Supprimer()){
                        $Notif=new xErreur;
                        $Notif->OK=1;
                        $Notif->Extra="La Direction ".$Direction->Nom." a été supprimée correctement.";
                        if ($Direction->IdDirectionParent>0){
                            $Notif->Extra="La Sous-Direction ".$Direction->Nom." a été supprimée correctement.";
                        }
                        $Notif->Source=$action ;
                        echo json_encode($Notif);
                        exit;
                    }
                }
                $Err->OK=0;
                $Err->TxErreur="Vous ne disposez pas d\'autorisation suffisant pour effectuer cette opération." ;
            }else{
                $Err->TxErreur="Impossible de continuer sans compte utilisateur valide";
               
            }
            echo json_encode($Err);
            exit ;
                        
            break;
        
		default:
			Retourne();	
			break;
	}
	 
	
	
	function Retourne($lien=null){
		
		 exit ;
	}