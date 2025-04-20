<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use DateTime;
use NAbySy\Lib\ModuleExterne\OilStation\Structure\xInfoControlStock;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;
use xNotification;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 */
class xCuveStockageCarburant extends xORMHelper {

    public const FAMILLE_CARBURANT = "Carburant";
    public const FAMILLE_LUBRIFIANT= "Lubrifiant";

    public const CARBURANT_ESSENCE = "Essence";
    public const CARBURANT_GASOIL = "Gasoil";
    public const CARBURANT_GAZ = "Gaz";
    public const CARBURANT_KEROZENE_JET_A1 = "JET-A1";

    /**
     * Liste des nom d'articles pour la liaison avec NAbySyGS
     * @var array
     */
    public static array $FamilleProduitStation=[];

    /**
     * Liste des produits de la sation par famille
     * ListePdtStation['CARBURANT']
     * ListePdtStation['LUBRIFIANT']
     * @var mixed
     */
    public static $ListePdtStation =null;

    #region Fonction Standard
        public function __construct(xNAbySyGS $NabySy,?int $IdUser=null,$CreationChampAuto=true,$TableName="station_cuvecarburant"){
            if ($TableName==''){
                $TableName="station_cuvecarburant";
            }
            parent::__construct($NabySy,(int)$IdUser,$CreationChampAuto,$TableName);
            
            if (count(self::$FamilleProduitStation) == 0){
                self::$FamilleProduitStation[]=self::FAMILLE_CARBURANT;
                self::$FamilleProduitStation[]=self::FAMILLE_LUBRIFIANT;
            }
            self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_ESSENCE;
            self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_GASOIL;
            self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_GAZ;
            self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_KEROZENE_JET_A1;
            self::$ListePdtStation['LUBRIFIANT'] = []; //Les lubrifiants sont dynamic
            //Création des Catégories
            self::CreateFamilleProduitStation();
        }

        /**
         * Vérifie l'existance d'une Cuve
         * @param string $NomCuve 
         * @param null|array $IgnoreId 
         * @return bool 
         */
        public function Existe(string $NomCuve, ?array $IgnoreId = []):bool{
            if (!$this->TableExiste()){
                return false;
            }
            if ($this->TableIsEmpty()){
                return false;
            }
            $TxSQL="Nom like '".$NomCuve."'";
            if (isset($IgnoreId)){
                foreach ($IgnoreId as $IdI){
                    $TxSQL .=" and Id <> ".(int)$IdI;
                }
            }
            $Lst = $this->ChargeListe($TxSQL);
            if ($Lst){
                if ($Lst->num_rows>0){
                    return true;
                }
            }
            return false;
        }

        /**
         * Retourne le Type de Carburant selon le nom fournit
         * @param string $TypeName 
         * @return string : CARBURANT_ESSENCE | CARBURANT_GASOIL | CARBURANT_KEROZENE_JET_A1
         */
        public static function GetTypeCarburant(string $TypeName):string{
            if (strtolower($TypeName) == strtolower(self::CARBURANT_ESSENCE) ||
                strtolower($TypeName)==strtolower("éssence") || strtolower($TypeName)==strtolower("Super") ){return self::CARBURANT_ESSENCE;}

            if (strtolower($TypeName) == strtolower(self::CARBURANT_GASOIL) || 
                strtolower($TypeName) == strtolower("Gazoil") ){return self::CARBURANT_GASOIL;}
            if (strtolower($TypeName) == strtolower(self::CARBURANT_GAZ)){return self::CARBURANT_GAZ;}
            if (strtolower($TypeName) == strtolower(self::CARBURANT_KEROZENE_JET_A1)){return self::CARBURANT_KEROZENE_JET_A1;}
            return "";
        }
        /**
         * Retourne la cuve de stockage par défaut. Si aucune cuve n'existe elle sera créee.
         * La cuve de stockage créee en premier sera considérée comme celle par défaut.
         * @return null|xCuveStockageCarburant 
         */
        public function GetDefautCarburant():?xCuveStockageCarburant{
            if ($this->TableIsEmpty()){
                $nCuve=new xCuveStockageCarburant($this->Main,null,true,$this->Table);
                $nCuve->Nom = self::CARBURANT_GASOIL;
                $nCuve->DateJaugeB=date("Y-m-d H:i:s");
                $nCuve->Stock=0;
                $nCuve->UniteMesure="Litre(s)";
                $nCuve->Enregistrer();
                return $nCuve;
            }
            $Lst=$this->ChargeListe();
            if ($Lst->num_rows){
                $rw=$Lst->fetch_assoc();
                $nCuve=new xCuveStockageCarburant($this->Main,$rw['ID'],true,$this->Table);
                return $nCuve;
            }else{
                $nCuve=new xCuveStockageCarburant($this->Main,null,true,$this->Table);
                $nCuve->Nom = self::CARBURANT_GASOIL;
                $nCuve->DateJaugeB=date("Y-m-d H:i:s");
                $nCuve->Stock=0;
                $nCuve->UniteMesure="Litre(s)";
                $nCuve->Enregistrer();
                return $nCuve;
            }
            return null;
        }

