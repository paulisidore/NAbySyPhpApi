<?php

use NAbySy\GS\Panier\xArticlePanier;
use NAbySy\GS\Panier\xCartGeneric;

Class xReductionStock
{
	public $Id;
	public $IdFacture ; // = Id 
	public $Date;
	public $Heure;
	public $Caissier ;
	public $IdCaissier;
	
	public $IdPoste;
	public $TotalFacture;
	public $NBLIGNE;

	public $Motif ;
	public $Responsable ;
	
	public $Client;
	public $TypeBon;
	public $ETAT;
	public $TEntete ;
	public $TDetail ;
	public $EnteteTable;
	public $Main ;
	public $MaBoutique ;
	public $RS ;
	public $DBase ;
	
	public function __construct(xNAbySyGS $NAbySyGS,$Boutique=null,$IdRetour=null){
		$this->Main = $NAbySyGS ;
		$this->MaBoutique = $NAbySyGS->MaBoutique ;
		$this->DBase=$this->MaBoutique->DBase;
		if (isset($Boutique)){
			$this->MaBoutique = $Boutique ;
		}
		$this->TEntete=$this->EnteteTable;
		if ($this->EnteteTable==''){
			$this->EnteteTable = 'retourfournisseur' ;
			$this->TEntete='retourfournisseur';
		}		
		$this->TDetail='detail_retourfournisseur';
		
		$this->Caissier='' ;
		if (isset($_SESSION['user'])){
			$this->Caissier=$_SESSION['user']; 
			$this->IdCaissier=$_SESSION['id_user'];
		}
		if (isset($IdRetour)){
			$this->ChargeReduction($IdRetour);
		}
	}
	public function ChargeReduction($Id,$IdDetail=0){
		//Permet de lire une réduction par son Id ou IdDetail
		$OK=false;
		$NbLigne=0;
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		$sql  ="select E.ID as 'IdFacture',E.DateRetour as 'DateFacture',E.HeureRetour,E.TotalRetour as 'TotalFacture',	";
		$sql .="D.IDPRODUIT as 'IdProduit',u.login as 'Caissier',E.IDUTILISATEUR as 'IdCaissier',E.MOTIF, E.RESPONSABLE,
			A.Nom as 'Designation',D.PRIXPUBLIC as 'PrixVente',D.PRIXCESSION,
			D.QTE as 'Qte',D.TypeStock as 'VenteGros',D.ID as 'IdLigne',a.nbunite,a.unitec,a.united ";
		$sql .=" from ".$TableD." D 
		left outer join ".$TableE." E on D.IDRETOUR=E.ID "; 
		$sql .=" left outer join article A on D.IDPRODUIT=A.ID ";
		$sql .=" left outer join ".$this->Main->MaBoutique->DBase.".utilisateur u on u.id=E.IDUTILISATEUR" ;
		$sql .=" where E.ID>'0' " ;
		$crit="" ;
		if ($Id>0){
			$crit=$crit." AND E.ID='".$Id."' " ;
		}
		if ($IdDetail>0){
			$crit=$crit." AND D.ID='".$IdDetail."' " ;
		}
		$ordre=" ORDER BY E.ID ";
		$sql=$sql.$crit.$ordre ;
		//echo $sql ;
		$reponse=$this->Main->ReadWrite($sql,$OK,false,$NbLigne,null,null,false) ;
		$ret=$reponse ;
		if (!$reponse)
		{
			echo $this->Main->MODULE->Nom."Erreur interne de lecture des Reductions de Stock ...".$Id." - ".$IdDetail ;
		}else{
			$this->RS=array() ;
			$nb=1;
			while ($row=$reponse->fetch_assoc()){
				$this->RS[]=$row ;
				if ($nb==1){
					$this->Id=$row['IdFacture'] ;
					$this->Date=$row['DateFacture'] ;
					$this->Heure=$row['HeureRetour'] ;
					$this->IdCaissier=$row['IdCaissier'];
					$this->TotalFacture=$row['TotalFacture'];
					$this->Motif=$row['MOTIF'];
					$this->Responsable=$row['RESPONSABLE'] ;
				}
				$nb++ ;
			}
			
			if ($this->IdCaissier>0){
				$this->Caissier=new xUser($this->MaBoutique,$this->IdCaissier) ;
			}
		}
		
		return $this->RS ;
	}

	public function GetListe($DateDu=null,$DateAu=null,$IdCaissier=null,$IdLigneRetour=null, $AutreCritere=null,$GroupeRetour=null){
		//Permet de lire une vente par son Id ou IdDetail
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		$OK=false;
		$NbLigne=0;
		$sql  ="select E.ID as 'IdFacture', E.ID as 'IdRetour',E.MOTIF,E.RESPONSABLE,E.DateRetour,E.DateRetour as 'DateFacture',E.HeureRetour,E.TotalRetour,E.TotalRetour as 'TotalFacture',	";
		$sql .="D.IDPRODUIT as 'IdProduit',u.login as 'Caissier',E.IDUTILISATEUR as 'IdCaissier',
			A.Nom as 'Designation',D.PRIXPUBLIC as 'PrixVente',D.PRIXCESSION,D.STOCK_AVANTRETOUR,D.STOCK_APRESRETOUR,D.ID as 'IdLigne',
			D.QTE as 'Qte',D.TypeStock as 'VenteGros',a.nbunite,a.unitec,a.united ";
		$sql .=" from ".$TableD." D left outer join ".$TableE." E on D.IDRETOUR=E.ID "; 
		$sql .=" left outer join article A on D.IDPRODUIT=A.ID ";
		$sql .=" left outer join ".$this->DBase.".utilisateur u on u.id=E.IDUTILISATEUR" ;
		$sql .=" where E.ID>'0' " ;

		$crit="" ;
		if (isset($DateDu)){
			$crit=" AND E.DateRetour='".$DateDu."' " ;
			if (isset($DateAu)){
				$crit=" AND (E.DateRetour>='".$DateDu."' AND E.DateRetour<='".$DateAu."') " ;
			}
		}
		if (isset($IdCaissier)){
			$crit=$crit." AND E.IDUTILISATEUR='".$IdCaissier."' " ;
		}
		if (isset($IdLigneRetour)){
			$crit=$crit." AND D.ID='".$IdLigneRetour."' " ;
		}
		if (isset($AutreCritere)){
			$crit=$crit." ".$AutreCritere ;
		}
		$GroupBy='' ;
		if (isset($GroupeRetour)){
			$GroupBy=" GROUP BY ".$GroupeRetour ;
		}
		$ordre=" ORDER BY E.ID desc ";
		$sql=$sql.$crit.$GroupBy.$ordre ;
		
		$reponse=$this->Main->ReadWrite($sql,$OK,false,$NbLigne,null,null,false) ;
		if (!$reponse)
			{
				echo $this->Main->MODULE->Nom."Erreur interne de lecture des reductions de Stock ...".$DateDu." - ".$DateAu." - ".$IdCaissier ;
			}
		
		return $reponse;
	}
	
	public function GetPanier($IdFacture,$IsTemp=null,$IdLigneRetour=null){
		//Charge depuis la base de donnée les produits du Retour IdFacture dans
		//le panier $Panier
		$liste=$this->GetListe(null,null,null,null," AND E.ID=".$IdFacture);
		if (!$liste){
			return false ;
		}
		
		$NewPanier=new xCartGeneric($this->MaBoutique,1,null,true) ;
		$NewPanier->IdFacture=$IdFacture ;
		$c=0 ;
		
		$Liste=array();
		while ($rw=$liste->fetch_assoc()){
			$Liste[]=$rw;
			//var_dump($rw) ;
		}
		//var_dump($Liste) ;
		foreach ($Liste as $row ){
			$c++;
			//echo '</pre>Ajout du produit '.$row['Designation'] ;
			if ($c == 1){
				//$Panier=new xCart($this->MaBoutique,1,true)		 ;
				//$Panier->SaveInfosClient()
				$NewPanier->SaveInfosClient($row['MOTIF'],$row['RESPONSABLE'],null,$IdFacture,$row['DateRetour']) ;	
				$NewPanier->DateFacture($row['DateRetour'])		;	
			}
			//Charge chaque ligne de la vente dans le Panier
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
		//var_dump($_SESSION[$InfoC]) ;
		$NewPanier->IdFacture=$IdFacture ;
		//$NewPanier->SaveInfosClient($row['MOTIF'],$row['RESPONSABLE'],null,$IdFacture,$this->Date) ;
		return $NewPanier ;
				
	}
	
	public function Valider(xCartGeneric $Panier){
		
		if (empty($Panier->getList())){
			//On replace la saisie
			echo '<script type="text/javascript">
					window.open("reductionstock.php?Idanier='.$Panier.',"","",true);
				</script>' ;
			exit ;
			return 0;
		}
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
				
		/*
			Si IdFacture dans panier <=0 alors on creer une nouvelle facture
			si non il sagit d'une modification de facture
		*/
		$Tache="" ;	
		$Panier->GetInfosClient() ;
		//var_dump($Panier) ;
		//exit ; 
		if ($Panier->IdFacture >0){
			//Une Reduction en modification
			$Tache="Modification du retour numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
		
			//On compare les ligne pour chaque produit du panier précédent
			//$PrecPanier->Dump() ;
			$PdtFacture=$Panier->getList() ;

			//On supprime dans la Base de donnée
			$this->Supprimer($Panier->IdFacture) ;
						
			foreach ($PdtFacture as $P){
					//var_dump($P) ;
					$vId=$P['vId'] ;
					$Article=$Panier->GetArticle($vId);
					if ($Article){
						//Pdt additionnel trouver dans la Panier
						$vId=$P['vId'];
						$Article=$Panier->GetArticle($vId);
						$Tache="RETOUR EN MODIFICATION" ;
						$Note="Retour de ".$P['produit']." dans la retour n°".$Panier->IdFacture." en cour de modification." ;
						//echo "</br>".$Note ;
						$Pdt=$Article->Pdt ;
						if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
						$vQte=$P['qte'] ;
						if ($P['typev']==1){
							$vQte=$P['qte']*$Pdt->StockInitial ;
						}
						$PrecStockG=$Pdt->GetStockGros() ;
						$PrecStockD=$Pdt->GetStockRestantDetail() ;
						
						$AncStock=$Pdt->Stock ;
						$NewStock=$Pdt->Stock-$vQte ;
						$NewStockG=$NewStock / $Pdt->StockInitial ;
						$NewStockG=(int)$NewStockG ;
						$NewStockD=$NewStock % $Pdt->StockInitial ;
						$NewStockD=(int)$NewStockD ;
						$IsOK=$Article->Pdt->RetirerStock($vQte) ;
						if ($IsOK){						
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
							
							$TxSQL="delete from ".$TableD." where IDRETOUR='".$Panier->IdFacture."' and IDPRODUIT='".$P['id_produit']."' and TypeStock='".$P['typev']."'" ;
							$this->Main->ReadWrite($TxSQL,null,true) ;
							$PrixT=$PrixVente*$P['qte'] ;
							$TxSQL="insert into ".$TableD." (IDRETOUR,IDPRODUIT,QTE,PRIXPUBLIC,PRIXTOTAL,STOCK_APRESRETOUR,TypeStock,PRIXCESSION,STOCK_AVANTRETOUR) 
							values (".$Panier->IdFacture.", ".$P['id_produit'].", ".
							$P['qte']." , ".$PrixVente.",".$PrixT.", ".$StockSuiv.", ".$P['typev'].",".$PrixAchat.", ".$AncStock."
							)";
							$this->Main->ReadWrite($TxSQL,null,true) ;

							// ****************************************************************************
									
							$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
							à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
							$this->MaBoutique->AddToJournal($_SESSION['user'],0,$Tache,$Note) ;

						}
							
					}
				}
			
			if (isset($PrecPanier)){
				$PrecPanier->Fermee=true ;
				$PrecPanier->DejaValider(true) ;
				$PrecPanier->Vider() ;
				unset ($PrecPanier) ;
			}
			
			
		}
		else{//Nouvelle Reduction de Stock
			$Panier->IdFacture=0 ;
			$IdF=$this->SavePanierToDB($Panier,true) ; //Pour avoir un numero de facture
			if ($Panier->IdFacture != $IdF){
				//echo "console.write('IdFacture Obtenue='".$IdF.")" ;
				$Panier->IdFacture = $IdF ;
			}
						
			$Tache="Nouvelle Reduction de Stock n° ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			//On va supprimer les lignes de ventes eventuelle pour stocker les nouvelles
			$TxSQL="delete from ".$TableD." where IDRETOUR='".$Panier->IdFacture."' " ;
			$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
		
			//Mise a jour des stocks avant enregistrement
			foreach($Panier->getList() as $P){ 
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Réductrion du Stock de ".$P['produit']." dans le retour numero ".$Panier->IdFacture ;
				if ($Article){
					$Pdt=$Article->Pdt ;
					if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
					$vQte=$P['qte'] ;
					if ($P['typev']==1){
						$vQte=$P['qte']*$Pdt->StockInitial ;
					}
					$PrecStockG=$Pdt->GetStockGros() ;
					$PrecStockD=$Pdt->GetStockRestantDetail() ;
					
					$AncStock=$Pdt->Stock ;
					$NewStock=$Pdt->Stock-$vQte ;
					$NewStockG=$NewStock / $Pdt->StockInitial ;
					$NewStockG=(int)$NewStockG ;
					$NewStockD=$NewStock % $Pdt->StockInitial ;
					$NewStockD=(int)$NewStockD ;
					$IsOK=$Article->Pdt->RetirerStock($vQte) ;
					if ($IsOK){	
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
						$PrixT=$PrixVente*$P['qte'] ;
						$TxSQL="insert into ".$TableD." (IDRETOUR,IDPRODUIT,QTE,PRIXPUBLIC,PRIXTOTAL,STOCK_APRESRETOUR,TypeStock,PRIXCESSION,STOCK_AVANTRETOUR) 
							values (".$Panier->IdFacture.", ".$P['id_produit'].", ".
							$P['qte']." , ".$PrixVente.",".$PrixT.", ".$StockSuiv.", ".$P['typev'].",".$PrixAchat.", ".$AncStock."
							)";
						$this->Main->ReadWrite($TxSQL,null,true) ;
		
		// ****************************************************************************
						$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
						à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
						$this->MaBoutique->AddToJournal($_SESSION['user'],0,$Tache,$Note) ;
					}
					
				}
			}
		}
		
		//On enregistre le panier comme une vente dans la base de donnée
		$this->SavePanierToDB($Panier) ;
		$IdFacture=$Panier->IdFacture ;
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
		
		if (isset($Panier)){
			$Panier->Fermee=true ;
			$Panier->DejaValider(true) ;
			$Panier->Vider() ;
			unset ($Panier) ;
		}
		//exit ;
		
		return $IdFacture ;
		
	}
	
	private function SavePanierToDB(xCartGeneric $Panier,$GetIdFacture=null){
		//Enregistre ou met á jour reelement le panier dans la base de donnee
		//Enregistrons l'entete 
		//$Panier->NomClt -> Contient le Motif de la Réduction
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		
		if (!$Panier->getList()){
			return false ;
		}
		
		if ($Panier->IdFacture < 0){
			$Panier->IdFacture=0 ;
		}
		if ($Panier->IdClient == ''){
			$Panier->IdClient=0 ;
		}
		$DateF=date_create($Panier->DateFacture()) ;
		$DateFacture=date_format($DateF,'Y-m-d') ;
		$Dt = new DateTime('NOW');
		$Panier->HeureFacture=$Dt->format('H:i:s') ;
		//echo $Panier->HeureFacture;

		$TxTable=$TableE ;
		$TxSQL="insert into ".$TxTable."(ID,IDUTILISATEUR,TOTALRETOUR,DATERETOUR,HEURERETOUR,MOTIF,RESPONSABLE)
		values(".$Panier->IdFacture.",".$Panier->IdCaissier.",".$Panier->getTotalPriceCart().
		",'".$Panier->DateFacture()."','".$Panier->HeureFacture."','".$Panier->NomClt."','".$Panier->PrenomClt."')";
		
		if (!isset($GetIdFacture)){
			if ($Panier->IdFacture>0){
				//Remise du total = 0 dans la facture pour forcer l'enregistrement du montant total dans la facture
				$SQL="update ".$TxTable." SET TotalRetour=0 where id=".$Panier->IdFacture." limit 1" ;
				$this->Main->ReadWrite($SQL,null,true) ;
				
				$TxSQL="replace into ".$TxTable."(ID,IDUTILISATEUR,TOTALRETOUR,DATERETOUR,HEURERETOUR,MOTIF,RESPONSABLE)
				values(".$Panier->IdFacture.",".$Panier->IdCaissier.",".$Panier->getTotalPriceCart().
				",'".$Panier->DateFacture()."','".$Panier->HeureFacture."','".$Panier->NomClt."','".$Panier->PrenomClt."')";
			}
		}
		
		if ($Panier->IdFacture>0){
			$this->Main->ReadWrite($TxSQL,null,true) ;
			$Id=$Panier->IdFacture ;
		}else{
			$Id=$this->Main->ReadWrite($TxSQL,null,true,null,$Panier->IdFacture,$TxTable) ;
			$Panier->IdFacture=$Id ;
		}
		$Panier->SaveInfosClient($Panier->NomClt,$Panier->PrenomClt,$Panier->IdClient,$Panier->IdFacture ) ;
		if (isset($GetIdFacture)){
			if ($GetIdFacture){
				return $Id ;
			}
		}
		$TxHeure=date('H:i:s') ;
		$TxDt="update ".$TxTable." SET HEURERETOUR='".$TxHeure."' where id='".$Panier->IdFacture."' limit 1 " ;
		//$this->Main->ReadWrite($TxDt,null,true,null,null,null,true) ;

		
		$Panier->Fermee=true ;
		
		return true ;
		
		
	}

	public function SupprimerPanier(xCartGeneric $PanierToSup,$SuppressionComplete=false){
		if (!isset($PanierToSup)){
			return false ;
		}
		
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		
		//Supprime un panier. Si le Panier à une Facture liée alors on supprime la facture aussi
		if ($PanierToSup->IdFacture>0){
			//Le panier est lié à une facture

			//On supprime la facture ligne par ligne ->getList()
			$NbFois=0 ;
			$Tache="Suppresion de Panier Retour avec le Retour n°".$PanierToSup->IdFacture ;
			$ListeP=$PanierToSup->getList() ;
			foreach($ListeP as $PrecP){ 
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
							$Note .=". Sont stock est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
							à ".$NewStockG." carton(s) ".$NewStockD." pièce(s). Quantité retournée: ".$PrecP['qte']." (Vente en Gros=".$PrecP['typev'].") </br>" ;
							$this->MaBoutique->AddToJournal($_SESSION['user'],0,$Tache,$Note) ;
						}
						//On va supprimer la ligne de vente supprimer du panier
						$TxSQL="delete from ".$TableD." where IDRETOUR='".$PanierToSup->IdFacture."' and IDPRODUIT='".$Article->Pdt->Id."'" ;
						$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
						
					}
				}
				
			}

			
			/********************************************************************** */
					
		}
		//On remet le total facture à jour
		$TotalF=0;			
		//On supprime de la mémoire les différentes lignes de panier
		$ListeP=$PanierToSup->getList() ;
		foreach($ListeP as $PrecP){
			$PanierToSup->removeProduct($PrecP['vId']) ;
		}
		if ($SuppressionComplete){
			if ($PanierToSup->IdFacture>0){
				$TotalF=$PanierToSup->getTotalPriceCart() ;
				$TxTable=$TableE ;
				$TxSQL="UPDATE ".$TxTable." SET TotalRetour='".$TotalF."' where ID='".$PanierToSup->IdFacture."' limit 1" ;
				$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
			}
		}else{
			if ($PanierToSup->IdFacture>0){
				$NPanier=$this->GetPanier($PanierToSup->IdFacture) ;
				$TotalF=$NPanier->getTotalPriceCart() ;
				$TxTable=$TableE ;
				$TxSQL="UPDATE ".$TxTable." SET TotalRetour='".$TotalF."' where ID='".$PanierToSup->IdFacture."' limit 1" ;
				$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;

				$Tache="Suppresion de la Réduction du Stock N° ".$NPanier->IdFacture." avec ".$NPanier->getNbProductsInCart()." article(s) " ;
				$cNote="Total Retour est passé à ".$NPanier->getTotalPriceCart();			
				$this->MaBoutique->AddToJournal($_SESSION['user'],0,$Tache,$cNote) ;

			}
		}
		
		return true ;
	}

	public function Supprimer($IdFacture=null,$IdLigne=null,$NoRetournePdt=0){
		if (!isset($IdFacture)){
			$IdFacture=$this->Id;
		}
		
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		
		//On ramene les qtés sortie dans le stock 	
		$IdLigneSupp=null ;	
		$SuppressionComplete=true;
		if (isset($IdLigne)){
			$SuppressionComplete=false;
			$IdLigneSupp=$IdLigne;
		}
		
		if ($IdFacture<=0){
			if ($SuppressionComplete){
				//Il s'agit d'une facture en cour de validation
				echo "Facture en cour de validation.</br>" ;
				return false ;
			}
		}

		echo "Suppression de la Facture n°".$IdFacture." LigneID=".$IdLigne."</br>" ;
		$Reduct=new xReductionStock($this->Main,null,$IdFacture) ;

		$Tache ="ANNULATION DE LA REDUCTION DE STOCK " ;
		$Note = "La reduction du stock numero ".$IdFacture." du MOTIF=".$Reduct->Motif." a ete annulee (".$this->Main->MaBoutique->Nom.")" ;
		if (isset($IdLigne)){
			$Note = "La reduction du stock numero ".$IdFacture." du MOTIF=".$Reduct->Motif." a vue sa ligne numero ".$IdLigne." supprimee. (".$this->Main->MaBoutique->Nom.")" ;
		}
		$this->MaBoutique->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
		
		if ($NoRetournePdt == 1){
			$this->MaBoutique->AddToJournal($_SESSION['user'],'0',$Tache,"La suppression de la reduction numero ".$IdFacture." ligne ".$IdLigne.
			" est fait sans impacter le stock comme souhaité par ".$_SESSION['user']) ;
		}
		
		//On doit d'abors ramener les stocks
		$PrecListeArticle=$Reduct->RS ;
		if (!$SuppressionComplete){
			$LigneP=$Reduct->GetListe(null,null,null,$IdLigne) ;
			$PrecListeArticle=array() ;
			if ($LigneP){
				$UnArticle=$LigneP->fetch_assoc() ;
				$PrecListeArticle[]=$UnArticle ;
			}
			//echo "Ligne à supprimer: ".$IdLigne ;
			//var_dump($PrecListeArticle) ;
		}
		//var_dump($Reduct->RS) ;
		//exit ;

		foreach ($PrecListeArticle as $PdtRS){
			
			if ($SuppressionComplete){
				$IdLigneSupp=$PdtRS['IdLigne'] ;
			}
			$IsOK=true ;
			if ($PdtRS['IdLigne']==$IdLigneSupp){
				$IdPdt=$PdtRS['IdProduit'];
				if ($NoRetournePdt==0){
					$Article=new xArticlePanier($this->Main,$IdPdt,$PdtRS['Qte'],$PdtRS['VenteGros'],$this->MaBoutique );
					$Pdt=$Article->Pdt ;
					if ($Pdt->StockInitial==0){$Pdt->StockInitial=1 ;}
					$vQte=$PdtRS['Qte'] ;
					if ($PdtRS['VenteGros']==1){
						$vQte=$PdtRS['Qte'] *$Pdt->StockInitial ;
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
						$Note="Suite à la suppression de la réduction, " ;
						$Note .=" le stock de ".$Article->Nom." est passé de ".$PrecStockG." carton(s) ".$PrecStockD." pièce(s) 
						à ".$NewStockG." carton(s) ".$NewStockD." pièce(s) </br>" ;
						$this->MaBoutique->AddToJournal($_SESSION['user'],0,$Tache,$Note) ;
					}
				}
				if ($IsOK){
					//On va supprimer le Retour
					$TxSQL="delete from ".$TableD." where ID='".$PdtRS['IdLigne']."' limit 1 " ;
					$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
				}
			}
			

		}

		//*************************************** */


		//On va supprimer le Retour
		if ($SuppressionComplete==false){
			//On met à jour le Total
			$TxSQL="update ".$TableD." SET PRIXTOTAL=PRIXPUBLIC*QTE " ;
			$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;

			$TxSQL="update ".$TableE." SET TOTALRETOUR=(select sum(PRIXTOTAL) from ".$TableD." where IDRETOUR=".$IdFacture.") where ID=".$IdFacture." LIMIT 1" ;
			$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;

		}else{
			$TxSQL="delete from ".$TableE." where ID='".$IdFacture."' " ;
			$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;

			$TxSQL="delete from ".$TableD." where IDRETOUR='".$IdFacture."' " ;
			$this->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
		}		
		//------------------------------------------------------------------	

		//exit ;

		return true ;

	}
	
	public function GetMotif($xMotif=null,$IdRetour=null){
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		$TxSQL="select MOTIF from ".$TableE." E Where E.MOTIF not like '' " ;
		if (isset($xMotif)){
			$TxSQL.=" AND E.MOTIF Like '".$xMotif."%'" ;
		}
		if (isset($IdRetour)){
			$TxSQL.=" AND E.ID = '".$IdRetour."'" ;
		}
		$TxSQL .=" GROUP BY E.MOTIF ORDER BY E.MOTIF " ;

		$Reponse=$this->Main->ReadWrite($TxSQL) ;
		return $Reponse ;

	}
	public function GetResponsable($Responsable=null,$IdRetour=null){
		$TableE=$this->DBase.".`".$this->TEntete."`" ;
		$TableD=$this->DBase.".`".$this->TDetail."`" ;
		$TxSQL="select RESPONSABLE from ".$TableE." E Where E.RESPONSABLE not like '' " ;
		if (isset($Responsable)){
			$TxSQL.=" AND E.RESPONSABLE Like '".$Responsable."%'" ;
		}
		if (isset($IdRetour)){
			$TxSQL.=" AND E.ID = '".$IdRetour."'" ;
		}
		$TxSQL .=" GROUP BY E.RESPONSABLE ORDER BY E.RESPONSABLE " ;

		$Reponse=$this->Main->ReadWrite($TxSQL) ;
		//echo 'lut:'.$TxSQL ;
		return $Reponse ;

	}
}

?>