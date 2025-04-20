<?php
namespace NAbySy\GS\Comptabilite ;

use DateTime;
use Exception;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Stock\xJournalCaisse;
use NAbySy\ORM\xORMHelper;
use xErreur;
use NAbySy\xNAbySyGS;
use xNotification;
use xUser;

/**
 * Module de Gestion des Transactions.
 * @package NAbySy\GS\Comptabilite
 */
Class xHistoriqueTransaction extends xTransactionInfos{

    public static xCategorieTransaction $Categories ;

	public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="transaction"){
		if ($TableName==''){
            $TableName="transaction";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        self::$Categories = new xCategorieTransaction($NabySy);
	}

    /**
     * Enregistre unue transaction dans l'historique
     * @param xTransactionInfos $Infos 
     * @return xHistoriqueTransaction 
     */
    public function EnregistrerInfoTransaction(xTransactionInfos $Infos):xHistoriqueTransaction{
        $Data=$Infos->ListeChampDB ;
        $NewInfo=new xHistoriqueTransaction($this->Main) ;
        $NewInfo->ListeChampDB = $Infos->ListeChampDB;
        $NewInfo->Enregistrer();
        return $NewInfo ;        
    }

    /**
     * Enregistre un nouveau versement client dans la base de donnée et ajuste le solde du client
     * @param xClient $Client 
     * @param float $Montant 
     * @param string|null $Libelle 
     * @param xCategorieTransaction|null $Categorie 
     * @param null|string|DateTime $DateVers 
     * @param string $ModeReglement 
     * @param xInfosCheque|null $ChequeInfos 
     * @return xHistoriqueTransaction 
     * @throws Exception 
     */
    public function EnregistrerNouveauVersementClient(xClient $Client, float $Montant,string $Libelle=null,xCategorieTransaction $Categorie=null,string|DateTime $DateVers = null,string $ModeReglement="E",xInfosCheque $ChequeInfos=null): xHistoriqueTransaction{
        $date=date("Y-m-d");
        if (is_string($DateVers)){
            $dte=new DateTime($DateVers);
            if ($dte){
                $date = $dte->format("Y-m-d");
            }
        }elseif(is_object($DateVers)){
            $date = $DateVers->format("Y-m-d");
        }

        if(!isset($Categorie)){
            $Categorie= self::$Categories::GetCategorieVersBonClt() ; //new xORMHelper($this->Main);
        }
        if (!isset($Categorie)){
            throw new Exception("Catégorie non définit ou introuvable", 1);
        }

        $NewInfo=new xHistoriqueTransaction($this->Main) ;
        $NewInfo->IDCLIENT = $Client->Id." BCLIENT";
        $NewInfo->TypeTransaction = "E";
        $NewInfo->IdCategorie = $Categorie->Id;
        $NewInfo->NomCategorie = $Categorie->Nom;
        if (!isset($Libelle)){
            $DteV=new DateTime($date);
            $Libelle = "VERSEMENT BONS CLIENT DU ".$DteV->format("d/m/Y"." à ".date('H:i:s'));
        }
        $NewInfo->Libelle = $Libelle ;
        $NewInfo->DATEOP=$date;
        $NewInfo->DateEnregistrement = date("Y-m-d");
        $NewInfo->HeureOP = date("H:i:s");
        $NewInfo->Montant = $Montant ;
        $SoldePrec=$Client->Solde ;
        $SoldeSuive=$Client->Solde - $Montant ;
        $NewInfo->SoldePrecedent = $SoldePrec ;
        $NewInfo->SoldeSuivant = $SoldeSuive ;
        $NewInfo->IdOperateur = $this->Main->User->Id;
        $NewInfo->NomCaissier = $this->Main->User->Login ;
        $NewInfo->Type_Reglement = $ModeReglement ;

        $Banque=null;
        if(isset($ChequeInfos)){
            $NewInfo->Emetteur = $ChequeInfos->Emetteur ;
            $NewInfo->IdCompteBancaire = $ChequeInfos->IdBanqueReception ;
            $NewInfo->NumCheque = $ChequeInfos->NumCheque ;
            if ($NewInfo->IdCompteBancaire){
                $Banque=new xCompteBancaire($this->Main,$NewInfo->IdCompteBancaire);
                $NewInfo->BANQUE_SOLDEPREC = $Banque->Solde ;
                $NewInfo->BANQUE_SOLDESUIV = $Banque->Solde + $NewInfo->Montant ;                
            }            
        }
        
        $NewInfo->Enregistrer() ;
        $IdTransact = $NewInfo->Id ;
        $Trans=new xHistoriqueTransaction($this->Main,$IdTransact);
        if ($Trans->Id>0){
            return $Trans ;
        }
        return null;
    }

    public function Enregistrer(): bool {
        $MtPrec=null;
        #region Modification de Transaction
            if ($this->Id>0){
                $TransPrec=new xHistoriqueTransaction($this->Main,$this->Id);
                $MtPrec=$TransPrec->Montant;
                $IdClt=$TransPrec->IdClient;
                if(strpos($IdClt," BCLIENT")){
                    $IdClt = str_replace(" BCLIENT","",$IdClt);
                }
                $IdClient=(int)$IdClt;
                if ($IdClient>0){
                    $Client=new xClient($TransPrec->Main,$IdClient);
                    if ($TransPrec->TypeTransaction =="E"){
                        $SoldeCPrec=$Client->Solde;
                        $SoldeCSuiv=$Client->Solde + $MtPrec ;
                        $Client->CrediterSolde($MtPrec);
                    }else{
                        $SoldeCPrec=$Client->Solde;
                        $SoldeCSuiv=$Client->Solde - $MtPrec ;
                        $Client->DebiterSolde($MtPrec);
                    }
                    $TxJ="Suite à une modification de la transaction de type ".$TransPrec->TypeTransaction.", 
                    le solde du compte client ".$Client->Id." est passé de ".$SoldeCPrec." à ".$SoldeCSuiv;
                    $Client->AddToJournal("MODIFICATION",$TxJ);
                    
                }
                
                //On annule le solde du compte Bancare eventuel
                $Banque=null;
                if($TransPrec->IdCompteBancaire >0){
                    if ($TransPrec->IdCompteBancaire){
                        $Banque=new xCompteBancaire($this->Main,$TransPrec->IdCompteBancaire);
                        if ($TransPrec->TypeTransaction =="E"){
                            $Banque->Debiter($TransPrec->Montant) ; 
                        }else{
                            $Banque->Crediter($TransPrec->Montant) ; 
                        }
                    }
                }

                //On annule de la caisse du jour
                #region Enregitrement de la caisse du jour
                    $CaisseGlobale=new xJournalCaisse($TransPrec->Main,null,$TransPrec->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,0,$TransPrec->DATEOP);
                    $CaisseU=new xJournalCaisse($TransPrec->Main,null,$TransPrec->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,$TransPrec->Main->User->Id,$TransPrec->DATEOP);
                    $CaisseGlobale->TOTAL_ENTREE -= $TransPrec->Montant;
                    $CaisseU->TOTAL_ENTREE -= $TransPrec->Montant;
                    $CaisseGlobale->NB_ENTREE -= 1;
                    $CaisseU->NB_ENTREE -= 1;               
                    $CaisseGlobale->Enregistrer();
                    $CaisseU->Enregistrer();                		
                #endregion
            }
        #endregion

        if(parent::Enregistrer()){
            //$this->AddToLog("Le IdClient de la Transaction est ".$this->IdClient);
            $IdClt=$this->IdClient;
            if(strpos($IdClt," BCLIENT")){
                $IdClt = str_replace(" BCLIENT","",$IdClt);
            }
            $IdClient=(int)$IdClt;
            if($IdClient>0){
                $Client=new xClient($this->Main,$IdClient);
                $SoldeCPrec=$Client->Solde;
                $SoldeCSuiv=$Client->Solde - $this->Montant ;
                $TxJ = "Suite à un versement client, le compte-client n°".$Client->Id." est passé de ".$SoldeCPrec." à ".$SoldeCSuiv;
                if ($Client->DebiterSolde($this->Montant)){
                    $Client->AddToJournal("VERSEMENT CLIENT",$TxJ);
                } 
            }

            #region Enregitrement de la caisse du jour
                $CaisseGlobale=new xJournalCaisse($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,0,$this->DATEOP);
                $CaisseU=new xJournalCaisse($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,null,$this->Main->User->Id,$this->DATEOP);
                $CaisseGlobale->TOTAL_ENTREE += $this->Montant;
                $CaisseU->TOTAL_ENTREE += $this->Montant;
                $CaisseGlobale->NB_ENTREE += 1;
                $CaisseU->NB_ENTREE += 1;               
                $CaisseGlobale->Enregistrer();
                $CaisseU->Enregistrer();                		
            #endregion

            //On ajuste éventuelement le solde du compte Bancare eventuel
            $Banque=null;
            if($this->IdCompteBancaire >0){                
                $Banque=new xCompteBancaire($this->Main,$this->IdCompteBancaire);
                $Banque->Crediter($this->Montant);                            
            }

            return true;
        }
        return false;
    }
   
    public function GetTotalTransacton(DateTime $DateDu, DateTime $DateAu,int $IdCaissier=null, int $IdClient=null, int $IdCompte=null ):xNotification{
        $Err=new xErreur ;
        $Reponse=new xNotification;
        $Err->OK=0;
        $Reponse->OK=1;

        $Resume['VERSEMENT']=[];
        $Resume['DEPENSE']=[];
        $Trans = new xHistoriqueTransaction($this->Main);
        if (!isset($DateDu)){
            $DateDu= new DateTime('now');
        }
        if (!isset($DateAu)){
            $DateAu= new DateTime('now');
        }
        if(isset($_REQUEST['IDCAISSIER'])){
            if((int)$_REQUEST['IDCAISSIER'] > 0 ){
                $IdCaissier = (int)$_REQUEST['IDCAISSIER'];
            }
        }
        if(isset($_REQUEST['IDCLIENT'])){
            if((int)$_REQUEST['IDCLIENT'] > 0 ){
                $IdClient = (int)$_REQUEST['IDCLIENT'];
            }
        }
        $Resume['PERIODE_DU']=$DateDu->format("Y-m-d");
        $Resume['PERIODE_AU']=$DateAu->format("Y-m-d");
        $Resume['IDCAISSIER']=$IdCaissier;
        $Resume['CAISSIER']=null;
        $Resume['IDCLIENT']=$IdClient;
        $Resume['CLIENT']=null;
        $Resume['IDCOMPTE']=$IdCompte;
        $Resume['COMPTE']=null;
        $Resume['VERSEMENT']['MONTANT']=0;
        $Resume['VERSEMENT']['NB']=0;
        $Resume['DEPENSE']['MONTANT']=0;
        $Resume['DEPENSE']['NB']=0;
        
        $Critere="DATEOP>='".$DateDu->format("Y-m-d")."' and DATEOP <='".$DateAu->format("Y-m-d")."' " ;
        if(isset($IdCaissier)){
            if($IdCaissier>0){
                $Operateur=new xUser($this->Main,$IdCaissier);
                if ($Operateur->Id){
                    $Resume['CAISSIER']=$Operateur->Login;
                    $Critere .=" and IDOPERATEUR = ".(int)$IdCaissier;
                }else{
                    $Err->TxErreur="Opérateur/Caissier introuvable !";
                    return $Err ;
                }
            }
            
        }
        if(isset($IdClient)){
            $Client=new xClient($this->Main,$IdClient);
            if($Client->Id>0){
                $Resume['CLIENT']=$Client->Prenom." ".$Client->Nom;
                $Critere .=" and (IDCLIENT = ".(int)$IdClient." OR IDCLIENT like '".(int)$IdClient."%') "  ;
            }else{
                $Err->TxErreur="Client introuvable !";
                return $Err ;
            }            
        }
        if(isset($IdCompte)){
            if ($IdCompte>0){
                $CompteB=new xCompteBancaire($this->Main,$IdCompte);
                if ($CompteB->Id>0){
                    $Resume['COMPTE']=$CompteB->Nom;
                }
            }else{
                $Resume['COMPTE']="CAISSE";
            }           
            $Critere .=" and IdCompteBancaire like '".(int)$IdCompte  ;
        }
        $SelectChamp = "SUM(Montant) as 'Montant', COUNT(ID) as 'NB' " ;
        //Recherche du total des Transactions en versement
        $Trans->DebugSelect=false ;
        $Lst=$Trans->ChargeListe($Critere." and TypeTransaction like 'E'",null,$SelectChamp);
        if($Lst->num_rows>0){
            $rw=$Lst->fetch_assoc();
            $Resume['VERSEMENT']['MONTANT']= (float)$rw['Montant'];
            $Resume['VERSEMENT']['NB']=(int)$rw['NB'];
        }
        //Recherche du total des Transactions en dépense
        $Trans->DebugSelect=false ;
        $Lst=$Trans->ChargeListe($Critere." and TypeTransaction not like 'E'",null,$SelectChamp);
        if($Lst->num_rows>0){
            $rw=$Lst->fetch_assoc();
            $Resume['DEPENSE']['MONTANT']= (float)$rw['Montant'];
            $Resume['DEPENSE']['NB']= (int)$rw['NB'];
        }        
        $Reponse->Contenue = $Resume;
        //$this->AddToLog("GetTransaction Line ".__LINE__." ".json_encode($Reponse));

        return $Reponse;
    }


}


