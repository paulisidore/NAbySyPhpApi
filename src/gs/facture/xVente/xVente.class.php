<?php
namespace NAbySy\GS\Facture ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Panier\xCart;
use NAbySy\GS\Panier\xPanier;
use NAbySy\GS\Stock\xJournalCaisse;
use NAbySy\Lib\BonAchat\xBonAchatManager;
use NAbySy\ORM\xORMHelper;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;

include_once 'xDetailVente.class.php';

Class xVente extends xORMHelper
{

	public xClient $Client;	
	public xBoutique $MaBoutique ;

	public xDetailVente $DetailVente ;
	
	public function __construct(xNAbySyGS $NAbySyGS,?int $IdFacture=null,$AutoCreateTable=false,$TableName='facture',xBoutique $Boutique=null){
		$this->Main = $NAbySyGS ;
		$this->MaBoutique = $NAbySyGS->MaBoutique ;
		if (isset($Boutique)){
			$this->Main = $Boutique->Main ;
			$this->MaBoutique=$Boutique;
		}
		parent::__construct($NAbySyGS,$IdFacture,$AutoCreateTable,$TableName,$this->MaBoutique->DataBase) ;

		if(!$this->ChampsExisteInTable('REFCMD')) {
			$this->MySQL->AlterTable($this->Table, "REFCMD",'TEXT','ADD','',$this->DataBase);
		}

		$this->Client=new xClient($this->Main );
		//$this->DetailVente=new xDetailVente($this->Main,null,$AutoCreateTable,'detailfacture',$this->MaBoutique);
		if ($this->Id>0){
			$this->Client=new xClient($this->Main,$this->IdClient);
			$this->DetailVente=new xDetailVente($NAbySyGS,null,$AutoCreateTable,null,null,$this->Id);
		}
		
	}

	/**
	 * Retourne la liste des articles d'une facture ou uniquement une ligne
	 * @param int $Id : IdFacture
	 * @param int $IdDetail : Id de la ligne de facture
	 * @return array : Tableau assossiatif des données
	 */
	public function GetVente($Id=0,$IdDetail=0):array{
		if($Id==0){
			$Id = $this->Id;
		}
		//Permet de lire une vente par son Id ou IdDetail
		$LDetailVente=new xDetailVente($this->Main,$IdDetail,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,'detailfacture',$this->MaBoutique,$Id);
		return $LDetailVente->ListeProduits;
	}

	public function GetListeVente($DateDu=null,$DateAu=null,$IdCaissier=null,$AutreCritere=null, string $LimiteSansMotLimit=null){
		//Permet de lire une vente par son Id ou IdDetail
		$OK=false;
		$NbLigne=0;
		$sql  ="SELECT v.*, c.Nom as 'NomClt',c.Prenom as 'PrenomClt', U.Login as 'Caissier' from ".$this->Table." v 
				left outer join ".$this->Client->Table." c on c.id=v.IdClient
				left outer join utilisateur U on U.id=v.IdCaissier
				WHERE v.id > 0 ";
		$crit="" ;
		if (isset($DateDu)){
			$crit=" AND v.DateFacture='".$DateDu."' " ;
			if (isset($DateAu)){
				$crit=" AND (v.DateFacture>='".$DateDu."' AND v.DateFacture<='".$DateAu."') " ;
			}
		}
		if (isset($IdCaissier)){
			$crit=$crit." AND v.IdCaissier='".$IdCaissier."' " ;
		}
		if (isset($AutreCritere)){
			$crit=$crit." ".$AutreCritere ;
		}
		$ordre=" ORDER BY v.ID desc ";
		$limit="";
		if($LimiteSansMotLimit){
			$limit = " LIMIT ".$LimiteSansMotLimit;
		}
		$sql=$sql.$crit.$ordre.$limit ;
		//print($sql."</br>") ;

		$reponse=$this->Main->ReadWrite($sql) ;
		if (!$reponse)
			{
				echo $this->Main->MODULE->Nom."Erreur interne de lecture des ventes ...".$DateDu." - ".$DateAu." - ".$IdCaissier ;
			}
		return $reponse;
	}
	
	public function ChargerPanier($IdFacture,$IsTemp=null){
		//Charge depuis la base de donnée les produits de la facture IdFacture dans
		//le panier $Panier
		$liste=$this->GetVente($IdFacture);
		if (!$liste){
			return false ;
		}
		
		$NewPanier=$this->MaBoutique->GetNewPanier(null,$IsTemp) ;
		$IdVente=$IdFacture ;
		$NewPanier->IdFacture=$IdFacture ;
		$c=0 ;
		foreach ($liste as $row ){
			$c++;
			//echo '</pre>Ajout du produit '.$row['Designation'] ;
			if ($c == 1){
				$this->Client = new xClient($this->Main,$row['IdClient']) ;
				$this->IdClient=$row['IdClient'] ;
				$this->Caissier=$row['Caissier'] ;
				$this->Date=$row['DateFacture'] ;
				$this->Heure=$row['HeureFacture'] ;
				$this->TotalFacture=$row['TotalFacture'] ;
				$this->Id=$row['IdFacture'] ;
				$this->IdFacture=$IdVente ;
				$NewPanier->HeureFacture=$this->Heure ;
				$NewPanier->SaveInfosClient($row['Nom'],$row['Prenom'],$row['IdClient'],$row['IdFacture']) ;
				$NewPanier->DateFacture($this->Date);				
				$NewPanier->Dump() ;
			}
			//Charge chaque ligne de la vente dans le Panier
			$ok=false ;
			$Qte=$row['Qte'];
			$vQte=$row['Qte'];
			$QG=$row['VenteGros'];
			$IdPdt=$row['IdProduit'] ;
			$Designation=$row['Designation'] ;
			$PrixU=$row['PrixVente'] ;
			if ($QG > 0){
				$Qte=$Qte*$row['nbunite'];
			}
			
			$ok=$NewPanier->addProduct($IdPdt,$Designation,$vQte,$PrixU,$QG,$NewPanier->IdClient) ;
		}
				
		
		
		//var_dump($_SESSION[$NewPanier->PanierId]) ;
		$InfoC=$NewPanier->PanierId."CLIENT" ;
		//var_dump($_SESSION[$InfoC]) ;
		return $NewPanier ;
				
	}
	
	public function Valider(xCart $Panier){
		$Err=new xErreur;
		$Err->OK=0;
		$Err->TxErreur="Impossible de valider.";
		$Err->Source=__CLASS__ ;
		if (empty($Panier->getList())){
			$Err->TxErreur="Panier Vide";
			return $Err;
		}

		$TDetail=new xDetailVente($this->Main);
		$TxTableDet=$TDetail->Table ;
		
		if (!$this->MySQL->ChampsExiste($this->Table,'StockSuivant')){
			$this->MySQL->AlterTable($this->Table,'StockSuivant',"INT(11)","ADD","0");
		}
		if (!$this->MySQL->ChampsExiste($TDetail->Table,'StockSuivant')){
			$this->MySQL->AlterTable($TDetail->Table,'StockSuivant',"INT(11)","ADD","0");
		}
		/*
			Si IdFacture dans panier <=0 alors on creer une nouvelle facture
			si non il sagit d'une modification de facture
		*/
		$Tache="" ;

		if (isset($Panier->Client)){
			if ($Panier->Client->Id <=0){
				if ((float)$Panier->MontantVerse == 0){
					$Panier->MontantVerse=$Panier->getTotalNetAPayer() ;
				}
				
				if ($Panier->MontantVerse < $Panier->getTotalNetAPayer()){
					//Montant inferieur a la facture
					//echo "xVente Total V: ".$Panier->MontantVerse."</br>";
					//echo "xVente Total Total: ".$Panier->getTotalNetAPayer()."</br>";
					$Err->TxErreur="Montant versé incomplet pour solder la facture !" ;
					return $Err ;			
				}
				$Panier->MontantRendu=$Panier->MontantVerse - $Panier->getTotalNetAPayer();
			}
		}else{
				if ((float)$Panier->MontantVerse == 0){
					$Panier->MontantVerse=$Panier->getTotalNetAPayer() ;
				}
				//echo "Total Versé: ".$Panier->MontantVerse." et TotalNet: ".$Panier->getTotalNetAPayer();exit;

				if ($Panier->MontantVerse < $Panier->getTotalNetAPayer()){
					//Montant inferieur a la facture
					$Err->TxErreur="Montant versé incomplet pour solder la facture !!! ".$Panier->MontantVerse." <> ".$Panier->getTotalNetAPayer() ;
					return $Err ;		
				}
				$Panier->MontantRendu=$Panier->MontantVerse - $Panier->getTotalNetAPayer();
		}
		
		$Bout=null ;
		if ($Panier->MaBoutique->IdCompteClient==0){
			if ($Panier->IdClient >0){
				//Si client boutique on Le charge
				$Lst=$this->Main->MaBoutique->ChargeListe("IdCompteClient = ".$Panier->IdClient);
				if ($Lst->num_rows>0){
					$row=$Lst->fetch_assoc();
					$Bout=new xBoutique($this->Main,$row['Id']) ;
				}
			}
		}
		
		//var_dump($Panier->getList());

		if ($Panier->IdFacture >0){	//Une facture en modification
			$Tache="Modification de la facture numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			$PrecPanier=$this->ChargerPanier($Panier->IdFacture,true);	
			$cNote="Total Facture Precedant:".$PrecPanier->getTotalNetAPayer();			
			$this->MaBoutique->AddToJournal($Tache,$cNote) ;

			//On demandera aux modules de Bon d'Achat de mettre leurs informations sur la facture en cour de Modification
			$BonAchatMgr=new xBonAchatManager($this->Main);
			$BonAchatMgr->RollBackFacture($Panier->IdFacture);

			//On compare les ligne pour chaque produit du panier précédent
			//$PrecPanier->Dump() ;
			$PdtFacture=$Panier->getList() ;

			$PrecPdtFacture=$PrecPanier->getList() ;
			$this->SupprimerPanier($PrecPanier) ;
			$NbFois=0 ;
			
			$ListePdtOK=array() ;
			//exit ;
			//MAJ du Solde Client
			if ($Panier->IdClient > 0){
				//Si le Client est un Bon alors on corrige son solde
				if (!isset($Panier->Client)){
					$Client=new xClient($this->Main,$Panier->IdClient) ;
					$Panier->Client=$Client ;
				}
				$SoldePrec=$Panier->Client->Solde ;
				$Panier->Client->CrediterSolde($Panier->getTotalNetAPayer()) ;
				$Panier->Client->ChargeClient($Panier->IdClient) ;
				$SoldeSuiv=$Panier->Client->Solde ;
				$cTache="MISE A JOUR SOLDE CLIENT" ;
				$cNote="Suite à la modification de la facture n°".$Panier->IdFacture." 
				Le solde du client ".$Panier->Client->Prenom." ".$Panier->Client->Nom." 
				 est passé de ".$SoldePrec." à ".$SoldeSuiv ;
				$this->MaBoutique->AddToJournal($cTache,$cNote) ;
			}

			$TxSQLFinale="";
			$EnteteSQL="insert into ".$TxTableDet." (IdFacture,IdProduit,QTE,PrixVente,StockSuivant,VENTEDETAILLEE,PRIXCESSION,DESIGNATION) 
        	values ";

			foreach ($PdtFacture as $P){
					//var_dump($P) ;
					$vId=$P['vId'] ;
					$Article=$Panier->GetArticle($vId);
					if ($Article){
						//Pdt additionnel trouver dans la Panier
						$vId=$P['vId'];
						//$Article=$Panier->GetArticle($vId);
						$Tache="FACTURE EN MODIFICATION" ;
						$Tim=date("H:i:s");
						$Note="Vente de ".$P['produit']." dans la facture n°".$Panier->IdFacture." en cour de modification." ;
						//echo "<script>console.log('".$Tim." : ".$Note."')</script>" ;
						$Pdt=$Article->Pdt ;
						if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
						$vQte=$P['qte'] ;
						if ($P['typev']==1){
							$vQte=$P['qte']*$Pdt->StockInitial ;
						}
						$PrecStockG=$Pdt->GetStockGros() ;
						$PrecStockD=$Pdt->GetStockRestantDetail() ;
						
						$NewStock=$Pdt->Stock-$vQte ;
						$NewStockG=$NewStock / $Pdt->StockInitial ;
						$NewStockG=(int)$NewStockG ;
						$NewStockD=$NewStock % $Pdt->StockInitial ;
						$NewStockD=(int)$NewStockD ;
						$Tim=date("H:i:s");
						$Note="Retrait Stock de ".$P['produit']." ..." ;
						$IsOK=$Article->Pdt->RetirerStock($vQte) ;
						$Tim=date("H:i:s");
						$Note="Retrait de Stock: ".$IsOK ;

						if ($IsOK){						
							if ($Bout){
										//Si boutique client alors on corrige le stock client
										$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
										$TacheBout="Mise a jour de stock boutique" ;
										$NoteBout="Suite à la modification de la facture n°".$Panier->IdFacture." 
										depuis ".$this->MaBoutique->Nom.", le stock de ".$PdtBoutique->Nom."a été modifié";
										$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
										$PdtBoutique->AjouterStock($vQte);
										$Bout->AddToJournal($_SESSION['user'],0,$TacheBout,$NoteBout) ;
									}

							//Mise a jour de la base de donnée en meme temps
							$StockSuiv=(int)$NewStock ;
							$PrixAchat=(int)$Article->Pdt->PrixAchat ;
							if ($P['typev']==1){
								$PrixAchat=(int)$Article->Pdt->PrixAchatCarton ;
							}
							$PrixVente=$P['PrixU'] ;
							if ($PrixVente == 0){
								$PrixVente=(int)$Article->Pdt->PrixVente ;
							}
							if (!isset($P['PrixU'])){
								$PrixVente=(int)$Article->Pdt->PrixVente ;
							}
							
							$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
                        	$P['qte']."' , '".$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".$PrixAchat."','".$P['produit']."'),";

							$TxSQLFinale .=$TxSQL ;

									
							$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
							à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
							$this->MaBoutique->AddToJournal($Tache,$Note) ;

						}
							
					}
				}
			
			if ($TxSQLFinale !== ''){

				$TxSQL="delete from ".$TxTableDet." where IdFacture='".$Panier->IdFacture."' " ;
				$this->Main->ReadWrite($TxSQL,true) ;

				$TxSQL=$EnteteSQL ;
				$TxF=substr($TxSQLFinale,0,strlen($TxSQLFinale)-1) ;
				$TxSQL .=$TxF." ;" ;				
				$this->Main->ReadWrite($TxSQL,true) ;

			}

			if($Panier->RefCMD !=='' && $this->REFCMD !== $Panier->RefCMD){
				$this->REFCMD = $Panier->RefCMD ;
				if(!$this->ChampsExisteInTable('REFCMD')) {
					$this->MySQL->AlterTable($this->Table, "REFCMD",'TEXT','ADD','',$this->DataBase);
				}
				if($this->ChampsExisteInTable('REFCMD')) {
					$this->ExecUpdateSQL("update `".$this->DataBase."`.`".$this->Table."` SET REFCMD='".$this->Main::$db_link->escape_string($Panier->RefCMD)."' where ID=".$Panier->IdFacture ) ;
				}else{
					self::$xMain::$Log->AddToLog('Le champ REFCMD est introuvable dans la table '.$this->Table) ;
				}
			}

			//$BonAchatMgr->UpDateFacture($Panier->IdFacture,$Panier);
			
			if (isset($PrecPanier)){
				$PrecPanier->Fermee=true ;
				$PrecPanier->DejaValider(true) ;
				$PrecPanier->Vider() ;
				unset ($PrecPanier) ;
			}
			
			
		}
		else{//Nouvelle Facture
			
			//$Panier->IdFacture=0 ;
			$IdF=$this->SavePanierToDB($Panier,true) ; //Pour avoir un numero de facture
			
			if ($Panier->IdFacture !== $IdF){
				//echo "console.write('IdFacture Obtenue='".$IdF.")" ;
				$Panier->IdFacture = $IdF ;
			}
			
			if ($Panier->IdClient > 0){
					//Si le Client precedent est un Bon alors on corrige son solde
					if (!isset($Panier->Client)){
						$Client=new xClient($this->Main,$Panier->IdClient) ;
						$Panier->Client=$Client ;
					}
					$SoldePrec=$Panier->Client->Solde ;
					$Panier->Client->CrediterSolde($Panier->getTotalPriceCart()) ;
					$SoldeSuiv=$Panier->Client->Solde ;
					$cTache="MISE A JOUR SOLDE CLIENT" ;
					$cNote="Suite à la nouvelle facture n°".$Panier->IdFacture." 
					Le solde du client ".$Panier->Client->Prenom." ".$Panier->Client->Nom." 
					 est passé de ".$SoldePrec." à ".$SoldeSuiv ;
					$this->MaBoutique->AddToJournal($cTache,$cNote) ;
				}
				
			$Tache="Nouvelle Facture numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			//On va supprimer les lignes de ventes eventuelle pour stocker les nouvelles
			$TDetail=new xDetailVente($this->Main);
			$TxTableDet=$TDetail->Table ;

			$TxSQL="delete from ".$TxTableDet." where IdFacture='".$Panier->IdFacture."' " ;
			//$this->Main->ReadWrite($TxSQL,true) ;
		
			//Mise a jour des stocks avant enregistrement
			$TxSQLFinale="";
			$TVA=0;
			$TotalTVA=0;
			$NbLigne=0;

			$EnteteSQL="insert into ".$TxTableDet." (IdFacture,IdProduit,Qte,PrixVente,StockSuivant,VenteDetaillee,PRIXCESSION,DESIGNATION,DATEFACTURE,HEUREFACTURE, 
			PrixTotal, TVA ) 
			values ";

			//Mise a jour des stocks avant enregistrement
			//echo json_encode($Panier->getList());exit;
			foreach($Panier->getList() as $P){
				$NbLigne++;
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Vente de ".$P['produit']." dans la facture numero ".$Panier->IdFacture ;
				if ($Article){
					$Pdt=$Article->Pdt ;
					if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
					$vQte=(float)$P['qte'] ;
					if ($P['typev']==1){
						if ($Pdt->VENTEDETAILLEE == "OUI"){
							$vQte=(float)$P['qte']*(float)$Pdt->StockInitialDetail ;
						}						
					}
					$PrecStockG=$Pdt->GetStockGros() ;
					$PrecStockD=$Pdt->GetStockRestantDetail() ;
					
					$NewStock=(float)$Pdt->Stock - (int)$vQte ;
					$NewStockG=$NewStock;
					$NewStockD=0;
					if ((int)$Pdt->StockInitial !== 0){
						$NewStockG=$NewStock / (float)$Pdt->StockInitial ;
						$NewStockG=(int)$NewStockG ;
						$NewStockD=$NewStock % $Pdt->StockInitial ;
						$NewStockD=(int)$NewStockD ;
					}

					$IsOK=$Article->Pdt->RetirerStock($vQte,true) ;
					if ($IsOK){						
						if ($Bout){
							//Si boutique client alors on corrige le stock client
							$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
							$TacheBout="Mise a jour de stock boutique" ;
							$NoteBout="Suite à la nouvelle facture numero ".$Panier->IdFacture." 
							depuis ".$this->MaBoutique->Nom.", le stock de ".$PdtBoutique->Designation."a été modifié";
							$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
							$PdtBoutique->AjouterStock($vQte,true);
							$Bout->AddToJournal($_SESSION['user'],0,$TacheBout,$NoteBout) ;
						}
						
						//Mise a jour de la base de donnée en meme temps
						$StockSuiv=(int)$NewStock ;
						$PrixAchat=(int)$Article->Pdt->PrixAchat ;
						//var_dump($Article->Pdt->Designation);
						$P['produit']=$Article->Pdt->Designation ;
						if ($P['typev']==1){
							$PrixAchat=(int)$Article->Pdt->PrixAchatCarton ;
						}
						$PrixVente=$P['PrixU'] ;
						if ($PrixVente == 0){
							$PrixVente=(int)$Article->Pdt->PrixVente ;
						}
						if (!isset($P['PrixU'])){
							$PrixVente=(int)$Article->Pdt->PrixVente ;
						}

						$PrixTotal=$P['qte']*$PrixVente;

						//Mise a jour des datefactures et heure
						$TxDate=$Panier->DateFacture(); // date('Y-m-d') ;
						$TxHeure=date('H:i:s') ;
						$TxDateS=$TxDate." ".$TxHeure ;

						if ($Article->Pdt->RETIRER_TVA>0){
							$TauxTVA=(int)$Article->Pdt->TVA ;
							if ($TauxTVA==0){
								$TauxTVA=1;
							}
							$PrixHT=$PrixVente/(1+($TauxTVA/100));
							$vTVA=$PrixVente - $PrixHT;
							$TVA=round($vTVA,0)*$P['qte'];
							$TotalTVA +=$TVA;
						}
	
						$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
							$P['qte']."' , '".(int)$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".(int)$PrixAchat."','".$P['produit']."',
							'".$TxDate."','".$TxHeure."','".$PrixTotal."','".$TVA."'),";
	
						$TxSQLFinale .=$TxSQL ;
						
						$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
						à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
						$this->MaBoutique->AddToJournal($Tache,$Note) ;
					}
					
				}
			}

			if ($TxSQLFinale !== ''){	
				$TxSQL=$EnteteSQL ;
				$TxF=substr($TxSQLFinale,0,strlen($TxSQLFinale)-1) ;
				$TxSQL .=$TxF." ;" ;
				//echo "<script>console.log('".$TxSQL."');</script>" ;
				//echo hrtime(true)." : Nouvelle inscription des données dans la base de donnée ....".' ('.date('H:i:s.u').'</br>' ;
				$this->Main->ReadWrite($TxSQL,true) ;
				//echo hrtime(true)." : Inscription Terminée ....".' ('.date('H:i:s.u').'</br>' ;
				//print_r($TxSQL) ;
				
			}

		}
		
		//On enregistre le panier comme une vente dans la base de donnée
		$this->SavePanierToDB($Panier) ;
		$IdFacture=$Panier->IdFacture ;
		if ($IdFacture>0){
			$Vte=new xVente($this->Main,$IdFacture);
			$Vte->TotalTVA=$TotalTVA;
			$Vte->NbLigne=$NbLigne;
			if ($Vte->IdClient==0){
				$Vte->IdClient=2; //pour compatibilité avec NAbySy et Technopharm
			}
			$Vte->Enregistrer();
		}
		
		
		$Panier->Fermee=true ;

		//Enregistrer dans Activité Client
		//$Activite=new xActiviteClient($this->MaBoutique) ;
		//$Activite->Save($IdFacture) ;		
		//-------------------------------------
		$Panier->DejaValider(true) ;
		$Panier->Existe=false ;
			
		if ($Panier->Fermee){
			$Panier->Vider() ;
		}
		
		//exit ;
		
		return $IdFacture ;
		
	}
	
	private function SavePanierToDB(xCart $Panier,$GetIdFacture=null){
		//Enregistre ou met á jour reelement le panier dans la base de donnee
		//Enregistrons l'entete de la facture
		
		if (!$Panier->getList()){
			return false ;
		}
		$IsUpDate=false;

		if ($Panier->IdFacture < 0){
			$Panier->IdFacture=0 ;
		}

		if ($Panier->IdFacture >0){
			$IsUpDate=true;
		}
		if ((int)$Panier->IdClient == 0){
			$Panier->IdClient=0 ;
		}
		$DateF=$Panier->DateFacture() ;
		$DateFacture=$DateF ;
				
		if ($Panier->IdClient>0){
			$SoldePrecedent=$Panier->Client->Solde+$Panier->getTotalNetAPayer() ;
			$SoldeSuivant=$Panier->Client->Solde ;
			//$Panier->MontantVerse=$SoldePrecedent ;
			//$Panier->MontantRendu=$SoldeSuivant ;
		}
		$TxTable=$this->Table ;
		if (!$this->MySQL->ChampsExiste($TxTable,'MontantReduction')){
			$this->MySQL->AlterTable($TxTable,'MontantReduction','int(11)',"ADD",0);
		}
		$TxSQL="insert into ".$TxTable."(Id,IdCaissier,IdClient,TotalFacture,ModeReglement,NOMBENEFICIAIRE,DateFacture,MontantVerse,MontantRendu,
		MontantRemise, MontantReduction)
		values(".(int)$Panier->IdFacture.",".(int)$Panier->IdCaissier.",".(int)$Panier->IdClient.",".$Panier->getTotalPriceCart().
		",'".$Panier->ModePaiement."','".$Panier->NomClt." ".$Panier->PrenomClt."','".$DateFacture."','".
		(int)$Panier->MontantVerse."','".(int)$Panier->MontantRendu."','".(int)$Panier->TotalRemise."','".(int)$Panier->TotalReduction."' )";
		
		$this->IdClient=$Panier->IdClient;
		$this->IdCaissier=$Panier->IdCaissier;
		$this->NomCaissier=$this->Main->User->Login;
		$this->TotalFacture=$Panier->getTotalPriceCart();
		$this->ModeReglement=$Panier->ModePaiement;
		if ($this->ModeReglement==""){
			$this->ModeReglement="E";
		}

		if ($this->IdClient >2){
			$this->ModeReglement = "BP";
		}

		$this->NomBeneficiaire=$Panier->PrenomClt." ".$Panier->NomClt;
		$this->DateFacture=$DateFacture;
		$this->HeureFacture=date('H:i:s');
		$this->IDCAISSE=$this->Main->IdPosteClient;
		$this->NomCaisse=$this->Main->NomPosteClient;
		$this->MontantReduction=(int)$Panier->TotalReduction;
		$this->MontantRemise = (float)$Panier->TotalRemise;
		if ($this->MontantRemise<>0){
			$this->AvecRemise='OUI';
			//On détermine le Pourcentage de la remise
			$PourCRem=(float)(((float)$this->MontantRemise / $this->TotalFacture)*100) ;
			$this->ValRemise=$PourCRem;
			//$this->MODEREGLEMENT='R';
			$this->NomBeneficiaire=$Panier->NomBeneficiaireRemise;
		}else{
			$this->AvecRemise='NON';
			$this->ValRemise=0;
			$this->MontantRemise=0;
		}

		if ($this->IdClient>2){
			//Vente a crédit
			$this->PAYER='NON';
			$this->NomBeneficiaire=$Panier->PrenomClt." ".$Panier->NomClt;
			$this->SoldePrec=$SoldePrecedent;
			$this->SoldeSuiv=$SoldeSuivant;

		}else{
			$this->PAYER='OUI';
			$this->MontantVerse=$Panier->MontantVerse;
			$this->MontantRendu=$Panier->MontantRendu;
		}

		if ((float)$Panier->TotalReduction<>0){
			$this->MontantReduction=(float)$Panier->TotalReduction;
		}
		
		$Id=0 ;
		if ($this->Enregistrer()){
			$Id=$this->Id;
			$Panier->IdFacture=$this->Id;
			if($Panier->RefCMD !=='' && $this->REFCMD !== $Panier->RefCMD){
				$this->REFCMD = $Panier->RefCMD ;
				if($this->ChampsExisteInTable('REFCMD')) {
					$this->ExecUpdateSQL("update `".$this->DataBase."`.`".$this->Table."` SET REFCMD='".$this->Main::$db_link->escape_string($Panier->RefCMD)."' where ID=".$Panier->IdFacture ) ;
				}else{
					self::$xMain::$Log->AddToLog('Le champ REFCMD est introuvable dans la table '.$this->Table) ;
				}
			}		
		}
		
		$Panier->SaveInfosClient($Panier->NomClt,$Panier->PrenomClt,$Panier->IdClient,$Panier->IdFacture ) ;
		if (isset($GetIdFacture)){
			if ($GetIdFacture){
				return $Id ;
			}
		}

		/*Pour ne pas conserver la date de la facture et enregistrer la date de modification */
		if ($IsUpDate){
			$TxDate=$DateFacture ;
			$TxHeure=date('H:i:s') ;
			$this->DateFacture=$TxDate;
			$this->HeureFacture=$TxHeure;
			$this->Enregistrer();
		}

		#region Enregitrement de la caisse du jour
			$CaisseGlobale=new xJournalCaisse($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,0,$this->DateFacture);
			if (is_string($CaisseGlobale->TOTALFACTURE)){
				if ($CaisseGlobale->TOTALFACTURE=="0" || $CaisseGlobale->TOTALFACTURE==""){
					$CaisseGlobale->TOTALFACTURE=0;
				}else{
					$CaisseGlobale->TOTALFACTURE=(float)($CaisseGlobale->TOTALFACTURE);
				}
			}
			
			$CaisseU=new xJournalCaisse($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,$this->Main->User->Id,$this->DateFacture);
			if (is_string($CaisseU->TOTALFACTURE)){
				if ($CaisseU->TOTALFACTURE=="0" || $CaisseU->TOTALFACTURE==""){
					$CaisseU->TOTALFACTURE=0;
				}else{
					$CaisseU->TOTALFACTURE=(float)($CaisseU->TOTALFACTURE);
				}
			}

			$CaisseGlobale->TOTAL_FACTURE += (float)$this->TotalFacture;
			$CaisseU->TOTAL_FACTURE += (float)$this->TotalFacture;

			if ($this->ModeReglement == 'BP'){
				//Vente en Bo P
				$CaisseGlobale->TOTAL_BONP += $this->TotalFacture - $this->MontantReduction;
				$CaisseU->TOTAL_BONP += $this->TotalFacture - $this->MontantReduction;
				$CaisseGlobale->NB_BONP +=1;
				$CaisseU->NB_BONP +=1;
			}elseif ($this->ModeReglement == 'E'){
				$CaisseGlobale->TOTAL_ESPECE += $this->TotalFacture - $this->MontantReduction;
				$CaisseU->TOTAL_ESPECE += $this->TotalFacture - $this->MontantReduction;
				$CaisseGlobale->NB_ESP += 1;
				$CaisseU->NB_ESP += 1;
			}
			if ($this->MontantReduction !==0){
				$CaisseGlobale->TOTAL_REMISE += $this->MontantReduction;
				$CaisseGlobale->NB_REM +=1;
				$CaisseU->TOTAL_REMISE += $this->MontantReduction;
				$CaisseU->NB_REM +=1;
			}
			$CaisseGlobale->Enregistrer();
			$CaisseU->Enregistrer();
		#endregion
		
		$Panier->Fermee=true ;		
		return true ;
		
		
	}

	public function SupprimerPanier(xCart $PanierToSup){
		if (!isset($PanierToSup)){
			return false ;
		}
		$Bout=null ;
		if ($PanierToSup->MaBoutique->IdCompteClient==0){
			if ($PanierToSup->IdClient >0){
				//Si client boutique on Le charge
				$Lst=$this->Main->MaBoutique->ChargeListe("IdCompteClient = ".$PanierToSup->IdClient);
				if ($Lst->num_rows>0){
					$row=$Lst->fetch_assoc();
					$Bout=new xBoutique($this->Main,$row['Id']) ;
				}
			}
		}
		//Supprime un panier. Si le Panier à une Facture liée alors on supprime la facture aussi
		if ($PanierToSup->IdFacture>0){
			$PrecFact=new xVente($this->Main,$PanierToSup->IdFacture);
			//Le panier est lié à une facture
			if ($PanierToSup->IdClient > 0){
				//Si le Client est un Bon alors on corrige son solde
				$SoldePrec=$PanierToSup->Client->Solde ;
				$PanierToSup->Client->DebiterSolde($PanierToSup->getTotalPriceCart()) ;
				$PanierToSup->Client->ChargeClient($PanierToSup->IdClient);
				$SoldeSuiv=$PanierToSup->Client->Solde ;
				$cTache="MISE A JOUR SOLDE CLIENT-SUPPRESSION DE PANIER" ;
				$cNote="Suite à la modification de la facture n°".$PanierToSup->IdFacture." 
				Le solde du client ".$PanierToSup->Client->Prenom." ".$PanierToSup->Client->Nom." Code Client: ".$PanierToSup->IdClient."
					est passé de ".$SoldePrec." à ".$SoldeSuiv ;
				$this->MaBoutique->AddToJournal($cTache,$cNote) ;
			}
			

			//On supprime la facture ligne par ligne ->getList()
			$NbFois=0 ;
			$Tache="Suppresion de Panier avec la facture n°".$PanierToSup->IdFacture ;
			foreach($PanierToSup->getList() as $PrecP){ 
				$vId=$PrecP['vId'];
				$TypeVente="Pièces" ;
				if ($PrecP['typev']==1){
					$TypeVente="Carton(s)";
				}
				
				$Note="" ;
				$P=$PrecP ;
				// Produit a supprimer du panier
				//echo "</br>".$PrecP['produit']." (vId=".$vId.") etait dans le panier ".$PanierToSup->PanierId;
				//array_push($ListePdtOK,$PrecP['vId']) ; //Vue qu'il n'est plus dans le panier il ne devrait meme pas etre vue
				//dans la routine qui recherche les nouveaux articles de la facture
				
				if (isset($P)){
					$Article=$PanierToSup->GetArticle($vId);
					$NbFois++ ;
					if ($Article){
						$Pdt=$Article->Pdt ;
						if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
						$vQte=$PrecP['qte'] ;
						if ($PrecP['typev']==1){
							$vQte=$PrecP['qte']*$Pdt->StockInitial ;
						}
						$PrecStockG=$Pdt->GetStockGros() ;
						$PrecStockD=$Pdt->GetStockRestantDetail() ;
						
						$NewStock=$Pdt->Stock+$vQte ;
						$NewStockG=$NewStock / $Pdt->StockInitial ;
						$NewStockG=(int)$NewStockG ;
						$NewStockD=$NewStock % $Pdt->StockInitial ;
						$NewStockD=(int)$NewStockD ;
						$IsOK=$Article->Pdt->AjouterStock($vQte) ;
						if ($IsOK){
							if (isset($Bout)){
								//Si boutique client alors on corrige le stock client
								$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
								$TacheBout="Mise a jour de stock boutique" ;
								$NoteBout="Suite à la suppression de la facture n°".$PanierToSup->IdFacture." 
								depuis ".$this->MaBoutique->Nom.", le stock de ".$PdtBoutique->Nom."a été modifié.";
								$PdtBoutique->RetirerStock($vQte);
								$Bout->AddToJournal($TacheBout,$NoteBout) ;
							}
							$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
							à ".$NewStockG." carton(s) ".$NewStockD." pièce(s). Quantité facturée: ".$PrecP['qte']." (Vente en Gros=".$PrecP['typev'].") </br>" ;
							$this->MaBoutique->AddToJournal($Tache,$Note) ;
						}
						//On va supprimer la ligne de vente supprimer du panier
						$TxTableDet=$this->TDetail ;
						$TxSQL="delete from ".$TxTableDet." where IdFacture='".$PanierToSup->IdFacture."' and IdProduit='".$Article->Pdt->Id."'" ;
						$this->Main->ReadWrite($TxSQL,true) ;
						
					}
				}
				
			}

			
			/********************************************************************** */
					
		}

		#region Suppression dans le journal Caisse
			$CaisseGlobale=new xJournalCaisse($PrecFact->Main,null,$PrecFact->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,0,$PrecFact->DateFacture);
			$CaisseU=new xJournalCaisse($PrecFact->Main,null,$PrecFact->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,$PrecFact->Main->User->Id,$PrecFact->DateFacture);
			$CaisseGlobale->TOTAL_FACTURE -= $PrecFact->TotalFacture;
			$CaisseU->TOTAL_FACTURE -= $PrecFact->TotalFacture;

			if ($PrecFact->ModeReglement == 'BP'){
				//Vente en Bo P
				$CaisseGlobale->TOTAL_BONP -= ($PrecFact->TotalFacture - $PrecFact->MontantReduction);
				$CaisseU->TOTAL_BONP -= ($PrecFact->TotalFacture - $PrecFact->MontantReduction);
				$CaisseGlobale->NB_BONP -=1;
				$CaisseU->NB_BONP +=1;
			}elseif ($PrecFact->ModeReglement == 'E'){
				$CaisseGlobale->TOTAL_ESPECE -= $PrecFact->TotalFacture - $PrecFact->MontantReduction;
				$CaisseU->TOTAL_ESPECE -= $PrecFact->TotalFacture - $PrecFact->MontantReduction;
				$CaisseGlobale->NB_ESP -= 1;
				$CaisseU->NB_ESP -= 1;
			}
			if ($PrecFact->MontantReduction !==0){
				$CaisseGlobale->TOTAL_REMISE -= $PrecFact->MontantReduction;
				$CaisseGlobale->NB_REM -=1;
				$CaisseU->TOTAL_REMISE -= $PrecFact->MontantReduction;
				$CaisseU->NB_REM -=1;
			}
			$CaisseGlobale->Enregistrer();
			$CaisseU->Enregistrer();
		#endregion

		//On remet le total facture à jour
		$TotalF=0;			
		//On supprime de la mémoire les différentes lignes de panier
		$ListeP=$PanierToSup->getList() ;
		foreach($ListeP as $PrecP){
			$PanierToSup->removeProduct($PrecP['vId']) ;
		}
		if ($PanierToSup->IdFacture>0){
			$TotalF=$PanierToSup->getTotalPriceCart() ;
			$TxTable=$this->TEntete ;
			$TxSQL="UPDATE ".$TxTable." SET TotalFacture='".$TotalF."' where id='".$PanierToSup->IdFacture."' limit 1" ;
			$this->Main->ReadWrite($TxSQL,true) ;
		}
		return true ;
	}

	public function SupprimerVente($IdFacture=null){
		if (!isset($IdFacture)){
			$IdFacture=$this->Id;
		}
		$client= "0" ;
		//On ramene les qtés sortie dans le stock des boutiques clientes
		$Panier=new xPanier($this->Main) ;
		$Panier->Charger($IdFacture) ;
		$Tache ="SUPPRESSION DE VENTE " ;
		$Note = "Suppression de la facture n°".$IdFacture." du Client=".$Panier->Client->Nom." (Ma Boutique=".$this->Main->MaBoutique->Nom.")" ;
		//$This->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
		$this->Main->MaBoutique->AddToJournal($Tache,$Note) ;
		if ($Panier->Client->Id > -1){
			$client= "1" ;
		}
		if ($client =="1"){
			if ($this->Main->MaBoutique->IdCompteClient =='0'){
				//Si compte boutique et differend de notre boutique alors on met a jour le stock de la boutique concerné 
				if ($this->Main->MaBoutique->IdCompteClient !== $Panier->Client->Id){			
					foreach ($this->Main->Boutiques as $Bout){
						if ($Bout->IdCompteClient == $Panier->Client->Id){
							
							//On enleve le stock precedent de la boutique
							foreach ($Panier->Articles as $Article){
								$StockPrec=$Article->Pdt->Stock ;
								//$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,"Suppression du stock de ".$Article->Pdt->Designation." Qte supprimee: ".$Article->Qte." , Stock Précédent=".$StockPrec) ;
								$Bout->AddToJournal($_SESSION['user'],'0',$Tache,"update_list_vente:Suppression du stock de ".$Article->Pdt->Designation." Qte supprimee: ".$Article->Qte." , Stock Précédent=".$StockPrec) ;
								$Bout->RetirerStockBoutique($Bout->IdCompteClient,$Article->IdProduit,$Article->Qte) ;
							}
							break ;	//Il doit y avoir seulement un client qui a ce compte client
						}
					}
				}
			}
			
		}
		//On va supprimer la la facture
		$Panier->AnnulerVente() ;		

		$TxTableDet=$this->TDetail ;
		$TxSQL="delete from ".$TxTableDet." where id_vente='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		$TxTable=$this->TEntete ;
		$TxSQL="delete from ".$TxTable." where id='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		//------------------------------------------------------------------	

		return true ;

	}

	public function VitesseValidation(xCart $Panier){
		$Panier->GetInfosClient() ;
		if (empty($Panier->getList())){
			//On replace la vente
			echo 'Aucun élément dans le panier.' ;
			exit ;
		}
		$TxTableDet=$this->TDetail ;
		
		/*
			Si IdFacture dans panier <=0 alors on creer une nouvelle facture
			si non il sagit d'une modification de facture
		*/
		$Tache="" ;
		echo hrtime(true).": Test de validation : ".$Panier->getNbProductsInCart()." ligne(s)".' ('.date('H:i:s.u').'</br>' ;
		'</br>IdFacture='.$Panier->IdFacture.' ('.date('H:i:s.u').'</br>' ;		
		if (isset($Panier->Client)){
			if ($Panier->Client->Id <=0){
				$Panier->MontantVerse=(int)$Panier->MontantVerse ;
				if ($Panier->MontantVerse < $Panier->getTotalPriceCart()){
					//Montant inferieur a la facture
					$Panier->MontantVerse = $Panier->getTotalPriceCart() ;
				}
				$Panier->MontantRendu=$Panier->MontantVerse - $Panier->getTotalPriceCart();
			}
		}else{
				$Panier->MontantVerse=(int)$Panier->MontantVerse ;
		}
		
		$Bout=null ;
		if ($Panier->MaBoutique->IdCompteClient==0){
			if ($Panier->IdClient >0){
				//Si client boutique on Le charge
				$Lst=$this->Main->MaBoutique->ChargeListe("IdCompteClient = ".$Panier->IdClient);
				if ($Lst->num_rows>0){
					$row=$Lst->fetch_assoc();
					$Bout=new xBoutique($this->Main,$row['Id']) ;
				}
			}
		}
		
		echo '</br>'.hrtime(true).": IdFacture : ".$Panier->IdFacture.' ('.date('H:i:s.u').'</br>' ;
		//exit ;
		if ($Panier->IdFacture >0){
			//Une facture en modification
			$Tache="Modification de la facture numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			$PrecPanier=$this->ChargerPanier($Panier->IdFacture,true);	
			$cNote="Total Facture Precedant:".$PrecPanier->getTotalPriceCart();			
			echo $Tache.' ('.date('H:i:s.u').'</br>' ;
			echo $cNote.' ('.date('H:i:s.u').'</br>';
			$PdtFacture=$Panier->getList() ;

			$PrecPdtFacture=$PrecPanier->getList() ;
			$DT=new \DateTime('now');
			$Tim= hrtime(true);
			print_r($Tim."-> Suppression des données de la facture précédente...".' ('.date('H:i:s.u').'</br>') ;
			$this->SupprimerPanier($PrecPanier) ;
			$Tim2=hrtime(true);
			$EcartT=$Tim2-$Tim ;
			echo "Durée: ".$EcartT.' ('.date('H:i:s.u').'</br>' ;
			print_r($Tim2."-> Suppression terminée.") ;

			if ($Panier->IdClient > 0){
				//Si le Client est un Bon alors on corrige son solde
				$Client=new xClient($this->Main,$Panier->IdClient) ;
				$Panier->Client=$Client ;
				$Tim= hrtime(true);
				print_r($Tim."-> MAJ solde client...") ;
				$SoldePrec=$Panier->Client->Solde ;
				$Panier->Client->CrediterSolde($Panier->getTotalPriceCart()) ;
				$Panier->Client->ChargeClient($Panier->IdClient) ;
				$SoldeSuiv=$Panier->Client->Solde ;
				$cTache="MISE A JOUR SOLDE CLIENT" ;
				$cNote="Suite à la modification de la facture n°".$Panier->IdFacture." 
				Le solde du client ".$Panier->Client->Prenom." ".$Panier->Client->Nom." 
				 est passé de ".$SoldePrec." à ".$SoldeSuiv ;
				$this->MaBoutique->AddToJournal($cTache,$cNote) ;
				$Tim2=hrtime(true);
				$EcartT=$Tim2-$Tim ;
				echo "Durée: ".$EcartT.' ('.date('H:i:s.u').'</br>' ;
				print_r($Tim2."-> Terminée.".' ('.date('H:i:s.u').'</br>') ;
			}

			$TxSQLFinale="";
			$EnteteSQL="insert into ".$TxTableDet." (id_vente,id_article,quantite,prix,StockSuivant,typev,prixr,DESIGNATION) 
			values ";

			foreach ($PdtFacture as $P){
					//var_dump($P) ;
					$vId=$P['vId'] ;
					$Article=$Panier->GetArticle($vId);
					if ($Article){
						//Pdt additionnel trouver dans la Panier
						$vId=$P['vId'];
						//$Article=$Panier->GetArticle($vId);
						$Tache="FACTURE EN MODIFICATION" ;
						$Tim=hrtime(true);
						$Note="Vente de ".$P['produit']." dans la facture n°".$Panier->IdFacture." en cour de modification." ;
						echo $Tim." : ".$Note.' ('.date('H:i:s.u').'</br>' ;
						$Pdt=$Article->Pdt ;
						if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
						$vQte=$P['qte'] ;
						if ($P['typev']==1){
							$vQte=$P['qte']*$Pdt->StockInitial ;
						}
						$Tim=hrtime(true);
						$Note="Lecture Stock Précédent en gros en cour ..." ;
						echo hrtime(true)." : ".$Note.' ('.date('H:i:s.u').'</br>' ;
						$PrecStockG=$Pdt->GetStockGros() ;
						$Note="Lecture Stock Précédent au détail en cour ..." ;
						echo hrtime(true)." : ".$Note.' ('.date('H:i:s.u').'</br>' ;
						$PrecStockD=$Pdt->GetStockRestantDetail() ;
						$Note="Lecture terminée" ;
						echo hrtime(true)." : ".$Note."'".' ('.date('H:i:s.u').'</br>' ;
						
						$NewStock=$Pdt->Stock-$vQte ;
						$NewStockG=$NewStock / $Pdt->StockInitial ;
						$NewStockG=(int)$NewStockG ;
						$NewStockD=$NewStock % $Pdt->StockInitial ;
						$NewStockD=(int)$NewStockD ;

						$Note="Retrait Stock de ".$P['produit']." ..." ;
						$Tim=hrtime(true);
						echo $Tim." : ".$Note.' ('.date('H:i:s.u').'</br>' ;
						$IsOK=$Article->Pdt->RetirerStock($vQte) ;
						$Tim2=hrtime(true);
						$EcartT=$Tim2-$Tim ;
						echo "Durée: ".$EcartT.' ('.date('H:i:s.u').'</br>' ;
						$Note="Retrait de Stock: ".$IsOK ;
						echo hrtime(true)." : ".$Note." terminé.".' ('.date('H:i:s.u').'</br>' ;

						if ($IsOK){						
							if ($Bout){
										//Si boutique client alors on corrige le stock client
										echo hrtime(true)." : lecture stock boutique ....".' ('.date('H:i:s.u').'</br>' ;
										$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
										echo hrtime(true)." : lecture stock boutique terminé".' ('.date('H:i:s.u').'</br>' ;
										$TacheBout="Mise a jour de stock boutique" ;
										$NoteBout="Suite à la modification de la facture n°".$Panier->IdFacture." 
										depuis ".$this->MaBoutique->Nom.", le stock de ".$PdtBoutique->Nom."a été modifié";
										$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
										echo hrtime(true)." : Ajout au stock stock boutique ....".' ('.date('H:i:s.u').'</br>' ;
										$PdtBoutique->AjouterStock($vQte);
										echo hrtime(true)." : Ajout stock boutique terminé".' ('.date('H:i:s.u').'</br>' ;
										$Bout->AddToJournal($_SESSION['user'],0,$TacheBout,$NoteBout) ;
										echo hrtime(true)." : Inscription au journal terminée.".' ('.date('H:i:s.u').'</br>' ;
									}

							//Mise a jour de la base de donnée en meme temps
							$StockSuiv=$NewStock ;
							$PrixAchat=$Article->Pdt->PrixAchat ;
							if ($P['typev']==1){
								$PrixAchat=$Article->Pdt->PrixAchatCarton ;
							}
							$PrixVente=$P['PrixU'] ;
							if ($PrixVente == 0){
								$PrixVente=$Article->Pdt->PrixVente ;
							}
							if (!isset($P['PrixU'])){
								$PrixVente=$Article->Pdt->PrixVente ;
							}
							
							$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
							$P['qte']."' , '".$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".$PrixAchat."','".$P['produit']."'),";

							$TxSQLFinale .=$TxSQL ;

							/* Il ne sert plus ici l'insertion est défférée
							$TxDt="update ".$TxTableDet." SET DESIGNATION='".$P['produit']."' where id_vente='".$Panier->IdFacture."' 
							and id_article='".$P['id_produit']."' and typev='".$P['typev']."'	" ;
							echo hrtime(true)." : Mise a jour en base de donnée de ".$P['produit']." dans ".$TxTableDet." ....".' ('.date('H:i:s.u').'</br>' ;
							$this->Main->ReadWrite($TxDt,null,true) ;
							echo hrtime(true)." : Mise a jour terminée.".' ('.date('H:i:s.u').'</br>' ;
							// ****************************************************************************
							*/

							$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
							à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
							echo hrtime(true)." : Inscription au journal dépot ....".' ('.date('H:i:s.u').'</br>' ;
							$this->MaBoutique->AddToJournal($Tache,$Note) ;
							echo hrtime(true)." : Inscription terminée.".' ('.date('H:i:s.u').'</br>' ;

						}
							
					}
				}
			
			if ($TxSQLFinale !== ''){
				echo hrtime(true)." : Suppression des lignes de ventes précédente de la facture ".$Panier->IdFacture." ....".' ('.date('H:i:s.u').'</br>' ;
				$TxSQL="delete from ".$TxTableDet." where id_vente='".$Panier->IdFacture."' " ;
				$this->Main->ReadWrite($TxSQL,null,true) ;
				echo hrtime(true)." : Suppression terminée.".' ('.date('H:i:s.u').'</br>' ;

				$TxSQL=$EnteteSQL ;
				$TxF=substr($TxSQLFinale,0,strlen($TxSQLFinale)-1) ;
				$TxSQL .=$TxF." ;" ;
				//echo "<script>console.log('".$TxSQL."');</script>" ;
				echo hrtime(true)." : Nouvelle inscription des données dans la base de donnée ....".' ('.date('H:i:s.u').'</br>' ;
				$this->Main->ReadWrite($TxSQL,null,true) ;
				echo hrtime(true)." : Inscription Terminée ....".' ('.date('H:i:s.u').'</br>' ;
				print_r($TxSQL) ;
				
			}
			
			if (isset($PrecPanier)){
				//echo hrtime(true)." : Vidange du panier précédent en mémoire ....".' ('.date('H:i:s.u').'</br>' ;
				$PrecPanier->Fermee=true ;
				$PrecPanier->DejaValider(true) ;
				$PrecPanier->Vider() ;
				unset ($PrecPanier) ;
				//echo hrtime(true)." : Vidange Terminée ....".' ('.date('H:i:s.u').'</br>' ;
			}
			
			
		}
		else{//Nouvelle Facture

			$Tache="Nouvelle facture avec ".$Panier->getNbProductsInCart()." article(s) " ;		
			echo '</br>'.$Tache.' ('.date('H:i:s.u').'</br>' ;

			$Panier->IdFacture=0 ;
			echo hrtime(true)." : Obtention d'un numero de facture ....".' ('.date('H:i:s.u').'</br>' ;
			$IdF=$this->SavePanierToDB($Panier,true) ; //Pour avoir un numero de facture
			echo hrtime(true)." : Nouvelle facture n° ".$IdF.' ('.date('H:i:s.u').'</br>' ;
			if ($Panier->IdFacture != $IdF){
				$Panier->IdFacture = $IdF ;
			}
			
			if ($Panier->IdClient > 0){
					//Si le Client precedent est un Bon alors on corrige son solde
					echo hrtime(true)." : MAJ Solde client ....".' ('.date('H:i:s.u').'</br>' ;
					$Client=new xClient($this->Main,$Panier->IdClient) ;
					$Panier->Client=$Client ;
					$SoldePrec=$Panier->Client->Solde ;
					$Panier->Client->CrediterSolde($Panier->getTotalPriceCart()) ;
					$SoldeSuiv=$Panier->Client->Solde ;
					$cTache="MISE A JOUR SOLDE CLIENT" ;
					$cNote="Suite à la nouvelle facture n°".$Panier->IdFacture." 
					Le solde du client ".$Panier->Client->Prenom." ".$Panier->Client->Nom." 
					 est passé de ".$SoldePrec." à ".$SoldeSuiv ;
					$this->MaBoutique->AddToJournal($cTache,$cNote) ;
					echo hrtime(true)." : Terminée.".' ('.date('H:i:s.u').'</br>' ;
				}
				
			$Tache="Nouvelle Facture numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			//On va supprimer les lignes de ventes eventuelle pour stocker les nouvelles
			$TxTableDet=$this->TDetail ;
			$TxSQL="delete from ".$TxTableDet." where id_vente='".$Panier->IdFacture."' " ;
			echo hrtime(true)." : Effacement d'enventuelle ligne de facture ....".' ('.date('H:i:s.u').'</br>' ;
			$this->Main->ReadWrite($TxSQL,true) ;
			echo hrtime(true)." : Terminée.".' ('.date('H:i:s.u').'</br>' ;
		
			//Mise a jour des stocks avant enregistrement
			$TxSQLFinale="";
			$EnteteSQL="insert into ".$TxTableDet." (id_vente,id_article,quantite,prix,StockSuivant,typev,prixr,DESIGNATION,DATEFACTURE,HEUREFACTURE) 
			values ";

			foreach($Panier->getList() as $P){ 
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Vente de ".$P['produit']." dans la facture numero ".$Panier->IdFacture ;
				if ($Article){
					$Pdt=$Article->Pdt ;
					if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
					$vQte=$P['qte'] ;
					if ($P['typev']==1){
						$vQte=$P['qte']*$Pdt->StockInitial ;
					}
					$PrecStockG=$Pdt->GetStockGros() ;
					$PrecStockD=$Pdt->GetStockRestantDetail() ;
					
					$NewStock=$Pdt->Stock-$vQte ;
					$NewStockG=$NewStock / $Pdt->StockInitial ;
					$NewStockG=(int)$NewStockG ;
					$NewStockD=$NewStock % $Pdt->StockInitial ;
					$NewStockD=(int)$NewStockD ;
					$IsOK=$Article->Pdt->RetirerStock($vQte,true) ;
					if ($IsOK){						
						if ($Bout){
									//Si boutique client alors on corrige le stock client
									$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
									$TacheBout="Mise a jour de stock boutique" ;
									$NoteBout="Suite à la nouvelle facture numero ".$Panier->IdFacture." 
									depuis ".$this->MaBoutique->Nom.", le stock de ".$PdtBoutique->Nom."a été modifié";
									$PdtBoutique=$Bout->GetArticle($Article->Pdt->IdProduit) ;
									$PdtBoutique->AjouterStock($vQte);
									$Bout->AddToJournal($_SESSION['user'],0,$TacheBout,$NoteBout) ;
								}								
						
						//Mise a jour de la base de donnée en meme temps
						$StockSuiv=$NewStock ;
						$PrixAchat=$Article->Pdt->PrixAchat ;
						if ($P['typev']==1){
							$PrixAchat=$Article->Pdt->PrixAchatCarton ;
						}
						$PrixVente=$P['PrixU'] ;
						if ($PrixVente == 0){
							$PrixVente=$Article->Pdt->PrixVente ;
						}
						if (!isset($P['PrixU'])){
							$PrixVente=$Article->Pdt->PrixVente ;
						}
						/*
						$TxSQL="insert into ".$TxTableDet." (id_vente,id_article,quantite,prix,StockSuivant,typev,prixr) 
						values (".$Panier->IdFacture.", ".$P['id_produit'].", ".
						$P['qte']." , ".$PrixVente.", ".$StockSuiv.", ".$P['typev'].",".$PrixAchat."
						)";
						*/

						//Mise a jour des datefactures et heure
						$TxDate=date('Y-m-d') ;
						$TxHeure=date('H:i:s') ;
						$TxDate=$TxDate." ".$TxHeure ;

						$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
							$P['qte']."' , '".$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".$PrixAchat."','".$P['produit']."'".$TxDate."'".$TxHeure."'),";

						$TxSQLFinale .=$TxSQL ;

						$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
						à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
						echo hrtime(true)." : Inscription au journal de la boutique en cour ....".' ('.date('H:i:s.u').'</br>' ;
						$this->MaBoutique->AddToJournal($Tache,$Note) ;
						echo hrtime(true)." : Terminée.".' ('.date('H:i:s.u').'</br>' ;
					}
					
				}
			}
		}
		
		//On enregistre le panier comme une vente dans la base de donnée
		echo hrtime(true)." : Lancement de l'enregistrement de l'entete de facture finale avec SavePanierToDB....".' ('.date('H:i:s.u').'</br>' ;
		$this->SavePanierToDB($Panier) ;
		echo hrtime(true)." : Terminée.".' ('.date('H:i:s.u').'</br>' ;
		$IdFacture=$Panier->IdFacture ;
		$Panier->Fermee=true ;

		$Panier->DejaValider(true) ;
		$Panier->Existe=false ;
			
		if ($Panier->Fermee){
			$Panier->Vider() ;
		}
		
		//exit ;
		
		return $IdFacture ;
	}

	public function ToJSON($TableStructure = false, $RemoveFieldList = []): string
	{
		$Reponse=parent::ToJSON($TableStructure, $RemoveFieldList);
		$DetailV=$this->GetVente($this->Id);
		if (isset($DetailV)){
			$oVente=json_decode($Reponse,true);
			$oVente['ListeProduit']=$DetailV;
			$Reponse=json_encode($oVente);
		}
		return $Reponse ;
	}

	/**
	 * Retourne les lignes de ventes
	 */
	public function DetailToJSON($TableStructure = false, $FullDetail=true): string
	{
		$Reponse=json_encode([]);;
		if ($FullDetail){
			$DetailV=$this->DetailVente->GetFullInfosFactureByLine($this->Id);
		}else{
			$DetailV=$this->GetVente($this->Id);
		}		
		if (isset($DetailV)){
			$Reponse=json_encode($DetailV);
		}
		return $Reponse ;
	}
	
}

?>