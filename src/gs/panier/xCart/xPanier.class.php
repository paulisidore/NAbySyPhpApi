<?php
namespace NAbySy\GS\Panier ;

use NAbySy\GS\Client\xClient;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Stock\xJournalCaisse;
use NAbySy\GS\Stock\xProduit;
use xNAbySyGS;

Class xPanier
{
	public $Caissier ;
	public $Date;
	public $Poste;
	public $IdFacture;
	public $IdClient;
	public $NomClient;
	public $NBProduit;
	public $TotalFacture;
	public $MontantVerse;
	public $MontantRendu;
	public $Articles=array();
	public xClient $Client ;
	
	public xNAbySyGS $Main;
	
	public function __construct($NAbySy)
	{
		$this->Main = $NAbySy ;
	}
	
	public function AjouteArticle(xArticlePanier $Article){
		if ($Article->Calcule()){
			//$this->TotalFacture = $this->TotalFacture + $Article->PrixTotal ;
			$this->NBProduit = $this->NBProduit + 1 ;
			array_push($this->Articles,$Article);
		}
		return true ;
	}
	public function SupprimeArticle(xArticlePanier $Article){
			$index = array_search($Article->Pdt->Designation,$this->Articles) ;
			if ($index >-1){
				$this->TotalFacture = $this->TotalFacture - $Article->PrixTotal ;
				$this->NBProduit = $this->NBProduit - 1 ;
				unset($this->Articles[$index]);
			}
		return true ;
	}	

	public function Valider(){
		//Enregistre le contenue du panier dans la table vente et/ou, met a jour les qté
		echo "Valider Panier" ;
		if ($this->IdFacture == ''){
			return false ;
		}
		foreach ($this->Articles as $Article){
			if (isset($Article->Pdt)){
				$StockPrec=$Article->Pdt->Stock ;
				$StockSuiv=$StockPrec - $Article->Qte ;
				$Tache="SORTIE DE STOCK APRES VENTE" ;
				$Note="Validation de la vente N°".$this->IdFacture.": Sortie de stock de ".$Article->Pdt->Designation.". 
				Le Stock passe de ".$StockPrec." a ".$StockSuiv ;
				if ($Article->Pdt->nbunite >0){
					$StockPrecG=$Article->Pdt->Stock / $Article->Pdt->nbunite ;
					$StockPrecD=$Article->Pdt->Stock % $Article->Pdt->nbunite ;
					$StockSuivG=$StockSuiv / $Article->Pdt->nbunite ;
					$StockSuivD=$StockSuiv % $Article->Pdt->nbunite ;
					$Note .=" (Stock précédent: ".$StockPrecG." ".$Article->Pdt->unitec." et ".$StockPrecD." ".$Article->Pdt->united.")" ;
					$Note .=" passé à (Stock suivant: ".$StockSuivG." ".$Article->Pdt->unitec." et ".$StockSuivD." ".$Article->Pdt->united.")" ;
				}				
				$IsOK=$Article->Pdt->RetirerStock($Article->Qte) ;
				$this->Main->MaBoutique->AddToJournal($this->Main->User->Login,$this->Main->User->Id,$Tache,$Note) ;
				
				//Recharge du panier car il peut y avoir le meme article dans le panier avec peut etre ou non une quantite au det ou gros
				$this->Actualiser() ;				
			}
		}
		//Mise a jour du compte de la boutique 6 on ajoute q son solde
			if ($this->Client){ 
				//Il peut arrivé que la facture soit sans client ou que la facture soit supprimée
				$this->Client->CrediterSolde($this->TotalFacture) ;
			}
		//-------------------------------------------
		$this->Vider() ;
		return true ;
	}
	public function AnnulerVente(){
		
		if ((int)$this->IdFacture ==0){
			return false ;
		}
		$PrecFact = new xVente($this->Main,(int)$this->IdFacture);
		//Mise a jour du compte de la boutique
			if(isset($this->Client)){
				if ($this->Client->Id>0){
					$this->Client->DebiterSolde($this->TotalFacture) ;
				}
			}
		//-------------------------------------------			
		foreach ($this->Articles as $Article){
			if (isset($Article->Pdt)){
				//Remise dans le stock
				$TxStockDet="";
				$StockPrec=$Article->Pdt->Stock ;
				$VenteDet=false;
				if ($Article->TypeVente == 0){
					//Vente au Détail
					$VenteDet=true;
					$TxStockDet=" au détail ";
					$StockPrec=$Article->Pdt->StockDetail ;
				}
				$StockSuiv=$StockPrec+$Article->Qte ;
				$Tache="RETOUR EN STOCK APRES ANNULATION" ;
				$Note="Annulation de la vente N°".$this->IdFacture.": Retour en stock de ".$Article->Pdt->Designation.". 
				Le Stock ".$TxStockDet." est passé de ".$StockPrec." a ".$StockSuiv ;
				if ($Article->Pdt->nbunite >0){
					$StockPrecG=$Article->Pdt->Stock / $Article->Pdt->nbunite ;
					$StockPrecD=$Article->Pdt->Stock / $Article->Pdt->nbunite ;
					$Note .=" (Stock ".$TxStockDet." précédent: ".$StockPrecG." ".$Article->Pdt->unitec." et ".$StockPrecD." ".$Article->Pdt->united.")" ;
				}
				$Article->Pdt->AjouterStock($Article->Qte,false,$VenteDet) ;
				$this->Main->MaBoutique->AddToJournal($this->Main->User->Login,$this->Main->User->Id,$Tache,$Note) ;
				$this->Main->AddToJournal($this->Main->User->Login,$this->Main->User->Id,$Tache,$Note) ;
				
				//Recharge du panier car il peut y avoir le meme article dans le panier avec peut etre ou non une quantite au det ou gros
				$this->Actualiser() ;
			}
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
		
		//On vide le panier
		$this->Vider();	
		return true ;
	}

	/**
	 * Charge le contenue d'une vente dans le panier
	 * @param int $IdVente 
	 * @return bool 
	 */
	public function Charger(int $IdVente){
		if ($IdVente==0){
			return false ;
		}
		$this->NomClient="";
		//Recharge le panier correspondant a une facture pour une eventuelle modification
		$sql="select d.IDPRODUIT as 'id_article', d.QTE as 'quantite',d.PrixVente as 'prix',d.VENTEDETAILLEE, d.VENTEDETAILLEE as 'typev',d.VENTEDETAILLEE as 'VenteGros',a.STOCKINITDETAIL as 'nbunite',e.IDCLIENT as 'id_client', e.DATEFACTURE as 'date', e.TotalFacture,
		c.nom as 'NomClient',c.prenom as 'PrenomClient',c.Solde as 'SoldeClient', e.IDCAISSIER as 'id_caissier', u.Login as 'Caissier' from ".$this->Main->MaBoutique->DBase.".detailfacture d 
		left outer join ".$this->Main->MaBoutique->DBase.".produits a on a.id=d.IDPRODUIT
		left outer join ".$this->Main->MaBoutique->DBase.".facture e on e.id=d.IDFACTURE  
		left outer join ".$this->Main->MaBoutique->DBase.".client c on c.id=e.IDCLIENT  
		left outer join ".$this->Main->MaBoutique->DBase.".utilisateur u on u.id=e.IDCAISSIER  
		where d.IDFACTURE='".$IdVente."' and e.id>0 " ;
		
		$liste=$this->Main->ReadWrite($sql,null,null,null,null,null,false) ;
		if ($liste)
			$this->IdFacture=$IdVente ;
		$c=0 ;
		$this->Articles = array() ;
		//echo $sql ;
		
		while ($row = $liste->fetch_assoc()){
			$c++;
			if ($c == 1){
				if ((int)$row['id_client']>2){
					$this->Client = new xClient($this->Main,(int)$row['id_client']) ;
					$this->IdClient=$row['id_client'] ;
					$this->Caissier=$row['Caissier'] ;
					$this->Date=$row['date'] ;
					$this->TotalFacture=$row['TotalFacture'] ;
					$this->NomClient=$row['PrenomClient'].' '.$row['NomClient'];
				}
			}
			//Charge chaque ligne de la vente 
			$ok=false ;
			$Qte=$row['quantite'];
			$IsVenteDetail=(int)$row['VENTEDETAILLEE'];
			$QG=1;
			if ($IsVenteDetail > 0){
				$QG=0;
				//Il s'agit d'une vente sur le stock au détail
			}
			$Article=new xArticlePanier($this->Main,$row['id_article'],$Qte,$QG) ;
			if (isset($Article)){
				//On ajoute au panier
				$Article->PrixU=$row['prix'] ;
				$ok=$this->AjouteArticle($Article) ;
			}			
		}
		$this->IdFacture=$IdVente ;
		
		return true ;
	}
	
	public function Vider(){
				//On vide le panier
			foreach ($this->Articles as $Article){
				if (isset($Article->Pdt)){
					if ($Article->Pdt){
						$this->SupprimeArticle($Article) ;
					}
				}
			}
	}
	
	public function Actualiser(){
		//Actualise les infos de chaque produits du panier
		foreach ($this->Articles as $Article){
			if (isset($Article->Pdt)){
				if ($Article->Pdt){
					$Article->Pdt->Actualiser() ;
				}
			}
		}
		return true ;
	}
	
	
}
?>