/**
 * Information générale d'un chèque
 * @package NAbySy\GS\Comptabilite
 */
class xInfosCheque {
    public string $NumCheque = "";
    public string $NomBanque = "";
    public string $NumCompte = "";
    public string $Porteur = "" ;
    public string $Emetteur="" ;
    public float $Montant = 0;
    public int $IdBanqueReception = 0;
}

/**
 * Gestion des categories de Transaction
 * @package NAbySy\GS\Comptabilite
 */
class xCategorieTransaction extends xORMHelper{
    public const CATEGORIE_REGLEMENT_CLIENT = "REGLEMENT BON CLIENT";
    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="categorie"){
		if ($TableName==''){
            $TableName="categorie";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        if($this->TableIsEmpty()){
            $TxSQL="insert into `".$TableName."` (`Nom`) value('".self::CATEGORIE_REGLEMENT_CLIENT."')";
            $this->ExecUpdateSQL($TxSQL);
        }
	}

    /**
     * Renvoie la catégorie des versements bons clients.
     * @return xCategorieTransaction 
     */
    public static function GetCategorieVersBonClt():xCategorieTransaction{
        $Categ=new xCategorieTransaction(self::$xMain);
        return $Categ->GetCategorieByName(self::CATEGORIE_REGLEMENT_CLIENT);
    }

    /**
     * Retourne une caégorie par son Nom
     * @param string $Nom 
     * @return null|xCategorieTransaction 
     */
    public function GetCategorieByName(string $Nom):?xCategorieTransaction{
        $Nom=$this->Main->EscapedForJSON($Nom);        
        $Lst=$this->ChargeListe("Nom like '".$Nom."' ");
        if($Lst->num_rows){
            $rw = $Lst->fetch_assoc();
            $Categorie=new xCategorieTransaction($this->Main,$rw['ID']);
            return $Categorie ;
        }
        return null;
    }
}