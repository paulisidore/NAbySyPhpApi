<?php

use NAbySy\RH\Personnel\xEmploye;
use NAbySy\RH\Personnel\xJournalPerformance;

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
    $Err->Source= __FUNCTION__ ;
    if (!isset($action)){		
        //Il n'y a pas d'action, on retourne a la page précedente
        $Err->OK=0;
        $Err->TxErreur='Action non définit !' ;
        $Err->Source= __FUNCTION__ ;
        $reponse=json_encode($Err) ;
        echo $reponse ;
        exit;	
	}

    if(!isset($nabysy->User )){
        $Err->TxErreur='Utilisateur non authentifié.' ;
        $Err->Source= __FUNCTION__ ;
        $reponse=json_encode($Err) ;
        echo $reponse ;
        exit;	
    }

    $IdEmploye=null;
    $IdPerformance=null;

    if (isset($PARAM['IDEMPLOYE'])){
		$IdEmploye=$PARAM['IDEMPLOYE'] ;
	}
    if (isset($PARAM['IDPERFORMANCE'])){
		$IdPerformance=$PARAM['IDPERFORMANCE'] ;
	}

	$Employee=new xEmploye($nabysy,$IdEmploye) ;

    $Performance=new xJournalPerformance($nabysy,$IdPerformance,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE) ;
    if ($Employee->Id==0){
        if ($Performance->Id>0){
            $IdEmploye=$Performance->IdEmploye ;
            $Employee=new xEmploye($nabysy,$IdEmploye) ;
        }
    }

	switch ($action){
        case 'ADD_PERFORMANCE'	:

            if ($Employee->Id==0){
                $Err->TxErreur='Employé non définit. vueillez indiquer un Id Employé valide svp.' ;
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }

            $Motif='';
            $Commentaire='';
            $NbPoint=1;
            $DateT=new DateTime();
            $Dte=$DateT->format('Y-m-d');

            if ($Performance->Id>0){
                $Motif=$Performance->Motif;
                $Commentaire=$Performance->Commentaires;
                $NbPoint=$Performance->NbPointAjoute;
                $DateT=new DateTime($Performance->DateEnreg);
                if ($DateT !== false){
                    $Dte=$DateT->format('Y-m-d');
                }
            }

            if (isset($PARAM['MOTIF'])){
                $Motif=$PARAM['MOTIF'] ;
            }
            if (isset($PARAM['COMMENTAIRE'])){
                $Commentaire=$PARAM['COMMENTAIRE'] ;
            }
            if (isset($PARAM['NBPOINT'])){
                $NbPoint=$PARAM['NBPOINT'] ;
            }
            if (isset($PARAM['DATE'])){
                $Dte=$PARAM['DATE'] ;
                $DateT=new DateTime($PARAM['DATE']);
                if ($DateT !== false){
                    $Dte=$DateT->format('Y-m-d');
                }                
            }
            if (isset($PARAM['IDPERFORMANCE'])){
                $IdPerformance=$PARAM['IDPERFORMANCE'] ;
            }

            $Lst['IDEMPLOYE']=$Employee->Id;
            $Lst['DATE_PERFORMANCE']=$Dte ;
            $Lst['PREC_PERFORMANCE']=$Employee->NbPerformance ;

            if ($Performance->Id<=0){
                $Ret=$Performance->Ajouter($Employee,$Dte,$NbPoint,$Motif,$Commentaire);
                if ($Ret==true){
                    $Employee=new xEmploye($nabysy,$Employee->Id) ;
                    $Notif=new xErreur ;
                    $Notif->OK=1;
                    $Lst['NOUV_PERFORMANCE']=$Employee->NbPerformance ;                
                    $Notif->Extra=$Lst ;
                    $Retour=json_encode($Notif);
                    echo $Retour ;
                    exit;
                }
            }else{
                //On retire le nombre de point précédent
                $NbPointPrec=$Performance->NbPointAjoute;
                $Ret=$Employee->DiminuerPerformance($NbPointPrec);
                if ($Ret){
                    $Performance->IdEmploye=$Employee->Id;
                    $Performance->DateEnreg=$Dte ;
                    $Performance->Motif=$Motif ;
                    $Performance->Commentaires=$Commentaire ;
                    $Performance->NbPointAjoute=$NbPoint ;
                    if ($Performance->Enregistrer()){
                        $Employee->AugmenterPerformance($NbPoint) ;
                        $Employee=new xEmploye($nabysy,$Employee->Id) ;
                        $Notif=new xErreur ;
                        $Notif->OK=1;
                        $Lst['IDPERFORMANCE']=$Performance->Id;
                        $Lst['EDIT_PERFORMANCE']=$Employee->NbPerformance ;                
                        $Notif->Extra=$Lst ;
                        $Retour=json_encode($Notif);
                        $TxP="";
                        if ($NbPoint !== $NbPointPrec){
                            $TxP=" Le nombre de point est passé de ".$NbPointPrec." à ".$NbPoint ;
                        }
                        $Performance->AddToJournal("PERFORMANCE_EDIT","La performance n°".$Performance->Id." de ".$Employee->Prenom." ".$Employee->Nom." a été modifiée.".$TxP);

                        echo $Retour ;
                        exit;
                    }
                }                
            }
            

            $Err->TxErreur="Erreur inconnue !";
            $reponse=json_encode($Err) ;
            echo $reponse ;
            exit;

            break;
		case 'GET_PERFORMANCE' :
            
            if (isset($IdPerformance)){
                $Rep=$Performance->ToJSON();
                echo $Rep ;
                exit;
            }
            $ListeDetaille=false ;
            $Motif=null;
            $Commentaire=null;
            $NbPointSup=null;
            $NbPointInf=null;
            $NbPoint=null;
            $Date=null;
            $Sexe=null;
            $Fonction=null;

            $Critere="( Id>0 " ;

            $ListeReponse=[];

            if(isset($IdEmploye )){
                $Critere .=" AND IDEMPLOYE =".(int)$IdEmploye ;
            }

            if(isset($_REQUEST['DATEDEPART'] )){
                if (!isset($_REQUEST['DATEFIN'])){
                    $Critere .=" AND DATEENREG='".$_REQUEST['DATEDEPART']."' " ;
                }else{
                    $Critere .=" AND (DATEENREG>='".$_REQUEST['DATEDEPART']."' AND DATEENREG<='".$_REQUEST['DATEFIN']."') " ;
                }                
            }

            if(isset($_REQUEST['IDSERVICE'] )){
                $Critere .=" AND IDSERVICE='".$_REQUEST['IDSERVICE']."' " ;
            }
            if(isset($_REQUEST['IDDIRECTION'] )){
                $Critere .=" AND IDDIRECTION like '%".$_REQUEST['IDDIRECTION']."%' " ;
            }
            if(isset($_REQUEST['NOMEMPLOYE'] )){
                $Critere .=" AND NOMEMPLOYE like '%".$_REQUEST['NOMEMPLOYE']."%' " ;
            }
            if(isset($_REQUEST['PRENOMEMPLOYE'] )){
                $Critere .=" AND PRENOMEMPLOYE like '%".$_REQUEST['PRENOMEMPLOYE']."%' " ;
            }            
            if(isset($_REQUEST['MOTIF'] )){
                $Critere .=" AND MOTIF like '%".$_REQUEST['MOTIF']."%' " ;
            }
            if(isset($_REQUEST['COMMENTAIRE'] )){
                $Critere .=" AND COMMENTAIRES like '%".$_REQUEST['COMMENTAIRE']."%' " ;
            }
            if(isset($_REQUEST['SEXE'] )){
                $Critere .=" AND SexeEmploye like '%".$_REQUEST['SEXE']."%' " ;
            }
            if(isset($_REQUEST['FONCTION'] )){
                $Critere .=" AND FonctionEmploye like '%".$_REQUEST['FONCTION']."%' " ;
            }
            
                       
            $Critere .=") " ;
            $Performance->MySQL->DebugMode=false;

            if (!isset($_REQUEST['GROUP'])){
                $Ordre="DATEENREG" ;
                if(isset($_REQUEST['Ordre'] )){
                    if (!$Performance->MySQL->ChampsExiste($Performance->Table,$_REQUEST['ORDRE'])){
                        $_REQUEST['ORDRE']=" DATEENREG " ;
                    }
                    $Ordre =" ".$_REQUEST['Ordre'] ;
                } 
                $Lst=$Performance->ChargeListe($Critere,$Ordre) ;
            }else{
                $Ordre="TOTAL_PERFORMANCE" ;
                if(isset($_REQUEST['Ordre'] )){
                    if (!$Performance->MySQL->ChampsExiste($Performance->Table,$_REQUEST['ORDRE'])){
                        $_REQUEST['ORDRE']=" TOTAL_PERFORMANCE " ;
                    }
                    $Ordre =" ".$_REQUEST['Ordre'] ;
                }
                
                if (!$Performance->MySQL->ChampsExiste($Performance->Table,$_REQUEST['GROUP'])){
                    $_REQUEST['GROUP']=" IDEMPLOYE " ;
                }
                $TxSQL="select COUNT(P.ID) as 'NB_ENREGISTREMENT', SUM(P.NbPointAjoute) as 'TOTAL_PERFORMANCE', P.* from ".$Performance->Table." P where P.ID>0 AND ".$Critere.
                " Group By ".$_REQUEST['GROUP']." Order By ".$Ordre ;
                $Lst=$Performance->ExecSQL($TxSQL);
            }
            
            if ($Lst->num_rows>0){
                while ($row = $Lst->fetch_assoc()){
                    $ListeReponse[]=$row ;
                }
            }
            $reponse=json_encode($ListeReponse);
			echo $reponse;
			break;
        case 'SUPPRIME_PERFORMANCE' :
            if ($Performance->Id<=0){
                $Err->OK=0;
                $Err->TxErreur="Id de la performance introuvable." ;
                echo json_encode($Err);
                exit ;
            }
            if(isset($nabysy->User )){
                if ($nabysy->User->Acces>=3){
                    $Employe=new xEmploye($nabysy,$Performance->IdEmploye);
                    if ($Performance->Supprimer()){
                        if ($Employe->Id>0){
                            $Employe->DiminuerPerformance($Performance->NbPointAjoute);
                        }
                        $Notif=new xErreur;
                        $Notif->OK=1;
                        $Notif->Extra="La performance n°".$Performance->Id.' de '.$Performance->PrenomEmploye.' '.$Performance->NomEmploye.' au motif : '.$Performance->Motif."  a été supprimée correctement.";
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


    }


?>