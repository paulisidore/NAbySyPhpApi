<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use DateTime;
use Exception;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Facture\xDetailVente;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Panier\xArticlePanier;
use NAbySy\GS\Panier\xCart;
use NAbySy\GS\Panier\xPanier;
use NAbySy\GS\Stock\xJournalCaisse;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;
use xNotification;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 * Module de Gestion de l'historique des prises d'index par pompe/piston
 */
class xIndexPompe extends xORMHelper {

    /**
     * La Pompe de Carburant liée ce Index
     * @var null|xPompe
     */
    public ?xPompe $Pompe = null;

    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="station_histindex"){
        if ($TableName==''){
            $TableName="station_histindex";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        if ($this->Id>0){
            //On charge la Cuve Aussi
            $this->Pompe = new xPompe($this->Main,$this->IDPOMPE) ;
        }
    }

    /**
     * Enregistre l'index du carburant déroulé dans une Pompe
     * @param DateTime|null $Date 
     * @return bool | xNotification 
     */
    public function SaveIndex(float $IndexAct, DateTime $Date = null):bool|xNotification{
        $Reponse=new xNotification();
        $Reponse->OK=0;
        if (!isset($this->Pompe) || $IndexAct <0){
            $Reponse->TxErreur="Aucune Cuve liée à la pompe ou Index relevé incorrect.";
            return $Reponse;
        }
        if (!isset($Date)){
            $Date=new DateTime('now');
        }
        $IsNewJauge=true;
        $DateJauge=$Date->format("Y-m-d");        
        $Ecart=$IndexAct - (float)$this->Pompe->INDEXACT ;
        if ($Ecart<0){
            $Reponse->TxErreur="Index relevé incorrect. Index doit etre suppérieur à l'index actuel de la Pompe.";
            return $Reponse;
        }
        if ($Ecart  == 0){
            $Reponse->TxErreur="Aucun changement d'Index relevé.";
            return $Reponse;
        }

        //$LastJauge=$this->GetLastIndex($Date);
        $TxJ="Le niveau d'index du piston ".$this->Pompe->Nom." est passé de ".$this->Pompe->INDEXACT." à ".$IndexAct ;
        if ($this->Id>0){
            $IsNewJauge=false;
            //$this->ChargeOne($LastJauge->Id);
            $TxJ="Mise à jour quotidienne du niveau d'index du piston ".$this->Pompe->Nom.". Il passe de ".$this->Pompe->INDEXACT." à ".$IndexAct;
        }
        $TxJ .=" (soit un écart de ".$Ecart.")";
        if ($IsNewJauge){
            $this->DATEINDEX = $DateJauge;            
            $this->IDPOMPE=$this->Pompe->Id;
        }
        //var_dump($TxJ);
        $DateEnreg=date("Y-m-d H:i:s");
        $HeureEnreg=date("H:i:s");
        $this->DATEINDEX = $DateJauge;
        $this->DATEENREG = $DateEnreg ;
        $this->HEURENREG = $HeureEnreg ;
        $this->INDEXACT = $IndexAct ;
        $this->ECART=$Ecart ;
        if ($this->Enregistrer()){
            $this->Main->AddToJournal(null,null,"INDEX_POMPE",$TxJ);
            $this->Pompe->INDEXACT = $IndexAct;
            $this->Pompe->Enregistrer();
            $Reponse->OK=1;
            $Reponse->Extra=$this->Id;
            if ((int)$this->IdFacture==0){
                //$this->AddToLog("Ajout de la facture concernant l'ecart No.".$this->Id);
                $Retour=$this->SaveEcartAsVente($this);
                $this->AddToLog("Reponse: ".json_encode($Retour));
                if ($Retour){
                    if ($Retour->OK==0){
                        //On annule l'enregistrement de l'index

                        return $Retour;
                    }
                }
                $Reponse->Contenue['FACTURE']=$Retour->Contenue;
            }else{
                $Facture=new xVente($this->Main,(int)$this->IdFacture);
                $Reponse->Contenue['FACTURE']= $Facture->ToObject();
            }
            $Reponse->Contenue['INDEX']=$this->ToObject();
        }
        return $Reponse;
    }

    /**
     * Retourne le dernier Index enregistré pour la pompe en cour
     * @param DateTime|null $Date 
     * @return xIndexPompe 
     */
    public function GetLastIndex(DateTime $Date = null):?xIndexPompe{
        if ($this->TableIsEmpty()){
            return null;
        }
        if (!isset($this->Pompe)){
            return null ;
        }
        if ($this->Pompe->Id==0){
            var_dump($this->Pompe);
        }
        if (!isset($Date)){
            $Date=new DateTime('now');
        }
        $LastInd=null;
        $DateJauge=$Date->format("Y-m-d");
        $Lst = $this->ChargeListe(" IDPOMPE =".$this->Pompe->Id." AND DATEINDEX <= '".$DateJauge."' ","ID DESC","*",null,"1");
        if($Lst->num_rows){
            $rw=$Lst->fetch_assoc();
            $LastInd=new xIndexPompe($this->Main,$rw['ID']);
        }
        return $LastInd;
    }

    /**
     * Enregistrement une facture correspondante à l'ecart des index relevée.
     * @param xIndexPompe $IndexPompe 
     * @return xNotification 
     */
    public static function SaveEcartAsVente(xIndexPompe $IndexPompe):xNotification{
        $Reponse=new xNotification;
        $Reponse->OK=0;
        if ($IndexPompe->Id==0){
            $Reponse->TxErreur-"Aucun Index enregistrée.";
            return $Reponse;
        }
        if ($IndexPompe->IdFacture>0){
            $Reponse->TxErreur-"Ecart déjà facturé. Voir Facture ".$IndexPompe->IdFacture;
            $Reponse->Extra=$IndexPompe->IdFacture;
            return $Reponse;
        }
        if ((int)$IndexPompe->Ecart==0){
            $Reponse->TxErreur = "Aucune Vente effectuée. Ecart null";
            return $Reponse;
        }
        if ((float)$IndexPompe->Pompe->TauxConvertionLitre == 0){
            //$IndexPompe->Pompe->AddToLog("Ajout du Taux de Conversion en Litre TauxConvertionLitre = 1");
            $IndexPompe->Pompe->TauxConvertionLitre = 1;
            $IndexPompe->Pompe->Enregistrer();
        }
        $IndexPompe->IDPOMPE = $IndexPompe->Pompe->Id;
        //$IndexPompe->Pompe->AddToLog("IdPompe Trouvé= ".$IndexPompe->Pompe->Id);
        //$IndexPompe->Pompe->AddToLog("IdPompe Enregistré = ".$IndexPompe->IDPOMPE);
        $QteVendu=$IndexPompe->Ecart * (float)$IndexPompe->Pompe->TauxConvertionLitre ;
        $IdCpteClt=0 ; //Pour les Vente Directe
        $Carburant = new xCuveStockageCarburant($IndexPompe->Main,$IndexPompe->Pompe->IdCarburant);
        $Designation = $Carburant->TypeCarburant ;
        $CodeBar = $Designation."_".$Carburant::FAMILLE_CARBURANT;

        $Pdt=new xProduitLiaison(self::$xMain);
        $Pdt = $Pdt->GetPdtNAbySyGS(null,$CodeBar);
        if (!isset($Pdt)){
            $Reponse->TxErreur="Produit ".$Designation." introuvable dans NAbySyGS.";
            return $Reponse;
        }
        //$Pdt->AddToLog("Carburant Recherché ".$Designation.". Celui trouvé: ".$Pdt->Designation." IdPdt = ".$Pdt->Id);

        $Client=null;
        $ModeReglement="E";
        if ((int)$IndexPompe->Pompe->IsVenteDirecte ==0){
            $ModeReglement="BP";
            //Vente Bon Client, Recherche de l'IdClient du Caissier
            $Client=new xClient(self::$xMain);
            $IdCpteClt= (int)self::$xMain->User->IdCompte;
            if ((int)$IdCpteClt == 0){
                //Création du compte client de l'utilisateur connecté
                $Client->Nom = self::$xMain->User->Nom;
                $Client->Prenom = self::$xMain->User->Prenom;
                $Client->Adresse = self::$xMain->User->Adresse;
                $Client->Tel = self::$xMain->User->Tel;
                $Client->Enregistrer();
                $IdCpteClt=$Client->Id;
                self::$xMain->User->IdCompte = $IdCpteClt ;
                self::$xMain->User->Enregistrer();    
            }else{
                $Client=new xClient(self::$xMain,(int)self::$xMain->User->IdCompte);
                $IdCpteClt=$Client->Id;
            }
        }
        //Création du Panier de Vente
        $Vente=new xVente(self::$xMain) ;
        $Panier=new xCart($Vente->Main->MaBoutique);
        if ($Client){
            $Panier->IdClient=$Client->Id;
            $Panier->Client=$Client;
        }
        $TypeVenteParDefaut=0 ;
		$Grossiste=false;
        $NewArticle=new xArticlePanier($Vente->Main,$Pdt->Id,$QteVendu,$TypeVenteParDefaut,$Vente->Main->MaBoutique) ;
        if ($NewArticle){
            $Modif=false ;
            if ($Panier->PdtExiste($Pdt->Id,$TypeVenteParDefaut)){
                //'On modifie la quantité'
                $Modif=false ;
            }
            /*Si Boutique avec Prix Calculé et non Grossiste */
            if ($Vente->Main->MaBoutique->AutoCalculPV>0){
                if (!$Grossiste){
                    $EnPlus=$NewArticle->PrixU * round(($Vente->Main->MaBoutique->TauxPV /100),0) ;
                    $vEnPlus=($NewArticle->PrixU*($Vente->MaBoutique->TauxPV /100)) ;
                    $EnPlus=(int)round($vEnPlus,0,PHP_ROUND_HALF_UP) ;
                    $NewArticle->PrixU +=$EnPlus ;
                }
            }
            //$Vente->AddToLog("Ajout de l'article dans le panier: ".$NewArticle->Nom." PrixVente=".$NewArticle->PrixU." x Qte=".$NewArticle->Qte." pour TypeV=".$NewArticle->TypeVente);
            $Rep=$Panier->addProduct($NewArticle->IdProduit,$NewArticle->Nom,$NewArticle->Qte,$NewArticle->PrixU,$NewArticle->TypeVente,$Panier->IdClient,$Modif) ;
            if ($Rep !== true ){
                $Reponse->TxErreur=$Rep ;
                return $Reponse ;
            }

            if ((int)$Panier->MontantVerse ==0){
                $Panier->MontantVerse = $Panier->getTotalPriceCart() - (int)$Panier->TotalRemise - (int)$Panier->TotalReduction ;
            }
        }

        $IdFacture=0;
        $ReponseID=$Vente->Valider($Panier) ;
        //var_dump($ReponseID);
        

        if (is_object($ReponseID)){
            if (get_class($ReponseID) !== "xErreur"){
                $IdFacture=$ReponseID;
                $IndexPompe->AddToLog("Reponse Après Validation Relevé Index. ReponseID = ".$ReponseID);
            }else{
                //Erreur
                if ($ReponseID->OK>0){
                    $IdFacture=$ReponseID->Extra;
                    $IndexPompe->AddToLog("Reponse Après Validation Relevé Index. IdFacture = ".$IdFacture);
                }else{
                    $Reponse = new xNotification(json_encode($ReponseID));
                    $TxJ="La facture correspondant à l'index prise de la Pompe ".$IndexPompe->Pompe->Nom." n'pas été enregistrée. 
                    Ecart correspondamt: ".$QteVendu.", Id de l'Index=".$IndexPompe->Id;
                    $IndexPompe->AddToLog($TxJ);
                    $IndexPompe->AddToJournal("INDEX-POMPE",$TxJ);
                    $Panier->Vider();
                    return $Reponse;
                }
            }
        }else{
            $IdFacture=$ReponseID;
            $IndexPompe->AddToLog("Reponse Après Validation Relevé Index. L".__LINE__." IdFacture = ".$ReponseID);
        }
        $IndexPompe->IdFacture=$IdFacture;
        if ((int)$IndexPompe->IdFacture>0){
            $IndexPompe->AddToLog("Validation Relevé Index. IdFacture = ".$IdFacture);
            $IndexPompe->TotalASolder = $Panier->getTotalNetAPayer() ;
        }else{
            $IndexPompe->AddToLog("Reponse Erreur Après Validation Relevé Index. L".__LINE__." IdFacture = ".$ReponseID);
        }
        
        $Panier->Vider();            
        $Reponse->Extra=$IdFacture;
        if ($IdFacture>0){            
            $Reponse->OK=1;
            $Facture=new xORMHelper($Vente->Main,$IdFacture,false,$Vente->Table);
            $IndexPompe->TotalASolder = $Facture->TotalFacture ;
            $IndexPompe->AddToLog("Enregistrement du montant sur l/ index: ".$IndexPompe->TotalASolder);
            $IndexPompe->Enregistrer();
            $vFact=$Facture->ToArray();
            $DetailF=new xDetailVente($Facture->Main,null,false,null,null,$IdFacture);
            $Lignes=$DetailF->GetFullInfosFactureByLine($IdFacture);
            if ($Lignes){
                if(count($Lignes)){
                    $vFact['DETAIL']=$Lignes;
                }
            }
            $Reponse->Contenue =$vFact ;
            //$Reponse->Contenue=$Facture->ToObject();
        }
        return $Reponse;
    }

    /**
     * Retourne la date d'ouverture d'une pompe
     * Retourne null si aucun enregistrement trouvée sur la période
     * @param xPompe $Pompe 
     * @param DateTime $Date 
     * @return xNotification|null 
     */
    public function GetDateOuverturePompe(xPompe $Pompe,DateTime $Date):xNotification|null{
        $IndexPompe=new xIndexPompe($Pompe->Main);
        $Dte=null;
        $Reponse=new xNotification;
        $Reponse->OK=0;
        $Reponse->Extra=$Dte;
        $Reponse->Contenue['INDEX_OUVERTURE']=0;
        $Reponse->Contenue['INDEX_ID']=0;

        $OrdreTr="ASC";
        $Critere="DATEINDEX <='".$Date->format("Y-m-d")."' ";
        $Lst=$IndexPompe->ChargeListe($Critere,"ID ASC","*",null,"2");
        if($Lst->num_rows ==0){
            $OrdreTr="DESC";
            $Critere="DATEINDEX >='".$Date->format("Y-m-d")."' ";
            $Lst=$IndexPompe->ChargeListe($Critere,"ID ASC","*",null,"2");
        }
        if($Lst->num_rows == 0){
            return $Reponse;
        }
        if ($OrdreTr=="ASC"){
            //l'Heure d'ouverture se  trouve en 1erePos
            $rw=$Lst->fetch_assoc();
            $Dte=new DateTime($rw['DATEINDEX']);
            
        }elseif ($OrdreTr=="DESC"){
            //l'Heure d'ouverture se  trouve en 1erePos
            $rw=$Lst->fetch_assoc();
            $Dte=new DateTime($rw['DATEINDEX']);
        }
        $Reponse->Extra=$Dte;
        $Reponse->Contenue['INDEX_OUVERTURE']=$rw['INDEXACT'];
        $Reponse->Contenue['INDEX_ID']=$rw['ID'];

        return $Reponse;
    }

    /**
     * Retourne la date de fermeture d'une pompe
     * Retourne null si aucun enregistrement trouvée sur la période
     * @param xPompe $Pompe 
     * @param DateTime $Date 
     * @return xNotification|null 
     */
    public function GetDateFermeturePompe(xPompe $Pompe,DateTime $Date):xNotification|null{
        $IndexPompe=new xIndexPompe($Pompe->Main);
        $Dte=null;
        $Reponse=new xNotification;
        $Reponse->OK=0;
        $Reponse->Extra=$Dte;
        $Reponse->Contenue['INDEX_OUVERTURE']=0;
        $Reponse->Contenue['INDEX_ID']=0;

        $OrdreTr="ASC";
        $Critere="DATEINDEX <='".$Date->format("Y-m-d")."' ";
        $Lst=$IndexPompe->ChargeListe($Critere,"ID DESC","DATEINDEX",null,"2");
        if($Lst->num_rows ==0){
            $OrdreTr="DESC";
            $Critere="DATEINDEX >='".$Date->format("Y-m-d")."' ";
            $Lst=$IndexPompe->ChargeListe($Critere,"ID ASC","DATEINDEX",null,"2");
        }
       
        if ($OrdreTr=="ASC"){
            if($Lst->num_rows <= 1){
                //On a que l'heure d'ouverture dans ce cas
                return $Reponse;
            }
            //l'Heure de fermeture se  trouve en 1erePos
            $rw=$Lst->fetch_assoc();
            $Dte=new DateTime($rw['DATEINDEX']);
        }elseif ($OrdreTr=="DESC"){
            if($Lst->num_rows <= 0){
                //On a que l'heure d'ouverture dans ce cas
                return $Reponse;
            }
            //l'Heure de fermeture se  trouve en 1erePos
            $rw=$Lst->fetch_assoc();
            $Dte=new DateTime($rw['DATEINDEX']);
        }
        $Reponse->Extra=$Dte;
        $Reponse->Contenue['INDEX_FERMETURE']=$rw['INDEXACT'];
        $Reponse->Contenue['INDEX_ID']=$rw['ID'];
        return $Reponse;
    }

    /**
     * Supprime l'enregistrement d'un indexe si c'est bien la dernière de la pompe concernée
     * @return bool 
     * @throws Exception 
     */
    public function Supprimer(): bool{
        //On vérifie s; il s'gait bien du dernier Index
        $LastInd=$this->GetLastIndex();
        if (!isset($LastInd)){
            return false;
        }
        if($LastInd->Id !== $this->Id){
            return false;
        }
        if ($this->Ecart !== 0){
            //On retire l'ecart de l'index de la pompe
            $this->Pompe->INDEXACT =(float)$this->Pompe->INDEXACT - (float)$this->Ecart ;
            $this->Pompe->Enregistrer();
        }
        //On va supprimer la facture liée
        $Fact=new xVente($this->Main, (int)$this->IdFacture);
        if ($Fact->Id){
           $Panier=new xPanier($this->Main);
           $Panier->Charger($Fact->Id);
           if ($Panier->AnnulerVente()){//L'annulation met a jour le solde du client également.
                if($Fact->Supprimer()){
                    $Fact->ExecUpdateSQL("delete from detail".$Fact->Table." where idfacture=".$Fact->Id);
                }
           }
        }
        $TxJ="Suppression de l'index n°".$this->Id." d'une valeur de ".$this->IndexAct." (avec ".$this->Ecart." d'écart) de la pompe ".$this->Pompe->Nom." (IdPompe ".$this->Pompe->Id.") ";
        $this->AddToJournal("INDEX_POMPE",$TxJ);
        return parent::Supprimer();
    }

    public function GetBilanPompe(?int $IdPompe=null,?string $DateDepart=null, ?string $DateFin=null):xNotification{
        $DateDu=null;
        $DateAu=null;
        $Reponse = new xNotification;
        $Reponse->OK=1;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDPOMPE'])){
            if ((int)$_REQUEST['IDPOMPE']>0){
                $IdPompe = (int)$_REQUEST['IDPOMPE'];
            }
        }
        $Pompe=new xPompe($this->Main,$IdPompe);
        $IndexDepart=0;
        $IdIndexDepart=0;
        $IndexFin=0;
        $IdIndexFin=0;
        if(isset($DateDepart)){
            if ($DateDepart !==""){
                $Dte=new DateTime($DateDepart);
                //Recherche de la vrai date d'Ouverture
                $Dte=$this->GetDateOuverturePompe($Pompe,$Dte);
                if($Dte){
                    if ($Dte->Contenue->Extra){
                        $IndexDepart = $Dte->Contenue->Contenue['INDEX_OUVERTURE'];
                        $DateDu=$Dte->Contenue->Extra->format("Y-m-d");
                        $IdIndexDepart =  (int)$Dte->Contenue->Contenue['INDEX_ID'];
                    }
                }
            }
        }

        if(isset($DateFin)){
            if ($DateFin !==""){
                $Dte=new DateTime($DateFin);
                //Recherche de la vrai date de fermeture
                $Dte=$this->GetDateFermeturePompe($Pompe,$Dte);
                if($Dte){
                    if ($Dte->Contenue->Extra){
                        $IndexFin = $Dte->Contenue->Contenue['INDEX_FERMETURE'];
                        $IdIndexFin =  (int)$Dte->Contenue->Contenue['INDEX_ID'];
                        $DateAu=$Dte->Contenue->Extra->format("Y-m-d");
                    }
                }
            }
        }
        
        $Ecart=$IndexFin - $IndexDepart ;
        if (!isset($DateDu)){
            $DateDu=date("Y-m-")."01";
        }

        $Cuve=new xCuveStockageCarburant($Pompe->Main,$Pompe->IdCarburant);
        $IndexPompeStart=new xIndexPompe($Pompe->Main,$IdIndexDepart);
        $IndexPompeEnd=new xIndexPompe($Pompe->Main,$IdIndexFin);
        $BILAN["POMPE_".$Pompe->Id]=[];
        $REP=$BILAN["POMPE_".$Pompe->Id];
        $REP['DEBUT'] = $DateDu ;
        $REP['FIN'] = $DateAu ;
        $REP['CARBURANT']=$Cuve->TypeCarburant;
        $REP['UNITE_MESURE']=$Cuve->UniteMesure;
        $REP['INDEX_DEPART']=$IndexDepart;
        $REP['INDEX_FIN']=$IndexFin;        
        $REP['ECART']= round($Ecart,$this->Main->MaBoutique->Parametre->NbArrondie) ; // $IndexFin - $IndexDepart;
        $REP['STOCK_CUVE']=round($Cuve->Stock,$this->Main->MaBoutique->Parametre->NbArrondie) ;   
        //Recherche du nbialan Comptable
            

        return $REP;
    }

    /**
     * Enregistre une vente de Carburant dans un Compte à Crédit.
     * Le montant de la vente sera déduite du compte client du pompiste ayant servit
     * @param xClient $ClientDst : Le compte client à facturer
     * @param float $QteServit: La quantité servit au client
     * @param xIndexPompe $IndexPompe L'historique d'enregistrement de l'index de pompe concerné
     * @return xNotification 
     */
    public static function SaveVenteToBonClient(xClient $ClientDst, float $QteServit, xIndexPompe $IndexPompe, string|DateTime $DateVers = null):xNotification{
        $Reponse=new xNotification;
        $Reponse->OK=0;
        #region "Condition Initiale"
            $date=date("Y-m-d");
            if (is_string($DateVers)){
                $dte=new DateTime($DateVers);
                if ($dte){
                    $date = $dte->format("Y-m-d");
                }
            }elseif(is_object($DateVers)){
                $date = $DateVers->format("Y-m-d");
            }

            if(!isset($ClientDst)){
                $Reponse->TxErreur-"Absence du Compte Client Destinataire.";
                return $Reponse;
            }
            if($ClientDst->Id==0){
                $Reponse->TxErreur-"Absence du Compte Client Destinataire.";
                return $Reponse;
            }
            if ($QteServit == 0){
                $Reponse->TxErreur-"Aucune quantité fournit.";
                return $Reponse;
            }
            if ($IndexPompe->Id==0){
                $Reponse->TxErreur-"Aucun Index enregistré.";
                return $Reponse;
            }
            if ($IndexPompe->IdFacture == 0){
                $Reponse->TxErreur-"Aucune Facture émise. Vueillez enregistrer l'indexe en cour de la pompe ".$IndexPompe->Pompe->Nom;
                return $Reponse;
            }

            if ((int)$IndexPompe->Ecart==0){
                $Reponse->TxErreur = "Aucune Vente effectuée. Ecart null";
                return $Reponse;
            }
        #endregion

        $VenteOrig=new xVente(self::$xMain,$IndexPompe->IDFACTURE) ;
        if ($VenteOrig->Id == 0){
            $VenteOrig->DateFacture=$date ;
        }
        $DetailF=new xDetailVente($VenteOrig->Main,null,false,null,null,$VenteOrig->Id);

        $IdCpteClt=$VenteOrig->IdClient ; //Pour les Vente Directe
        $Carburant = new xCuveStockageCarburant($IndexPompe->Main,$IndexPompe->Pompe->IdCarburant);
        $Designation = $Carburant->TypeCarburant ;
        $CodeBar = $Designation."_".$Carburant::FAMILLE_CARBURANT;

        $Pdt=new xProduitLiaison(self::$xMain);
        $Pdt = $Pdt->GetPdtNAbySyGS(null,$CodeBar);
        if (!isset($Pdt)){
            $Reponse->TxErreur="Produit ".$Designation." introuvable dans NAbySyGS.";
            return $Reponse;
        }
        //$Pdt->AddToLog("Carburant Recherché ".$Designation.". Celui trouvé: ".$Pdt->Designation." IdPdt = ".$Pdt->Id);

        $ClientPompiste=new xClient(Self::$xMain,$IdCpteClt) ;
        
        $ModeReglement="BP";
        
        //On va valorier la qté de carburant servit en montant
        $IdDetailFacture = null;
        foreach($DetailF->ListeProduits as $rw){
            if ($Pdt->Id == (int)$rw['IDPRODUIT']){
                //Produit Trouvé
                $IdDetailFacture = (int)$rw['ID'];
                break;
            }
        }
        if(!isset($IdDetailFacture)){
            $Reponse->TxErreur="Produit ".$Designation." introuvable dans la facture du pompiste. Enregistré un relevé d'index avant de continuer.";
            return $Reponse;
        }

        //Modification de la Qté Servit
        $LigneVente=new xDetailVente(self::$xMain,$IdDetailFacture);
        if ($LigneVente->Qte < $QteServit){
            $Reponse->TxErreur="La quantité servit de ".$Designation." est inférieur à la quantité enregistrée pour le compte de la pompe.";
            return $Reponse;
        }
        $QteRest = $LigneVente->Qte - $QteServit ;
        $PTRest = $QteRest * $LigneVente->PrixVente ;
        

        #region Création du Panier de Vente pour le compte du client destinataire
            $Vente=new xVente(self::$xMain) ;
            $Panier=new xCart($Vente->Main->MaBoutique);
            $Panier->DateFacture($VenteOrig->DateFacture);
            $Client = $ClientDst ;
            if ($Client){
                $Panier->IdClient=$Client->Id;
                $Panier->Client=$Client;
            }
            $TypeVenteParDefaut=0 ;
            $Grossiste=false;
            $NewArticle=new xArticlePanier($Vente->Main,$Pdt->Id,$QteServit,$TypeVenteParDefaut,$Vente->Main->MaBoutique) ;
            if ($NewArticle){
                $Modif=false ;
                if ($Panier->PdtExiste($Pdt->Id,$TypeVenteParDefaut)){
                    //'On modifie la quantité'
                    $Modif=false ;
                }
                $NewArticle->PrixU = $LigneVente->PrixVente;
                $Vente->AddToLog(__FILE__.":L".__LINE__.": Ajout de l'article dans le panier: ".$NewArticle->Nom." PrixVente=".$NewArticle->PrixU." x Qte=".$NewArticle->Qte." pour TypeV=".$NewArticle->TypeVente);
                $Rep=$Panier->addProduct($NewArticle->IdProduit,$NewArticle->Nom,$NewArticle->Qte,$NewArticle->PrixU,$NewArticle->TypeVente,$Panier->IdClient,$Modif) ;
                if ($Rep !== true ){
                    $Reponse->TxErreur=$Rep ;
                    return $Reponse ;
                }

                if ((int)$Panier->MontantVerse ==0){
                    $Panier->MontantVerse = $Panier->getTotalPriceCart() - (int)$Panier->TotalRemise - (int)$Panier->TotalReduction ;
                }
            }

            $IdFacture=0;
            $ReponseID=$Vente->Valider($Panier) ;
            if (is_object($ReponseID)){
                if (get_class($ReponseID) !== "xErreur"){
                    $IdFacture=$ReponseID;
                }else{
                    //Erreur
                    if ($ReponseID->OK>0){
                        $IdFacture=$ReponseID->Extra;
                    }else{
                        $Reponse = new xNotification(json_encode($ReponseID));
                        $TxJ="La facture correspondant à l'index prise de la Pompe ".$IndexPompe->Pompe->Nom." n'pas été enregistrée pour le compte du client ".$ClientDst->Id." 
                        La quantité correspondante: ".$QteServit.", Id de l'Index Liée=".$IndexPompe->Id;
                        $IndexPompe->AddToLog($TxJ);
                        $IndexPompe->AddToJournal("INDEX-POMPE",$TxJ);
                        $Panier->Vider();
                        return $Reponse;
                    }
                }
            }else{
                $IdFacture=$ReponseID;
            }
        #endregion
        
        if ($IdFacture == 0){
            $Reponse->TxErreur = "Enrreur de validation de la vente au client en bon." ;
            return $Reponse;
        }

        #region Réduction du Solde du Pompiste
            $MtReduction = $LigneVente->PrixTotal - $PTRest ;

            $IndexPompe->TotalASolder -= $MtReduction ;
            $IndexPompe->Enregistrer();

            $TxJ="Suite à la validation d'une Vente à Crédit sur le compte client ".$ClientDst->Prenom." ".$ClientDst->Prenom." (IdClt:".$ClientDst->Id."), 
            Le solde du Pompiste ".$ClientPompiste->Prenom." ".$ClientPompiste->Nom." (IdClt:".$ClientPompiste->Id.") est passé de ".$ClientPompiste->Solde." 
            à ".$ClientPompiste->Solde-$MtReduction.". Le compte facturé est passé ".$ClientDst->Solde+$MtReduction ;
            
            if ($ClientPompiste->DebiterSolde($MtReduction)){
                //Modification de la facture du Pompiste                    
                $LigneVente->Qte = $QteRest ;
                $LigneVente->PrixTotal = $QteRest * $LigneVente->PrixVente ;
                //$VenteOrig->AddToLog("Montant a supprimer de la facture d'origine: ".$MtReduction);
                //$VenteOrig->AddToLog("Montant de la facture d'origine: ".(float)$VenteOrig->TotalFacture);
                $TotalFactOrig = $PTRest ;
                //$VenteOrig->AddToLog("Le Total de la facture Numero ".$VenteOrig->Id.", passe de ".$VenteOrig->TotalFacture." à ".$TotalFactOrig);
                $VenteOrig->TotalFacture = $TotalFactOrig ;
                if ($LigneVente->Enregistrer()){
                    if ($VenteOrig->Enregistrer()){
                        $Pdt->Refresh();
                        //$VenteOrig->Refresh();
                        //On rajoute la quanté de carburant doublement enlevé lors de la facture du compte client en bon particulier
                        $Pdt->AjouterStock($QteServit);
                        $VenteOrig->AddToJournal("VENTE_BON",$TxJ);
                        //Retirons l'ecart financier de la caisse du jour
                            #region Suppression dans le journal Caisse
                            $CaisseGlobale=new xJournalCaisse($VenteOrig->Main,null,$VenteOrig->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,0,$VenteOrig->DateFacture);
                            $CaisseU=new xJournalCaisse($VenteOrig->Main,null,$VenteOrig->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,(int)$VenteOrig->IdCaissier,$VenteOrig->DateFacture);
                            $CaisseGlobale->TOTAL_FACTURE -= $MtReduction;
                            $CaisseU->TOTAL_FACTURE -= $MtReduction;

                            if ($VenteOrig->ModeReglement == 'BP'){
                                //Vente en Bo P
                                $CaisseGlobale->TOTAL_BONP -= $MtReduction;
                                $CaisseU->TOTAL_BONP -= $MtReduction;
                                if ($TotalFactOrig <=0){
                                    $CaisseGlobale->NB_BONP -=1;
                                    $CaisseU->NB_BONP -=1;
                                }
                            }elseif ($VenteOrig->ModeReglement == 'E'){
                                $CaisseGlobale->TOTAL_ESPECE -= $MtReduction;
                                $CaisseU->TOTAL_ESPECE -= $MtReduction;
                                if ($TotalFactOrig <=0){
                                    $CaisseGlobale->NB_ESP -= 1;
                                    $CaisseU->NB_ESP -= 1;
                                }
                            }
                            if ($VenteOrig->MontantReduction !==0 && $Panier->TotalReduction){
                                $CaisseGlobale->TOTAL_REMISE -= (float)$Panier->TotalReduction;                                
                                $CaisseU->TOTAL_REMISE -=(float)$Panier->TotalReduction;
                                if ($TotalFactOrig <=0){
                                    $CaisseGlobale->NB_REM -=1;
                                    $CaisseU->NB_REM -=1;
                                }
                            }
                            $CaisseGlobale->Enregistrer();
                            $CaisseU->Enregistrer();
                        #endregion
                        
                    }
                }
            }
        #endregion
        $Reponse->Extra=$IdFacture;
        $Reponse->OK=1;
        $Facture=new xORMHelper($Vente->Main,$IdFacture,false,$Vente->Table);
        $vFact=$Facture->ToArray();
        $DetailF=new xDetailVente($Facture->Main,null,false,null,null,$IdFacture);
        $Lignes=$DetailF->GetFullInfosFactureByLine($IdFacture);
        if ($Lignes){
            if(count($Lignes)){
                $vFact['DETAIL']=$Lignes;
            }
        }
        $Reponse->Contenue =$vFact ;        
        return $Reponse;
    }

    /**
     * Enregistre une vente de Carburant dans un Compte à Crédit à partir du montant versé.
     * Le montant de la vente sera déduite du compte client du pompiste ayant servit
     * @param xClient $ClientDst 
     * @param float $MtServit 
     * @param xIndexPompe $IndexPompe 
     * @return xNotification 
     * @throws Exception 
     */
    public static function SaveVenteToBonClientFromMontant(xClient $ClientDst, float $MtServit, xIndexPompe $IndexPompe, string|DateTime $DateVers = null):xNotification{
        $Reponse=new xNotification;
        $Reponse->OK=0;
        #region "Condition Initiale"
            $date=date("Y-m-d");
            if (is_string($DateVers)){
                $dte=new DateTime($DateVers);
                if ($dte){
                    $date = $dte->format("Y-m-d");
                }
            }elseif(is_object($DateVers)){
                $date = $DateVers->format("Y-m-d");
            }
            if(!isset($ClientDst)){
                $Reponse->TxErreur-"Absence du Compte Client Destinataire.";
                return $Reponse;
            }
            if($ClientDst->Id==0){
                $Reponse->TxErreur-"Absence du Compte Client Destinataire.";
                return $Reponse;
            }
            if ($MtServit == 0){
                $Reponse->TxErreur-"Aucun montant fournit.";
                return $Reponse;
            }
            if ($IndexPompe->Id==0){
                $Reponse->TxErreur-"Aucun Index enregistré.";
                return $Reponse;
            }
            if ($IndexPompe->IdFacture == 0){
                $Reponse->TxErreur-"Aucune Facture émise. Vueillez enregistrer l'indexe en cour de la pompe ".$IndexPompe->Pompe->Nom;
                return $Reponse;
            }

            if ((int)$IndexPompe->Ecart==0){
                $Reponse->TxErreur = "Aucune Vente effectuée. Ecart null";
                return $Reponse;
            }
        #endregion

        $VenteOrig=new xVente(self::$xMain,$IndexPompe->IDFACTURE) ;
        if ($VenteOrig->Id == 0){
            $VenteOrig->DateFacture=$date ;
        }
        $DetailF=new xDetailVente($VenteOrig->Main,null,false,null,null,$VenteOrig->Id);
        $IdCpteClt=$VenteOrig->IdClient ; //Pour les Vente Directe
        $Carburant = new xCuveStockageCarburant($IndexPompe->Main,$IndexPompe->Pompe->IdCarburant);
        $Designation = $Carburant->TypeCarburant ;
        $CodeBar = $Designation."_".$Carburant::FAMILLE_CARBURANT;
        $QteServit = 0;
        
        $Pdt=new xProduitLiaison(self::$xMain);
        $Pdt = $Pdt->GetPdtNAbySyGS(null,$CodeBar);
        if (!isset($Pdt)){
            $Reponse->TxErreur="Produit ".$Designation." introuvable dans NAbySyGS.";
            return $Reponse;
        }
        //On va valorier la qté de carburant servit en montant
        $IdDetailFacture = null;
        foreach($DetailF->ListeProduits as $rw){
            if ($Pdt->Id == (int)$rw['IDPRODUIT']){
                //Produit Trouvé
                $IdDetailFacture = (int)$rw['ID'];
                break;
            }
        }
        if(!isset($IdDetailFacture)){
            $Reponse->TxErreur="Produit ".$Designation." introuvable dans la facture du pompiste. Enregistré un relevé d'index avant de continuer.";
            return $Reponse;
        }
        //Calcul de la Qté Servit
        $LigneVente=new xDetailVente(self::$xMain,$IdDetailFacture);
        $QteServit = $MtServit / (float)$LigneVente->PrixVente ;
        if ($QteServit ==0){
            $Reponse->TxErreur="Erreur sur le Montant  ou le Prix de Vente.";
            return $Reponse;
        }
        //$LigneVente->AddToLog("Equivalent de ".$MtServit." servit en espece donne ".$QteServit." en carburant");
        return self::SaveVenteToBonClient($ClientDst, $QteServit, $IndexPompe);        
    }
}

?>