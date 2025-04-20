<?php
namespace NAbySy\GS\Facture ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\Lib\BonAchat\IBonAchatManager;
use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Gestion des Lignes de facture
 */
Class xDetailVente extends xORMHelper{

    /**
     * Contient un tableau des lignes des articles de la facture en cour
     */
    public array $ListeProduits ;
    public array $ListeMethodePaie ;
    
    public function __construct(xNAbySyGS $NAbySyGS,$Id=null,$AutoCreateTable=false,$TableName='detailfacture',
        xBoutique $Boutique=null,$IdFacture=null, $FullInfos=true){
        $DataBase=$NAbySyGS->MaBoutique->DataBase;
        if (isset($Boutique)){
            $DataBase=$Boutique->DataBase;
        }
        if (!isset($TableName)){
            $TableName='detailfacture';
        }
		parent::__construct($NAbySyGS,$Id,$AutoCreateTable,$TableName,$DataBase) ;

        $this->ListeProduits=[];

		$Facture=new xVente($this->Main);
        
		if ($IdFacture>0){
			$sql="select E.ID as 'IdFacture',E.DateFacture, E.HeureFacture,E.TotalFacture,
			E.IdClient,E.MODEREGLEMENT,
			E.IDCAISSE, E.MontantVerse, E.MontantRendu, ";
            $sql .="C.Prenom as 'PrenomClt', C.Nom as 'NomClt',D.IdProduit,u.Login as 'Caissier',E.IdCaissier,
                D.Designation,D.PrixVente,D.PRIXCESSION,
                D.Qte,D.VenteDetaillee,C.Tel,C.Solde, C.Avoir, D.* ";
            $sql .=" from ".$this->Table." D left outer join ".$Facture->Table." E on D.IdFacture=E.ID "; 
            $sql .=" left outer join ".$this->Main->MaBoutique->DBase.".".$Facture->Client->Table." C on C.Id=E.IdClient " ;
            $sql .=" left outer join ".$this->Main->MaBoutique->DBase.".utilisateur u on u.id=E.IdCaissier " ;
            $sql .=" where E.ID = ".$IdFacture ;

            $Lst=$this->ExecSQL($sql);
            if ($Lst->num_rows){
                while ($row=$Lst->fetch_assoc()){
                    $this->ListeProduits[]=$row;
                }
            }

            /** On Ajoute la liste des Méthodes de Paiement */

            /* @var ClassName[] $ModPaie xNAbySyGS\GS\Facture\IModulePaieManager */
            foreach($this->Main::$ListeModulePaiement as $ModPaie ){
                if (is_a($ModPaie,'xNAbySyGS\GS\Facture\IModulePaieManager') ){
                    $LPaiement=$ModPaie->GetDetailFacture($IdFacture);
                    if (count($LPaiement)>0){
                        $this->ListeMethodePaie[]=$LPaiement;
                    }
                }
            }
		}
		
	}

    /** Retourne le nombre de Carton dans la facture */
    public function NbCarton(){
		$Nb=0 ;
		foreach ($this->ListeProduits as $Article){	
			//var_dump($Article->TypeVente.' Qte='.$Article->Qte.' NbUnite='.$Article->Pdt->nbunite) ;
			//echo "</br>" ;
            //var_dump($Article);
			if ($Article['VenteDetaillee'] == 'OUI'){
                $Pdt=new xProduit($this->Main,$Article['IdProduit']);
				$NbUnite=(int)$Pdt->StockInitialDetail ;
				if ($NbUnite<=0){
					$NbUnite=1;
				}
				$QteG=(int)$Article['Qte'] / $NbUnite ;				
				$Nb +=$QteG ;
			}else{
                $Nb +=$Article['Qte'] ;
            }		
		}
		return $Nb ;
	}

    /** Retourne le nombre de pièce dans la facture */
	public function NbDetail(){
		$Nb=0 ;
		foreach ($this->ListeProduits as $Article){			
			if ($Article['VenteDetaillee'] == 'OUI'){
                $Pdt=new xProduit($this->Main,$Article['IdProduit']);
				$NbUnite=(int)$Pdt->StockInitialDetail ;
				if ($NbUnite<=0){
					$NbUnite=1;
				}
                if ($Article['Qte']> $NbUnite){
                    $QteD=(int)$Article['Qte'] % $NbUnite ;	
                }else{
                    $QteD=(int)$Article['Qte'] ;
                }							
				$Nb +=$QteD ;
			}		
		}
		return $Nb ;
	}

    /**
     * Retourne les infos de facturation détaillé par ligne de produit
     * comprenant éventuellement les modes de reduction.
     */
    public function GetFullInfosFactureByLine($IdFacture=null):array{
        if (!isset($IdFacture)){
            $IdFacture=$this->IDFACTURE;
        }
        $Facture=new xVente($this->Main);
        $ReponseListeProduits=[];

        $sql="select E.*, D.Id as 'IdFacture', D.Designation,D.PrixVente,D.PRIXCESSION,
        D.Qte,D.VenteDetaillee, D.TVA, D.StockSuivant, D.IdProduit,
        C.Prenom as 'PrenomClt', C.Nom as 'NomClt',D.IdProduit,u.Login as 'Caissier',
        C.Tel,C.Solde, C.Avoir ";
        $sql .=" from ".$this->Table." D left outer join ".$Facture->Table." E on D.IdFacture=E.ID "; 
        //$sql .=" left outer join article A on D.Id_article=A.ID ";
        $sql .=" left outer join ".$this->Main->MaBoutique->DBase.".".$Facture->Client->Table." C on C.Id=E.IdClient " ;
        $sql .=" left outer join ".$this->Main->MaBoutique->DBase.".utilisateur u on u.id=E.IdCaissier " ;
        $sql .=" where E.ID = ".$IdFacture ;
        //var_dump($sql);
        $Lst=$this->ExecSQL($sql);
        if ($Lst->num_rows){
            while ($row=$Lst->fetch_assoc()){
                //On ajoute eventuellement le paiement par bon achat
                if ((int)$row['MontantReduction']){
                    foreach ($this->Main::$ListeModuleBonAchat as $ModBon){
                        if ($ModBon instanceof IBonAchatManager ){
                            $DetBon=$ModBon->GetDetailFacture($IdFacture);
                            foreach($DetBon as $Key => $Valeur){
                                $row[$Key]=$Valeur;
                            }
                        }
                    }
                }
                /******************************************************* */
                $ReponseListeProduits[]=$row;
            }
        }

        return $ReponseListeProduits;
    }

}
?>