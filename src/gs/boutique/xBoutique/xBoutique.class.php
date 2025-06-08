<?php
namespace NAbySy\GS\Boutique;

use Exception;
use mysqli;
use mysqli_result;
use NAbySy\GS\Panier\xCart;
use NAbySy\GS\Panier\xCartProForma;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

Class xBoutique extends xORMHelper  {
	public mysqli $Conn ;
	public xORMHelper $Parametre ;

	public const TABLE_PARAMETRE = 'parametre';

	public function __construct(xNAbySyGS $NAbySy, ?int $IdBoutique=0,$AutoCreateTable=false,$NomTable=null, string $BoutiqueDBName=null){
		$this->Conn = $NAbySy::$db_link;
		if (!isset($NomTable)){
			$NomTable='boutique';
		}
		if (!isset($BoutiqueDBName)){
			$BoutiqueDBName=$NAbySy->DataBase ;
		}
		if($BoutiqueDBName==""){
			$BoutiqueDBName=$NAbySy->DataBase ;
		}
		parent::__construct($NAbySy,$IdBoutique,$NAbySy::GLOBAL_AUTO_CREATE_DBTABLE,$NomTable,$BoutiqueDBName) ;

		if(isset($NAbySy->MaBoutique)){
			if (!$this->MySQL->ChampsExiste($this->Table,'LOGO_TICKET')){
				$this->MySQL->AlterTable($this->Table,'LOGO_TICKET');
			}
			$this->Parametre=new xORMHelper($NAbySy,1,$NAbySy::GLOBAL_AUTO_CREATE_DBTABLE,self::TABLE_PARAMETRE,$BoutiqueDBName);
			if ($this->Parametre->Id > 0){
				//$this->AddToLog("Table Paramètre de la boutique Id ".$IdBoutique."=".$this->Parametre->ToJSON());
			}else{
				//$this->AddToLog("Table Paramètre vide sur la boutique Id ".$IdBoutique.". Id Param = 1");
			}
		}
	}

	/** Retourne Vrai si la boutique est un Dépot */
	public function IsDepot(){
		if ($this->Id==0){
			return false;
		}
		if ($this->Isboutique ==0 || $this->IdCompteClient==0){
			return true;
		}
		return false;
	}

	/** Retourne Vrai si la boutique n'est pas un Dépot */
	public function IsBoutique(){
		if ($this->Id==0){
			return false;
		}
		if ($this->Isboutique ==0 || $this->IdCompteClient==0){
			return false;
		}
		return true;
	}

	/**
	 * Retourne une liste des boutiques disponible dans NAbySy GS et Point of Sell
	 * @param string $Critere : Critère sans le mot clé WHERE
	 * @return mysqli_result
	 */
	public function getListeBoutique($Critere=null):?mysqli_result{
		$TxTable=$this->Main->MasterDataBase.".".$this->Table;;
		$TxSQL="select * from ".$TxTable." B where B.ID>0 ";
		if (isset($Critere)){
			$TxSQL .=" AND ".$Critere ;
		}		
		$Lst= $this->Main->ReadWrite($TxSQL);
		if ($Lst instanceof mysqli_result){
			return $Lst ;
		}
		return null;
	}

	/**
	 * Retourne une liste des Depots disponible dans NAbySy GS 
	 * @param string $Critere : Critère sans le mot clé WHERE
	 * @return ?mysqli_result
	 */
	public function getListeDepot($Critere=null):?mysqli_result{
		$TxTable=$this->Main->MasterDataBase.".".$this->Table;
		$TxSQL="select * from ".$TxTable." B where B.ID>0 and B.IdCompteClient=0 ";
		if (isset($Critere)){
			$TxSQL ." AND ".$Critere ;
		}
		$Lst= $this->Main->ReadWrite($TxSQL);
		if ($Lst instanceof mysqli_result){
			return $Lst ;
		}
		return null;
	}

	/**Retourne le nom complet de la table boutique comprenant le nom de la base de donnée */
	public function vTableName():string{
		return $TxTable=$this->DBName.".".$this->Table;
	}
	
	public function CreerArticle(xProduit $Article,$AvecStock=null){
		//Créer un article dans la boutique
		if (!$Article->Id > 0){
				//Article introuvable
				return false ;
		}
		
		$sql="insert ignore into ".$Article->Table." (select * from ".$Article->TEntete." A where A.Id='".$Article->Id."') "  ;
		$isok = $this->Main->ReadWrite($sql,true, $Article->Table) ;
		
		$Tache="NOUVEL ARTICLE ".$this->Nom ;
		$Note=$Article->Designation." ajouté dans la boutique ".$this->Nom." : PrixAchatCarton=".$Article->PrixAchatCarton." 
		 ; PrixAchatPiece=".$Article->PrixAchat." et Stock=".$Article->Stock ;
		$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
		$this->AddToJournal($Tache,$Note) ;
		
		//Verification du Nouvelle Id pourqu'il soit conforme partout !!
		$NewIdB=$isok ;
		if ($NewIdB<>$Article->Id){
			//MAJ de l'autoincrement du stock de la boutique
			$Note="MAJ auto-increment de la table article de la boutique " ;
			$Tache="DEBUG_CreerArticle" ;
			$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
			
			$sqli="delete from ". $Article->Table." where id=".$NewIdB." limit 1" ;
			$miseajour=$this->Main->ReadWrite($sql,null,true) ;
			
			$sqli="update table ". $Article->Table." SET AUTO_INCREMENT=".$Article->Id ;
			$miseajour=$this->Main->ReadWrite($sql,null,true) ;
			$Note="Auto Increment est passé à ".$Article->Id ;
			$Tache="DEBUG_CreerArticle" ;
			$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;			
			
			$sql="insert ignore into ". $Article->Table." (select * from ".$Article->TEntete." A where A.Id='".$Article->Id."') "  ;
			$isok = $this->Main->ReadWrite($sql,true, $Article->Table) ;
			
			$Note=$Article->Nom." ajouté avec Id=".$isok." Cet article a pour Id au Depot=".$Article->Id." et Stock=".$Article->Stock ;
			$Tache="CreerArticle" ;
			$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;	
		}
		
		//--------------------------------------------------------------
		if (!$AvecStock){
			$sql="update ". $Article->Table." SET quantite=0 where id='".$Article->Id."' limit 1" ;
			$this->Main->ReadWrite($sql,null,true) ;
		}
		
		//On MAJ les prix de revients dans la Boutique
		
		
		$NewPdt = $this->GetArticle($isok) ;
		if ($NewPdt){
			$Depot=$this->GetDepot() ;
			$PdtDepot=$Depot->GetArticle($Article->Id) ;
			$NewPdt->Id = $PdtDepot->Id ;
			$NewPdt->PrixAchatCarton = $PdtDepot->PrixVenteCarton ;
			$NewPdt->PrixAchat = $PdtDepot->PrixVente ;
			$NewPdt->Enregistrer() ;
			
			$Tache="NOUVEL ARTICLE ".$this->Nom ;
			$Note=$NewPdt->Designation." ajouté dans la boutique ".$this->Nom." : PrixAchatCarton=".$NewPdt->PrixAchatCarton." 
			 ; PrixAchatPiece=".$NewPdt->PrixAchat." et Stock=".$NewPdt->Stock ;
			$this->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
			$this->AddToJournal($Tache,$Note) ;
		}			
		return $NewPdt ;
	}
	public function SupprimerArticle(xProduit $Article){
		//Supprime un article dans la boutique
		if (!$Article->Id > 0){
				//Article introuvable
				return false ;
		}
		//On enregistre dans le journal
		$Tache="Suppression Article Boutique ".$this->Nom ;
		$Note=$Article->Designation." a été supprimé et son stock était = ".$Article->Stock ;
		if ($Article->nbunite>1){
			$NbCarton=$Article->Stock / $Article->nbunite ;
			$NbPcs=$Article->Stock % $Article->nbunite ;
			$Note .=" (".$NbCarton." ".$Article->unitec." ".$NbPcs." ".$Article->united.")" ;
		}
		
		$sql="delete from ". $Article->Table." where Id='".$Article->Id."' limit 1 "  ;
		$isok = $this->Main->ReadWrite($sql,null,true) ;

		$this->Main->AddToJournal($_SESSION['user'],0,$Tache,$Note) ;
		$this->AddToJournal($Tache,$Note) ;
		
		return $isok ;
	}	
	public function ListeArticle($crit = null, xBoutique $Depot=null){
		//Correction des date expirations
		$Article=new xProduit($this->Main);
		$sql="update ". $Article->Table." A set A.date_expiration='2050-01-01' where date_expiration like ''";
		$this->Main->ReadWrite($sql,null,true) ;
		
		//Charge la liste des articles de la boutique
		$sql="select A.*, C.nom as 'categorie', R.nom as 'rayon' from ". $Article->Table." A 
		left outer join categorie C on C.id=A.id_categorie
		left outer join rayon R on R.id=A.id_rayon
		where A.Id>0 " ;

		if (isset($Depot)){
			'Ajoute les prix de revient du Depot' ;
			$TableDepot=$Depot->TableArticle ;

			$sql="select A.*,D.prixrc as 'PrixAchatDepot', D.prix_revient as 'PrixAchatPcsDepot', C.nom as 'categorie', R.nom as 'rayon' from ". $Article->Table." A 
			left outer join ".$TableDepot." D on D.id=A.id
			left outer join categorie C on C.id=A.id_categorie
			left outer join rayon R on R.id=A.id_rayon
			where A.Id>0 " ;			
		}

		if ($crit){
			$sql=$sql." AND ".$crit ;
		}
		
		$sql .=" order by A.nom " ;
		$liste = $this->Main->ReadWrite($sql) ;
		return $liste ;		
		
	}
	
	public function SuggestionCmd($DateDepart=null,$DateFin=null,$IdBoutique=null,$IdCaissier=null,$IdCategorie=null,$IdRayon=null,$ConvertToVente=false){
		//Charge la liste des articles vendu de la boutique
		$Article =new xProduit($this->Main);
		$sql="select V.date as 'DateFacture',sum(D.quantite) as 'Qte',D.Id_Article as 'IdProduit',D.typev, D.typev as 'VenteGros',
		A.nom as 'Designation', A.code as 'CodeBar',A.quantite as 'StockAct',A.united,A.unitec,A.nbunite, D.prix, D.prix as 'PrixVente', 
		r.nom as 'Rayon', c.nom as 'Categorie', D.StockCarton as 'INV_StockCarton', D.StockDetail as 'INV_StockDetail',
		D.StockDetailMachine, D.StockCartonMachine 
		from ".$this->DBase.".ligne_vente D " ;
		$sql=$sql." left outer join ".$this->TableVente." V on V.id=D.id_vente " ;
		$sql=$sql." left outer join ". $Article->Table." A on A.id=D.Id_Article " ;
		$sql=$sql." left outer join ".$this->DBase.".categorie c on c.id=A.id_categorie " ;
		$sql=$sql." left outer join ".$this->DBase.".rayon r on r.id=A.id_rayon " ;
		$sql=$sql." where D.Id>0 " ;
		
		//echo $sql ;
		//exit ;
		if (!$DateFin){
			if ($DateDepart){
				$sql=$sql." AND V.date = '".$DateDepart."' " ;
			}
		}else{
			if ($DateDepart){
					$sql=$sql." AND V.date >= '".$DateDepart."' AND V.date <= '".$DateFin."' " ;
				}
				else{
					$sql=$sql." AND V.date <= '".$DateFin."' " ;
				}
		}		
		
		if (isset($IdCaissier)){
			$sql=$sql." AND V.id_caissier ='".$IdCaissier."' " ;
		}		
		if (isset($IdCategorie)){
			$sql=$sql." AND A.id_categorie ='".$IdCategorie."' " ;
		}	
		if (isset($IdRayon)){
			$sql=$sql." AND A.id_rayon ='".$IdRayon."' " ;
		}	


		$sql=$sql." Group By D.Id_Article,D.typev " ;	
		$sql=$sql." Order By 'Categorie',A.nom " ;

		$liste = $this->Main->ReadWrite($sql) ;
		return $liste ;		
		
	}	
	
	public function GetKSSV_IdCaisse(){
		$date = date('Y-m-d');
		$sql="select o.id as id_caisse from ouverture_caisse o where o.ouvert='1' and  id_caissier=".$_SESSION['id_user']." ORDER BY o.id DESC ";
		$resultat=$this->Main->ReadWrite($sql);
		if (!$resultat){
			//Aucune caisse ouverte dans KSSV Soft, on redirige vers l(ouverture de caisse)
			header("Location:../vues/ouverture_caisse.php");
		}
		$data=$resultat->fetch_assoc();
		$id_caisse=$data['id_caisse'];
		if (!isset($id_caisse)){
			$id_caisse = '0' ;
		}
		return $id_caisse ;
	}
	
	public function GetArticle($IdProduit=null,$Designation=null,$PrixAchat=null,$PrixVente=null){
		$Produit = new xProduit($this->Main) ;
		$Produit->GetProduit($IdProduit,$Designation,$PrixAchat,$PrixVente) ;
		return $Produit ;
		
	}
	
	public function GetArticleByName($Designation,$PrixAchat=null,$PrixVente=null){
		$Produit = new xProduit($this->Main) ;
		$Produit->GetProduit(null,$Designation,$PrixAchat,$PrixVente) ;
		return $Produit ;
		
	}

	public function GetDepot($Id=null){
		$TxI="" ;
		if (isset($Id)){
			if ($Id>0){
				$TxI=" AND ID='".$Id."' " ;
			}			
		}
		if(!$this->TableExiste()){
			//La table n'existe pas, on la crée
			$this->Main->CreateTable($this->Table) ;
			$this->IdCompteClient=0 ;
			$this->Nom = $this->Main->MODULE->MCP_CLIENT ;
			$this->Isboutique=0 ;
			$this->IsDepot=1 ;
			$this->MasterDataBase = $this->Main->MasterDataBase ;
			$this->DBName = $this->Main->MasterDataBase ;
			$this->DBase = $this->Main->MasterDataBase ;
			$this->Serveur = $this->Main->db_serveur ;
			$this->DBUser = $this->Main->db_user ;
			$this->DBPassword = $this->Main->db_pass ;
			$this->Actif = 1;
			$this->Enregistrer() ;
		}
		$TxSQL="select Id,Nom from ".$this->Main->MasterDataBase.".boutique where IdCompteClient='0' ".$TxI." " ;
		$OK=false;
		//var_dump($TxSQL);
		$reponse=$this->Main->ReadWrite($TxSQL) ;
		if ($reponse->num_rows==0)
			return null ;
		
		$row = $reponse->fetch_assoc() ;
		
		$IdDepot = $row['Id'] ;
		$BoutiqueDepot = new xBoutique($this->Main,$IdDepot,false,'boutique',$this->MasterDataBase) ;
		return $BoutiqueDepot ;
	}

	/**
	 * Retourne un objet Panier
	 */
	public function GetNewPanier($IdSession=null,$IsTemp=null,$Proforma=null){
		//$this->ChargeListePanier();
		$NbPanier=0 ;
		$PanierIdR='panier' ;
		if (!isset($Proforma)){
			$NbPanier=count($this->ListePanier) ;
		}else{
			$NbPanier=count($this->ListePanierProforma) ;
			$PanierIdR='panierproforma' ;
		}
		
		$MAX=200 ;
		$Tmp='' ;
		if (isset($IsTemp)){
			$Tmp='temp' ;
		}
		for ($i=1 ; $i <= $MAX ;$i++){
			$PanierIdR .=$i.$Tmp ;
			//echo "</br>Je recherche un nouveau Id Panier...".$i ;
			if (!isset($_SESSION[$PanierIdR])){
				$NewIdPanier=$i.$Tmp ;
				//echo "</br> Id Panier libre:".$NewIdPanier ;
				break ;
			}
		}
		if (isset($IdSession)){
			$NewIdPanier=$IdSession ;
		}
		if (!isset($Proforma)){
			$NewPanier=new xCart($this,$NewIdPanier,$IsTemp);
		}else{
			$NewPanier=new xCartProForma($this,$NewIdPanier,$IsTemp);
		}
		//$NewPanier->Id=$NewId ;
		if ($NewPanier){
			$NewPanier->Existe=true;
			$NewPanier->DejaValider(false) ;
			array_push($this->ListePanier,$NewPanier);
		}
		return $NewPanier ;
	}

	/**
	 * Retourne l'entete des facture au forma tableau
	 */
	public function GetEnteteArray():array{
		$Rep=[];
		$Sep="*--*";
		try{
			//var_dump($this->IMP_LIGNE);
			$Tab=explode($Sep, $this->IMP_LIGNE);
			$Rep=$Tab ;
			if (count($Tab)){
				$ListeC=[];
				$i=0;
				foreach($Tab as $Nom => $Valeur){
					$ListeC['LIGNE'.$i]=$Valeur;
					$i++;
				}
				$ListeC['LOGO']=$this->GetLogoUrl();
				return $ListeC;
			}
		}catch(Exception $ex){
			//Ligne ou donnée incompatible
			$Rep[]=$ex->getMessage();
		}

		$httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		//On ajoute l'url du logo ?
		$Rep['LOGO']=$this->GetLogoUrl();
		/******************************* */
		//var_dump($Rep);
		return $Rep ;
	}

	/**
	 * Imprime l'entete de facture A4 pour la boutique
	 * @param $pdf  : Le moteur PDF en cour d'utilisation 
	 * @return int : La position Y aprés la sorite de l entete
	 */
	public function GetEntetePDF($pdf):int{
		//Coordonnées dynamique des Boutiques
		$NB_LIGNE=count($this->GetEnteteArray()) ;
		$Font="Arial" ;
		$Italic ="" ;
		$Tail=12 ;
		$PosX=60;
		$PosY=42+6;
		$Col=array(0,0,0);
		for ($i=0;$i<$NB_LIGNE;$i++){
			$Font="Arial" ;
			$Italic ="" ;
			$Tail=12 ;
			$Col=array(0,0,0);
			$Text=$this->IMP_LIGNE[$i] ;
			//echo "N°".$i."=".$Text."</br>" ;
			if ($i==0){
				//Entete K.S.S.V
				$Italic="I" ;
				$Tail=24 ;
				$Col=array(0,0,128);
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text(87,'14',utf8_decode($Text));
			}
			
			if ($i==1){
				//Entete K.S.S.V
				$Italic="I" ;
				$Tail=13 ;
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text(71,'19',utf8_decode($Text));
			}

			if ($i==2){
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text(79,'24',utf8_decode($Text));
			}

			if ($i==3){
				//Entete K.S.S.V
				$Italic="I" ;
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text('68','30',utf8_decode($Text));
			}
			if ($i==4){
				//Entete K.S.S.V
				$Italic="I" ;
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text('45','36',utf8_decode($Text));
			}	
			if ($i==5){
				//Entete K.S.S.V
				$Italic="I" ;
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text('60','42',utf8_decode($Text));
			}	
			$PosX=60;
			$PosY=42+6;
			if ($i>=6){
				//$Italic="I" ;
				$pdf->SetTextColor($Col[0], $Col[1],$Col[2]);
				$pdf->SetFont($Font,$Italic,$Tail);
				$pdf->Text($PosX,$PosY,utf8_decode($Text));
			}
			
		}
		$PosY+=4 ;
		$pdf->SetTextColor(0, 0,0);
		$pdf->SetDrawColor(0,0,128);
		$pdf->SetLineWidth(0.5);
		$pdf->Line(0,$PosY,220,$PosY);
		
		return $PosY ;
	}

	public function GetLogoUrl():string{
		$httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		//On ajoute l'url du logo ?
		$Lien="";
		if ($this->LOGO_TICKET !==''){
			$Logo=$this->LOGO_TICKET;
			//On vérifie si le fichier existe
			if (file_exists("./logos/")){				
				if (file_exists("./logos/".$Logo)){
					//var_dump($Logo);
					$Lien=$httpX.$_SERVER['SERVER_NAME']."/logos/".$Logo ;
					$Lien;
				}elseif (file_exists("./logos/logo.png")){
					$Lien=$httpX.$_SERVER['SERVER_NAME']."/logos/logo.png" ;
				}
			}
		}
		/******************************* */

		return $Lien;
	}
	
}



?>