        /**
         * Retourne la Famille d'Hydrocarbure si elle existe.
         * @param mixed $NomFamille 
         * @return null|xORMHelper 
         */
        public static function GetFamillePdtStationByName($NomFamille):?xORMHelper{
            $Famille=new xORMHelper(self::$xMain,null,true,"famille");
            //foreach (self::$FamilleProduitStation as $FamilleTr){
                $Critere="Nom like '".$NomFamille."'";
                $Lst=$Famille->ChargeListe($Critere,null,null,null,"1");
                if ($Lst){
                    if ($Lst->num_rows > 0){
                        $rw=$Lst->fetch_assoc();
                        $IdFamille= $rw['ID'];
                        $FTrouve=new xORMHelper(self::$xMain,$IdFamille,true,"famille");
                        return $FTrouve;
                    }
                }
            //}
            return null;
        }

        /**
         * Créer les groupes de famille des produits d'hydrocarbure qui seront liée au module
         * @return bool 
         */
        public static function CreateFamilleProduitStation():bool{
            $Categ=new xORMHelper(self::$xMain,null,true,"famille");
            foreach (self::$FamilleProduitStation as $Famille){
                $Critere="Nom like '".$Famille."'";
                $Lst=$Categ->ChargeListe($Critere,null,null,null,"1");
                if ($Lst){
                    if ($Lst->num_rows ==0){
                        $Categ->Nom = $Famille;
                        $Categ->Enregistrer();
                    }
                }
            }
            return self::CreateProduitDefaut();
        }

        /**
         * Créer les articles par défaut pour le module d'hydrocarbure.
         * La lisaison est faite via le champ CODEBAR3 des articles NAbySyGS
         * @return bool 
         */
        public static function CreateProduitDefaut():bool{
            $PdtLiaison=new xProduitLiaison(self::$xMain);
            if(!$PdtLiaison->ChampsExisteInTable("CODEBAR3")){
                $PdtLiaison->MySQL->AlterTable($PdtLiaison->Table, "CODEBAR3");
            }
            $Categ=new xORMHelper(self::$xMain,null,true,"famille");

            foreach (self::$FamilleProduitStation as $NomFamille){
                $Critere="Nom like '".$NomFamille."'";
                $Lst=$Categ->ChargeListe($Critere,null,null,null,"1");
                if ($Lst){
                    if ($Lst->num_rows){
                        while($rw = $Lst->fetch_assoc()){
                            $Famille=new xORMHelper(self::$xMain,(int)$rw['ID'],true,"famille");
                            if (strtolower($Famille->Nom) == strtolower('Carburant')){
                                foreach (self::$ListePdtStation['CARBURANT'] as $NomCarburant){
                                    $LstP = $PdtLiaison->ChargeListe("CODEBAR3 like '".$NomCarburant."_".$Famille->Nom."' ");
                                    if($LstP->num_rows==0){
                                        //On l'ajoute
                                        $PdtLiaison=new xProduitLiaison(self::$xMain);
                                        $Famille->AddToLog("Création de ".$NomCarburant);
                                        $PdtLiaison->Designation = $NomCarburant ;
                                        $PdtLiaison->CODEBAR3 = $NomCarburant."_".$Famille->Nom ;
                                        $PdtLiaison->IDFAMILLE = $Famille->Id;
                                        $PdtLiaison->PERISSABLE = 'NON';
                                        $PdtLiaison->Etat = 'A';
                                        $PdtLiaison->Enregistrer();
                                    }
                                }
                            }elseif (strtolower($Famille->Nom) == strtolower('Lubrifiant')){
                                foreach (self::$ListePdtStation['LUBRIFIANT'] as $NomLubrifiant){
                                    $LstP = $PdtLiaison->ChargeListe("CODEBAR3 like '".$NomLubrifiant."_".$Famille->Nom."' ");
                                    if($LstP->num_rows==0){
                                        //On l'ajoute
                                        $PdtLiaison=new xProduitLiaison(self::$xMain);
                                        $PdtLiaison->Designation = $NomLubrifiant ;
                                        $PdtLiaison->CODEBAR3 = $NomLubrifiant."_".$Famille->Nom ;
                                        $PdtLiaison->IDFAMILLE = $Famille->Id;
                                        $PdtLiaison->Enregistrer();
                                    }
                                }
                            }
                        }
                        
                    }
                }
            }
            return true;
        }

