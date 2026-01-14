<?php
namespace NAbySy\GS\Facture ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Panier\xCart;
use NAbySy\GS\Panier\xCartProForma;
use NAbySy\GS\Panier\xPanier;
use NAbySy\GS\Stock\xJournalCaisse;
use NAbySy\Lib\BonAchat\xBonAchatManager;
use NAbySy\ORM\xORMHelper;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;

//include_once '../NAbySyPhpApi/src/gs/facture/xVente/xDetailVente.class.php';
//include_once '../xVente/xDetailVente.class.php';
try {
	if(!class_exists('NAbySy\GS\Facture\xDetailVente')){
		include_once 'xDetailVente.class.php';
	}
} catch (\Throwable $th) {

}

//include_once '../../xVente/xDetailVente.class.php';

Class xProforma extends xORMHelper
{

	public xClient $Client;	
	public xBoutique $MaBoutique ;

	public xDetailVente $DetailVente ;
	
	public function __construct(xNAbySyGS $NAbySyGS,?int $IdFacture=null,$AutoCreateTable=false,$TableName='factureproforma',xBoutique $Boutique=null){
		$this->Main = $NAbySyGS ;
		$this->MaBoutique = $NAbySyGS->MaBoutique ;
		if (isset($Boutique)){
			$this->Main = $Boutique->Main ;
			$this->MaBoutique=$Boutique;
		}else{
			$Boutique=$NAbySyGS->MaBoutique;
		}
		if(!$Boutique->MySQL->TableExiste($TableName)){
			$TbleC="`".$Boutique->DBName."`.`".$TableName."`";
			$this->MaBoutique->ExecUpdateSQL("create table ".$TbleC." like facture");
		}
		if(!$Boutique->MySQL->TableExiste("detail".$TableName)){
			$TbleC="`".$Boutique->DBName."`.`detail".$TableName."`";
			$this->MaBoutique->ExecUpdateSQL("create table ".$TbleC." like detailfacture");
		}
		if(!$Boutique->MySQL->TableExiste($TableName)){
			$Boutique->AddToLog("ERREUR DE CREATION DE LA TABLE PROFORMA") ;
			return;
		}
		if(!$Boutique->MySQL->TableExiste("detail".$TableName)){
			$Boutique->AddToLog("ERREUR DE CREATION DE LA TABLE DETAILPROFORMA") ;
			return;
		}

		parent::__construct($NAbySyGS,$IdFacture,$AutoCreateTable,$TableName,$this->MaBoutique->DataBase) ;

		if(!$this->ChampsExisteInTable('REFCMD')) {
			$this->MySQL->AlterTable($this->Table, "REFCMD",'TEXT','ADD','',$this->DataBase);
		}

		$this->Client=new xClient($this->Main );
		//$this->DetailVente=new xDetailVente($this->Main,null,$AutoCreateTable,'detailfacture',$this->MaBoutique);
		if ($this->Id>0){
			$this->Client=new xClient($this->Main,$this->IdClient);
			$this->DetailVente=new xDetailVente($NAbySyGS,null,$AutoCreateTable, "detail".$TableName,$this->MaBoutique,$this->Id);
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
		$LDetailVente=new xDetailVente($this->Main,$IdDetail,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,'detail'.$this->Table,$this->MaBoutique,$Id);
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
				$this->NomCaissier=$row['Caissier'] ;
				$this->DateFacture=$row['DateFacture'] ;
				$this->HeureFacture=$row['HeureFacture'] ;
				$this->TotalFacture=$row['TotalFacture'] ;
				$this->Id=$row['IdFacture'] ;
				$this->ID=$IdVente ;
				$NewPanier->HeureFacture=$this->Heure ;
				$NewPanier->SaveInfosClient($row['NomClt'],$row['PrenomClt'],$row['IdClient'],$row['IdFacture']) ;
				$NewPanier->DateFacture($this->DateFacture);				
				//$NewPanier->Dump() ;
			}
			//Charge chaque ligne de la vente dans le Panier
			$ok=false ;
			$Qte=$row['QTE'];
			$vQte=$row['QTE'];
			$QG=(int)$row['VENTEDETAILLEE'];
			$IdPdt=$row['IdProduit'] ;
			$Designation=$row['Designation'] ;
			$PrixU=$row['PrixVente'] ;
			if ($QG == 0 && isset($row['nbunite'])){
				$Qte=$Qte*$row['nbunite'];
			}
			
			$ok=$NewPanier->addProduct($IdPdt,$Designation,$vQte,$PrixU,$QG,$NewPanier->IdClient) ;
		}
				
		
		
		//var_dump($_SESSION[$NewPanier->PanierId]) ;
		$InfoC=$NewPanier->PanierId."CLIENT" ;
		//var_dump($_SESSION[$InfoC]) ;
		return $NewPanier ;
				
	}
	
	public function Valider(xCart | xCartProForma $Panier){
		$Err=new xErreur;
		$Err->OK=0;
		$Err->TxErreur="Impossible de valider.";
		$Err->Source=__CLASS__ ;

		//$this->AddToLog("Nous allons valider le panier de la Proforma Ici: ".__FILE__." LIGNE ".__LINE__, 4);

		if (empty($Panier->getList())){
			$this->AddToLog("Panier de la Proforma Vide Ici: ".__FILE__." LIGNE ".__LINE__, 4);
			$Err->TxErreur="Panier Vide";
			return $Err;
		}

		//$this->AddToLog("Chargement xDetailVente Ici: ", 2);
		$TDetail=null;
		try {
			$Vente=new xVente($this->Main); //Force le chargement de xDetailVente
			$TDetail=new xDetailVente($this->Main, null,true,"detail".$this->Table, $this->MaBoutique);
		} catch (\Throwable $th) {
			$this->AddToLog("ERREUR: ".$th->getMessage());
		}
		if(!isset($TDetail)){
			$Err->TxErreur="ERR SYSTEME:Ligne DetailF introuvable !!!";
			$Err->SendAsJSON();
			return $Err;
		}
		//$TDetail=new xDetailVente($this->Main, null,true,"detail".$this->Table);
		$TxTableDet=$TDetail->FullTableName() ;
		//$this->AddToLog("Table xDetailVente: =".$TxTableDet, 2);
		
		if (!$TDetail->ChampsExisteInTable('STOCKSUIV')){
			$this->AddToLog("Création en cour de STOCKSUIV ...");;
			$this->MySQL->AlterTable($TDetail->Table,'STOCKSUIV',"INT(11)","ADD","0");
		}
		
		if (!$TDetail->ChampsExisteInTable('STOCKSUIV')){
			$TDetail->AddToLog("Champ STOCKSUIV impossible a créer");
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
				$Panier->MontantRendu=$Panier->MontantVerse - $Panier->getTotalNetAPayer();
			}
		}else{
				if ((float)$Panier->MontantVerse == 0){
					$Panier->MontantVerse=$Panier->getTotalNetAPayer() ;
				}
				$Panier->MontantRendu=$Panier->MontantVerse - $Panier->getTotalNetAPayer();
		}
		
		$Bout=null ;
		$TotalTVA=0;

		if ($Panier->IdFacture >0){	//Une facture en modification
			$DateFacture = $Panier->DateFacture();
			$HeureFacture = $Panier->HeureFacture;
			$PrecPanier=$this->ChargerPanier($Panier->IdFacture,true);	
			if($PrecPanier && is_object($PrecPanier)){
				$cNote="Total Facture Precedant:".$PrecPanier->getTotalNetAPayer();			
				$PrecPdtFacture=$PrecPanier->getList() ;
				$this->SupprimerPanier($PrecPanier) ;
			}

			$PdtFacture=$Panier->getList() ;
			$NbLigne=0;
			//exit ;

			$TxSQLFinale="";
			$EnteteSQL="insert into ".$TxTableDet." (IdFacture,IdProduit,QTE,PrixVente,StockSuiv,VENTEDETAILLEE,PRIXCESSION,DESIGNATION, PrixTotal, TVA) 
        	values ";

			foreach ($PdtFacture as $P){
					$NbLigne++ ;
					$vId=$P['vId'] ;
					$Article=$Panier->GetArticle($vId);
					$TVA=0;
					if ($Article){
						//Pdt additionnel trouver dans la Panier
						$vId=$P['vId'];
						//$Article=$Panier->GetArticle($vId);
						$Tache="PROFORMA EN MODIFICATION" ;
						$Tim=date("H:i:s");
						$Note="Saisie de ".$P['produit']." dans la facture n°".$Panier->IdFacture." en cour de modification." ;
						//echo "<script>console.log('".$Tim." : ".$Note."')</script>" ;
						$Pdt=$Article->Pdt ;
						if ($Pdt->STOCKINITDETAIL==0){$Pdt->STOCKINITDETAIL=1 ;}
						$vQte=$P['qte'] ;
						if ($P['typev']==1){
							$vQte=$P['qte']*$Pdt->STOCKINITDETAIL ;
						}
						$StockSuiv=(int)$Article->Pdt->Stock ;
						$PrixAchat = $Article->Pdt->PrixAchatTTC ;
						if ((int)$P['typev']==0){
							$PrixAchat=(int)$Article->Pdt->PrixAchatTTC / (int)$Pdt->STOCKINITDETAIL ;
						}
						$PrixVente=$P['PrixU'] ;
						if ($PrixVente == 0){
							$PrixVente=(int)$Article->Pdt->PrixVente ;
						}
						if (!isset($P['PrixU'])){
							$PrixVente=(int)$Article->Pdt->PrixVente ;
						}

						$PrixTotal=$P['qte']*$PrixVente;

						if ($Article->Pdt->RETIRER_TVA>0){
							$TauxTVA=(int)$Article->Pdt->TVA ;
							if ($TauxTVA==0){
								$TauxTVA=1;
							}
							$PrixHT=$PrixVente/(1+($TauxTVA/100));
							$vTVA=$PrixVente - $PrixHT;
							$TVA=round($vTVA,0)*$vQte;
							$TotalTVA +=$TVA;
						}

						$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
						$P['qte']."' , '".$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".$PrixAchat."','".$TDetail->Main::$db_link->real_escape_string( $P['produit'])."','".$PrixTotal."','".$TVA."'),";

						$TxSQLFinale .=$TxSQL ;
					}
				}
			
			if ($TxSQLFinale !== ''){

				$TxSQL="delete from ".$TxTableDet." where IdFacture='".$Panier->IdFacture."' " ;
				$this->Main->ReadWrite($TxSQL,true) ;

				$TxSQL=$EnteteSQL ;
				$TxF=substr($TxSQLFinale,0,strlen($TxSQLFinale)-1) ;
				$TxSQL .=$TxF." ;" ;				
				$this->Main->ReadWrite($TxSQL,true) ;

				$TxUpDte="update ".$TxTableDet." D inner join ".$this->FullTableName()." F on F.ID=D.IDFACTURE SET D.DATEFACTURE = F.DATEFACTURE , D.HEUREFACTURE = F.HEUREFACTURE where D.IDFACTURE='".$Panier->IdFacture."' " ;
				$this->Main->ReadWrite($TxUpDte,true) ;

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
			
			if (isset($PrecPanier) && is_object($PrecPanier)){
				$PrecPanier->Fermee=true ;
				$PrecPanier->DejaValider(true) ;
				$PrecPanier->Vider() ;
				unset ($PrecPanier) ;
			}
			
			
		}
		else{//Nouvelle Facture Proforma
			
			//$Panier->IdFacture=0 ;
			//$this->AddToLog("Nouvelle Pro Forma... ");
			$IdF=$this->SavePanierToDB($Panier,true) ; //Pour avoir un numero de facture
			
			if ($Panier->IdFacture !== $IdF){
				$this->AddToLog("Id Nouvelle Pro Forma... ".$IdF);
				$Panier->IdFacture = $IdF ;
			}
			
			if ($Panier->IdClient > 0){
				//Si le Client precedent est un Bon alors on corrige son solde
				if (!isset($Panier->Client)){
					$Client=new xClient($this->Main,$Panier->IdClient) ;
					$Panier->Client=$Client ;
				}
			}
				
			$Tache="Nouvelle proforma numero ".$Panier->IdFacture." avec ".$Panier->getNbProductsInCart()." article(s) " ;
			//On va supprimer les lignes de ventes eventuelle pour stocker les nouvelles
			$TDetail=new xDetailVente($this->Main, null,true,"detail".$this->Table);
			$TxTableDet=$TDetail->FullTableName() ;
		
			//Mise a jour des stocks avant enregistrement
			$TxSQLFinale="";
			$TVA=0;
			$TotalTVA=0;
			$NbLigne=0;

			$EnteteSQL="insert into ".$TxTableDet." (IdFacture,IdProduit,Qte,PrixVente,StockSuiv,VenteDetaillee,PRIXCESSION,DESIGNATION,DATEFACTURE,HEUREFACTURE, 
			PrixTotal, TVA ) 
			values ";

			foreach($Panier->getList() as $P){
				$NbLigne++;
				$vId=$P['vId'];
				$Article=$Panier->GetArticle($vId);
				$Note="Vente de ".$P['produit']." dans la facture numero ".$Panier->IdFacture ;
				if ($Article){
					$Pdt=$Article->Pdt ;
					if ($Pdt->STOCKINITDETAIL==0){$Pdt->STOCKINITDETAIL=1 ;}
					$vQte=(float)$P['qte'] ;
					if ($P['typev']==1){
						if ($Pdt->VENTEDETAILLEE == "OUI"){
							$vQte=(float)$P['qte']*(float)$Pdt->STOCKINITDETAIL ;
						}						
					}
				
					$P['produit']=$Article->Pdt->Designation ;
					$PrixAchat = $Article->Pdt->PrixAchatTTC ;
					if ((int)$P['typev']==0){
						$PrixAchat=(int)$Article->Pdt->PrixAchatTTC / (int)$Pdt->STOCKINITDETAIL ;
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

					$StockSuiv=(int)$Article->Pdt->Stock ;

					$TxSQL="('".$Panier->IdFacture."', '".$P['id_produit']."', '".
						$P['qte']."' , '".(int)$PrixVente."', '".$StockSuiv."', '".$P['typev']."','".(int)$PrixAchat."','".$this->Main::$db_link->real_escape_string( $P['produit'])."','".$TxDate."','".$TxHeure."','".$PrixTotal."','".$TVA."'),";

					$TxSQLFinale .=$TxSQL ;

					$this->AddToLog("Requette ECHAPPEE ICI: ".$this->Main::$db_link->real_escape_string( $P['produit']),2);
					
				}
			}

			if ($TxSQLFinale !== ''){	
				$TxSQL=$EnteteSQL ;
				$TxF=substr($TxSQLFinale,0,strlen($TxSQLFinale)-1) ;
				$TxSQL .=$TxF." ;" ;
				$this->AddToLog("Ajout ici Ligne Produit: ".$TxSQLFinale);
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
			$Vte=new xProforma($this->Main,$IdFacture, $this->Main::GLOBAL_AUTO_CREATE_DBTABLE);
			$Vte->TotalTVA=$TotalTVA;
			$Vte->NbLigne=$NbLigne;
			if ($Vte->IdClient==0){
				$Vte->IdClient=2; //pour compatibilité avec NAbySy et TechnoPharm
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
	
	private function SavePanierToDB(xCart | xCartProForma $Panier,$GetIdFacture=null){
		//Enregistre ou met á jour reelement le panier dans la base de donnee
		//Enregistrons l'entete de la facture
		
		if (!$Panier->getList()){
			$this->AddToLog("Aucune liste d'article dans la Pro Forma.");
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
		if (!$this->ChampsExisteInTable('MontantReduction')){
			$this->MySQL->AlterTable($TxTable,'MontantReduction','int(11)',"ADD",0, $this->DataBase);
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
		//$this->AddToLog("Pret a enregistrer la Pro Forma...".$this->FullTableName());
		if ($this->Enregistrer()){
			$Id=$this->Id;
			//$this->AddToLog("Id Pro Forma = ".$Id);
			$Panier->IdFacture=$this->Id;
			if($Panier->RefCMD !=='' && $this->REFCMD !== $Panier->RefCMD){
				$this->REFCMD = $Panier->RefCMD ;
				if($this->ChampsExisteInTable('REFCMD')) {
					$this->ExecUpdateSQL("update `".$this->DataBase."`.`".$this->Table."` SET REFCMD='".$this->Main::$db_link->escape_string($Panier->RefCMD)."' where ID=".$Panier->IdFacture ) ;
				}else{
					self::$xMain::$Log->AddToLog('Le champ REFCMD est introuvable dans la table '.$this->Table) ;
				}
			}		
		}else{
			$this->AddToLog("Erreur lors de l'enregistrement de la Pro Forma.");
		}
		
		//$this->AddToLog("Sauvegarde InfoClient la Pro Forma.");
		$Panier->SaveInfosClient($Panier->NomClt,$Panier->PrenomClt,$Panier->IdClient,$Panier->IdFacture ) ;
		if (isset($GetIdFacture)){
			if ($GetIdFacture){
				return $Id ;
			}
		}
		//$this->AddToLog("Id Pro Forma Trouvé = ".$Id);

		/*Pour ne pas conserver la date de la facture et enregistrer la date de modification */
		if ($IsUpDate){
			$TxDate=$DateFacture ;
			$TxHeure=date('H:i:s') ;
			$this->DateFacture=$TxDate;
			$this->HeureFacture=$TxHeure;
			$this->Enregistrer();
		}

		$Panier->Fermee=true ;	
		return true ;
		
		
	}

	public function SupprimerPanier(xCart | xCartProForma $PanierToSup){
		if (!isset($PanierToSup)){
			return false ;
		}
		$Bout=null ;
		//Supprime un panier. Si le Panier à une Facture liée alors on supprime la facture aussi
		if ($PanierToSup->IdFacture>0){
			$PrecFact=new xProforma($this->Main,$PanierToSup->IdFacture);
			$DetailVente =new xDetailVente($this->Main,null,$this->AutoCreate, "detail".$PrecFact->Table,$this->MaBoutique,$this->Id);
			//Le panier est lié à une facture
			//On supprime la facture ligne par ligne ->getList()
			$NbFois=0 ;
			$Tache="Suppresion de Panier avec la proforma n°".$PanierToSup->IdFacture ;
			foreach($PanierToSup->getList() as $PrecP){ 
				$vId=$PrecP['vId'];
				$TypeVente="Pièces" ;
				if (isset($PrecP['typev']) && $PrecP['typev']==1){
					$TypeVente="Carton(s)";
				}
				
				$Note="" ;
				$P=$PrecP ;
				
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

						//On va supprimer la ligne de vente supprimer du panier
						$TxTableDet=$DetailVente->FullTableName();
						$TxSQL="delete from ".$TxTableDet." where IdFacture='".$PanierToSup->IdFacture."' and IdProduit='".$Article->Pdt->Id."'" ;
						$this->Main->ReadWrite($TxSQL,true) ;
						
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
		if ($PanierToSup->IdFacture>0){
			$TotalF=$PanierToSup->getTotalPriceCart() ;
			$TxTable=$this->FullTableName() ;
			$TxSQL="UPDATE ".$TxTable." SET TotalFacture='".$TotalF."' where id='".$PanierToSup->IdFacture."' limit 1" ;
			$this->Main->ReadWrite($TxSQL,true) ;
		}
		return true ;
	}

	public function SupprimerProForma($IdFacture=null){
		if (!isset($IdFacture)){
			$IdFacture=$this->Id;
		}
		$client= "0" ;
		//On ramene les qtés sortie dans le stock des boutiques clientes
		$Panier=new xPanier($this->Main) ;
		$Panier->Charger($IdFacture) ;
		$Tache ="SUPPRESSION PROFROMA" ;
		$Note = "Suppression de la proforma n°".$IdFacture." du Client=".$Panier->Client->Nom." (Ma Boutique=".$this->Main->MaBoutique->Nom.")" ;
		//$This->Main->AddToJournal($_SESSION['user'],'0',$Tache,$Note) ;
		$this->Main->MaBoutique->AddToJournal($Tache,$Note) ;
		
		//On va supprimer la la facture pro forma
		$DetailVente =new xDetailVente($this->Main,null,$this->AutoCreate, "detail".$this->Table,$this->MaBoutique,$this->Id);

		$TxTableDet=$DetailVente->FullTableName() ;
		$TxSQL="delete from ".$TxTableDet." where IDFACTURE='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		$TxTable=$this->FullTableName() ;
		$TxSQL="delete from ".$TxTable." where ID='".$IdFacture."' " ;
		$this->Main->ReadWrite($TxSQL,true) ;
		//------------------------------------------------------------------	

		return true ;

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
			$DetailVente =new xDetailVente($this->Main,null,$this->AutoCreate, "detail".$this->Table,$this->MaBoutique,$this->Id);
			$DetailV=$DetailVente->GetFullInfosProformaByLine($this->Id);
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