<?php
namespace NAbySy\GS\Panier ;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Stock\xProduit;
use NAbySy\GS\Stock\xProduitNC;

Class xArticlePanier
{
	public xProduit $Pdt ;
	public $Nom ;
	public $IdProduit;
	public float $PrixU;
	public float $Qte;
	public $TypeVente ;
	public $Conditionnement ;
	public float $PrixTotal;
	public float $PrixTotalAchat ;
	public $AvecRemise;
	public float $PourCRemise;
	public float $ValeurRemise;
	public float $TotalAvecRemise;
	
	public $Main;
	public $Boutique ;

	public bool $IsPdtClown ;

	public $CodeBar ;
	
	public function __construct($NAbySy,$IdP=null,float $Qte=1,$TypeV=0,$MaBoutique=null,$CodeB=null){
		$this->Main = $NAbySy ;
		$this->Boutique=$this->Main->MaBoutique ;
		if (isset($MaBoutique)){
			$this->Boutique=$MaBoutique ;
			$this->Main=$this->Boutique->Main ;
		}
		$this->Nom='' ;
		$this->Pdt = new xProduit($NAbySy) ;
		if (isset($IdP)){
			if ($IdP>0){
				$this->Pdt=new xProduit($NAbySy,$IdP) ;
			}else{
				//Pdt Non Classé ou clown
				$IdP=-1;
				$this->Pdt=new xProduitNC ($NAbySy,null,$NAbySy::GLOBAL_AUTO_CREATE_DBTABLE,null,$MaBoutique,$CodeB) ;
			}
			//var_dump(get_class($this->Pdt));
			//var_dump($this->Pdt->CodeBar);
			$this->IdProduit = $IdP ;
			$this->CodeBar=$this->Pdt->CodeBar;

			$this->Nom=$this->Pdt->Designation ;
			$this->PrixU = $this->Pdt->PrixVenteTTC ;
			if ($TypeV==1){
				$this->PrixU = $this->Pdt->PrixVenteTTC ;
			}
			
			$this->Qte = $Qte ;
			$this->TypeVente = $TypeV ;
			$this->Conditionnement=$this->Pdt->nbunite ;
			
			
		}
		else {
			if (isset($this->IdProduit)){
				$this->Pdt=new xProduit($NAbySy,$this->IdProduit) ;
				$this->PrixU = $this->Pdt->PrixVenteTTC ;
				$this->Qte = $Qte ;
				if ($TypeV==1){
					$this->PrixU = $this->Pdt->PrixVenteTTC ;
				}
				$this->TypeVente = $TypeV ;
				$this->Conditionnement=$this->Pdt->nbunite ;
			}
		}

		//Si la boutique dois appliquer des prix Calculé pour le détail et que le client n'est pas identifié
		if ($this->Conditionnement == 0){
			$this->Conditionnement=1 ;
			//echo "Conditionnement est = ".$this->Conditionnement ;
			//exit;
		}
		
	}
	
	public function SetPrixAchat(float $NewPC=0){
		//Correction du prix achat
		echo 'PrixAchat en correction ...' ;
		if (isset($this->Pdt)){
			//$this->Pdt->PrixAchat=$NewPC ;
			$this->Pdt->Enregistrer() ;
			//echo 'PrixAchat corrigé' ;
			exit;
			
		}
	}
	
	public function Calcule(){
		
		if ($this->TypeVente == 0){
			$PrixA=$this->Pdt->PrixAchatDetail ;
		}else{
			$PrixA=$this->Pdt->PrixAchatTTC ;
		}
		
		$PrixV=$this->PrixU ;

		
		$this->PrixTotal = $PrixV * $this->Qte ;
		$this->PrixTotal = round($this->PrixTotal,2) ;
		
		$this->PrixTotalAchat = $PrixA * $this->Qte ;
		$this->PrixTotalAchat = round($this->PrixTotalAchat,2) ;
		
		if ($this->AvecRemise > '0'){
			$this->ValeurRemise = $this->PourCRemise * ($this->PrixTotal/100);
			$this->ValeurRemise = round($this->ValeurRemise,2) ;
			$this->TotalAvecRemise = $this->PrixTotal - $this->ValeurRemise;
			$this->TotalAvecRemise = round($this->TotalAvecRemise,2);
			$this->PrixTotal = $this->TotalAvecRemise ;
		}
		//echo "xArticlePanier->Calcule Qte = ".$this->Qte ;
		//exit;
		return true ;
	}	
	public function CalculePC(){
		$this->PrixTotalAchat = $this->Pdt->PrixAchat * $this->Qte ;
		$this->PrixTotalAchat = round($this->PrixTotalAchat,2) ;
		
		if ($this->AvecRemise > '0'){
			$this->ValeurRemise = $this->PourCRemise * ($this->PrixTotalAchat/100);
			$this->ValeurRemise = round($this->ValeurRemise,2) ;
			$this->TotalAvecRemise = $this->PrixTotalAchat - $this->ValeurRemise;
			$this->TotalAvecRemise = round($this->TotalAvecRemise,2);
			$this->PrixTotalAchat = $this->TotalAvecRemise ;
		}
		
		return true ;
	}
}


?>