        /**
         * Retourne le produit correspondant dans la boutique NAbySyGS de ce carburant
         * @return xProduitLiaison 
         */
        public function GetThisCuveProduit():xProduitLiaison{
            if ($this->Id==0){
                return null;
            }
            $Designation = $this->TypeCarburant ;
            $CodeBar = $Designation."_".self::FAMILLE_CARBURANT;
            $Pdt=new xProduitLiaison(self::$xMain);
            return $Pdt->GetPdtNAbySyGS(null,$CodeBar);        
        }

        public function Enregistrer(): bool{
            if ($this->Id>0){
                $Pdt=$this->GetThisCuveProduit();
                if ($Pdt){
                    if ((float)$this->Stock !== $Pdt->Stock){
                        $StockPrec=$Pdt->Stock ;
                        $StockSuiv=(float)$this->Stock ;
                        $TxJ="Suite à une modification du stock de la cuve ".$this->TypeCarburant.", 
                        Son stock dans NAbySyGS est passé de ".$StockPrec." à ".$StockSuiv;
                        $Pdt->Stock = $StockSuiv ;
                        if ($Pdt->Enregistrer()){
                            $Pdt->AddToJournal("MODIFICATION",$TxJ);
                        }else{
                            $TxJ="Erreur de mise à jour suite à une modification du stock de la cuve ".$this->TypeCarburant.", 
                            Son stock dans NAbySyGS devrait passé de ".$StockPrec." à ".$StockSuiv;
                            $Pdt->AddToJournal("DEBUG",$TxJ);
                            $Pdt->AddToLog($TxJ,true);
                        }
                    }
                }            
            }
            return parent::Enregistrer();
        }
    #endregion

    #region Fonction de Statistque sur le stock de carburant
        /**
         * Retourne le premier JaugeB de la journée.
         * Si aucune jaugeB enregistrée, le dernier du jour précédent sera employé
         * @param DateTime $Date 
         * @return null|xJaugeB 
         */
        public function GetJaugeBMatin(DateTime $Date):?xJaugeB{
            $JaugeB = new xJaugeB($this->Main);
            $dte = $Date->format("Y-m-d");
            $Critere="IDCUVE=".$this->Id." and DATEJAUGE >= '".$dte."'";
            //$JaugeB->DebugSelect=true;
            $Lst = $JaugeB->ChargeListe($Critere,"ID ASC","ID",null);
            if ($Lst->num_rows >1){
                $rw=$Lst->fetch_assoc();
                $JaugeB = new xJaugeB($this->Main,(int)$rw['ID']);
                //echo "JaugeB Matin du ".$dte.": ".$JaugeB->ToJSON();
                return $JaugeB;
            }else{
                //echo __LINE__."La premiere recherche de la JageB du Matin a donnée comme reponse: ".$Lst->num_rows."</br>";
                //Si dans la journée on a une seule JaugeB enregistrée, on prends celle de la veille pour la Jauge du Matin
                $Critere="IDCUVE=".$this->Id." and DATEJAUGE < '".$dte."'";
                $Lst = $JaugeB->ChargeListe($Critere,"ID DESC","ID",null,"1");
                if ($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $JaugeB = new xJaugeB($this->Main,(int)$rw['ID']);
                    //if ($this->Id == 2){
                        //$this->AddToLog("Pour IdCuve ".$this->Id." ID de la Jauge B du Matin = ".$rw['ID']);
                    //}
                    //var_dump($JaugeB->ToObject());
                    return $JaugeB;
                }
            }
            return null;
        }

