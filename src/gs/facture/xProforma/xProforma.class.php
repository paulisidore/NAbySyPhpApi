<?php
namespace NAbySy\GS\Facture;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Panier\xCartProForma;
use NAbySy\GS\Panier\xPanier;
use NAbySy\xNAbySyGS;

Class xProforma
{
	public $Id;
	public $IdFacture ; // = Id 
	public $Date;
	public $Heure;
	public $Caissier ;
	public $IdCaissier;
	
	public $IdPoste;
	public $TotalFacture;

	public $TotalRemise ;

	public $TotalReduction ;
	
	public $NBLIGNE;
	
	public $Client;

	public $IdClient ;
	public $TypeBon;
	public $ETAT;
	public $TEntete ;
	public $TDetail ;
	public $EnteteTable;
	public xNAbySyGS $Main ;
	public xBoutique $MaBoutique ;
	
	public function __construct(xNAbySyGS $NAbySyGS,$Boutique=null){
		$this->Main = $NAbySyGS ;
		$this->MaBoutique = $NAbySyGS->MaBoutique ;
		if (isset($Boutique)){
			$this->Main = $Boutique->Main ;
			$this->MaBoutique = $Boutique ;
		}
		$this->TEntete=$this->EnteteTable;
		if ($this->EnteteTable==''){
			$this->EnteteTable = $this->MaBoutique->DBase.'.proforma' ;
			$this->TEntete=$this->MaBoutique->DBase.".proforma";
		}		
		$this->TDetail=$this->MaBoutique->DBase.".detailproforma";
		
		$this->Caissier='' ;
		if (isset($_SESSION['user'])){
			$this->Caissier=$_SESSION['user']; 
			$this->IdCaissier=$_SESSION['id_user'];
		}
	}
	public function GetVente($Id,$IdDetail=0){
		//Permet de lire une proforma par son Id ou IdDetail
		$OK=false;
		$NbLigne=0;
		$sql  ="select E.ID as 'IdFacture',E.date as 'DateFacture',E.Montant as 'TotalFacture',
			E.ID_CLIENT as 'IdClient',E.Mode_Payment as 'MODEREGLEMENT',E.Nom,
			E.Id_Caisse as 'IDCAISSE',";
		$sql .="E.Prenom,D.Id_Article as 'IdProduit',u.login as 'Caissier',E.id_caissier as 'IdCaissier',
			A.Nom as 'Designation',D.PRIX as 'PrixVente',D.Prixr as 'PRIXCESSION',
			D.quantite as 'Qte',D.typev as 'VenteGros',a.nbunite,E.compte ";
		$sql .=" from ".$this->TDetail." D left outer join ".$this->TEntete." E on D.ID_Vente=E.ID "; 
		$sql .=" left outer join article A on D.Id_article=A.ID ";
		$sql .=" left outer join ".$this->Main->MaBoutique->DBase.".utilisateur u on u.id=E.id_caissier" ;
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
		
		$reponse=$this->Main->ReadWrite($sql) ;
		if (!$reponse)
			{
				echo $this->Main->MODULE->Nom."Erreur interne de lecture des ventes ...".$Id." - ".$IdDetail ;
			}
	
	return $reponse;
	}

	public function GetListeVente($DateDu=null,$DateAu=null,$IdCaissier=null,$AutreCritere=null){
		//Permet de lire une vente par son Id ou IdDetail
		$OK=false;
		$NbLigne=0;
		$sql  ="SELECT v.*, c.nom as 'NomClt',c.prenom as 'PrenomClt', U.Login as 'Caissier' from ".$this->TEntete." v 
				left outer join client c on c.id=v.id_client
				left outer join utilisateur U on U.id=v.id_caissier
				WHERE v.id > 0 ";
		$crit="" ;
		if (isset($DateDu)){
			$crit=" AND v.date='".$DateDu."' " ;
			if (isset($DateAu)){
				$crit=" AND (v.date>='".$DateDu."' AND v.date<='".$DateAu."') " ;
			}
		}
		if (isset($IdCaissier)){
			$crit=$crit." AND v.id_caissier='".$IdCaissier."' " ;
		}
		if (isset($AutreCritere)){
			$crit=$crit." ".$AutreCritere ;
		}
		$ordre=" ORDER BY v.ID desc ";
		$sql=$sql.$crit.$ordre ;
		
		$reponse=$this->Main->ReadWrite($sql) ;
		if (!$reponse)
			{
				echo $this->Main->MODULE->Nom."Erreur interne de lecture des proformas ...".$DateDu." - ".$DateAu." - ".$IdCaissier ;
			}
		//echo $sql ;
	return $reponse;
	}
	public function ChargerPanier($IdFacture,$IsTemp=null){
		//Charge depuis la base de donnée les produits de la facture IdFacture dans
		//le panier $Panier
		$liste=$this->GetVente($IdFacture);
		if (!$liste){
			return false ;
		}
		
		$NewPanier=$this->MaBoutique->GetNewPanier(null,$IsTemp,true) ;
		$IdVente=$IdFacture ;
		
		$NewPanier->IdFacture=$IdFacture ;
		$c=0 ;
		while ($row = $liste->fetch_assoc()){
			$c++;
			if ($c == 1){
				$this->Client = new  xClient($this->MaBoutique,$row['IdClient']) ;
				$this->IdClient=$row['IdClient'] ;
				$this->Caissier=$row['Caissier'] ;
				$this->Date=$row['DateFacture'] ;
				$this->TotalFacture=$row['TotalFacture'] ;
				$this->Id=$row['IdFacture'] ;
				$this->IdFacture=$IdVente ;
				$NewPanier->SaveInfosClient($row['Nom'],$row['Prenom'],$row['IdClient'],$row['IdFacture']) ;
				$NewPanier->DateFacture($this->Date) ;
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
	
	public function Valider(xCartProForma $Panier){
		
		if (empty($Panier->getList())){
			return false;
		}
		$TxTableDet=$this->TDetail ;
		
		/*
			Si IdFacture dans panier <=0 alors on creer une nouvelle facture
			si non il sagit d'une modification de facture
		*/
		$Tache="" ;
		$IsNew=false ;
		if ($Panier->IdFacture >0){
			//Une Proforma en modification
			$Tache="Modification de la Proforma numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			$PrecPanier=$this->ChargerPanier($Panier->IdFacture,true);	
			$cNote="Total ProForma Precedant:".$PrecPanier->getTotalPriceCart();			
			$this->MaBoutique->AddToJournal($Tache,$cNote) ;
			//On compare les ligne pour chaque produit du panier précédent
			//$PrecPanier->Dump() ;			
			$this->SupprimerPanier($PrecPanier) ;
			$PdtFacture=$Panier->getList() ;
			
			echo "Dump de PdtFacture: ".var_dump($PdtFacture) ;
			
			//Mise a jour des stocks avant enregistrement
			foreach($Panier->getList() as $P){ 
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Saisie de ".$P['produit']." dans la Proforma numero ".$Panier->IdFacture ;
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
					$TxSQL="insert into ".$TxTableDet." (id_vente,id_article,quantite,prix,StockSuivant,typev,prixr) 
					values (".$Panier->IdFacture.", ".$P['id_produit'].", ".
					$P['qte']." , ".$PrixVente.", ".$StockSuiv.", ".$P['typev'].",".$PrixAchat."
					)";
					$this->Main->ReadWrite($TxSQL,null,true) ;

					//Mise a jour des datefactures et heure
					$TxDate=$Panier->DateFacture() ;
					$TxHeure=date('H:i:s') ;
					$TxDate=$TxDate." ".$TxHeure ;
					$TxDt="update ".$TxTableDet." SET DATEFACTURE='".$TxDate."', HEUREFACTURE='".$TxHeure."' where id_vente='".$Panier->IdFacture."' 
					and id_article='".$P['id_produit']."' and typev='".$P['typev']."'	" ;
					$this->Main->ReadWrite($TxDt,null,true) ;
					
				}
			}
			
			if (isset($PrecPanier)){
				$PrecPanier->Fermee=true ;
				$PrecPanier->DejaValider(true) ;
				$PrecPanier->Vider() ;
				unset ($PrecPanier) ;
			}
			
			
		}
		else{//Nouvelle Proforma
			$Panier->IdFacture=0 ;
			$IsNew=true;
			$IdF=$this->SavePanierToDB($Panier,true) ; //Pour avoir un numero de proforma
			if ($Panier->IdFacture != $IdF){
				echo "console.write('IdProforma Obtenue='".$IdF.")" ;
				$Panier->IdFacture = $IdF ;
			}
				
			$Tache="Nouvelle Proforma numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			//On va supprimer les lignes de ventes eventuelle pour stocker les nouvelles
			$TxTableDet=$this->TDetail ;
			$TxSQL="delete from ".$TxTableDet." where id_vente='".$Panier->IdFacture."' " ;
			$this->Main->ReadWrite($TxSQL,true) ;
		
			//Mise a jour des stocks avant enregistrement
			foreach($Panier->getList() as $P){ 
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Saisie de ".$P['produit']." dans la Proforma numero ".$Panier->IdFacture ;
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
					$TxSQL="insert into ".$TxTableDet." (id_vente,id_article,quantite,prix,StockSuivant,typev,prixr) 
					values (".$Panier->IdFacture.", ".$P['id_produit'].", ".
					$P['qte']." , ".$PrixVente.", ".$StockSuiv.", ".$P['typev'].",".$PrixAchat."
					)";
					$this->Main->ReadWrite($TxSQL,null,true) ;

					//Mise a jour des datefactures et heure
					$TxDate=date('Y-m-d') ;
					$TxHeure=date('H:i:s') ;
					$TxDate=$TxDate." ".$TxHeure ;
					$TxDt="update ".$TxTableDet." SET DATEFACTURE='".$TxDate."', HEUREFACTURE='".$TxHeure."' where id_vente='".$Panier->IdFacture."' 
					and id_article='".$P['id_produit']."' and typev='".$P['typev']."'	" ;
					$this->Main->ReadWrite($TxDt,null,true) ;
					
				}
			}
		}
		
		//On enregistre le panier comme une vente dans la base de donnée
		$this->SavePanierToDB($Panier) ;
		
		$IdFacture=$Panier->IdFacture ;
		$Panier->Fermee=true ;
		$Panier->DejaValider(true) ;
		$Panier->Existe=false ;
			
		if ($Panier->Fermee){
			$Panier->Vider() ;
		}		
		return $IdFacture ;
		
	}
	
	private function SavePanierToDB(xCartProForma $Panier,$GetIdFacture=null){
		//Enregistre ou met á jour reelement le panier dans la base de donnee
		//Enregistrons l'entete de la proforma
		
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

		$TxTable=$this->TEntete ;
		$TxSQL="insert into ".$TxTable."(id,id_caissier,id_client,montant,mode_payment,nom,prenom,date,restant,compte)
		values(".$Panier->IdFacture.",".$Panier->IdCaissier.",".$Panier->IdClient.",".$Panier->getTotalPriceCart().
		",'".$Panier->ModePaiement."','".$Panier->NomClt."','".$Panier->PrenomClt."','".$Panier->DateFacture()."',".
		(int)$Panier->MontantVerse.",".(int)$Panier->MontantRendu.")";
		
		if (!isset($GetIdFacture)){
			if ($Panier->IdFacture>0){
				//Remise du total = 0 dans la facture pour forcer l'enregistrement du montant total dans la facture
				$SQL="update ".$TxTable." SET montant=0 where id=".$Panier->IdFacture." limit 1" ;
				$this->Main->ReadWrite($SQL,null,true) ;
				
				$TxSQL="replace into ".$TxTable."(id,id_caissier,id_client,montant,mode_payment,nom,prenom,date,restant,compte)
				values(".$Panier->IdFacture.",".$Panier->IdCaissier.",".$Panier->IdClient.",".$Panier->getTotalPriceCart().
				",'".$Panier->ModePaiement."','".$Panier->NomClt."','".$Panier->PrenomClt."','".$Panier->DateFacture()."',".
				(int)$Panier->MontantVerse.",".(int)$Panier->MontantRendu.")";
			}
		}
		
		if ($Panier->IdFacture>0){
			$this->Main->ReadWrite($TxSQL,null,true) ;
			$Id=$Panier->IdFacture ;
		}else{
			$Id=$this->Main->ReadWrite($TxSQL,true,$TxTable,true) ;
			$Panier->IdFacture=$Id ;
		}
		$Panier->SaveInfosClient($Panier->NomClt,$Panier->PrenomClt,$Panier->IdClient,$Panier->IdFacture ) ;
		if (isset($GetIdFacture)){
			if ($GetIdFacture){
				return $Id ;
			}
		}
		$TxHeure=date('H:i:s') ;
		$TxDt="update ".$TxTable." SET HEURE='".$TxHeure."' where id='".$Panier->IdFacture."' limit 1 " ;
		$this->Main->ReadWrite($TxDt,true) ;

		
		$Panier->Fermee=true ;
		
		return true ;
		
		
	}

	public function SupprimerPanier(xCartProForma $PanierToSup){
		if (!isset($PanierToSup)){
			return false ;
		}
		$Bout=null ;
		if ($PanierToSup->MaBoutique->IdCompteClient==0){
			if ($PanierToSup->IdClient >0){
				//Si client boutique on Le charge
				//$Bout=$this->Main->GetBoutiqueFromCache(null,$PanierToSup->IdClient) ;
				//var_dump($Bout) ;
			}
		}
		//Supprime un panier. Si le Panier à une Facture liée alors on supprime la facture aussi
		if ($PanierToSup->IdFacture>0){
			//Le panier est lié à une facture
			if ($PanierToSup->IdClient > 0){
				//Si le Client est un Bon alors on corrige son solde
			}
			
			//On supprime la facture ligne par ligne ->getList()
			$NbFois=0 ;
			$Tache="Suppresion de Panier avec la proforma n°".$PanierToSup->IdFacture ;
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
						
						//On va supprimer la ligne de proforma supprimer du panier
						$TxTableDet=$this->TDetail ;
						$TxSQL="delete from ".$TxTableDet." where id_vente='".$PanierToSup->IdFacture."' and id_article='".$Article->Pdt->Id."'" ;
						$this->Main->ReadWrite($TxSQL,true) ;
						
					}
				}
				
			}

			
			/********************************************************************** */
					
		}
		//On remet le total proforma à jour
		$TotalF=0;			
		//On supprime de la mémoire les différentes lignes de panier
		$ListeP=$PanierToSup->getList() ;
		foreach($ListeP as $PrecP){
			$PanierToSup->removeProduct($PrecP['vId']) ;
		}
		if ($PanierToSup->IdFacture>0){
			$TotalF=$PanierToSup->getTotalPriceCart() ;
			$TxTable=$this->TEntete ;
			$TxSQL="UPDATE ".$TxTable." SET montant='".$TotalF."' where id='".$PanierToSup->IdFacture."' limit 1" ;
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
		$Tache ="SUPPRESSION DE PRO FORMA " ;
		$Note = "Suppression de la pro forma n°".$IdFacture." de la boutique (Ma Boutique=".$this->Main->MaBoutique->Nom.")" ;
		//$This->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
		$this->Main->MaBoutique->AddToJournal($Tache,$Note) ;
		
		//On va supprimer la la facture pro forma
		$TxTableDet=$this->TDetail ;
		$TxSQL="delete from ".$TxTableDet." where id_vente='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		$TxTable=$this->TEntete ;
		$TxSQL="delete from ".$TxTable." where id='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		//------------------------------------------------------------------	

		return true ;

	}
	
}

?>