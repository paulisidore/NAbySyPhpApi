<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Facture\Impression\xFactureA4;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Stock\xProduit;
use NAbySy\Lib\ModuleExterne\TechnoWEB\xTechnoWEB;
use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\Lib\ModulePaie\xNAbySyWaveNetwork;
use NAbySy\ORM\xORMHelper;
use NAbySy\xDB;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;
use NAbySy\xNotification;

$nabysy = xNAbySyGS::getInstance() ;
$Reponse = new xNotification();

switch ($action){
        case 'ETS_GETINFOS': //Retourne les information personnelle de l'entreprise cliente
            $IdBout=xNAbySyGS::getInstance()->MaBoutique->Id;
            $nabysy->AutorisationCORS();
            if (isset($PARAM['ID'])){
                $IdBout=(int)$PARAM['ID'] ;
            }
            if (isset($PARAM['IDBOUTIQUE'])){
                $IdBout=(int)$PARAM['IDBOUTIQUE'] ;
            }
            //echo(xNAbySyGS::getInstance()->MaBoutique->Nom);exit;
            $Bout=xNAbySyGS::getInstance()->MaBoutique ; // new xBoutique($nabysy,$IdBout,xNAbySyGS::GLOBAL_AUTO_CREATE_DBTABLE);
            if(xNAbySyGS::$TECHNOWEB_ACTIVE && isset(xNAbySyGS::$TechnoWEBClient)){
                $Bout->SupportArticlePhotos = (int)xNAbySyGS::$TechnoWEBClient->SupportArticlePhotos ;
                $Bout->Nom = xNAbySyGS::$TechnoWEBClient->RaisonSocial ;
            }
            
            if($Bout->SupportArticlePhotos=='' && $Bout->Id>0){
                $Bout->SupportArticlePhotos = 0; //Par defaut les photos d'article ne seront pas authorisé
                $Bout->Enregistrer();
            }
            if($Bout->Id==0 && !xNAbySyGS::$TECHNOWEB_ACTIVE ){
                $Err=new xErreur();
                $Err->Autres = $IdBout ;
                $Err->TxErreur="Information du client PAM introuvable !";
                $Err->SendAsJSON();
            }
            
            $Reponse = new xNotification ;
            $Reponse->OK=1;
            $rw = $Bout->ToArrayAssoc();

            
            //$rw['DBASE'] = $Bout->DBname;
            //xNAbySyGS::getInstance()::$Log->AddToLog("Boutique trouvée: ".json_encode($rw));

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
            $CanSupportPhoto = $rw['SupportArticlePhotos'] ?? 0;
            $rw['LOGO_TICKET'] = $lien_logo ;
            $rw['SupportArticlePhotos'] = (int)$CanSupportPhoto;
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
            foreach ($rw as $key => $value) {
                $rw[$key] = xNAbySyGS::EscapedForJSON($value);
            }
            $Reponse->Contenue = $rw ;

            //On ajoute éventuellement les donnée de renouvellement Abonnement TechnoWeb
            if(xNAbySyGS::$TECHNOWEB_ACTIVE && isset(xNAbySyGS::$TechnoWEBClient)){
                $Billing = xNAbySyGS::$TechnoWEBMgr::GetClientBillingInfos(xNAbySyGS::$TechnoWEBClient);
                $BillOK = xNAbySyGS::$TechnoWEBMgr::BillingIsOK(xNAbySyGS::$TechnoWEBClient);

                $MtAbon=xNAbySyGS::$TechnoWEBMgr::GetMontantAbonnement(xNAbySyGS::$TechnoWEBClient);
                $Montant = $MtAbon;
                $TotalTVA = xNAbySyGS::$TechnoWEBMgr::GetMontantTVA($MtAbon);
                if($TotalTVA != 0){
                    //On prends en charge la TVA
                    $TauxTVA = xNAbySyGS::$TechnoWEBMgr::GetTauxTVA(xNAbySyGS::$TechnoWEBClient);
                    $Montant += $TotalTVA;
                }else{
                    $TotalTVA=0;
                    $TauxTVA=0;
                }

                $Reponse->Contenue['bill'] = [];
                $Reponse->Contenue['bill']['active'] = $BillOK ? 1 : 0 ;
                $Reponse->Contenue['bill']['infos'] = $Billing->ToObject() ;
                $Reponse->Contenue['bill']['tarifs']['MontantHT'] = $MtAbon ;
                $Reponse->Contenue['bill']['tarifs']['Montant'] = $Montant ;
                $Reponse->Contenue['bill']['tarifs']['TauxTVA'] = $TauxTVA ;
                $Reponse->Contenue['bill']['tarifs']['TotalTVA'] = $TotalTVA ;
                $Reponse->Contenue['bill']['tarifs']['Type'] = "ABONNEMENT";
                $Reponse->Contenue['bill']['tarifs']['Duree'] = xNAbySyGS::$TechnoWEBMgr::GetDureeAbonnement(xNAbySyGS::$TechnoWEBClient);
                //On va ajouter la liste des méthodes de paiement et leurs Handles
                $Reponse->Contenue['bill']['tarifs']['methodepaies']=[];
                if(count(xNAbySyGS::$ListeModulePaiement)){
                    foreach(self::$ListeModulePaiement as $Mod){
                        try{
                            if ($Mod instanceof IModulePaieManager){
                                if ($Mod->HandleModuleName() != ""){
                                    $Meth=[
                                        "Nom" => $Mod->UIName() ,
                                        "Description" => $Mod->Description(),
                                        "HandleName" => $Mod->HandleModuleName(),
                                        "Logo" => $Mod->LogoURL(),
                                    ];
                                   $Reponse->Contenue['bill']['tarifs']['methodepaies'][] = $Meth ;
                                }
                            }
                        }
                        catch (Exception $ex){

                        }
                    }
                }

            }
            $Reponse->SendAsJSON();
            //echo json_encode($Reponse);
            exit;
            break;
        
        case 'ETS_CONFIG_SAVE': //Retourne une configuration
            $IdConfig=1;
            $NewConfig=false;
            $IdBout=xNAbySyGS::getInstance()->MaBoutique->Id;
            if(isset($_REQUEST['ID'])){
                if ((int)($_REQUEST['ID'])){
                    $IdConfig = (int)$_REQUEST['ID'];
                }
            }

            /* if(isset($PARAM['IDTECHNOWEB'])){
                if(trim($PARAM['IDTECHNOWEB']) !==''){
                    if(isset(xNAbySyGS::$TechnoWEBMgr)){
                        $ClientTechnoWeb=xNAbySyGS::$TechnoWEBMgr->GetClientTechnoWeb($PARAM['IDTECHNOWEB']);
                        if($ClientTechnoWeb){
                            $IdBTrouve=null;
                            if($ClientTechnoWeb->ServiceDB == xNAbySyGS::getInstance()->MaBoutique->DBName ){
                                $IdBout=xNAbySyGS::getInstance()->MaBoutique->Id;
                                $IdBTrouve = $IdBout ;
                            }else{
                                $IdBout = $ClientTechnoWeb->Id ;
                                $Critere="<p>DBName like '".$ClientTechnoWeb->ServiceDB."' " ;
                            
                                //echo "DB Recherché = ".$Critere . "</p>";
                                foreach (xNAbySyGS::getInstance()::$ListeBoutique as $BoutX) {
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
            } */
            if(xNAbySyGS::$TECHNOWEB_ACTIVE){
                $Bout = xNAbySyGS::getInstance()->MaBoutique;
                $Param=xNAbySyGS::getInstance()->Parametre ;
            }else{
                $Bout=new xBoutique($nabysy,$IdBout,xNAbySyGS::GLOBAL_AUTO_CREATE_DBTABLE);
                $Param=new xORMHelper($nabysy,$IdConfig,xNAbySyGS::GLOBAL_AUTO_CREATE_DBTABLE,"parametre", $Bout->DBName);
            }
            if($Bout->Id==0 && !xNAbySyGS::$TECHNOWEB_ACTIVE){
                $Err=new xErreur();
                $Err->Autres = $IdBout ;
                $Err->TxErreur="Information du client PAM introuvable !";
                $Err->SendAsJSON();
            }
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
            //$MySQL=new xDB($Param->Main);
            //$MySQL->DebugMode=false;
            $ListeChampIntrouvable=[];
            $ListeVariable=$_REQUEST;
            if(isset($ListeVariable['Config'])){
                $ListeVariable = json_decode($ListeVariable['Config'],true);
            }

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
                        if ($Param->ChampsExisteInTable($Champ)){
                            //$Param->AddToLog(__FILE__.":".__LINE__." Champ existant: ".$Champ."=".$Valeur);
                            $Param->$Champ=$Valeur;
                            $YouCanSave=true;
                            if($Bout->ChampsExisteInTable($Champ)){
                                $Bout->$Champ = $Valeur ;
                                $YouCanSaveBout = true;
                            }
                        }elseif(!xNAbySyGS::$TECHNOWEB_ACTIVE && $Bout->ChampsExisteInTable($Champ)){
                            $Bout->$Champ = $Valeur ;
                            $YouCanSaveBout = true;
                        }elseif(xNAbySyGS::$TECHNOWEB_ACTIVE && xNAbySyGS::$TechnoWEBClient){
                            $Bout->$Champ = $Valeur ;
                            //var_dump("Champ introuvable dans la base du client TechnoWeb: ".$Champ." Valeur: ".$Valeur);
                            $YouCanSaveBout = false;
                        }
                        else{
                            $ListeChampIntrouvable[]=$Champ;
                        }
                    }
                }
            }

            if ($YouCanSave){            
                //echo($Param->ToJSON());
                //exit;
                $Param->AutoCreate=true;
                if ($Param->Enregistrer()){
                    if ($NewConfig){
                        $Param->AddToJournal("PARAMETRE","Enregistrement d'un nouveau paramètre. IdParam = ".$Param->Id) ;
                    }
                }else{
                    $Param->AddToLog(__FILE__.":".__LINE__.": Param Err.".json_encode($Param->ToJSON()));
                }
            }

            if(!xNAbySyGS::$TECHNOWEB_ACTIVE && $YouCanSaveBout && $Bout->Id>0){
                if($Bout->Enregistrer()){
                    $Bout->AddToJournal("PARAMETRE-BOUTIQUE","Mise à jour des paramètres pour la boutique ".$Bout->Nom) ;
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
                if($CanSaveBout && $Bout->Id>0){
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
            $Reponse->Contenue=$Param->ToArrayAssoc();
            $Reponse->SendAsJSON();
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
            exit;
            break ;
        
        case "LISTE_PRODUIT":
            //Retourne la Liste des Articles de la Boutique
            $Produit=new xProduit($nabysy);
            $IdBoutique=$nabysy->MaBoutique->Id ;
            $Table=$nabysy->MaBoutique->DBName.".".$Produit->Table ;

            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DBName.".".$Produit->Table ;
            }
            if (isset($PARAM['IDBOUTIQUE'])){
                $IdBoutique=$PARAM['IDBOUTIQUE'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DBName.".".$Produit->Table ;
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
            if(xNAbySyGS::getInstance()->User->NiveauAcces < 4){
                $Rep->TxErreur="Accès refusé. Niveau d'accès insuffisant pour effectuer cette opération." ;
                $Rep->SendAsJSON();
                exit ;
            }
            $ChampFichier='fichier' ;
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }
            if(xNAbySyGS::getInstance()->MaBoutique->Id>0){
                $Rep=xNAbySyGS::getInstance()->MaBoutique->SaveEnteteA4($ChampFichier) ;
                xNAbySyGS::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête A4:".json_encode($Rep) );
            }elseif(xNAbySyGS::$TECHNOWEB_ACTIVE && xNAbySyGS::$TechnoWEBClient?->Id>0){
                xNAbySyGS::getInstance()->MaBoutique->Id=xNAbySyGS::$TechnoWEBClient->IDCLIENT;
                $Rep=xNAbySyGS::getInstance()->MaBoutique->SaveEnteteA4($ChampFichier) ;
                xNAbySyGS::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête A4:".json_encode($Rep) );
                xNAbySyGS::getInstance()->MaBoutique->Id=0;
            }else{
                $Rep->TxErreur="Aucune configuration trouvée pour l'enregistrement de l'entête A4." ;
            }
            $Rep->SendAsJSON();

        case "ETS_SAVE_ENTETE_TICKET": //
            $Rep=new xNotification() ;
            $Rep->OK=0 ;
            if(xNAbySyGS::getInstance()->User->NiveauAcces < 4){
                $Rep->TxErreur="Accès refusé. Niveau d'accès insuffisant pour effectuer cette opération." ;
                $Rep->SendAsJSON();
                exit ;
            }
            $ChampFichier='fichier' ;
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }
            if(xNAbySyGS::getInstance()->MaBoutique->Id>0){
                $Rep=xNAbySyGS::getInstance()->MaBoutique->SaveLogoTicket($ChampFichier) ;
                xNAbySyGS::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête Logo Ticket:".json_encode($Rep) );
            }elseif(xNAbySyGS::$TECHNOWEB_ACTIVE && xNAbySyGS::$TechnoWEBClient?->Id>0){
                xNAbySyGS::getInstance()->MaBoutique->Id=xNAbySyGS::$TechnoWEBClient->IDCLIENT;
                $Rep=xNAbySyGS::getInstance()->MaBoutique->SaveLogoTicket($ChampFichier) ;
                xNAbySyGS::getInstance()::$Log->Write(__FILE__." L".__LINE__." Réponse Enregistrement entête Logo Ticket:".json_encode($Rep) );
                 xNAbySyGS::getInstance()->MaBoutique->Id = 0;
            }else{
                $Rep->TxErreur="Aucune configuration trouvée pour l'enregistrement de l'entête Logo Ticket." ;
            }
            $Rep->SendAsJSON();

        case "ETS_PAIE_TECHNOWEB": //Renouvelle l'abonnement TechnoWEB
            $Reponse->OK=0;
            if(!xNAbySyGS::$TECHNOWEB_ACTIVE || !isset(xNAbySyGS::$TechnoWEBMgr)){
                $Err->TxErreur="Absence du module TechnoWEB";
                $Err->SendAsJSON();
            }

            $oBj = xNAbySyGS::$LastJsonObjectInBody ;
            if(!isset($oBj)){
                $Err->TxErreur = "Absence du corps de message.";
                $Err->SendAsJSON();
            }
            $IdClient = $oBj->idClient ?? null;
            if(!isset($IdClient)){
                $IdClient = $oBj->IdClient ?? null;
            }

            $Clt = xNAbySyGS::$TechnoWEBMgr::GetClientTechnoWebByID($IdClient);

            if($Clt->Id==0){
                $Err->TxErreur="Client introuvable ou non définit.";
                $Err->SendAsJSON();
            }

            if(!isset(xNAbySyGS::$TechnoWEBClient)){
                $Err->TxErreur="IdTechnoWEB ou CLient TechnoWEB non reconnue.";
                $Err->SendAsJSON();
            }
            if(xNAbySyGS::$TechnoWEBClient->Id !== $Clt->Id){
                $Err->TxErreur="IdTechnoWEB et IdCLient TechnoWEB ne correspondent pas.";
                $Err->SendAsJSON();
            }

            $HandleModName = $oBj->methode?->handleName ?? null ;
            $Mode = xNAbySyGS::getInstance()->GetModulePaie($HandleModName);
            if(!isset($Mode)){
                $Err->TxErreur="Méthode de paiement introuvable.";
                $Err->SendAsJSON();
            }

            $MethodeP = $Mode;
            
            //On prépare le ChekOut
            $Fact = new xVente(xNAbySyGS::getInstance(),null,true,"facture",xNAbySyGS::$TechnoWEBClient->DataBase);
            $Fact->IdClient = xNAbySyGS::$TechnoWEBClient->Id;
            $Fact->IdTechnoWEB = xNAbySyGS::$TechnoWEBClient->TechnoWEB_ID ;
            $Fact->DateFacture = date('Y-m-d H:i:s');
            $Fact->HeureFacture = date('H:i:s');
            $MtAbon=xNAbySyGS::$TechnoWEBMgr::GetMontantAbonnement(xNAbySyGS::$TechnoWEBClient);
            $Fact->Montant = xNAbySyGS::$TechnoWEBMgr::GetMontantTTC($MtAbon);

            if(xNAbySyGS::$TechnoWEBMgr::PriseEnChargeTVA(xNAbySyGS::$TechnoWEBClient)){
                //On prends en charge la TVA
                $Fact->TotalTVA = xNAbySyGS::$TechnoWEBMgr::GetMontantTVA($MtAbon);
                $Fact->TauxTVA = xNAbySyGS::$TechnoWEBMgr::GetTauxTVA(xNAbySyGS::$TechnoWEBClient);
            }else{
                $Fact->TotalTVA=0;
                $Fact->TauxTVA=0;
            }
            $Fact->TotalFacture = $Fact->Montant;
            //echo __FILE__.":".__LINE__." Montant Facture = ".$Fact->Montant." / TotalTVA = ".$Fact->TotalTVA." / TauxTVA = ".$Fact->TauxTVA." / TotalFacture = ".$Fact->TotalFacture."\n";exit;

            $Fact->DureeAbonnement = xNAbySyGS::$TechnoWEBMgr::GetDureeAbonnement(xNAbySyGS::$TechnoWEBClient);
            $Fact->PAYE = 'NON';
            
            $Fact->ModePaiement = $Mode->Nom();
            $Fact->ModeReglement = $Mode->UIName();
            $Fact->Enregistrer();

            $InfosCaisse = [];
            //Si abonnement pas encore expiré, on part ç partir de la fin de l'abonnement actuel si non à partir du momet ou ça sera payé
            $RefPanier =$Fact->Id."-UNI-".date('dmYHis');
            $InfosCaisse['IDFACTURE']=$Fact->Id;
            $InfosCaisse['REFFACTURE']=$Fact->Id;
            $InfosCaisse['ISGROUPE']=0;
            $InfosCaisse['IDCLIENT']=$Fact->IdClient;
            $InfosCaisse['MONTANT']=round($Fact->Montant, 0); //Pour permettre le paiement en CFA sans les centimes
            $InfosCaisse['CAISSE']=$Fact->Main->MODULE->MCP_CLIENT ;
            $InfosCaisse['CAISSIER']="SYSTEME";

            if (isset($PARAM['IDCAISSE'])){
                $InfosCaisse['IDCAISSE']=$PARAM['IDCAISSE'];
            }
            if (isset($PARAM['IDCAISSIER'])){
                $InfosCaisse['IDCAISSIER']=$PARAM['IDCAISSIER'];
            }
            $InfosCaisse['LISTEFACTURE'] = $Fact->Id ;

            $RefPanier = '';
            $TotalASolder = (int)$InfosCaisse['MONTANT'] ;
            $Reponse = $Mode->GetCheckOut($TotalASolder, $InfosCaisse);
            if(isset($_REQUEST['TRACKERID'])){
                $Reponse->Source = $_REQUEST['TRACKERID'];
            }
            if($Reponse->OK>0){
                $Reponse->Contenue = $Reponse->Autres;
                $Reponse->Autres = null;
            }
            $Reponse->SendAsJSON() ;
            break;

        case "TECHNOWEB_GET_LISTE_FACTURE": //Retourne la liste des factures TechnoWEB
            $IdClient = $PARAM['IdClient'] ?? $PARAM['IDCLIENT'] ?? $PARAM['idclient'] ??  null;
            if(!isset($IdClient)){
                $Err->TxErreur="IdClient introuvable.";
                $Err->SendAsJSON();
            }
            if(!xNAbySyGS::$TECHNOWEB_ACTIVE || !isset(xNAbySyGS::$TechnoWEBMgr)){
                $Err->TxErreur="Absence du module TechnoWEB";
                $Err->SendAsJSON();
            }

            $Clt = xNAbySyGS::$TechnoWEBMgr::GetClientTechnoWebByID($IdClient);
            if($Clt->Id==0){
                $Err->TxErreur="Client introuvable ou non définit.";
                $Err->SendAsJSON();
            }

            $ListeFacture = xNAbySyGS::$TechnoWEBMgr::GetListeFacture($Clt);
            $Reponse = new xNotification();
            $Reponse->OK=1;
            $Reponse->Contenue=$ListeFacture;
            $Reponse->SendAsJSON();
            break;

        case "PAIEMENT_REUSSIT": //EN cas de reussite d'un paiement
            $PARAM['SHOW_HTML']=1;
            include "./paiementwave_ok.php";
            exit;
        
        case "PAIEMENT_ERREUR": //En cas d'erreur de paiement
            $PARAM['SHOW_HTML']=1;
            include "./paiementwave_err.php";
            exit;

        case "TECHNOWEB_PRINT_PAIEMENTA4": //Imprime le reçus de paiement TechnoWEB
            $IdFacture=null;
            $IdDemande = $PARAM['IdDemande'] ?? null;
            $HandleModName = $PARAM["HandleName"] ?? null ;
            $Mode = xNAbySyGS::getInstance()->GetModulePaie($HandleModName);
            if(!isset($Mode)){
                $Err->TxErreur="Méthode de paiement introuvable.";
                $Err->SendAsJSON();
            }
            
            $Demande = $Mode->GetCheckOutByID( $IdDemande);
            if( !isset($Demande) || $Demande->Id==0){
                $Err->TxErreur="Demande de Paiement No.".(int)$IdDemande." introuvable !";
                $Err->SendAsJSON();
            }
            $ListeIdFacture=[];
            if((int)$Demande->IsGroupe > 0){
                $ListeIdFacture = explode(",", $Demande->LISTEFACTURE);
            }else {
                $ListeIdFacture[]=$Demande->REFFACTURE ;
            }
            
            if (count($ListeIdFacture) == 0){
                $Err->TxErreur="Aucune facture ratachée au paiement.";
                $Err->SendAsJSON();
            }
            $IdFacture = (int)$Demande->REFFACTURE ;
            $FactureA4=new xFactureA4 (xNAbySyGS::getInstance(),$IdFacture);
            if ($FactureA4->IdFacture>0){
                $FactureA4->ImprimeFacture(null,"clientmaj");
                exit;
            }else{
                $Err=new xErreur;
                $Err->TxErreur="Facture introuvable !!!";
                $Err->OK=0;
                echo json_encode($Err);
            }

            $Reponse->Extra ="Préparation du document PDF";
            $Reponse->OK=1;
            $Reponse->SendAsJSON();
            /**
             * On va creer une function comme celui des factures pour générer le doc Pdf en regroupant eventuellement
             * Les différent moratoir/machine
             */

            exit;


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