        /**
         * Retourne le dernier JaugeB de la journée.
         * Si aucun JaugeB trouvé le premier du jour suivant la journée sera utilisé
         * @param DateTime $Date 
         * @return null|xJaugeB 
         */
        public function GetJaugeBSoir(DateTime $Date):?xJaugeB{
            $JaugeB = new xJaugeB($this->Main);
            $dte = $Date->format("Y-m-d");
            $Critere="IDCUVE=".$this->Id." and DATEJAUGE >= '".$dte."'";
            //$JaugeB->DebugSelect=true;
            $Lst = $JaugeB->ChargeListe($Critere,"ID DESC","ID",null,"1");
            if ($Lst->num_rows){
                $rw=$Lst->fetch_assoc();
                $JaugeB = new xJaugeB($this->Main,(int)$rw['ID']);
                //var_dump($JaugeB->ToObject());
                return $JaugeB;
            }
            return null;
        }

        /**
         * Retourne le total livrée sur une période
         *   $REPONSE['TOTAL']=Montant total livré;
         *   $REPONSE['TVA']=Montant total de la TVA;
         *   $REPONSE['QTE']=Qté Total livré.;
         * @param DateTime $DateDu 
         * @param DateTime $DateFin 
         * @return null|array 
         */
        public function GetLivraison(DateTime $DateDu, DateTime $DateFin):?array{
            $Pdt=$this->GetThisCuveProduit();
            if(!isset($Pdt)){
                return null;
            }
            $TotalLivre=0;
            $TotalTVA=0;
            $TotalQte=0;

            $TxSQL = "select D.DATELIVREE, HEURELIVREE, D.DESIGNATION, sum(D.QTE) as 'QTE', SUM(D.PRIXTOTAL) as 'PRIXTOTAL', SUM(D.TVA) as 'TVA' , D.IDPRODUIT 
                from detailbl D 
                where D.IDPRODUIT = ".$Pdt->Id." 
                AND D.DATELIVREE>='".$DateDu->format("Y-m-d")."' AND D.DATELIVREE<='".$DateFin->format("Y-m-d")."' 
                GROUP BY D.IDPRODUIT ";
            $Lst = $this->ExecSQL($TxSQL);
            $DateF=null;
            if ($Lst->num_rows>0){
                //var_dump($TxSQL);
                $rw=$Lst->fetch_assoc();
                $DateF=$rw['DATELIVREE']." ".$rw['HEURELIVREE'];                
                $TotalLivre = $rw['PRIXTOTAL'];
                $TotalTVA = $rw['TVA'];
                $TotalQte = $rw['QTE'];
            }
            $REPONSE['DATE']=$DateF;
            $REPONSE['TOTAL']=round($TotalLivre, $this->Main->MaBoutique->Parametre->NbArrondie);
            $REPONSE['TVA']=round($TotalTVA, $this->Main->MaBoutique->Parametre->NbArrondie);
            $REPONSE['QTE']=round($TotalQte, $this->Main->MaBoutique->Parametre->NbArrondie);
            //var_dump($REPONSE);
            //exit;
            return $REPONSE;
        }

