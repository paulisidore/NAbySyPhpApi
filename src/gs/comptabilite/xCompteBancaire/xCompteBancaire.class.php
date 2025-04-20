<?php
namespace NAbySy\GS\Comptabilite ;

use Exception;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module de Gestion de Compte Bancaire
 * @package NAbySy\GS\Comptabilite
 */
Class xCompteBancaire extends xORMHelper
{
    public xHistoriqueCompteBancaire $Historique ;
    public string $TableHistoriqueBancaire ="transaction";

	public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="banque"){
		if ($TableName==''){
            $TableName="banque";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        $this->Historique = new xHistoriqueCompteBancaire($NabySy,null,true,$this->TableHistoriqueBancaire);

	}

    /**
     * Crée un nouveau compte bancaire.
     * Si la banque existe, Celle déjà existante sera renvoyée.
     * Le compte est identifié par son nom et son numero de comte.
     * @param string $NomBanque 
     * @param null|float|int $Solde 
     * @param null|string $NumeroCompte : Si aucun numero n'est fournit, un par défaut lui sera attribué
     * @return null|xCompteBancaire 
     * @throws Exception 
     */
    public function CreateNewCompte(string $NomBanque,?float $Solde=0, ?string $NumeroCompte=""):?xCompteBancaire{
        if (trim($NomBanque)==""){
            throw new Exception("Le nom de la banque ne peux être null", 1);
            return null;
        }

        if (!$this->TableIsEmpty()){
            foreach($this as $Compte){
                if (strtolower($Compte->NomBanque) == strtolower($NomBanque) ){
                    $Cpte=new xCompteBancaire($Compte->Main,$Compte->Id);
                    return $Cpte;
                }
            }
        }
        $Compte=new xCompteBancaire($this->Main);
        $Compte->NomBanque = $NomBanque;
        $Compte->Solde = $Solde ;
        if(trim($NumeroCompte=="")){
            $NumeroCompte = $NomBanque."_N".count($Compte)+1;
        }
        $Compte->NumeroCompte = $NumeroCompte;
        $Compte->IsMobilePaiement = 1;
        $Compte->Enregistrer();
        return $Compte;
    }

    /**
     * Retourne le compte bancaire correspondant aux critères
     * @var NAbySy\GS\Comptabilite\GetCompteBancairByName
     */
    public function GetCompteBancaireByName(string $NomBanque , string $NumeroCompte =null):?xCompteBancaire{
        if (!$this->TableIsEmpty()){
            foreach($this as $Compte){
                if (strtolower($Compte->NomBanque) == strtolower($NomBanque) ){
                    $Cpte=new xCompteBancaire($Compte->Main,$Compte->Id);
                    return $Cpte;
                }
            }
        }
        return null;
    }
    /**
     * Credite le solde du Compte Bancaire
     * @param float $Montant 
     * @return bool 
     * @throws Exception 
     */
    public function CrediterSolde(xTransactionInfos $InfoTransaction):bool{
        if ($this->Id <=0){
            throw new Exception("Le compte n'est pas encore chargé. Id du compte = 0", 1);
            return false;
        }
        $SoldeP=$this->Solde;
        $SoldeSuive=$SoldeP + $InfoTransaction->Montant ;
        $TxJ="Le Solde du Compte ".$this->NomBanque." a été crédité. Il est passé de " . $SoldeP . " à ". $SoldeSuive;
        $this->Solde = $SoldeSuive ;
        $TxSQL="update `".$this->DataBase."`.`".$this->Table."` SET Solde = Solde + ".$InfoTransaction->Montant." where ID = ".$this->Id." LIMIT 1";
        $IsOK=$this->ExecUpdateSQL($TxSQL);
        if ($IsOK){
            $this->AddToJournal("BANQUE",$TxJ);
            $InfoTransaction->IdCompteBancaire = $this->Id;
            $InfoTransaction->TypeTransaction = "E";
            if($InfoTransaction->Id==0){
                $InfoTransaction->IdOperateur = self::$xMain->User->Id ;
                $InfoTransaction->NomCaissier = self::$xMain->User->Login ;
                $InfoTransaction->DateEnregistrement = date('Y-m-d');
                
            }
            $InfoTransaction->BANQUE_SOLDEPREC = $SoldeP ;
            $InfoTransaction->BANQUE_SOLDESUIV = $SoldeP ;            
            //Enregistrement dans l'historique des transactions Bancaires
            $Hist=$this->Historique->EnregistrerInfoTransaction($InfoTransaction);
            
        }
        return $IsOK;
    }

    /**
     * Débite le solde du Compte Bancaire
     * @param float $Montant 
     * @return bool 
     * @throws Exception 
     */
    public function DebiterSolde(xTransactionInfos $InfoTransaction):bool{
        if ($this->Id <=0){
            throw new Exception("Le compte n'est pas encore chargé. Id du compte = 0", 1);
            return false;
        }
        $SoldeP=$this->Solde;
        $SoldeSuive=$SoldeP - $InfoTransaction->Montant ;
        $TxJ="Le Solde du Compte ".$this->NomBanque." a été débité. Il est passé de " . $SoldeP . " à ". $SoldeSuive;
        $this->Solde = $SoldeSuive ;
        $TxSQL="update `".$this->DataBase."`.`".$this->Table."` SET Solde = Solde - ".$InfoTransaction->Montant." where ID = ".$this->Id." LIMIT 1";
        $IsOK=$this->ExecUpdateSQL($TxSQL);
        if ($IsOK){
            $this->AddToJournal("BANQUE",$TxJ);
            $InfoTransaction->IdCompteBancaire = $this->Id;
            $InfoTransaction->TypeTransaction = "S";
            if($InfoTransaction->Id==0){
                $InfoTransaction->IdOperateur = self::$xMain->User->Id ;
                $InfoTransaction->NomCaissier = self::$xMain->User->Login ;
                $InfoTransaction->DateEnregistrement = date('Y-m-d');
                
            }
            $InfoTransaction->BANQUE_SOLDEPREC = $SoldeP ;
            $InfoTransaction->BANQUE_SOLDESUIV = $SoldeP ;            
            //Enregistrement dans l'historique des transactions Bancaires
            $Hist=$this->Historique->EnregistrerInfoTransaction($InfoTransaction);
            
        }
        return $IsOK;
    }

    /**
     * Crédite le solde de la banque
     * @param float $Montant 
     * @return bool 
     * @throws Exception 
     */
    public function Crediter(float $Montant):bool{
        if ($this->Id <=0){
            throw new Exception("Compte Introuvable. Id du compte = 0", 1);
            return false;
        }
        $SoldeP=$this->Solde;
        $SoldeSuive=$SoldeP + $Montant ;
        $TxJ="Le Solde du Compte ".$this->NomBanque." a été crédité. Il est passé de " . $SoldeP . " à ". $SoldeSuive;
        $this->Solde = $SoldeSuive ;
        $TxSQL="update `".$this->DataBase."`.`".$this->Table."` SET Solde = Solde + ".$Montant." where ID = ".$this->Id." LIMIT 1";
        $IsOK=$this->ExecUpdateSQL($TxSQL);
        if ($IsOK){
            $this->AddToJournal("BANQUE",$TxJ);
            return true;            
        }
        return false;
    }

    /**
     * Débite le solde de la banque
     * @param float $Montant 
     * @return bool 
     * @throws Exception 
     */
    public function Debiter(float $Montant):bool{
        if ($this->Id <=0){
            throw new Exception("Compte Introuvable. Id du compte = 0", 1);
            return false;
        }
        $SoldeP=$this->Solde;
        $SoldeSuive=$SoldeP - $Montant ;
        $TxJ="Le Solde du Compte ".$this->NomBanque." a été débité. Il est passé de " . $SoldeP . " à ". $SoldeSuive;
        $this->Solde = $SoldeSuive ;
        $TxSQL="update `".$this->DataBase."`.`".$this->Table."` SET Solde = Solde - ".$Montant." where ID = ".$this->Id." LIMIT 1";
        $IsOK=$this->ExecUpdateSQL($TxSQL);
        if ($IsOK){
            $this->AddToJournal("BANQUE",$TxJ);
            return true;            
        }
        return false;
    }

}