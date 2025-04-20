<?php
/**
 * Module Article pour NAbySy Gestion Commerciale
 * By Paul & Aïcha Machinerie
 * paul_isidore@hotmail.com
 */

namespace NAbySy\GS\Stock ;

use mysqli_result;
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\CodeBar\xCodeBarEAN13;
use NAbySy\ORM\xORMHelper;
use xPhoto;

Class xProduit extends xORMHelper
{
	public ?xBoutique $Boutique = null;
	//public string $DBase = "" ;
	public static array $TableConfig ;

	//public $RS ;
	
	public function __construct(\xNAbySyGS $NAbySy,int $Id=null,$AutoCreateTable=false,$TableName='produits', xBoutique $Boutique=null){
		self::$TableConfig=[];
		if (!isset($TableName)){
			$TableName="produits";
		}
		
		//var_dump($TableName);
		if ($this->LoadConfigFromFile('TableConfig.json')){
			if (isset(self::$TableConfig['Table'])){
				$TableName=self::$TableConfig['Table'];
			}
		}
		//var_dump($TableName);
		parent::__construct($NAbySy,$Id,$NAbySy::GLOBAL_AUTO_CREATE_DBTABLE,$TableName,$NAbySy->DataBase) ;
				
	}	
	
	public function GetProduit($Id=null,$Nom=null,$PrixAchat=null,$PrixVente=null,$CodeBar=null,$Ordre='Order By P.DESIGNATION'):mysqli_result{
		//Permet de lire un article par son Id ou $Nom
		//global $serveur,$user,$passwd,$bdd,$db_link,$MODULE ;
		$Table=$this->Table;
		$OK=false;
		$NbLigne=0;
		$sql="select P.*, R.Nom as 'Rayon', F.Nom as 'Famille' ";
		$sql=$sql."from ".$Table." P left outer join famille F on F.Id=P.IdFamille "; 
		$sql=$sql." left outer join rayon R on R.Id=P.IdFamille ";
		$sql=$sql." where P.Id>'0' " ;
		$crit="" ;
		if ($Id>0){
			$crit=$crit." AND P.Id='".$Id."' " ;
			$this->Id=$Id ;
		}
		if ($Nom !== null ){
			$vNom=$this->Main::$db_link->real_escape_string($Nom) ;
			$crit=$crit." AND P.Designation like '%".trim($vNom)."%' " ;
			$this->Designation=$Nom ;
		}
		if ($PrixAchat != null ){
			$crit=$crit." AND P.PrixAchatTTC = '".$PrixAchat."' " ;
		}	
		if ($PrixVente != null ){
			$crit=$crit." AND P.PrixVenteTTC = '".$PrixVente."' " ;
		}
		if ($CodeBar !== null ){
			//On va ajouter la prise en charge du Module xCodebar
			$xCodeB=new xCodeBarEAN13($this->Main->MaBoutique,$this->Main->MainDataBase) ;
			$vCodeBar=$this->Main::$db_link->real_escape_string($CodeBar) ;
			
			if ($xCodeB->IsCodePdt($CodeBar)){
				$CodeProduit=$xCodeB->GetCodePdt($vCodeBar) ;
				if (isset($CodeProduit)){
					return $this->GetProduit($CodeProduit) ;
				}
			}
			//---------------------------------------------------

			//On vérifie s'il correspond pas à un autre article avec code bar custumisé

			//-------------------------------------------------------------------------
			$crit=$crit." AND P.CodeBar like '".$vCodeBar."' " ;
			//var_dump($crit);
		}

		$sql=$sql.$crit.$Ordre ;
		//var_dump($sql);
		//var_dump($this->DataBase);
		$reponse=$this->ExecSQL($sql) ;
		if (!$reponse)
		{
			echo $this->Main->MODULE->Nom."Erreur interne de lecture des articles ...".$Id." - ".$Nom ;
			return $reponse ;
		}
		//var_dump($reponse);
		return $reponse;
	}
		
	
	public function AjouterStock(float $Qte, bool $ForBL=false,bool $InStockDetail=false){
		//On actualise dabord le stock on s'est jamais
		$Qte=(float)$Qte ;
		$this->Actualiser() ;
		$StockPrec=$this->Stock ;
		$StockSuiv=$this->Stock + $Qte ;
		$Tache="AJOUT AU STOCK [DEBUG]" ;
		$TxDet="";
		if($InStockDetail){
			$TxDet=" au détail ";
			$StockPrec=$this->StockDetail ;
			$StockSuiv=$this->StockDetail + $Qte ;
		}
		$TxJ="Le Stock".$TxDet." de ". $this->Designation." est passé de ".$StockPrec." à ".$StockSuiv." ".$this->united ;
		if($InStockDetail){
			if ($this->VENTEDETAILLEE=="OUI"){
				if ($this->STOCKINITDETAIL>0){
					$StockPrecC=$StockPrec/$this->STOCKINITDETAIL ;
					$StockSuivC=$StockSuiv/$this->STOCKINITDETAIL ;
					$TxJ.=" (Le stock".$TxDet." passe de ".$StockPrecC." à ".$StockSuivC.")" ;
				}
			}
		}
		$Note=$TxJ ;
		$TxDateV="";
		if ($ForBL){
			$TxDateV=", DERNIERELIVRAISON='".date("Y-m-d")."' " ;
		}
		//Ajoute Qte dans le stock du produit
		$sql="update ".$this->Table." A SET A.STOCK=A.STOCK+".$Qte.$TxDateV." Where A.Id='".$this->Id."' limit 1" ;
		if($InStockDetail){
			$this->StockDetail=(float)$this->StockDetail+(float)$Qte ;
			$sql="update ".$this->Table." A SET A.STOCKDETAIL=A.STOCKDETAIL+".$Qte.$TxDateV." Where A.Id='".$this->Id."' limit 1" ;
		}else{
			$this->Stock= (float)$this->Stock+$Qte ;
		}
		
		$isok=$this->ExecUpdateSQL($sql) ;
		
		
		//$this->Main->AddToJournal($_SESSION['user'],0,"Ajout dans le Stock de ".$this->Designation,"Qte ajoutĂ©= ".$Qte." 
		//Stock Prec: ".$StockPrec ) ;
		$this->AddToJournal($Tache,$Note) ;
		return true ;
		
	}

	public function RetirerStock(float $Qte, bool $ForVente=false,bool $InStockDetail=false){
		//On actualise dabord le stock on s'est jamais
		$Qte=(float)$Qte ;
		$this->Actualiser() ;
		$StockPrec=(float)$this->Stock ;
		$StockSuiv=(float)$this->Stock - $Qte ;
		$Tache="RETRAIT DU STOCK" ;
		$TxDet="";
		if($InStockDetail){
			$TxDet=" au détail ";
			$StockPrec=$this->StockDetail ;
			$StockSuiv=$this->StockDetail - $Qte ;
		}
		$TxJ="Le Stock".$TxDet." de ". $this->Designation." est passé de ".$StockPrec." à ".$StockSuiv." ".$this->united ;
		if ($this->VENTEDETAILLEE=="OUI"){
			if ($this->STOCKINITDETAIL>0){
				$StockPrecC=$StockPrec/$this->STOCKINITDETAIL ;
				$StockSuivC=$StockSuiv/$this->STOCKINITDETAIL ;
				$TxJ.=" (Le stock".$TxDet." passe de ".$StockPrecC." à ".$StockSuivC.")" ;
			}
		}
		$Note=$TxJ ;
		$TxDateV="";
		if ($ForVente){
			$TxDateV=", DERNIEREVENTE='".date("Y-m-d")."' " ;
		}
		//Retire Qte dans le stock du produit
		$sql="update ".$this->Table." A SET A.STOCK=A.STOCK-".$Qte.$TxDateV." Where A.Id='".$this->Id."' limit 1" ;
		if($InStockDetail){
			$this->StockDetail = (float)$this->StockDetail - $Qte ;
			$sql="update ".$this->Table." A SET A.STOCKDETAIL=A.STOCKDETAIL - ".$Qte.$TxDateV." Where A.Id='".$this->Id."' limit 1" ;
		}else{
			$this->Stock= (float)$this->Stock-$Qte ;
		}
		
		$isok=$this->ExecUpdateSQL($sql) ;	

		//$this->Main->AddToJournal($_SESSION['user'],0,"Retrait du Stock","Id Article: ".$this->Id." Qte= ".$Qte) ;
		$this->AddToJournal($Tache,$Note) ;
		return true ;
		
	}
	
	public function Actualiser(){
		$this->Refresh() ;
		return true ;
	}
	
	/**
	 * Retourne le Stock en gros
	 */
	public function GetStockGros():float{
		//Renvoie le stock en gros
		return (float)$this->Stock ;
	}
	/**
	 * Renvoie le Stock au Détail
	 */
	public function GetStockRestantDetail():float{
		return (float)$this->StockDetail ;
	}

	public function CanBeCmd($CanCmd=null){
		if (isset($CanCmd)){
			$Depot=$this->Main->MaBoutique->GetDepot() ;
			$PdtDepot=$Depot->GetArticle($this->Id) ;

			if ($PdtDepot->RS['INDISPONIBLE_FORCMD'] == $CanCmd){
				//Aucun chagement 
				return true ;
			}
			$Tache="DISPONIBILITE A LA COMMANDE DES BOUTIQUES" ;
			$TxJ="La disponibilité à la commande de ". $this->Designation." est passé à ".$CanCmd ;
			if ($PdtDepot->RS['INDISPONIBLE_FORCMD'] !== $CanCmd){
				$TxJ.=" (Etat précédent: ".$PdtDepot->RS['INDISPONIBLE_FORCMD'].")" ;
			}
			$Note=$TxJ ;
			$sql="update ".$PdtDepot->Table." A SET A.INDISPONIBLE_FORCMD=".$CanCmd." Where A.Id='".$PdtDepot->Id."' limit 1" ;
			$isok=$PdtDepot->Main->ReadWrite($sql,null,true) ;	
			$this->Boutique->AddToJournal($Tache,$Note) ;
			$this->RS['INDISPONIBLE_FORCMD'] = $PdtDepot->RS['INDISPONIBLE_FORCMD'] ;
			return true ;
		}

		return !$this->RS['INDISPONIBLE_FORCMD'] ;

	}

	public function ChargeCodeBar($CodeB){
		if (isset($CodeB)){
			$Depot=$this->Main->MaBoutique->GetDepot() ;
			
			if ($this->CodeBar == $CodeB){
				//Aucun chagement 
				return false ;
			}
			$Tache="MODIFICATION DU CODEBARRE" ;
			$TxJ="Le Code-Barre de ". $this->Designation." est passé de ".$this->CodeBar." à ".$CodeB ;
			$Note=$TxJ ;
			$sql="update ".$this->Table." A SET Code='".$CodeB."' Where A.Id='".$this->Id."' limit 1" ;
			$isok=$this->Main->ReadWrite($sql,null,true) ;

			$this->CodeBar=$CodeB ;
			$this->Boutique->AddToJournal($Tache,$Note) ;

			foreach ($this->Main->Boutiques as $Bout){
				if ($Bout->Id !== $this->Id){
					$PdtB=$Bout->GetArticle($this->Id) ;
					$PdtB->ChangeCodeBar($this->CodeBar) ;
				}
			}
			return true ;
		}

		return false ;

	}

	public function SavePhoto($ChampFichier='photo'){
		$Photo=new xPhoto($this->Main);
		$FileName=$this->Id.'photo.png' ;
		$Repo=$Photo->SaveToFile($ChampFichier,$FileName);
		return $Repo ;
	}

	public function GetPhoto($NoSendToClient=false){
		$Photo=new xPhoto($this->Main);
		$FileName=$this->Id.'photo.png' ;
		$vFileName=$this->Id.'photo.png' ;
		$DossierPhotos=$Photo->GetDossierPhoto() ;
		$FileName=$DossierPhotos.$this->Id.'photo.png' ;
		//On copie dans un dossier temporaire pour la sécurité
		$httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		//print_r($_SERVER);
		$DosTmp=$_SERVER['DOCUMENT_ROOT'].'/tmp' ;		

		if (!$NoSendToClient){
			$Photo->SendFile($FileName) ;
			return true ;
		}

		//echo $DosTmp ;

		if (!is_dir($DosTmp)){
			mkdir($DosTmp) ;
		}
		
		$File=$DosTmp.'/'.$vFileName ;
		$Site=$httpX.$_SERVER['SERVER_NAME'].'/tmp/'.$vFileName ;
		if (copy($FileName,$File)){
			//echo $httpX.$_SERVER['SERVER_NAME'] ;
			return $Site ;
		}

		return $Site;
	}

	public function PhotoExiste(){
		$Photo=new xPhoto($this->Main);
		$FileName=$this->Id.'photo.png' ;
		$DossierPhotos=$Photo->GetDossierPhoto() ;
		$FileName=$DossierPhotos.$this->Id.'photo.png' ;
		if (file_exists($FileName)){
            return true ;
        }
		return false ;
	}



	/**
	 * Permet de personnaliser les champs de la base de donnée à partir d'un fichier de configuration JSON
	 */
	public function LoadConfigFromFile($FichierConfig){
        if (!file_exists($FichierConfig)){
           return false ;
        }
        //On récupere la configuration
        $string = file_get_contents($FichierConfig);
        $Parametre = json_decode($string,true);
        // var_dump($Parametre);
        // var_dump($FichierConfig);
        if (isset($Parametre)){
            if (is_array($Parametre)){
                self::$TableConfig=$Parametre;
            }
        }
		return true;
    }
		
	
}

?>