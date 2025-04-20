<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\CodeBar\xCodeBarEAN13;
use NAbySy\GS\Facture\xProforma;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Panier\xArticlePanier;
use NAbySy\GS\Panier\xCart;
use NAbySy\GS\Panier\xCartProForma;
use NAbySy\GS\Stock\xProduit;
use NAbySy\GS\Stock\xProduitNC;
use NAbySy\Lib\BonAchat\IBonAchatManager;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;

    //include_once './nabysy_action.php';
    $IdPanier=null ;
    $IsProforma=false;
    if (isset($PARAM['IsProforma'])){
        $IsProforma=true ;
    }

    if (isset($PARAM['IdPanier'])){
		$IdPanier=$PARAM['IdPanier'] ;
	}
	
    $Panier=new xCart($nabysy->MaBoutique,$IdPanier);
	if ($IsProforma){
        $Panier=new  xCartProForma($nabysy->MaBoutique,$IdPanier);
	}
    //var_dump($action);
    switch ($action){
		case "GET_FACTURE":
			$IdFacture=null ;
			if (isset($_REQUEST['IdFacture'])){
				$IdFacture=$_REQUEST['IdFacture'] ;
			}
			$Err=new xErreur ;
			if ($IdFacture){
				$Vente=new xVente($nabysy,$IdFacture) ;				
				if ($Vente->Id>0){
					//var_dump($Vente->ToJSON()) ;
					$Reponse=$Vente->DetailToJSON();
					echo $Reponse ;
				}else{
					$Err->OK=0;
					$Err->TxErreur='Impossible de trouver la facture n°'.$IdFacture;
					$Err->Source='panier_action.php' ;
					$Reponse=json_encode($Err);
					echo $Reponse ;
				}
				exit;
			}
			$DateDeb=null;
			$DateFin=null;
			$Critere="" ;
			if (isset($_REQUEST['DATEDEBUT'])){
				$DateDeb=$_REQUEST['DATEDEBUT'] ;
				$DateD=new DateTime($DateDeb);
				if (isset($_REQUEST['DATEFIN'])){
					$DateFin=$_REQUEST['DATEFIN'] ;
					$DateF=new DateTime($DateFin);
				}
				if ($DateD !== false && $DateF !== false){
					$Critere .=" DATEFACTURE >='".$DateD->format('Y-m-d')."' and DATEFACTURE <='".$DateF->format('Y-m-d')."' ";
				}elseif ($DateD !== false ){
					$Critere .=" DATEFACTURE ='".$DateD->format('Y-m-d')."' ";
				}
			}
			$Vente=new xVente($nabysy) ;
			$Lst=$Vente->ChargeListe($Critere);
			$Reponse="";
			if ($Lst){
				$Rep=[];
				while ($row=$Lst->fetch_assoc()){
					$Rep[]=$row ;
				}
				$Reponse=json_encode($Rep);
			}
			echo $Reponse ;			

			exit;
		
		case "IMPRESSION_A4":
			$IdFacture=null ;
			$NomClient=null;
			
			if (isset($_REQUEST['IdFacture'])){
				$IdFacture=$_REQUEST['IdFacture'] ;
			}

			if (isset($_REQUEST['NomClient'])){
				$NomClient = $_REQUEST['NomClient'];
			}
			if (isset($_REQUEST['PrenomClient'])){
				$NomClient .=' '.$_REQUEST['PrenomClient'];
			}

			$Err=new xErreur ;
			$Vente=new xVente($nabysy,$IdFacture) ;
			if ($Vente->Id>0){
				if (isset($NomClient)){
					$Vente->NomBeneficiaire=$NomClient ;
					$Vente->Enregistrer();
				}
				$httpX='http://' ;
				if (isset($_SERVER['HTTPS'])){
					$httpX='https://';
				}
				//var_dump($_SERVER['SERVER_NAME']);
				$Lien=$httpX.$_SERVER['SERVER_NAME'].'/gs_api.php?Action=PRINT_FACTA4&Id='.$IdFacture.'&Token='.$nabysy->UserToken ;
				echo $Lien ;

			}else{
				$Err->OK=0;
				$Err->TxErreur='Impossible de trouver la facture n°'.$IdFacture;
				$Err->Source='panier_action.php' ;
				$Reponse=json_encode($Err);
				echo $Reponse ;
			}

			exit;

		
		case "SAVE_PANIER":
			$Contenu=null;
			$IdClient=null;
			$TotalReduction=0;
			$TotalRemise=null;
			$Err->TxErreur="";

			if (isset($_REQUEST['IdClient'])){				
				if ($_REQUEST['IdClient']>0){
					$IdClient=$_REQUEST['IdClient'] ;					
					$Client=new xClient($Boutique,$IdClient) ;
					$Panier->Client=$Client ;
					$Panier->IdClient=$IdClient;
					//var_dump($Panier->Client->Id);
				}				
			}

			if (isset($_REQUEST['Contenue'])){
				$Contenu=json_decode($_REQUEST['Contenue']) ;
                //var_dump($Contenu);
				if (isset($Contenu)){
					$ListeArticle=$Contenu->ListeArticle ;
				}
                //var_dump($ListeArticle);
				//exit;
			}
			$Grossiste=false;
			if (isset($PARAM['Grossiste'])){				
				if ($PARAM['Grossiste']=='true'){
					$Grossiste=true ;
				}else{
					$Grossiste=false ;
				}				
			}

			if (isset($PARAM['MontantVerse'])){				
				if ((int)$PARAM['MontantVerse'] !== 0){
					$Panier->MontantVerse=(int)$PARAM['MontantVerse'] ;
				}
			}
			if (isset($PARAM['MontantRendu'])){				
				if ((int)$PARAM['MontantRendu'] !== 0){
					$Panier->MontantRendu=(int)$PARAM['MontantRendu'] ;
				}
			}
			$Panier->TotalReduction=0;
			//var_dump($Panier->TotalReduction );
			foreach ($Contenu->ListeArticle as $Art){
				if ($Art->Id == -1 && $Art->IsPdtClown>0){
					$Pdt=new xProduitNC($nabysy,null,N::GLOBAL_AUTO_CREATE_DBTABLE,null,null,$Art->CodeBar);
				}else{
					$Pdt=new xProduit($nabysy,$Art->Id,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null, $nabysy->MaBoutique) ;
				}				
                //var_dump(get_class($Pdt));
				$TypeVenteParDefaut=1 ;
				if ($Art->VENTEDETAILLEE =='false' || $Art->VENTEDETAILLEE =='False' || $Art->VENTEDETAILLEE =='0'){
					$TypeVenteParDefaut=0 ;
				}
				$NewArticle=new xArticlePanier($nabysy,$Pdt->Id,$Art->Qte,$TypeVenteParDefaut,$nabysy->MaBoutique,$Art->CodeBar) ;
				//var_dump($NewArticle);
				if ($NewArticle){	
					$Modif=false ;					
					if ($Art->Id >0 && !$Art->IsPdtClown ){
						if ($Panier->PdtExiste($Pdt->Id,$TypeVenteParDefaut)){
							//'On modifie la quantité'
							$Modif=false ;
						}
					}											
					
					if (!$Art->IsPdtClown){
						/*Si Boutique avec Prix Calculé et non Grossiste */
						if ($nabysy->MaBoutique->AutoCalculPV>0){
							if (!$Grossiste){
								//echo ' </br>Je calcul le Prix de Vente: ' ;
								$EnPlus=$NewArticle->PrixU * round(($nabysy->MaBoutique->TauxPV /100),0) ;
								$vEnPlus=($NewArticle->PrixU*($nabysy->MaBoutique->TauxPV /100)) ;
								$EnPlus=(int)round($vEnPlus,0,PHP_ROUND_HALF_UP) ;
								//echo ' </br>Le Surplus de '.$nabysy->MaBoutique->TauxPV.'% ='.$EnPlus.' </br>' ;
								$NewArticle->PrixU +=$EnPlus ;
							}
						}	
					}
					
					//var_dump($NewArticle);
					//echo ' </br>Article Prix de Vente: '. $NewArticle->PrixU." Pour le TypeVente=".$TypeVenteParDefaut." Vente Grossiste=".$Grossiste ;
					/* 	---------------------------------------------------- */
					$Rep=$Panier->addProduct($NewArticle->IdProduit,$NewArticle->Nom,$NewArticle->Qte,$NewArticle->PrixU,$NewArticle->TypeVente,$Panier->IdClient,$Modif,false,$NewArticle->CodeBar) ;
					if ($Rep != true ){
						$TxErreur=$Rep ;
						echo $TxErreur ;
						exit ;
					}					
													
				}

			}			

						
            //On vérifie les Modules de Reduction			
            if (isset($PARAM['REMISE'])){
                //Il y a des remises
                $ListeRemise=json_decode($PARAM['REMISE'],true);
				
                $TotalRemise=(int)$Panier->TotalRemise ;
				//var_dump($TotalRemise);
                foreach($ListeRemise as $Remise){
					//var_dump($Remise);
					//foreach ($Rem as $Remise){
						
						if (isset($Remise['MontantRemise'])){
							$TotalRemise +=(int)$Remise['MontantRemise'];
							$Panier->NomBeneficiaireRemise=$Remise['NOMBENEFICIAIRE'] ;
						}else{
							//var_dump($Remise);
						}
					//}
					//var_dump($TotalRemise);
					//exit;
                }
                $Panier->TotalRemise=$TotalRemise;
            }

            $ListeModCallBack=[]; //Cette liste de module sera executée une fois la facture validée
			$ListeBonAchat=null; //Retient la liste des bons d'achat utilisé
			$ListeMethodePaie=[] ; //Liste des Méthodes de Paiement Utilisée.
			$ListeMobilePaieCheckOut=[]; //Liste des checkOut par module de paiement


            if (isset($PARAM['BONACHAT'])){
                //Il y a des bons d'achat
                $ListeBonAchat=json_decode($PARAM['BONACHAT'],true);
                $TotalReduction=(int)$Panier->TotalReduction ;
				//var_dump($nabysy::$ListeModuleBonAchat);
				//var_dump($PARAM);
				//var_dump($ListeBonAchat);
				if (is_array($ListeBonAchat)){
					//var_dump("ListeBonAchat: BON ACHAT est un Tableau");
				}

				if (is_array($ListeBonAchat)){
					if (count($ListeBonAchat)){
						foreach ($ListeBonAchat as $BonAchat){
							//On appel le module du Bon d'Achat Concerné pour la mise a jour des historiques et des Soldes
							//var_dump($BonAchat);
							if (isset($BonAchat['MODULE'])){
								$NomClass=$BonAchat['MODULE'];
								if (!$nabysy->PresenceBonAchatModule($NomClass)){
									$Err->TxErreur="Le module de Bon d'Achat ".$NomClass. " n'est pas diponible en ce moment. Contactez votre administrateur svp." ;
									$Rep=json_encode($Err);
									echo $Rep;
									exit;
								}
								try {
									//var_dump($BonAchat);
									foreach ($nabysy::$ListeModuleBonAchat as $ModBonAchat){
										//var_dump($ModBonAchat);
										if ($ModBonAchat instanceof IBonAchatManager){
											//var_dump($BonAchat['MontantBon']);
											if (isset($ModBonAchat)){
												$rep=$ModBonAchat->AutoriseTransaction($BonAchat,$Panier);
												if ($rep==false){
													$Err->TxErreur="Transaction non autorisée par le Module de Bon d'Achat ".$ModBonAchat->Nom();
													$Err->Source=$NomClass;
													$LastError=$ModBonAchat->Nom().": ".$ModBonAchat->LastError();
													if ($LastError !==""){
														$Err->TxErreur=$LastError ;
													}
													$Rep=json_encode($Err);
													echo $Rep;
													exit;
													//break;
												}else{
													$ListeModCallBack[]=$ModBonAchat;
													$TotalReduction +=$BonAchat['MontantBon'];
												}
											}
										}else{
											var_dump(gettype($ModBonAchat));
										}
									}
									
								}catch(Exception $ex){

								}
								
								
							}
							
						}
						//var_dump("Je Suis ici Ligne ".__LINE__);
						//exit;
						
					}
					$Panier->TotalReduction=$TotalReduction;
				}
            }

			//var_dump($Panier->TotalReduction);

			if (isset($PARAM['MODULE-PAIEMENT'])){
				//echo $PARAM['MODULE-PAIEMENT'];
				
				try {
					$ListeM=json_decode($PARAM['MODULE-PAIEMENT']);
					//var_dump($ListeM);
					foreach($ListeM as $xListeMethP){						
						//var_dump(isset($xListeMethP->TotalRemise));
						if (isset($xListeMethP->TotalRemise) && !isset($TotalRemise) ){
							//var_dump($xListeMethP->TotalRemise);
							$TotalRemise=(int)$xListeMethP->TotalRemise;
							$Panier->TotalRemise = $TotalRemise ;
						}
						foreach($xListeMethP->LISTE_MODULEPAIE as $xMethP){						
							$TotalReduction +=(int)$xMethP->MONTANT ;
							$Module=$nabysy->GetModulePaie($xMethP->HANDLE);
							if (isset($Module)){
								//var_dump($Module);
								$ListeMethodePaie[]=$Module ;
								$ListeMobilePaieCheckOut[]=$xMethP;
							}						
						}
					}
					
					$Panier->TotalReduction=$TotalReduction;
				}
				catch (Exception $ex){

				}
			}

			//var_dump($ListeModCallBack);
			$Liste=$Panier->GetList();
			//var_dump($Liste);
			//exit;

			if ($Liste){
				//On valide la vente 
				//var_dump($Panier->getTotalPriceCart());
				//exit ;
				$Vente=new xVente($nabysy) ;
				//var_dump($Panier->TotalReduction);
				if ((int)$Panier->MontantVerse ==0){
					$Panier->MontantVerse = $Panier->getTotalPriceCart() - (int)$Panier->TotalRemise - (int)$Panier->TotalReduction ;
				}
				//var_dump($Panier->MontantVerse);
				//var_dump($Panier->getTotalPriceCart());
				//var_dump($Panier->getTotalNetAPayer());

				$IdFacture=0;
				$ReponseID=$Vente->Valider($Panier) ;
				//var_dump($ReponseID);
				if (is_object($ReponseID)){
					if (get_class($ReponseID) !== "xErreur"){
						$IdFacture=$ReponseID;
					}else{
						//Erreur
						if ($ReponseID->OK>0){
							$IdFacture=$ReponseID->Extra;
						}else{
							echo json_encode($ReponseID);
							exit;
						}						
					}
				}else{
					$IdFacture=$ReponseID;
				}

				if ($IdFacture>0){
                    if (isset($ListeModCallBack)){
						if (count($ListeModCallBack)>0){
							foreach($ListeBonAchat as $LstBonAchat){
								//var_dump($LstBonAchat);
								//exit;
								$BonAchat=$LstBonAchat ;
								//foreach ($LstBonAchat as $BonAchat){
									//echo __FILE__." BonAchat: </br>";
									//var_dump($BonAchat);
									//exit;
									foreach ($ListeModCallBack as $ModBonAchat){							
										//Chaque Module de Bon d'achat validera de son coté pour gérer sa propre historique
										//var_dump($BonAchat);
										if ($ModBonAchat->UpDateFacture($IdFacture,$Panier,$BonAchat) == false){
											//On annule la facture ou bien ?
											$Err->TxErreur .=$ModBonAchat->Nom().": ".$ModBonAchat->LastError()."#";
										}										
									}
								//}
							}
						}						
                    }

					if (isset($ListeMethodePaie)){
						//var_dump($ListeMethodePaie);
						foreach($ListeMethodePaie as $MethP){
							try{
								$Ind=array_search($MethP,$ListeMethodePaie);
								if (isset($ListeMobilePaieCheckOut[$Ind])){
									$CheckOut=$ListeMobilePaieCheckOut[$Ind];
									//var_dump($CheckOut);
									$CheckOutArray=$CheckOut;
									if (is_object($CheckOutArray)){
										$CheckOutArray=(array)$CheckOut ;
									}									
									//var_dump($CheckOutArray);
									if ($MethP->UpDateFacture($IdFacture,$Panier,$CheckOutArray) == false){
										//On annule la facture ou bien ?
										$Err->TxErreur .=$MethP->Nom().": ".$MethP->LastError()."#";
									}
								}
								//exit;
							}catch(Exception $ex){

							}
							
						}
					}

					$Reponse=new xErreur;
					$Reponse->OK=1;
					$Reponse->Extra=$IdFacture ;
					$Reponse->Source="panier_action.php" ;
					if (isset($Err->TxErreur)){
						if ($Err->TxErreur !== ""){
							$Reponse->TxErreur=$Err->TxErreur;
						}
					}
					$retour=json_encode($Reponse) ;
					echo $retour ;
					$Panier->Vider() ;
					exit ;
				}

			}

			$json_liste=$Panier->GetListeJSON(null,true);
			echo $json_liste ;
			$Panier->Vider() ;
			exit ;
	
		case "GET_NEWPANIER" :
			/* Retourne le N° d'Un Nouveau Panier */
			//$Panier=new xCartProForma($nabysy->MaBoutique,0);
			//if (!$IsProforma){
				//print_r($nabysy->User);
				$Panier=new xCart($nabysy->MaBoutique,0);
				$Panier->IdCaissier=0 ;
				if (isset($_SESSION['IdUser'])){
					$Panier->IdCaissier=$_SESSION['IdUser'] ;
					$Panier->Caissier=$_SESSION['user'] ;
				}				
				$Panier->IdFacture=0;
				$Panier->IdClient=0;
				$Panier->MontantVerse=0;
				$Panier->MontantRendu=0;
				$Panier->HeureFacture=date("H:i:s") ;
				$Panier->DateFacture(date('d/m/Y'));

			//}
			$retour=$Panier->ToJSON() ; //json_encode($Panier->MaBoutique) ;
			echo $retour ;
			exit;
			
		case "LISTE_PANIER":
			$Grossiste=false;

			if (!isset($Panier)){
				return null ;
			}
			if (isset($PARAM['Grossiste'])){				
				if ($PARAM['Grossiste']=='true'){
					$Grossiste=true ;
				}else{
					$Grossiste=false ;
				}				
			}
			$TxGrossisteT="" ;
			if ($Grossiste){
				$TxGrossisteT="GrossisteT=1" ;
			}

			if ($PARAM['Reponse']=='JSON'){
				$json_liste=$Panier->GetListeJSON($TxGrossisteT);
				echo $json_liste ;
				exit ;
			}

            exit;
			
		case "AJOUT_PRODUIT":
			//Ajoute un produit au panier
			$TypeVenteParDefaut=1 ;
			$Grossiste=false;
			$Trouv=null ;
			$LaQte=1 ;
			if (isset($PARAM['quantite'])){
				$LaQte=$PARAM['quantite'] ;
			}
			
			if ($Panier->IdClient>0){
				$Grossiste=true ;
			}
			if ($nabysy->MaBoutique->IdCompteClient>0){
				$TypeVenteParDefaut=0;
			}
			if (isset($PARAM['TypeVente'])){
				$TypeVenteParDefaut=$PARAM['TypeVente'] ;
			}
			if (isset($PARAM['Grossiste'])){				
				if ($PARAM['Grossiste']=='true'){
					$Grossiste=true ;
				}else{
					$Grossiste=false ;
					//print_r($PARAM) ;
				}				
			}
			$Pdt=new xProduit($nabysy,null,false,null,$nabysy->MaBoutique) ;
			//Gestion de Lecteur de Code barre
			$AvecCodeBar=null;
			if (isset($PARAM['AvecCodeBar'])){
				$AvecCodeBar=true ;
			}

			if (isset($PARAM['CODEBAR'])){
				$CodeBar=$PARAM['CODEBAR'] ;
				$LaQte=1 ;
				if (isset($PARAM['quantite'])){
					$LaQte=$PARAM['quantite'] ;
				}
				if ($CodeBar !==''){
					$Pdt=new xProduit($nabysy,null,false,null,$nabysy->MaBoutique) ;
					$Trouv=$Pdt->GetProduit(null,null,null,null,$CodeBar);					
					if ($Trouv){
						if ($Trouv->num_rows==1){
							$IdPdt=$Pdt->Id ;		
							$Trouv=null ;	//Pour eviter de rechercher a nouveau dans la liste de choix
						}					
					}
				}							
			}
			
			
			if (isset($PARAM['nomrech'])){
				$Trouv=$Pdt->GetProduit(null,$PARAM['nomrech']);
				if ($Trouv){
					if ($Trouv->num_rows==1){
						$IdPdt=$Pdt->Id ;		
						$Trouv=null ;	//Pour eviter de rechercher a nouveau dans la liste de choix
					}						
				}				
			}

			if (isset($PARAM['IdPdt'])){
				$Pdt=new xProduit($nabysy,$PARAM['IdPdt'],$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$nabysy->MaBoutique) ;
				$IdPdt=$Pdt->Id;
				$Trouv=null ;	//Pour eviter de rechercher a nouveau dans la liste de choix
			}

			if (isset($Trouv)){				
				if ($Trouv->num_rows>1){
					//On affiche un tableau pour choisir le bon produit
					echo 'Trouv>1' ;
					return ;
				}
			}

			//var_dump($Pdt) ;

			if ($Pdt->Id >0){
				//On recherche éventuellement si l'article est déjà dans le panier
				$vId=$Pdt->Id."_".$TypeVenteParDefaut ;
				$QtePrec=0 ;
				if (isset($_SESSION['panier'.$Panier->Id][$vId])){
					//var_dump($_SESSION['panier'.$Panier->Id][$vId]) ;
				}
				if (isset($_SESSION['panier'.$Panier->Id][$vId])){
					$QtePrec=$_SESSION['panier'.$Panier->Id][$vId]['qte'] ;
				}			
				
				$NewArticle=new xArticlePanier($nabysy,$Pdt->Id,$LaQte,$TypeVenteParDefaut,$nabysy->MaBoutique) ;
				if ($NewArticle){	
					$Modif=false ;
					if (isset($PARAM['IsModification'])){
						//'On modifie la quantité'
						$Modif=true ;
					}else{
						if ($AvecCodeBar){
							if ($Panier->PdtExiste($Pdt->Id,$TypeVenteParDefaut)){
								//'On modifie la quantité'
								$Modif=false ;
							}
						}						
					}
					/*Si Boutique avec Prix Calculé et non Grossiste */
					if ($nabysy->MaBoutique->AutoCalculPV>0){
						if (!$Grossiste){
							//echo ' </br>Je calcul le Prix de Vente: ' ;
							$EnPlus=$NewArticle->PrixU * round(($nabysy->MaBoutique->TauxPV /100),0) ;
							$vEnPlus=($NewArticle->PrixU*($nabysy->MaBoutique->TauxPV /100)) ;
							$EnPlus=(int)round($vEnPlus,0,PHP_ROUND_HALF_UP) ;
							//echo ' </br>Le Surplus de '.$nabysy->MaBoutique->TauxPV.'% ='.$EnPlus.' </br>' ;
							$NewArticle->PrixU +=$EnPlus ;
						}
					}
					//var_dump($NewArticle);
					//echo ' </br>Article Prix de Vente: '. $NewArticle->PrixU." Pour le TypeVente=".$TypeVenteParDefaut." Vente Grossiste=".$Grossiste ;
					/* 	---------------------------------------------------- */
					$Rep=$Panier->addProduct($NewArticle->IdProduit,$NewArticle->Nom,$NewArticle->Qte,$NewArticle->PrixU,$NewArticle->TypeVente,$Panier->IdClient,$Modif) ;
					if ($Rep != true ){
						$TxErreur=$Rep ;
						echo $TxErreur ;
						exit ;
					}

					$TxGrossisteT="" ;
					if ($Grossiste){
						$TxGrossisteT="GrossisteT=1" ;
					}
					if (isset($PARAM['Reponse'])){
						if ($PARAM['Reponse']=='PanierHtml'){							
							$ListeHTML=$Panier->GetListePdtHTML($TxGrossisteT);
							echo $ListeHTML ;
							exit ;
						}
						if ($PARAM['Reponse']=='JSON'){
							$Liste=$Panier->GetList();
							$json_liste=$Panier->GetListeJSON($TxGrossisteT);
							echo $json_liste ;
							exit ;
						}
					}				
				}
				
			}
			//**************************************************************** */			
			if (isset($CallBack)){
				if ($CallBack !== 'SELF'){
					Retourne($CallBack) ;
				}else{					
					Retourne() ;
				}
			}
            exit;
			
		case "SUPP_PRODUIT":
			//Supprime un produit du Panier
			
			break;
		case "MODIF_PRODUIT":
			//Modifie un produit du Panier
			
			break;
		case "TRANSFORME_PROFORMA":
			//Charge une Proforma et la transforme dans un panier de Vente
			$IdBoutique=$nabysy->MaBoutique->Id ;
			if (isset($PARAM['IdBoutique'])){
				$IdBoutique=$PARAM['IdBoutique'] ;
			}
			$IdProforma=null ;
			if (isset($PARAM['IdProforma'])){
				$IdProforma=$PARAM['IdProforma'] ;
			}
			$IdClient=null ;
			$Client=null;
			if (isset($PARAM['IdClient'])){
				$IdClient=$PARAM['IdClient'] ;
			}
			$Bout=new xBoutique($nabysy,$IdBoutique) ;
			$Proforma=new xProforma($nabysy,$Bout) ;
			$PanierP=$Proforma->ChargerPanier($IdProforma,true) ;
			if (isset($IdClient)){
				$Client=new xClient($Bout->Main,$IdClient) ;
				if ($Client->Id>0){
					$PanierP->IdClient=$Client->Id ;
					$PanierP->SaveInfosClient($Client->Nom,$Client->Prenom,$Client->Id,$IdFacture=null,$DateFacture=null);
				}
			}
			$Reponse=new xErreur ;
			$Reponse->OK="0" ;
			$Reponse->TxErreur="Impossible de transformer la proforma n°".$IdProforma ;
			$Reponse->Extra="../liste_proforma.php" ;
			$Reponse->Source="panier_action.php" ;
			$PanierV=$PanierP->ToPanierVente() ;
			if (!isset($PanierV)){
				$retour=json_encode($Reponse) ;
			}else{
				if (isset($Client)){
					$PanierV->SaveInfosClient($Client->Nom,$Client->Prenom,$Client->Id,$IdFacture=null,$DateFacture=null);
				}
				$Reponse->OK="1";
				$Reponse->TxErreur="" ;
				$Reponse->Extra="../vues/vente.php?IdPanier=".$PanierV->Id ;
				$retour=json_encode($Reponse) ;
			}
			ob_clean();
			echo $retour ;
			exit ;
			
		case 'IMPRIME_CODEBAR' :
			$TypeVenteParDefaut=1 ;
			$Grossiste=true;
			$LaQte=1 ;
			if ($nabysy->MaBoutique->IdCompteClient>0){
				$TypeVenteParDefaut=0;
				$Grossiste=false ;
			}
			if (isset($PARAM['TypeVente'])){
				$TypeVenteParDefaut=$PARAM['TypeVente'] ;
			}
			if (isset($PARAM['Grossiste'])){				
				if ($PARAM['Grossiste']=='true'){
					$Grossiste=true ;
				}else{
					$Grossiste=false ;
					//print_r($PARAM) ;
				}				
			}
			$Pdt=new xProduit($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$nabysy->MaBoutique) ;
			//Gestion de Lecteur de Code barre
			$AvecCodeBar=null;
			if (isset($PARAM['AvecCodeBar'])){
				$AvecCodeBar=true ;
			}

			if (isset($PARAM['CODEBAR'])){
				$CodeBar=$PARAM['CODEBAR'] ;
				$LaQte=1 ;
				if (isset($PARAM['quantite'])){
					$LaQte=$PARAM['quantite'] ;
				}
				if ($CodeBar !==''){
					$Pdt=new xProduit($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$nabysy->MaBoutique) ;
					$Pdt->GetProduit(null,null,null,null,$CodeBar);
				}							
			}
			if (isset($PARAM['IdPdt'])){
				$Pdt=new xProduit($nabysy,$PARAM['IdPdt'],$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$nabysy->MaBoutique) ;
			}
			if (isset($PARAM['nomrech'])){
				$Trouv=$Pdt->GetProduit(null,$PARAM['nomrech']);
				if ($Trouv){
					$rw=$Trouv->fetch_assoc();
					$IdPdt=$rw['id'] ;
					$Pdt=new xProduit($nabysy,$IdPdt,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$nabysy->MaBoutique) ;
				}				
			}

			if ($Pdt->Id >0){				
				$Article=new xArticlePanier($nabysy,$Pdt->Id,$LaQte,$TypeVenteParDefaut,$nabysy->MaBoutique) ;
				if ($Article){	
					$Modif=false ;
					/*Si Boutique avec Prix Calculé et non Grossiste */
					if ($nabysy->MaBoutique->AutoCalculPV>0){
						if (!$Grossiste){
							//echo ' </br>Je calcul le Prix de Vente: ' ;
							$EnPlus=$Article->PrixU * round(($nabysy->MaBoutique->TauxPV /100),0) ;
							$vEnPlus=($Article->PrixU*($nabysy->MaBoutique->TauxPV /100)) ;
							$EnPlus=(int)round($vEnPlus,0,PHP_ROUND_HALF_UP) ;
							//echo ' </br>Le Surplus de '.$nabysy->MaBoutique->TauxPV.'% ='.$EnPlus.' </br>' ;
							$Article->PrixU +=$EnPlus ;
						}
					}
					
					//Lancement de la génération du CodeBarre
					$xCB=new xCodeBarEAN13($nabysy->MaBoutique)	;
					$McpCode=$xCB->GetMCPEAN13Code($Article->IdProduit) ;
					
					$xCB->ImprimeCodeBarEAN13($Pdt,$McpCode) ;
					exit ;
				}
				
			}
		    exit;

		case 'GET_ENTETE_TICKET':
			$NB_LIGNE=0;
			$Tab=$nabysy->MaBoutique->GetEnteteArray();			
			$Reponse=json_encode($Tab);
			if (substr($Reponse,0,1) !=='['){
				$Reponse="[".$Reponse."]" ;
			}
			echo $Reponse ;
            exit;

		case 'GET_PIED_TICKET':
			$PiedPage=$nabysy->MaBoutique->TicketTxPiedPage ;
			$Ret['PiedTicket']=$PiedPage ;			
			$Reponse=json_encode($Ret,JSON_FORCE_OBJECT);
			if (substr($Reponse,0,1) !=='['){
				$Reponse="[".$Reponse."]" ;
			}
			echo $Reponse ;
			exit;

		default:

		break;
	}


?>