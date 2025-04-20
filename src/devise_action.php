<?php 
    session_start();	  
	include_once 'nabysy_start.php';
	
	if (!isset($_POST['action'])){
		if (!isset($_GET['action'])){
			 //Il n'y a pas d'action, on retourne a la page précedente
            echo "Aucune action à valider " ;
            exit;
            Retourne();
		}
	}
	
	$action="" ;
	if (isset($_POST['action'])){
        $action=$_POST['action'] ;
        $PARAM=$_POST ;
	}
	if (isset($_GET['action'])){
        $action=$_GET['action'] ;
        $PARAM=$_GET ;
	}
	switch ($action){
		case "SAVE":
			$Id=0 ;
			if (isset($PARAM['ID'])){
                $Id=$PARAM['ID'] ;
			}
            $Devise=new xDevise($nabysy->MaBoutique,$Id) ;
            $Devise->Id=$Id ;

			if (isset($PARAM['NOM'])){
				$Devise->Nom=$PARAM['NOM'] ;
			}
			if (isset($PARAM['TAUX'])){
				$Devise->TauxEchange=$PARAM['TAUX'] ;
			}
			
			$NewId=$Devise->Save() ;

			$retour="[]" ;
			$Reponse=$Devise->Charge($NewId) ;
			if ($Reponse){
				$Liste=$Reponse ; //$nabysy->EncodeReponseSQL($Reponse) ;
				$retour=json_encode($Liste) ;
            }
            //Recharger la liste des devises
            header("Location:../vues/liste_devise.php");
			//Retourne($retour) ;
			break ;
			
		case "LISTE":
			$NomRech=null;
			$IdR=null ;
			if (isset($_POST['ID'])){
				$IdR=$_POST['ID'] ;
			}
			if (isset($_POST['NOM'])){
				$NomRech=$_POST['NOM'] ;
            }
            $Devise=new xDevise($nabysy->MaBoutique,$IdR) ;
            if (isset($NomRech)){
                $Reponse=$Devise->GetListe($NomRech) ;
            }
			
			if ($Reponse){
				$Ligne=$Reponse ;
				$retour=json_encode($Ligne) ;
			}
			Retourne($retour) ;
					
			break;
		case "SUPPRIMER":
            $Id=0 ;
			if (isset($PARAM['ID'])){
                $Id=$PARAM['ID'] ;
            }
            if ($Id>0){
                $Devise=new xDevise($nabysy->MaBoutique,$Id) ;
                $Devise->Supprimer() ;
            }
            //Recharger la liste des devises
            header("Location:../vues/liste_devise.php");
			
		case "MODIFIER":
            $Id=0 ;
			if (isset($PARAM['ID'])){
                $Id=$PARAM['ID'] ;
            }
            if ($Id>0){
                $Devise=new xDevise($nabysy->MaBoutique,$Id) ;
                
            }
            //Recharger la liste des devises
            header("Location:../vues/liste_devise.php");

			break;
			
		default:
			Retourne();	
			break;
	}
	 
	
	function Retourne($JSONdata=null){
		if (isset($JSONdata)){
			echo $JSONdata ;
			//echo "<script>console.log(".$JSONdata.")</script> ";
			exit ;
		}
		 echo 'OK' ;
		 exit ;
	}
	
	

?>