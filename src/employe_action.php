<?php

use NAbySy\RH\Calendrier\xCalendrierAbs as CalendrierXCalendrierAbs;
use NAbySy\RH\Calendrier\xMoisCalendrier;
use NAbySy\RH\CalendrierAbsence\xCalendrierAbs;
use NAbySy\RH\Personnel\Contrat\xContratTravail;
use NAbySy\RH\Personnel\xEmploye;
use NAbySy\RH\ZoneAffectation;
use NAbySy\RH\ZoneAffectation\xDirection;
use NAbySy\RH\ZoneAffectation\xService;

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
    if (isset($PARAM['IdEmploye'])){
		$IdEmploye=(int)$PARAM['IdEmploye'] ;
	}
    if (isset($PARAM['IDEMPLOYE'])){
		$IdEmploye=(int)$PARAM['IDEMPLOYE'] ;
	}    
	$Employee=new xEmploye($nabysy,$IdEmploye) ;
    $Employe=$Employee ;

	switch ($action){	
		case 'GET_EMPLOYE' :
            $IdDirection=null;
            $IdService=null;

            $Nom=null;
            $Prenom=null;
            $Adresse=null;
            $Tel=null;
            $Sexe=null;
            $Fonction=null;
            $Direction=new xDirection($Employe->Main);            
            $Service=new xService($Employe->Main);            

            $TxSQL="select E.*, if(E.IdService=0,D.Nom,DirectServ.Nom) as 'DIRECTION', 
                S.Nom as 'SERVICE', U.NIVEAUACCES, U.PROFILE as 'USER_PROFILE',if(U.IDEMPLOYE=E.ID,'1','0') as 'ACCES_RESEAU',
                if(U.RS>0,'1','0') as 'RS_ONLY', U.LOGIN, '**********' as 'PASSOWRD' from ".$Employe->Table." E 
            left outer join ".$Service->Table." S on S.ID=E.IdService 
            left outer join ".$Direction->Table." D on D.ID=E.IdDirection 
            left outer join ".$Direction->Table." DirectServ on DirectServ.ID=S.IdDirection 
            left outer join ".$nabysy->User->Table." U on U.IDEMPLOYE=E.Id
            where " ;

            $Critere="( E.Id>0 " ;

            $ListeReponse=[];
            if(isset($_REQUEST['IdEmploye'] )){
                $IdEmploye=(int)$_REQUEST['IdEmploye'] ;
                $Critere .=" AND E.ID=".$IdEmploye ;
            }

            if(isset($_REQUEST['IdDirection'] )){
                $Critere .=" AND E.IdDirection='".(int)$_REQUEST['IdDirection']."' " ;
            }
            if(isset($_REQUEST['IdService'] )){
                $Critere .=" AND E.IdService='".(int)$_REQUEST['IdService']."' " ;
            }
            if(isset($_REQUEST['Nom'] )){
                $Critere .=" AND E.Nom like '%".$_REQUEST['Nom']."%' " ;
            }
            if(isset($_REQUEST['Prenom'] )){
                $Critere .=" AND E.Prenom like '%".$_REQUEST['Prenom']."%' " ;
            }            
            if(isset($_REQUEST['Adresse'] )){
                $Critere .=" AND E.Adresse like '%".$_REQUEST['Adresse']."%' " ;
            }
            if(isset($_REQUEST['Tel'] )){
                $Critere .=" AND E.Tel like '%".$_REQUEST['Tel']."%' " ;
            }
            if(isset($_REQUEST['Sexe'] )){
                $Critere .=" AND E.Sexe like '%".$_REQUEST['Sexe']."%' " ;
            }
            if(isset($_REQUEST['Fonction'] )){
                $Critere .=" AND E.Fonction like '%".$_REQUEST['Fonction']."%' " ;
            }
            if(isset($_REQUEST['NUM_CNI'] )){
                $Critere .=" AND E.NUM_CNI like '%".$_REQUEST['NUM_CNI']."%' " ;
            }
            if(isset($_REQUEST['NUM_PASSPORT'] )){
                $Critere .=" AND E.NUM_PASSPORT like '%".$_REQUEST['NUM_PASSPORT']."%' " ;
            }

            $Critere .=") " ;

            $Ordre="E.NOM,E.PRENOM" ;
            if(isset($_REQUEST['Ordre'] )){
                $Ordre =" ".$_REQUEST['Ordre'] ;
            }

            $GroupeBy=' GROUP BY E.ID';

            $TxSQL .=$Critere.$GroupeBy.' order by '.$Ordre ;
            $Lst=$Employee->ExecSQL($TxSQL) ;
            
            //$Lst=$Employee->ChargeListe($Critere,$Ordre) ;
            if ($Lst->num_rows>0){
                while ($row = $Lst->fetch_assoc()){
                    $UrlPhoto=$Employe->GetURLPhoto($row['ID']);
                    $row['PHOTO_URL']=$UrlPhoto ;

                    //Calcule de l'acienneté
                    $DateEmbauche= new DateTime($row['DateEmbauche']);
                    if($DateEmbauche !==false){
                        $ToDay=new DateTime('now');
                        $interval = $DateEmbauche->diff($ToDay);
                        $Ancienneté= $interval->format('%y an(s) %m mois et %d jour(s)');
                        $row['ANCIENNETE']=$Ancienneté ;
                    }

                    $ListeReponse[]=$row ;
                }
            }

            //On ajoute les perfonmances
            if ($Employee->Id>0){
                $PerformanceAnnee=$Employee->GetPerformanceAnneeEnCour();
                if ($PerformanceAnnee->num_rows>0){
                    while ($rowP = $PerformanceAnnee->fetch_assoc()){
                        $Perf[]=$rowP ;
                    }
                    $ListeReponse['PERFORMANCE_ANNUELLE_ENCOUR']=$Perf ;
                }

                $PerformanceMois=$Employee->GetPerformanceMoisEnCour();
                if ($PerformanceMois->num_rows>0){
                    while ($rowP = $PerformanceMois->fetch_assoc()){
                        $PerfM[]=$rowP ;
                    }
                    $ListeReponse['PERFORMANCE_MENSUELLE_ENCOUR']=$PerfM ;
                }
            }
            

            $reponse=json_encode($ListeReponse);
			echo $reponse;
			break;
        case 'SAVE_EMPLOYE' :
            if ($nabysy->User->Acces<3){
                if ($nabysy->User->IdEmploye !== $Employee->Id){
                    $Err->TxErreur="Niveau d\'accès ".$nabysy->User->Acces." insuffisant pour cette opération. User= ".$nabysy->User->Login;
                    echo json_encode($Err);
                    exit ;
                }
            }
            $YouCanSave=false ;
            $MySQL=new xDB($nabysy);
            $MySQL->DebugMode=false;
            
            foreach($_REQUEST as $Champ => $Valeur){
                if (strtolower($Champ) !== 'id' and strtolower($Champ) !== 'token' 
                    and strtolower($Champ) !== 'action' and strtolower($Champ) !== 'niveauacces' ){
                    //echo 'Champ '.$Champ." = ".$Valeur." /br" ;
                    if ($MySQL->ChampsExiste($Employee->Table,$Champ,$Employee->DataBase)){
                        if ($Valeur !=='undefined'){
                            if ($Employe->IsTypeChampNumeric($Champ)){
                                if ($Employe->GetTypeChampInDB($Champ)==$Employe::$Ctype::FLOAT ||
                                    $Employe->GetTypeChampInDB($Champ)==$Employe::$Ctype::DOUBLE ||
                                    $Employe->GetTypeChampInDB($Champ)==$Employe::$Ctype::DECIMAL ){

                                    $Valeur=(float)$Valeur;
                                }else{
                                    $Valeur=(int)$Valeur;
                                }
                            }
                            if (strtolower($Champ) == strtolower('tel')){
                                //On vérifie si le + est devant le numero
                                $SignPlusPresent=true;
                                $Valeur=preg_replace('/ /i', '', $Valeur);
                                $PlusSign=substr($Valeur,0,1);
                                $Indicatif=substr($Valeur,1,3);
                                if ($PlusSign !== '+'){
                                    $SignPlusPresent=false;
                                    $PlusSign='+';
                                    $Indicatif=substr($Valeur,0,3);
                                }
                                if (substr($Valeur,0,1) !=="+"){
                                    if ($Indicatif !==$nabysy::$Siege->IndicatifTel){
                                        //echo "<br>Ajout de l'indicatif 221</br>";
                                        $Valeur=$nabysy::$Siege->IndicatifTel.$Valeur;
                                    }
                                }
                                if (!$SignPlusPresent){
                                    $Valeur=$PlusSign.$Valeur;
                                }
                            }
                            $Employee->$Champ=$Valeur;
                            $YouCanSave=true;
                        }
                    }else{
                        if ($nabysy->ActiveDebug){
                            $Employe->AddToLog(__FILE__.":".__LINE__.": Champ employe.".$Champ." introuvable.");
                            $Employe->AddToJournal("CHAMP DYNAMIQUE",__FILE__.":".__LINE__.": Champ employe.".$Champ." introuvable.");                            
                        }
                    }
                }           
            }

            if (isset($_REQUEST['ACCES_RESEAU'])){
                if ( (int)$_REQUEST['ACCES_RESEAU']>0 ){
                    $NewLogin=$Employe->Prenom ;
                    $NewLogin=preg_replace('/\s+/', '', $NewLogin); //Enleve tout espace du login
                    $NewLogin=strtolower($NewLogin);

                    if ( isset($_REQUEST['LOGIN'])){
                        $NewLogin=strtolower($_REQUEST['LOGIN']);
                    }

                    $PWD='';
                    if ( isset($_REQUEST['PASSWORD'] )){
                        $PWD=$_REQUEST['PASSWORD'];
                    }

                    $Profile=null;
                    if ( isset($_REQUEST['PROFILE'] )){
                        $Profile=$_REQUEST['PROFILE'];
                    }

                    $NiveauAcces=2;
                    if ( isset($_REQUEST['NIVEAUACCES'] )){
                        $NiveauAcces=(int)$_REQUEST['NIVEAUACCES'];
                        if ($NiveauAcces==0){
                            $NiveauAcces=1;
                        }
                    }

                    //On créer l'utilisateur
                    $UserC=new xUser($nabysy);
                    $Reponse=new xErreur ;
                    $Notif=$UserC->AddNewUserFromEmploye($NewLogin,$PWD,$Employe->Id,$Profile,$NiveauAcces);
                    if ((int)$Notif->OK>0){
                        $Reponse->OK=(int)$Notif->OK ;
                    }else{
                        $Reponse->Source = $Notif->TxErreur;
                    }
                    $reponse=json_encode($Notif);
                    echo $reponse;
                    exit ;
                }
            }

            if ($YouCanSave){
                $Employee->Enregistrer() ;
                $Reponse=new xErreur ;
                $Reponse->OK=1 ;
                $Reponse->Extra=$Employee->Id ;
                $Reponse->Source=$action;

                

                $reponse=json_encode($Reponse);
                echo $reponse;
                exit ;
            }else{
                $NiveauAcces=2;
                if ( isset($_REQUEST['NIVEAUACCES'] )){
                    $NiveauAcces=(int)$_REQUEST['NIVEAUACCES'];
                    if ($NiveauAcces==0){
                        $NiveauAcces=1;
                    }
                    if ($nabysy->User->NiveauAcces<4){
                        $Err->TxErreur="Niveau d\'accès ".$nabysy->User->Acces." insuffisant pour cette opération. User: ".$nabysy->User->Login;
                        echo json_encode($Err);
                        exit ;
                    }
                    $UserC=new xUser($nabysy);
                    if ($Employe->Id==0){
                        $Err->TxErreur="Employé introuvable ou non selectionné.";
                        echo json_encode($Err);
                        exit ;
                    }
                    $UserC=new xUser($nabysy);
                    $Lst=$UserC->ChargeListe("IDEMPLOYE=".(int)$Employe->Id);
                    if ($Lst->num_rows==0){
                        $Err->TxErreur="Aucun accès trouvé pour l'Employé selectionné. impossible de mettre à jour le niveau d'accès.";
                        echo json_encode($Err);
                        exit ;
                    }
                    $row=$Lst->fetch_assoc();
                    if ((int)$row['NIVEAUACCES']==$NiveauAcces ){
                        $Err->TxErreur="Niveau d'accès inchangé.";
                        echo json_encode($Err);
                        exit ;
                    }else{
                        $TxSQL="update ".$UserC->Table." set NiveauAcces='".(int)$NiveauAcces."' where Id=".$row['ID']." limit 1";
                        $Oper="NIVEAU ACCES MODIFIER";
                        $Note="Le niveau d'accès de ".$Employe->Prenom." ".$Employe->Nom." [".$Employe->Id."] est passé de "
                        .$row['NIVEAUACCES']." à ".$NiveauAcces;
                        $UserC->ExecUpdateSQL($TxSQL);

                        $UserC->AddToJournal($Oper,$Note);

                        $Reponse=new xErreur;
                        $Reponse->OK=1;
                        $Reponse->Extra=$Note;
                        echo json_encode($Reponse);
                        exit ;
                    }

                }

                if ( isset($_REQUEST['USER_PROFILE'] )){
                    $ProfileUser=$_REQUEST['USER_PROFILE'];
                    if ($ProfileUser==''){
                        $Err->TxErreur="Profile utilisateur non définit.";
                        echo json_encode($Err);
                        exit ;
                    }
                    if ($nabysy->User->NiveauAcces<4){
                        $Err->TxErreur="Niveau d\'accès ".$nabysy->User->Acces." insuffisant pour cette opération. User: ".$nabysy->User->Login;
                        echo json_encode($Err);
                        exit ;
                    }
                    $UserC=new xUser($nabysy);
                    if ($Employe->Id==0){
                        $Err->TxErreur="Employé introuvable ou non selectionné.";
                        echo json_encode($Err);
                        exit ;
                    }
                    $UserC=new xUser($nabysy);
                    $Lst=$UserC->ChargeListe("IDEMPLOYE=".(int)$Employe->Id);
                    if ($Lst->num_rows==0){
                        $Err->TxErreur="Aucun accès trouvé pour l'Employé selectionné. impossible de mettre à jour le niveau d'accès.";
                        echo json_encode($Err);
                        exit ;
                    }
                    $row=$Lst->fetch_assoc();
                    if (strtolower($row['PROFILE'])==strtolower($ProfileUser) ){
                        $Err->TxErreur="Profile inchangé.";
                        echo json_encode($Err);
                        exit ;
                    }else{
                        $TxSQL="update ".$UserC->Table." set Profile='".addslashes(strtoupper($ProfileUser))."' where Id=".$row['ID']." limit 1";
                        $Oper="PROFILE ACCES MODIFIER";
                        $Note="Le profile d'accès de ".$Employe->Prenom." ".$Employe->Nom." [".$Employe->Id."] est passé de "
                        .$row['PROFILE']." à ".addslashes(strtoupper($ProfileUser));
                        $UserC->ExecUpdateSQL($TxSQL);

                        $UserC->AddToJournal($Oper,$Note);

                        $Reponse=new xErreur;
                        $Reponse->OK=1;
                        $Reponse->Extra=$Note;
                        echo json_encode($Reponse);
                        exit ;
                    }

                }
            }
            $Err->TxErreur="Opération impossible. Vérifiez les champs de données svp.";
            $reponse=json_encode($Err);
            echo $reponse;
            break;
        case 'SUPPRIME_EMPLOYE' :
            $IdEmploye=null ;
            if(isset($_REQUEST['IdEmploye'] )){
                $IdEmploye= $_REQUEST['IdEmploye'] ;
            }
            $Employe=new xEmploye($nabysy,$IdEmploye);
            if(isset($nabysy->User )){
                if ($nabysy->User->Acces>=3){
                    if ($Employe->Supprimer()){
                        $Notif=new xErreur;
                        $Notif->OK=1;
                        $Notif->Extra="L/'employé ".$Employe->Prenom.' '.$Employe->Nom." a été supprimé correctement.";
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
        
        case 'AFFECTER_EMPLOYE' :
            $IdEmploye=null ;
            $Direction=null;
            $Service=null;
            $Employe=null;
            if ($nabysy->User->Acces<3){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
            if(isset($_REQUEST['IdEmploye'] )){
                $IdEmploye= $_REQUEST['IdEmploye'] ;
                $Employe=new xEmploye($nabysy,$IdEmploye);
            }
            if(isset($_REQUEST['IdDirection'] )){
                $Direction=new xDirection($nabysy,$_REQUEST['IdDirection']) ;
            }elseif(isset($_REQUEST['IdService'] )){
                $Service=new xService($nabysy,$_REQUEST['IdService']) ;
            }
            if (!isset($Employe)){
                $Err->OK=0;
                $Err->TxErreur="Aucun employé selectionné." ;
                echo json_encode($Err);
                exit ;
            }
            if(isset($nabysy->User )){
                if ($nabysy->User->Acces>=3){
                    $Notif=new xErreur;
                    $Notif->OK=1;
                    $AffectationOK=false ;
                    if (isset($Direction)){
                        $AffectationOK= $Employe->AffecterDansDirection($Direction);
                        $Notif->Extra="L/'employé ".$Employe->Prenom.' '.$Employe->Nom." a été affecté correctement dans ".$Direction->Nom;
                    }
                    if (isset($Service)){
                        $AffectationOK=$Employe->AffecterDansService($Service);
                        $Notif->Extra="L/'employé ".$Employe->Prenom.' '.$Employe->Nom." a été affecté correctement dans ".$Service->Nom;
                    }
                    if ($AffectationOK){
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

        case "AJOUT_ABSENCE" :
            $Employe=null;
            if ($nabysy->User->Acces<3){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
            if(isset($_REQUEST['IdEmploye'] )){
                $IdEmploye= $_REQUEST['IdEmploye'] ;
                $Employe=new xEmploye($nabysy,$IdEmploye);
            }
            if (!isset($Employe)){
                $Err->OK=0;
                $Err->TxErreur="Aucun employé selectionné." ;
                echo json_encode($Err);
                exit ;
            }
            if(!isset($_REQUEST['Motif'] )){
                $Err->OK=0;
                $Err->TxErreur="Motif introuvable." ;
                echo json_encode($Err);
                exit ;
            }
            if(!isset($_REQUEST['DateDepart'] )){
                $Err->OK=0;
                $Err->TxErreur="Date de départ de l\'absence introuvable." ;
                echo json_encode($Err);
                exit ;
            }
            if(!isset($_REQUEST['DateFin'] )){
                $Err->OK=0;
                $Err->TxErreur="Date de fin de l\'absence introuvable." ;
                echo json_encode($Err);
                exit ;
            }

            $IsPaye=0;
            if(isset($_REQUEST['IsPaye'] )){
                $IsPaye=$_REQUEST['IsPaye'];
            }

            $Annee=null;
            if(isset($_REQUEST['PourToujours'] )){
                if ($_REQUEST['PourToujours']>0){
                    $Annee='0000';
                }                
            }

            $Absence=new CalendrierXCalendrierAbs($nabysy);
            $Ret=$Absence->AjouterAbsence($_REQUEST['Motif'],$IsPaye,$_REQUEST['DateDepart'],$_REQUEST['DateFin'],$Annee, $Employe) ;
            if ((int)$Ret){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra=$Ret ;
                $Notif->Source=$action;
                $Reponse=json_encode($Notif);
                echo $Reponse;
                exit;
            }else{
                $Err->TxErreur=$Ret ;                
            }
            echo json_encode($Err);
            exit ;

        case "RETIRER_ABSENCE" :
            $Employe=null;
            $Absence=null;
            if ($nabysy->User->Acces<3){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
            if(isset($_REQUEST['IdAbsence'] )){
                $IdAbsence= $_REQUEST['IdAbsence'] ;
                $Absence=new CalendrierXCalendrierAbs($nabysy,$IdAbsence);
                if ($Absence->Id>0){
                    if ($Absence->IdEmploye>0){
                        $Employe=new xEmploye($nabysy,$Absence->IdEmploye);
                    }
                }
            }
            if (!isset($Employe)){
                $Err->OK=0;
                $Err->TxErreur="Aucune absence avec Id ".$Absence->Id. " trouvée pour cet employé." ;
                echo json_encode($Err);
                exit ;
            }

            if (!isset($Absence)){
                $Err->OK=0;
                $Err->TxErreur="Aucune absence trouvée." ;
                echo json_encode($Err);
                exit ;
            }

            if ($Absence->RetirerAbsence()){
                $Notif=new xErreur;
                $Notif->OK=1;
                $Notif->Extra="Absence Id ".$Absence->Id." retirée. " ;
                $Notif->Source=$action;
                $Reponse=json_encode($Notif);
                echo $Reponse;
                exit;
            }
            echo json_encode($Err);
            exit ;

        case 'SAVE_PHOTO':
            $ChampFichier='photo' ;
            if ($Employee->Id==0){
                $Err->TxErreur='Employé introuvable. Impossible de stocker la photo' ;
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }
            
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }

            if ($Employe->Id>0){                
                $Repo=$Employe->SavePhoto($ChampFichier);
                if ($Repo !== true){
                    $TypeReponse=get_class($Repo) ;
                    if ( $TypeReponse=='xErreur'){
                        $Reponse=json_encode($Repo) ;
                    }
                }else{
                    $Repo=new xErreur ;
                    $Repo->OK=1;
                    $Repo->Extra=$Employe->GetURLPhoto() ;
                }
                
                $Reponse=json_encode($Repo) ;
                echo $Reponse;
                
            }else{
                $Reponse=new xErreur ;
                $Reponse->OK=0;
                $Reponse->TxErreur="Impossible de valider l'opération !!!" ;
                $Reponse=json_encode($Reponse) ;
                echo $Reponse;
                
            }

            break;
        case 'GET_PHOTO':          
            $GetChemin=true;
            if (!$Employe->PhotoExiste()){
                $Err->OK=0;
                $Err->TxErreur="Aucune photo disponible pour cet employé !" ;
                $Reponse=json_encode($Err) ;
                echo $Reponse;
                exit ;
            }
            if ($Employe->Id>0){                
                $Chemin=$Employe->GetPhoto($GetChemin) ;
                if ($GetChemin){
                    echo $Chemin;
                }
            }else{
                $Reponse=new xErreur ;
                $Reponse->OK=0;
                $Reponse->TxErreur="Impossible de valider l'opération. Employé inconnue !" ;
                $Reponse=json_encode($Repo) ;
                echo $Reponse;
                exit ;
            }
            break ;
        
        case "AJOUT_CONTRAT_EMPLOYE":
            $Employe=null;
            if ($nabysy->User->Acces<3){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
            if(isset($_REQUEST['IDEMPLOYE'] )){
                $IdEmploye= (int)$_REQUEST['IDEMPLOYE'] ;
                $Employe=new xEmploye($nabysy,$IdEmploye);
            }
            
            $IdContrat=null;
            $Contrat=new xContratTravail($nabysy,$IdContrat);

            if(isset($_REQUEST['IDCONTRAT'] )){
                if ((int)$_REQUEST['IDCONTRAT']){
                    $IdContrat= (int)$_REQUEST['IDCONTRAT'] ;
                    $Contrat=new xContratTravail($nabysy,$IdContrat);
                    if ($Contrat->IdEmploye>0){
                        $Employe=new xEmploye($nabysy,$Contrat->IdEmploye);
                    }
                }                                
            }
           
            if (!isset($Employe)){
                $Err->OK=0;
                $Err->TxErreur="Aucun employé selectionné." ;
                echo json_encode($Err);
                exit ;
            }
            if ($Employe->Id<1){
                $Err->OK=0;
                $Err->TxErreur="Aucun employé selectionné ou Introuvable !" ;
                echo json_encode($Err);
                exit ;
            }

            if ($Contrat->IdEmploye !== $Employe->Id){
                $Contrat->IdEmploye=$Employe->Id ;
            }

            if (isset($_REQUEST['TITRE_CONTRAT'])){
                $Contrat->TitreContrat=$_REQUEST['TITRE_CONTRAT'] ;
            }
            if ($Contrat->TitreContrat==''){$Contrat->TitreContrat==$Contrat::CONTRAT_TRAVAIL; }

            if (isset($_REQUEST['DATEDEBUT'])){
                $DateD=new DateTime($_REQUEST['DATEDEBUT']);                
                if ($DateD !==false){
                    //On vérifie si un contrat existe pour la meme période
                    if ($Contrat->Id==0){
                        $Rep=$Contrat->ChargeListe("DATEDEBUT>='".$DateD->format('Y-m-d')."' and IDEMPLOYE=".$Employe->Id) ;
                        if ($Rep->num_rows>0){
                            //Un contrat couvrant la meme période
                            $Err->OK=0;
                            $Err->TxErreur="Il existe un contrat couvrant la même période pour l'employé." ;
                            $row=$Rep->fetch_assoc();
                            $Err->Source=$row ;
                            echo json_encode($Err);
                            exit ;
                        }
                    }
                   
                    $Contrat->DateDebut=$DateD->format('Y-m-d'); ;
                }else{
                    $Err->OK=0;
                    $Err->TxErreur="Date de début du contrat incorrecte." ;
                    echo json_encode($Err);
                    exit ;
                }             
            }
            if (isset($_REQUEST['DATEFIN'])){
                $DateF=new DateTime($_REQUEST['DATEFIN']);
                if ($DateF !==false){
                    //On vérifie si un contrat existe pour la meme période
                    if ($Contrat->Id==0){
                        $Rep=$Contrat->ChargeListe("DATEFIN<='".$DateF->format('Y-m-d')."' and IDEMPLOYE=".$Employe->Id) ;
                        if ($Rep->num_rows>0){
                            //Un contrat couvrant la meme période
                            $Err->OK=0;
                            $Err->TxErreur="Il existe un contrat couvrant la même période pour l'employé." ;
                            $row=$Rep->fetch_assoc();
                            $Err->Source=$row ;
                            echo json_encode($Err);
                            exit ;
                        }
                    }                    
                    $Contrat->DateFin=$DateF->format('Y-m-d');
                    $Contrat->TypeContrat=$Contrat::TYPE_CONTRAT_CDD;
                }else{
                    $Err->OK=0;
                    $Err->TxErreur="Date de fin du contrat incorrecte." ;
                    echo json_encode($Err);
                    exit ;
                }             
            }elseif(isset($_REQUEST['ILLIMITE'])){
                $Contrat->Illimite=1;
                $Contrat->TypeContrat=$Contrat::TYPE_CONTRAT_CDI ;
            }

            if ((int)$Contrat->Illimite==0){
                if ($Contrat->DateDebut==''){
                    $Err->OK=0;
                    $Err->TxErreur="Vueillez préciser la période du contrat svp." ;
                    echo json_encode($Err);
                    exit ;
                }else{
                    if ($Contrat->DateFin==''){
                        $Err->OK=0;
                        $Err->TxErreur="Vueillez préciser la date de fin du contrat svp." ;
                        echo json_encode($Err);
                        exit ;
                    }
                }
            }
            $Contrat->Enregistrer();
            $IdContrat=$Contrat->Id;
            $Reponse=new xErreur ;
            $Reponse->OK=1;
            $Reponse->Extra=$IdContrat ;
            $Reponse=json_encode($Reponse) ;
            echo $Reponse;
            exit;
            break;
        
        case 'JOINDRE_FICHIER_CONTRAT':            
            $IdContrat=null;
            $Employe=null;
            $Contrat=new xContratTravail($nabysy,$IdContrat);
            if(isset($_REQUEST['IDCONTRAT'] )){
                if ((int)$_REQUEST['IDCONTRAT']){
                    $IdContrat= (int)$_REQUEST['IDCONTRAT'] ;
                    $Contrat=new xContratTravail($nabysy,$IdContrat);
                    if ($Contrat->IdEmploye>0){
                        $Employe=new xEmploye($nabysy,$Contrat->IdEmploye);
                    }
                }                                
            }

            if ($Contrat->Id<1){
                $Err->OK=0;
                $Err->TxErreur="Aucun contrat trouvé." ;
                echo json_encode($Err);
                exit ;
            }
           
            if (!isset($Employe)){
                $Err->OK=0;
                $Err->TxErreur="Aucun employé trouvé pour ce contrat." ;
                echo json_encode($Err);
                exit ;
            }
            if ($Employe->Id<1){
                $Err->OK=0;
                $Err->TxErreur="employé inexistant ou Introuvable !" ;
                echo json_encode($Err);
                exit ;
            }
            $ChampFichier='fichier' ;
            $NomFichier='';
            if (isset($PARAM['NOMFICHIER'])){
                $NomFichier=$PARAM['NOMFICHIER'] ;
            }

            if ($NomFichier==''){
                $Err->TxErreur='Nom de fichier absent. Impossible de joindre le fichier' ;
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }

            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }
            if ($Contrat->TypeContrat !==''){
                $NomFichier=$Contrat->TypeContrat."-".$NomFichier;
            }
            
            if ($Employe->JoindreFichier($NomFichier,$ChampFichier,$Contrat)){  
                $Notif=new xErreur;              
                $Notif->OK=1;
                $Notif->Extra=$NomFichier;
                $Reponse=json_encode($Notif);
            }else{
                $Err->TxErreur='Impossible de joindre le fichier. Erreur inconnue.';
                $Reponse=json_encode($Err);
            }
            echo $Reponse;
            exit;
            break;
        case 'LISTE_FICHIER_CONTRAT':
            //Liste des fichiers joint au Rapport
            $Employe=null;
            $Contrat=null;
            $IdEmploye=0;
            if(isset($_REQUEST['IDEMPLOYE'] )){
                $IdEmploye= (int)$_REQUEST['IDEMPLOYE'] ;
                $Employe=new xEmploye($nabysy,$IdEmploye);
            }
            if(isset($_REQUEST['IDCONTRAT'] )){
                $IdContrat= (int)$_REQUEST['IDCONTRAT'] ;
                $Contrat=new xContratTravail($nabysy,$IdContrat);
                if ($Contrat->IdEmploye>0){
                    $IdEmploye=$Contrat->IdEmploye;
                    $Employe=new xEmploye($nabysy,$IdEmploye);
                }                
            }

            if ($nabysy->User->Acces<3 && $IdEmploye !== $nabysy->User->IdEmploye ){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
           
            if ($Employe->Id<=0){
                $Err->TxErreur='Employé introuvable.' ;
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }

            $ListeFichier=$Employe->GetListeDocument($Contrat);
            $Reponse=json_encode($ListeFichier);
            echo $Reponse;
            exit;
            break;

        case 'SUPPRIME_FICHIER_CONTRAT':
            $Employe=null;
            $IdEmploye=0;
            $Contrat=null;

            if(isset($_REQUEST['IDCONTRAT'] )){
                $IdContrat= (int)$_REQUEST['IDCONTRAT'] ;
                $Contrat=new xContratTravail($nabysy,$IdContrat);
            }
            if ($nabysy->User->Acces<3 && $IdEmploye !== $nabysy->User->IdEmploye ){
                $Err->TxErreur="Niveau d\'accès insuffisant pour cette opération.";
                echo json_encode($Err);
                exit ;
            }
           
            if ($Contrat->Id<=0){
                $Err->TxErreur='Contrat introuvable.' ;
                $reponse=json_encode($Err) ;
                echo $reponse ;
                exit;
            }

            $NomFichier=null;
            if (isset($_REQUEST['NOMFICHIER'])){
                $NomFichier=$_REQUEST['NOMFICHIER'];
            }

            $Employe=new xEmploye($nabysy,$Contrat->IdEmploye);

            $Notif=new xErreur;
            $Notif->OK=1;
            $Notif->Extra="Fichier ".$NomFichier." introuvable pour l'employé  Id-n° ".$Employe->Id;
            if (isset($NomFichier)){
                if ($Employe->DeleteDocument($NomFichier)){
                    $Notif->Extra='Fichier '.$NomFichier." supprimé correctement.";
                }
            }
            $Reponse=json_encode($Notif);
            echo $Reponse;
            exit;
            break;         
        case "GET_CONTRAT_EMPLOYE":
            $IdEmploye=null;
            $IdDirection=null;
            $IdService=null;
            $IdContrat=null;
            $Nom=null;
            $Prenom=null;
            $Adresse=null;
            $Tel=null;
            $Sexe=null;
            $Fonction=null;

            $Contrat = new xContratTravail($nabysy);
            $Direction=new xDirection($Employe->Main);            
            $Service=new xService($Employe->Main);            

            $TxSQL="select C.*, E.PRENOM as 'PRENOM_EMPLOYE',E.NOM as 'NOM_EMPLOYE', E.DateEmbauche, if(E.IdService=0,D.Nom,DirectServ.Nom) as 'DIRECTION', S.Nom as 'SERVICE' from ".$Contrat->Table." C 
            left outer join ".$Employe->Table." E on E.ID=C.IDEMPLOYE 
            left outer join ".$Service->Table." S on S.ID=E.IdService 
            left outer join ".$Direction->Table." D on D.ID=E.IdDirection 
            left outer join ".$Direction->Table." DirectServ on DirectServ.ID=S.IdDirection 
            where " ;

            $Critere="( C.Id>0 " ;

            if(isset($_REQUEST['IDCONTRAT'] )){
                $IdContrat=(int)$_REQUEST['IDCONTRAT'] ;
                $Critere ="( C.ID=".$IdContrat." " ;
            }

            $ListeReponse=[];
            if(isset($_REQUEST['IDEMPLOYE'] )){
                $IdEmploye=$_REQUEST['IDEMPLOYE'] ;
                $Critere .=" AND C.IDEMPLOYE=".$IdEmploye ;
            }

            if (isset($_REQUEST['DATEDEBUT'])){
                if (isset($_REQUEST['DATEFIN'])){
                    $Critere .=" and C.DATEDEBUT>='".$_REQUEST['DATEDEBUT']."' and C.DATEFIN<='".$_REQUEST['DATEFIN']."' " ;
                }else{
                    $Critere .=" and C.DATEDEBUT='".$_REQUEST['DATEDEBUT']."' " ;
                }
            }

            if(isset($_REQUEST['IDDIRECTION'] )){
                if ((int)$_REQUEST['IDDIRECTION']){
                    $Critere .=" AND (E.IDDIRECTION='".(int)$_REQUEST['IDDIRECTION']."' or S.IDDIRECTION='".(int)$_REQUEST['IDDIRECTION']."' ) " ;
                }                
            }
            if(isset($_REQUEST['IDSERVICE'] )){
                $Critere .=" AND E.IDSERVICE='".$_REQUEST['IDSERVICE']."' " ;
            }
            if(isset($_REQUEST['NOM'] )){
                $Critere .=" AND E.Nom like '%".$_REQUEST['NOM']."%' " ;
            }
            if(isset($_REQUEST['PRENOM'] )){
                $Critere .=" AND E.Prenom like '%".$_REQUEST['PRENOM']."%' " ;
            }            
            if(isset($_REQUEST['TITRE_CONTRAT'] )){
                $Critere .=" AND C.TITRECONTRAT like '%".$_REQUEST['TITRE_CONTRAT']."%' " ;
            }
            if(isset($_REQUEST['Tel'] )){
                $Critere .=" AND E.Tel like '%".$_REQUEST['Tel']."%' " ;
            }
            if(isset($_REQUEST['Sexe'] )){
                $Critere .=" AND E.Sexe like '%".$_REQUEST['Sexe']."%' " ;
            }
            if(isset($_REQUEST['Fonction'] )){
                $Critere .=" AND E.Fonction like '%".$_REQUEST['Fonction']."%' " ;
            }
            if(isset($_REQUEST['NUM_CNI'] )){
                $Critere .=" AND E.NUM_CNI like '%".$_REQUEST['NUM_CNI']."%' " ;
            }
            if(isset($_REQUEST['ILLIMITE'] )){
                if ((int)$_REQUEST['ILLIMITE']>0){
                    $Critere .=" AND C.ILLIMITE=1" ;
                }else{
                    $Critere .=" AND C.ILLIMITE=0" ;
                }                
            }

            $Critere .=") " ;

            $Ordre="E.NOM,E.PRENOM" ;
            if(isset($_REQUEST['Ordre'] )){
                $Ordre =" ".$_REQUEST['Ordre'] ;
            }

            $TxSQL .=$Critere.' order by '.$Ordre ;
            //echo($TxSQL);
            $Lst=$Employee->ExecSQL($TxSQL) ;
            
            //$Lst=$Employee->ChargeListe($Critere,$Ordre) ;
            if ($Lst->num_rows>0){
                while ($row = $Lst->fetch_assoc()){
                    $UrlPhoto=$Employe->GetURLPhoto($row['IdEmploye']);
                    $row['PHOTO_URL']=$UrlPhoto ;

                    //Calcule de l'acienneté
                    $DateEmbauche= new DateTime($row['DateEmbauche']);
                    if($DateEmbauche !==false){
                        $ToDay=new DateTime('now');
                        $interval = $DateEmbauche->diff($ToDay);
                        $Ancienneté= $interval->format('%y an(s) %m mois et %d jour(s)');
                        $row['ANCIENNETE']=$Ancienneté ;
                    }

                    $ListeReponse[]=$row ;
                }
            }else{
                //echo($TxSQL);
            }

            $reponse=json_encode($ListeReponse);
            echo $reponse ;
            exit;
            break;
		default:
			Retourne();	
			break;
	}
	 
	
	
	function Retourne($lien=null){
		
		 exit ;
	}