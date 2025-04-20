<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use DateTime;
use Exception;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 * Module de Gestion de l'historique des jauges-B
 */
class xJaugeB extends xORMHelper {

    /**
     * La Cuve de Carburant liée ce Jauge
     * @var null|xCuveStockageCarburant
     */
    public ?xCuveStockageCarburant $Cuve = null;

    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="station_histjaugeb"){
        if ($TableName==''){
            $TableName="station_histjaugeb";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        if ($this->Id>0){
            //On charge la Cuve Aussi
            $this->Cuve = new xCuveStockageCarburant($this->Main,$this->IDCUVE) ;
        }
    }

    /**
     * Enregistre le carburant jaugé dans une cuve
     * @param DateTime|null $Date 
     * @return bool 
     */
    public function SaveJaugeB(float $StockAct, DateTime $Date = null):bool{
        if (!isset($this->Cuve) || $StockAct <=0){
            return false ;
        }
        if (!isset($Date)){
            $Date=new DateTime('now');
        }
        $this->Cuve->AddToLog("Jauge-B en cour de sauvegarde ...");
        $IsNewJauge=true;
        $DateJauge=$Date->format("Y-m-d");
        $TxJ="Le niveau de la Cuve ".$this->Cuve->Nom." est passé de ".$this->Cuve->Stock." à ".$StockAct." ".$this->Cuve->UniteMesure; ;
        $LastJauge=$this->GetLastJaugeB($Date);
        $TxJ="Mise à jour quotidienne du niveau de la Cuve ".$this->Cuve->Nom.". Il passe de ".$this->Cuve->Stock." à ".$StockAct." ".$this->Cuve->UniteMesure;
        if ($LastJauge){
            if ($this->Id == $LastJauge->Id){
                $IsNewJauge=false;
                $this->ChargeOne($LastJauge->Id);
                $TxJ="Mise à jour quotidienne du niveau de la Cuve ".$this->Cuve->Nom.". Il passe de ".$this->Cuve->Stock." à ".$StockAct." ".$this->Cuve->UniteMesure;
                
            }
        }

        $Ecart=$this->Cuve->Stock - $StockAct;
        $TxJ .=" (soit un écart de ".$Ecart." ".$this->Cuve->UniteMesure.") ";;
        if ($IsNewJauge){
            $this->DATEJAUGE = $DateJauge;            
            $this->IDCUVE=$this->Cuve->Id;
        }
        $this->Cuve->AddToLog($TxJ);
        $DateEnreg=date("Y-m-d H:i:s");
        $HeureEnreg=date("H:i:s");
        $this->DATEENREG = $DateEnreg ;
        $this->HEURENREG = $HeureEnreg ;
        $this->STOCK_ACT = $StockAct ;
        $this->ECART=$Ecart ;
        $this->OPERATEUR = $this->Main->User->Login;
        $this->IDOPERATEUR = $this->Main->User->Id;

        if ($this->Enregistrer()){
            $this->Main->AddToJournal(null,null,"JAUGE-B",$TxJ);
            $this->Cuve->Stock = $StockAct;
            //On va retrouver le produit correspondant dans la vente pour le mettre à jour
            return $this->Cuve->Enregistrer();
        }
        return false;
    }    

    /**
     * Supprime et met à jour le stock de la Cuve de cette Jauge-B
     * @return bool 
     * @throws Exception 
     */
    public function Supprimer():bool{
        if ($this->Id == 0){
            return false ;
        }
        if (!isset($this->Cuve)){
            return false ;
        }
        $StockAct=$this->Cuve->Stock;
        $FutureStock=0;
        //Si Ecart <0 alors on avait augmenté sur le stock donc on reduit        
        $StockEcart = (float)$this->Ecart;
        if ($StockEcart < 0){
            //Reduction du stock Cuivre
            $FutureStock=$StockAct - $StockEcart ;
        }else{
            //On avait Reduit, donc on augment maintenant
            $FutureStock=$StockAct + $StockEcart ;
        }       
        $TxJ="Suppression de La jauge-B No".$this->Id." du ".$this->DateJauge." Le stock de la Cuve ".$this->Cuve->Nom." est passé de ".$this->Cuve->Stock." à ".$FutureStock." ".$this->Cuve->UniteMesure; ;
        
        $this->Cuve->Stock = $FutureStock;
        if ($this->Cuve->Enregistrer()){
            $this->AddToJournal("JAUGE-B",$TxJ);
            return parent::Supprimer();
        }
        return false;
    }

    /**
     * Retourne le Jaugage B de la cuve à la Date indiquée
     * @param DateTime|null $Date 
     * @return xJaugeB 
     */
    public function GetLastJaugeB(DateTime $Date = null):?xJaugeB{
        if ($this->TableIsEmpty()){
            return null;
        }
        if (!isset($this->Cuve)){
            return null ;
        }
        if (!isset($Date)){
            $Date=new DateTime('now');
        }
        $LastJaugeB=null;
        $DateJauge=$Date->format("Y-m-d");
        $Lst = $this->ChargeListe(" IDCUVE =".$this->Cuve->Id." AND DATEJAUGE = '".$DateJauge."' ","ID DESC","*",null,"1");
        if($Lst->num_rows){
            $rw=$Lst->fetch_assoc();
            $LastJaugeB=new xJaugeB($this->Main,$rw['ID']);
        }
        return $LastJaugeB;

    }

}

?>