        /**
         * Retourne le Total Vendu sur une période
         *   $REPONSE['TOTAL']=Montant total vendu;
         *   $REPONSE['TVA']=Montant total de la TVA;
         *   $REPONSE['QTE']=Qté Total Vendu.;
         * @param DateTime $DateDu 
         * @param DateTime $DateFin 
         * @param xPompe $Pompe : Si fournit la vente sera limitée à celle de la Pompe ou du Piston
         * @return null|array 
         */
        public function GetVente(DateTime $DateDu, DateTime $DateFin, xPompe $Pompe=null):?array{
            $Pdt=$this->GetThisCuveProduit();
            $TotalLivre=0;
            $TotalTVA=0;
            $TotalQte=0;
            if(!isset($Pdt)){
                $REPONSE['DATE']=null;
                $REPONSE['TOTAL']=$TotalLivre;
                $REPONSE['TVA']=$TotalTVA;
                $REPONSE['QTE']=$TotalQte;
                return $REPONSE;
            }
            
            $TxSQL = "select D.DESIGNATION, sum(D.QTE) as 'QTE', SUM(D.PRIXTOTAL) as 'PRIXTOTAL', SUM(D.TVA) as 'TVA' , D.IDPRODUIT ,
                D.DATEFACTURE, D.HEUREFACTURE from detailfacture D ";
                if (isset($Pompe)){
                    $IndexPompe=new xIndexPompe($this->Main);
                    $TxSQL .= " left outer join ".$IndexPompe->Table." I on I.IDFACTURE = D.IDFACTURE ";
                    $TxSQL .= " left outer join ".$Pompe->Table." P on P.ID = I.IDPOMPE ";
                }
                $TxSQL .=" where D.IDPRODUIT = ".$Pdt->Id." 
                AND D.DATEFACTURE>='".$DateDu->format("Y-m-d")."' AND D.DATEFACTURE<='".$DateFin->format("Y-m-d")."' ";
                if (isset($Pompe)){
                    $TxSQL .= " and I.IDPOMPE =".$Pompe->Id;
                }
                $TxSQL .= " GROUP BY D.IDPRODUIT ";
            $Lst = $this->ExecSQL($TxSQL);
            //echo $TxSQL;
            //$this->AddToLog("Recherche des vente du ".$DateDu->format("d-m-Y")." au ".$DateFin->format("d-m-Y")." IdProduit = ".$Pdt->Id);
            
            $DateF=null;
            if ($Lst->num_rows>0){
                $rw=$Lst->fetch_assoc();
                $DateF=$rw['DATEFACTURE']." ".$rw['HEUREFACTURE'];
                $TotalLivre = $rw['PRIXTOTAL'];
                $TotalTVA = $rw['TVA'];
                $TotalQte = $rw['QTE'];
                //$this->AddToLog("Nbre de Resultat = ".$Lst->num_rows);
            }
            $REPONSE['DATE']=$DateF;
            $REPONSE['TOTAL']=round($TotalLivre, $this->Main->MaBoutique->Parametre->NbArrondie);
            $REPONSE['TVA']=round($TotalTVA, $this->Main->MaBoutique->Parametre->NbArrondie);
            $REPONSE['QTE']=round($TotalQte, $this->Main->MaBoutique->Parametre->NbArrondie);
            //var_dump($REPONSE);
            //exit;
            return $REPONSE;
        }

        /**
         * Retourne les Information de Suivit du Stock Carburant Périodique
         * @param DateTime $DateDu 
         * @param DateTime $DateFin 
         * @param xPompe|null $Pompe 
         * @return xInfoControlStock 
         */
        public function GetInfosControlStock(DateTime $DateDu, DateTime $DateFin, xPompe $Pompe=null):xInfoControlStock{
            $NbArrondie=$this->Main->MaBoutique->Parametre->NbArrondie ;
            $Reponse=new xInfoControlStock($NbArrondie);
            $Reponse->OK=0;
            
            //$Reponse->Cuve=$this->ToObject() ;
            $Reponse->Cuve=$this ;
            if (isset($Pompe)){
                $Reponse->Pompe=$Pompe;
            }            
            $Reponse->DateDebut=$DateDu ;
            $Reponse->DateFin=$DateFin ;

            //Trouvons La JaugeB à l'ouverture de la journée
            $JaugeA = $this->GetJaugeBMatin($DateDu);
            $JaugeB = $this->GetJaugeBSoir($DateFin);
            if(!isset($JaugeA)){
                $Reponse->TxErreur="Aucune Jauge trouvée en debut de journée.";
                return $Reponse;
            }
            if(!isset($JaugeB)){
                $Reponse->TxErreur="Aucune Jauge trouvée en fin de journée.";
                return $Reponse;
            }
            
            $Reponse->OK=1;
            $Reponse->StockInitial =(float)$JaugeA->STOCK_ACT ;
            $Reponse->StockInitial =round($Reponse->StockInitial,$NbArrondie);
            $Reponse->StockDeFinGaugeB =(float)$JaugeB->STOCK_ACT ;

            $Livraison = $this->GetLivraison($DateDu,$DateFin);
            if($Livraison){
                $Reponse->InfoLivraison = $Livraison ;
                $Reponse->QteLivree =round($Livraison['QTE'],$NbArrondie);
            }

            $Vente= $this->GetVente($DateDu,$DateFin,$Pompe);
            if ($Vente){
                $Reponse->InfoVente = $Vente ;
                $Reponse->QteVendu = round($Vente['QTE'],$NbArrondie);
            }
            
            //var_dump($Reponse);
            //exit;
            return $Reponse ;

        }
    #endregion
}



?>