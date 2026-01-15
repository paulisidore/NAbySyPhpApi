<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;
use NAbySy\xDB;
use NAbySy\xErreur;
use NAbySy\xNotification;

$nabysy = N::getInstance() ;
switch ($action){
        case 'ETS_GETINFOS': //Retourne les information personnelle de l'entreprise cliente
            $IdBout=N::getInstance()->MaBoutique->Id;
            $nabysy->AutorisationCORS();
            if (isset($PARAM['ID'])){
                $IdBout=(int)$PARAM['ID'] ;
            }
            if (isset($PARAM['IDBOUTIQUE'])){
                $IdBout=(int)$PARAM['IDBOUTIQUE'] ;
            }
            //echo(N::getInstance()->MaBoutique->Nom);exit;

            if(isset($PARAM['IDTECHNOWEB'])){
                if(trim($PARAM['IDTECHNOWEB']) !==''){
                    if(isset(N::$TechnoWEBMgr)){
                        $ClientTechnoWeb=N::$TechnoWEBMgr->GetClientTechnoWeb($PARAM['IDTECHNOWEB']);
                        if($ClientTechnoWeb){
                            $IdBTrouve=null;
                            if($ClientTechnoWeb->ServiceDB == N::getInstance()->MaBoutique->DBName ){
                                $IdBout=N::getInstance()->MaBoutique->Id;
                                $IdBTrouve = $IdBout ;
                                N::getInstance()::$Log->AddToLog("NAbySyGS DB MainTable: " . N::getInstance()->MainDataBase ) ;
                                N::getInstance()::$Log->AddToLog("Maint DB Table Boutique: " . N::getInstance()->MaBoutique->FullTableName() ) ;
                                //N::getInstance()::$Log->AddToLog("Boutique déjà en cour IdBout = ".$IdBout." : DB=>".N::getInstance()->MaBoutique->DBName);
                            }else{
                                $IdBout = $ClientTechnoWeb->Id ;
                                //$Critere="<p>DBName like '".$ClientTechnoWeb->ServiceDB."' " ;
                                //echo "DB Recherché = ".$Critere . "</p>";
                                foreach (N::getInstance()::$ListeBoutique as $BoutX) {
                                    //echo($BoutX->Nom." : DB=>".$BoutX->DBName." </br>");
                                    //N::getInstance()::$Log->AddToLog("Recherche ... ".$BoutX->Nom." : DB=>".$BoutX->DBName);
                                    if($BoutX->DBName == $ClientTechnoWeb->ServiceDB ){
                                        $IdBout = $BoutX->Id;
                                        $IdBTrouve = $IdBout ;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $Bout=new xBoutique($nabysy,$IdBout,N::GLOBAL_AUTO_CREATE_DBTABLE);
            if($Bout->Id==0){
                $Err=new xErreur();
                $Err->Autres = $IdBout ;
                $Err->TxErreur="Information du client PAM introuvable !";
                $Err->SendAsJSON();
            }
            
            $Reponse = new xNotification ;
            $Reponse->OK=1;
            $rw = $Bout->ToArrayAssoc();
            //$rw['DBASE'] = $Bout->DBname;
            N::getInstance()::$Log->AddToLog("Boutique trouvée: ".json_encode($rw));
                    
            $rw['URL_ENTETE'] = $Bout->GetLogoEntete(true);
            $rw['ENTETE_TICKET'] =  $rw['URL_ENTETE'] ;
            $rw['ENTETE_A4'] = $Bout->GetEnteteA4(true);

            unset($rw['Serveur']);
            unset($rw['DBName']);
            unset($rw['PdtTable']);
            unset($rw['TablePrefix']);
            unset($rw['DBUser']);
            unset($rw['DBPassword']);
            unset($rw['ConnexionString']);
            unset($rw['ACTIF']);
            unset($rw['DBase']);
            unset($rw['MasterDataBase']);
            unset($rw['ListePanier']);

            $lien_logo = $Bout->GetLogoTicket(true);
            $rw['LOGO_TICKET'] = $lien_logo ;
            //unset($rw['LOGO_TICKET']);
            if(trim($rw['LOGO_TICKET']) !== ""){
               $rw['ENTETE_TICKET'] = $rw['LOGO_TICKET'];
            }

            $Param=$nabysy->Parametre;
            if(isset($Param) && $Param->Id){
                if(!$Param->ChampsExisteInTable("PIED_A4")){
                    $PrecAutoCreate=$Param->AutoCreate;;
                    $Param->AutoCreate=true;
                    $Param->PIED_A4="";
                    $Param->PIED_TICKET="";
                    $Param->Enregistrer();
                    $Param->AutoCreate=$PrecAutoCreate;
                }
            }
            if(isset($Param) && $Param->Id){
                $rw['Tel'] = $Param->Tel ;
                $rw['PIED_TICKET'] = $Param->PIED_TICKET ;
                $rw['PIED_A4'] = $Param->PIED_A4 ;
                $rw['MONNAIE'] = $Param->Monnaie ;
                $rw['MONNAIE_LONGUE'] = $Param->MonnaieLong ;
                $rw['PAYS'] = $Param->MonPays ;
                $rw['REGION'] = $Param->MaRegion ;
            }

            $Reponse->Contenue = $rw ;
            echo json_encode($Reponse);
            exit;
            break;
        
        case 'ETS_CONFIG_SAVE': //Retourne une configuration
            $IdConfig=1;
            $NewConfig=false;
            $IdBout=N::getInstance()->MaBoutique->Id;
            if(isset($_REQUEST['ID'])){
                if ((int)($_REQUEST['ID'])){
                    $IdConfig = (int)$_REQUEST['ID'];
                }
            }
            if(isset($PARAM['IDTECHNOWEB'])){
                if(trim($PARAM['IDTECHNOWEB']) !==''){
                    if(isset(N::$TechnoWEBMgr)){
                        $ClientTechnoWeb=N::$TechnoWEBMgr->GetClientTechnoWeb($PARAM['IDTECHNOWEB']);
                        if($ClientTechnoWeb){
                            $IdBTrouve=null;
                            if($ClientTechnoWeb->ServiceDB == N::getInstance()->MaBoutique->DBName ){
                                $IdBout=N::getInstance()->MaBoutique->Id;
                                $IdBTrouve = $IdBout ;
                            }else{
                                $IdBout = $ClientTechnoWeb->Id ;
                                $Critere="<p>DBName like '".$ClientTechnoWeb->ServiceDB."' " ;
                            
                                //echo "DB Recherché = ".$Critere . "</p>";
                                foreach (N::getInstance()::$ListeBoutique as $BoutX) {
                                    //echo($BoutX->Nom." : DB=>".$BoutX->DBName." </br>");
                                    if($BoutX->DBName == $ClientTechnoWeb->ServiceDB ){
                                        $IdBout = $BoutX->Id;
                                        $IdBTrouve = $IdBout ;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $Bout=new xBoutique($nabysy,$IdBout,N::GLOBAL_AUTO_CREATE_DBTABLE);
            if($Bout->Id==0){
                $Err=new xErreur();
                $Err->Autres = $IdBout ;
                $Err->TxErreur="Information du client PAM introuvable !";
                $Err->SendAsJSON();
            }

            $Param=new xORMHelper($nabysy,$IdConfig,N::GLOBAL_AUTO_CREATE_DBTABLE,"parametre", $Bout->DataBase);
            $Reponse=new xNotification;
            if ($Param->Id==0){
               $NewConfig=true;
            }

            if(!$Param->ChampsExisteInTable("Adresse")){
                $PrecAutoCreate=$Param->AutoCreate;;
                $Param->AutoCreate=true;
                $Param->Adresse="";
                $Param->Tel="";
                $Param->Email="";
                $Param->Enregistrer();
                $Param->AutoCreate=$PrecAutoCreate;
            }

            $YouCanSave=false ;
            $YouCanSaveBout=false;
            $MySQL=new xDB($nabysy);
            $MySQL->DebugMode=false;
            $ListeChampIntrouvable=[];
            $ListeVariable=$_REQUEST;
            if(isset($ListeVariable['Config'])){
                $ListeVariable = json_decode($ListeVariable['Config'],true);
            }
            //$Param->AddToLog(__FILE__.":".__LINE__.": Param.".json_encode($ListeVariable));

            foreach($ListeVariable as $Champ => $Valeur){
                
                if (strtolower($Champ) !== 'id' and strtolower($Champ) !== 'token' 
                    and strtolower($Champ) !== 'action' and strtolower($Champ) !== 'niveauacces' ){
                    //echo 'Champ '.$Champ." = ".$Valeur." /br" ;
                    if(strtoupper($Champ) == 'MONNAIE_LONGUE'){
                        $Champ = 'MONNAIELONG' ;
                    }
                    if ($Valeur !=='undefined'){
                        if ($Param->IsTypeChampNumeric($Champ)){
                            if ($Param->GetTypeChampInDB($Champ)==$Param::$Ctype::FLOAT ||
                                $Param->GetTypeChampInDB($Champ)==$Param::$Ctype::DOUBLE ||
                                $Param->GetTypeChampInDB($Champ)==$Param::$Ctype::DECIMAL ){

                                $Valeur=(float)$Valeur;
                            }else{
                                $Valeur=(int)$Valeur;
                            }
                        }
                        if ($MySQL->ChampsExiste($Param->Table,$Champ,$Param->DataBase)){
                            //$Param->AddToLog(__FILE__.":".__LINE__." Champ existant: ".$Champ."=".$Valeur);
                            $Param->$Champ=$Valeur;
                            $YouCanSave=true;
                            if($Bout->ChampsExisteInTable($Champ)){
                                $Bout->$Champ = $Valeur ;
                                $YouCanSaveBout = true;
                            }
                        }elseif($Bout->ChampsExisteInTable($Champ)){
                            $Bout->$Champ = $Valeur ;
                            $YouCanSaveBout = true;
                        }
                        else{
                            $ListeChampIntrouvable[]=$Champ;
                        }
                    }
                }
            }

            if ($YouCanSave){
                //var_dump($Param->ToJSON());
                //exit;                
                if ($Param->Enregistrer()){
                    if ($NewConfig){
                        $Param->AddToJournal("PARAMETRE","Enregistrement d'un nouveau paramètre. IdParam = ".$Param->Id) ;
                    }
                }
            }

            if($YouCanSaveBout){
                if($Bout->Enregistrer()){
                    $Bout->AddToJournal("PARAMETRE-BOUTIQUE","Miase à jour des paramètres pour la boutique ".$Bout->Nom) ;
                }
            }

            if($ListeChampIntrouvable && count($ListeChampIntrouvable)>0){
                $ListeChampParamBoutique=[];
                $Bout = $nabysy->MaBoutique ;
                $CanSaveBout=false;
                //$Bout->AddToLog(__FILE__.":".__LINE__.": Vérification dans les parametres de base de la Liste des champs introuvables: ".json_encode($ListeChampIntrouvable));
                foreach ($ListeChampIntrouvable as $key => $Champ) {
                    $Valeur = $ListeVariable[$Champ] ;
                    if($Bout->ChampsExisteInTable($Champ)){
                        $ListeChampParamBoutique[]=$Champ ;
                        //$Bout->AddToLog(__FILE__.":".__LINE__.": Le champ ".$Champ." existe dans les paramètres de la boutique.");
                        if ($Bout->IsTypeChampNumeric($Champ)){
                            if ($Bout->GetTypeChampInDB($Champ)==xORMHelper::$Ctype::FLOAT ||
                                $Bout->GetTypeChampInDB($Champ)==xORMHelper::$Ctype::DOUBLE ||
                                $Bout->GetTypeChampInDB($Champ)==xORMHelper::$Ctype::DECIMAL ){
                                $Valeur=(float)$Valeur;
                            }else{
                                $Valeur=(int)$Valeur;
                            }
                        }
                        $Bout->$Champ=$Valeur;
                        $CanSaveBout=true;
                    }
                }
                if($CanSaveBout){
                    if(!$Bout->Enregistrer()){
                        $Bout->AddToJournal("DEBUG","Mise à jour des paramètres impossible pour la boutique. IdBoutique = ".$Bout->Id) ;
                    }
                }
                foreach ($ListeChampParamBoutique as $key => $ChampSauve) {
                    $index = array_search($ChampSauve, $ListeChampIntrouvable);
                    if ($index !== false) {
                        unset($ListeChampIntrouvable[$index]);
                    }
                }
                if(count($ListeChampIntrouvable)>0){
                    $Param->AddToLog(__FILE__.":".__LINE__.": Liste des champs introuvables après vérification dans les paramètres de la boutique: ".json_encode($ListeChampIntrouvable));
                }
            }

            $Reponse->OK=1;
            $Reponse->Extra=json_encode($_REQUEST);
            $Reponse->Contenue=$Param->ToObject();
            echo json_encode($Reponse);
            exit;
            break;

		case "LISTE_BOUTIQUE":
			//Retourne la Liste des Boutiques
			$TxM=false ;
			$CallBack=null ;
			
			if (isset($PARAM['CallBack'])){
				$CallBack=$PARAM['CallBack'] ;
			}		
            $TxSQL="select * from ".$nabysy->MainDataBase.".".$nabysy->MaBoutique->Table." order by ID" ;
            //$Reponse=R::getAll($TxSQL) ;

            $Rep=$nabysy->ReadWrite($TxSQL) ;
                       
            $Liste=$nabysy->EncodeReponseSQL($Rep) ;
            $vListe=array() ;
            foreach ($Liste as $Ligne){
                $vListe[]=$Ligne ;
            }
            ;
            $json=json_encode($nabysy->utf8ize($vListe)) ;
            if (!$json){
                RetourneJsonError($nabysy->GetJsonError());
                exit ;
            }
            echo $json ;            

            break ;
        
        case "LISTE_PRODUIT":
            //Retourne la Liste des Articles de la Boutique
            $Produit=new xProduit($nabysy);
            $IdBoutique=$nabysy->MaBoutique->Id ;
            $Table=$nabysy->MaBoutique->DataBase.".".$Produit->Table ;

            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DataBase.".".$Produit->Table ;
            }
            if (isset($PARAM['IDBOUTIQUE'])){
                $IdBoutique=$PARAM['IDBOUTIQUE'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DataBase.".".$Produit->Table ;
            }
            $NbCrit=0 ;
            $TxCritere="" ;
            $TxOr="";

            $TxSQL="select * from ".$Table." where id>0 " ;
            if (isset($PARAM['DESIGNATION'])){
                $NbCrit ++;
                if ($NbCrit>1){
                    $TxOr=" OR ";
                }
                $TxCritere .=$TxOr ." nom like '%".$PARAM['DESIGNATION']."%' " ;
            }
            if (isset($PARAM['CODEBAR'])){
                $NbCrit ++;
                if ($NbCrit>1){
                    $TxOr=" OR ";
                }
                $TxCritere .=$TxOr ." code like '".$PARAM['CODEBAR']."' or id like '".$PARAM['CODEBAR']."' " ;
            }

            if ($NbCrit>0){
                $TxSQL .=" and ( ".$TxCritere.") " ;
            }            

            $Rep=$nabysy->ReadWrite($TxSQL) ;
            $Liste=array();
            if ($Rep)			{
                while ($RW=$Rep->fetch_assoc()){
                    $Liste[]=$nabysy->utf8ize($RW) ;
                }
            }
            $json=json_encode($nabysy->utf8ize($Liste)) ;
            if (!$json){
                RetourneJsonError($nabysy->GetJsonError());
                exit ;
            }
            echo $json ;
            
            break ;

        case "LISTE_USER":
            $IdBoutique=null;
            $Bout=$nabysy->MaBoutique ;
            
            if (isset($_REQUEST["IDBOUTIQUE"])){
                $IdBoutique=$_REQUEST["IDBOUTIQUE"] ;
                if ($nabysy->MaBoutique->Id != $IdBoutique){
                    $Bout= new xBoutique($nabysy, $IdBoutique) ; //$nabysy->GetBoutiqueFromCache($IdBoutique);
                    if (isset($Bout)){
                        $nabysy->MaBoutique=$Bout ;
                    }
                }
            }
            if (!isset($IdBoutique)){
                $Bout=$nabysy->MaBoutique ;
            }

            $Table=$Bout->DataBase.".".$nabysy->User->Table ;
            $TxSQL="select * from ".$Table." order by login " ;
            $Rep=$nabysy->ReadWrite($TxSQL) ;
            if ($Rep->num_rows>0){
                //$RW=$Rep->fetch_assoc() ;
                while ($RW=$Rep->fetch_assoc()){
                    $Liste[]=$nabysy->utf8ize($RW) ;
                }
                $json=json_encode($nabysy->utf8ize($Liste)) ;
            }else{
                $Err=new xErreur;
                $Err->TxErreur="Aucun utilisateur trouvé.";
                $Err->Source="boutique_action.php" ;
                $Err->OK=0 ;
                $json=json_encode($Err) ;
            }			
            echo $json ;

            break;
        case "ETS_SAVE_ENTETE_A4": //
            $Rep=new xNotification() ;
            $Rep->OK=0 ;
            if(N::getInstance()->User->NiveauAcces < 4){
                $Rep->TxErreur="Accès refusé. Niveau d'accès insuffisant pour effectuer cette opération." ;
                $Rep->SendAsJSON();
                exit ;
            }
            $ChampFichier='fichier' ;
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }
            if(N::getInstance()->MaBoutique->Id>0){
                $Rep=N::getInstance()->MaBoutique->SaveEnteteA4($ChampFichier) ;
                N::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête A4:".json_encode($Rep) );
            }else{
                $Rep->TxErreur="Aucune configuration trouvée pour l'enregistrement de l'entête A4." ;
            }
            $Rep->SendAsJSON();

        case "ETS_SAVE_ENTETE_TICKET": //
            $Rep=new xNotification() ;
            $Rep->OK=0 ;
            if(N::getInstance()->User->NiveauAcces < 4){
                $Rep->TxErreur="Accès refusé. Niveau d'accès insuffisant pour effectuer cette opération." ;
                $Rep->SendAsJSON();
                exit ;
            }
            $ChampFichier='fichier' ;
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }
            if(N::getInstance()->MaBoutique->Id>0){
                $Rep=N::getInstance()->MaBoutique->SaveLogoTicket($ChampFichier) ;
                N::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête Logo Ticket:".json_encode($Rep) );
            }else{
                $Rep->TxErreur="Aucune configuration trouvée pour l'enregistrement de l'entête Logo Ticket." ;
            }
            $Rep->SendAsJSON();

		default:
			//Retourne();	
			break;
    }

// function Retourne($lien=null){
//     $Err=new xErreur;
//     $Err->TxErreur="Go back.";
//     $Err->Source="boutique_action" ;
//     $Err->OK=0 ;
//     $json=json_encode($Err) ;
//     echo $json ;
// }

function RetourneJsonError($TxErr=''){
    if ($TxErr==''){
        $TxErr='Erreur non précisée';
    }
    $Err=new xErreur;
    $Err->OK=0;
    $Err->TxErreur = $TxErr;
    $json=json_encode($Err) ;
    echo $json ;
}


?>