<?php 
	include_once '../nabysy/nabysy_start.php';
    
    $PARAM=null;
    if (isset($_REQUEST['Action'])){
        $PARAM=$_REQUEST ;
    }
    if (isset($_REQUEST['action'])){
        $PARAM=$_REQUEST ;
    }

    //var_dump($PARAM) ;

	$action=null ;
	if (isset($PARAM['Action'])){
		$action=$PARAM['Action'] ;
	}
    if (isset($PARAM['action'])){
		$action=$PARAM['action'] ;
	}

    if (!isset($action)){
        //Il n'y a pas d'action, on retourne a la page précedente
        echo "Jai pas validé " ;
        exit;
    }

	switch ($action){
		case "PUT":
			//Modifie une autorisation sur une page
			$TxM=false ;
            $CallBack=null ;
            
            $U=new xUser($nabysy) ;
            $Accorder=null ;
            $Lien=null ;
            $Reponse="[ERREUR]" ;
			
			if (isset($PARAM['IdUser'])){
                $IdUser=$PARAM['IdUser'] ;
                $U=new xUser($nabysy,$IdUser) ;
			}			
			if (isset($PARAM['Titre'])){
				$Titre=$PARAM['Titre'] ;
			}
			if (isset($PARAM['Lien'])){
                $Lien=$PARAM['Lien'] ;
                if ($Lien ==''){
                    $Lien='#' ;
                    $PARAM['Lien']=$Lien ;
                }
            }
            if (!isset($PARAM['Accorder'])){
                $PARAM['Accorder']='0' ;
            }
			if (isset($PARAM['Accorder'])){
				$Accorder=$PARAM['Accorder'] ;
            }

           
            if ($U->Id>0){
                //var_dump($U);
                if (isset($Titre)){
                    //echo 'Accorder = '.$Accorder ;
                    if (isset($Accorder)){
                        if ($Accorder==1){
                            //echo "J'accorde: ".$Accorder ;
                            $Reponse=$U->RemovePageInterdite(null,$Titre,$Lien) ;
                        }
                        if ($Accorder ==0){
                            //echo "J'accorde pas: ".$Accorder ;
                            $Reponse=$U->AddPageInterdite($Titre,$Lien) ;
                        }
                    }
                }
            }
			echo $Reponse ;
			break ;
            
        case "MODULE_AUTH":
            $Module=null;
            $Auth=null;
            $IdUser=null;
            $U=null;
            $IdBoutique=null;
            $Bout=$nabysy->MaBoutique ;

            $Accorder=null ;
            $Lien=null ;
            $Rep=new xErreur ;
            $Rep->TxErreur="Impossible de valider l'opération";
            $Rep->OK=0;
            $Rep->Source="autorisation_action.php" ;

			if (isset($PARAM['IdUser'])){
                $IdUser=$PARAM['IdUser'] ;
                $U=new xUser($nabysy,$IdUser) ;
			}			
			if (isset($PARAM['Module'])){
				$Module=$PARAM['Module'] ;
            }
            if (isset($PARAM['Accorder'])){
                $Auth=$PARAM['Accorder'] ;
			}
            if (!isset($Module)){
                $Rep->TxErreur="Nom du Module absent. impossible de continuer cette opération";
                $Retour=json_encode($Rep);
                echo $Retour ;
                exit;
            }
            if (!isset($U)){
                $Rep->TxErreur="Utilisateur non trouvé. impossible de continuer cette opération";
                $Retour=json_encode($Rep);
                echo $Retour ;
                exit;
            }
            if (!isset($Auth)){
                $Auth=false;
            }
            $U->CanUseModule($Module,$Auth) ;
            $Rep=new xErreur;
            $Rep->OK=1;
            $Rep->Source="autorisation_action.php" ;
            $Rep->Extra="Opération validée correctement" ;
            $Retour=json_encode($Rep);
            echo $Retour ;
            exit;
            

            break ;
        
            default:
			Retourne();	
			break;
	}
	 
	
	function Retourne($lien=null){		
	    exit ;
	}

	

?>