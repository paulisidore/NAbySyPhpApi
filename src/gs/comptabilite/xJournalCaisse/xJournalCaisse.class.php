<?php
/**
 * Module Caisse pour NAbySy Gestion Commerciale
 * By Paul & Aïcha Machinerie
 * paul_isidore@hotmail.com
 */

namespace NAbySy\GS\Stock ;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

class xJournalCaisse extends xORMHelper {
    
    private int $Annee ;

    /**
     * Journal Caisse
     * @param xNAbySyGS $NabySy 
     * @param null|int $Id | Id de la caisse
     * @param bool $CreationChampAuto 
     * @param string $TableName 
     * @param int $IdCaissier | Si IdCaissier = 0, il s'agira de la caisse globale si non d'une caisse individuelle
     * @param string|null $dateCaisse | La date de caisse souhaitée. Si aucune Date fournit la date du jour sera utilisée.
     * @return void 
     * @throws Exception 
     */
    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="caisse",int $IdCaissier=0, string $dateCaisse=null){
        if ($TableName==''){
            $TableName="caisse";
        }
        //On determine la table en fonction de l'ID
        $this->Annee=date("Y");
        if (isset($dateCaisse)){
            $Dte=new DateTime($dateCaisse);
            if ($Dte){
                $this->Annee = $Dte->format("Y");
            }else{
                throw new Exception("Format de date incorrect. ".$dateCaisse." n'est pas une date de caisse valide!", 1);
            }
        }
        $vTableName = $TableName."_".$this->Annee ;
        if (isset($IdCaissier)){
            if ($IdCaissier>0){
                //Caisse Individuelle
                $vTableName = $TableName."_".$IdCaissier."_".$this->Annee ;
            }
        }

        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$vTableName);
        if (!$this->TableExiste()){
            //Création de la table
            $TxSQL="create table `".$this->Table."` like caisse";
            $this->ExecUpdateSQL($TxSQL);
            $this->InitialiseCaisse();
        }
        if (isset($dateCaisse)){
            $vID=$this->GetIdCaisse($dateCaisse);
            if ($vID>0){
                parent::__construct($NabySy,(int)$vID,$CreationChampAuto,$vTableName);
                if (!$this->TableExiste()){
                    //Création de la table
                    $TxSQL="create table `".$this->Table."` like caisse";
                    $this->ExecUpdateSQL($TxSQL);
                    $this->InitialiseCaisse();
                }
            }else{
                //On crée la caisse ?
                $this->AddToLog("Création de la caisse ".$this->Table." ...");
                $this->InitialiseCaisse(true);
                // $dt=$Dte->format("Y-m-d");
                // $TxSQL="insert into `".$this->Table."` (DATESERVICE) VALUE('".$dt."')";
                // $this->ExecUpdateSQL($TxSQL);
                $vID=$this->GetIdCaisse($dateCaisse);
                if ($vID>0){
                    parent::__construct($NabySy,(int)$vID,$CreationChampAuto,$vTableName);
                    $this->AddToLog("La caisse ".$this->Table." a été créee correctement.");
                }else{
                    throw new Exception("Impossible de créer la caisse ".$this->Table, 1);
                }
            }
        }        
    }

    /**
     * Initialise une caisse
     * @return bool 
     */
    public function InitialiseCaisse(bool $ForceCreate=false):bool{
        if (!$ForceCreate){
            if (!$this->TableIsEmpty()){
                return true;
            }
            $dteD=$this->Annee."-01-01";
            $dteF=$this->Annee."-12-31";
            $begin = new DateTime($dteD);
            $end = new DateTime($dteF);
            $interval = DateInterval::createFromDateString('1 day');
            $opt=2;
            $period = new DatePeriod($begin, $interval, $end,$opt);
            if (defined($period::INCLUDE_END_DATE)){
                $opt=DatePeriod::INCLUDE_END_DATE;
            }else{
                //$this->AddToLog(__FILE__."L".__LINE__.": La constante DatePeriod::INCLUDE_END_DATE n'est pas définit!.");
            }
            foreach ($period as $dt) {
                $dateFinale=$dt->format("Y-m-d");
                $TxSQL="insert ignore into `".$this->Table."` (DATESERVICE) VALUE('".$dateFinale."')";
                $this->ExecUpdateSQL($TxSQL);                
            }

        }else{
            $dteD=$this->Annee."-01-01";
            $dteF=$this->Annee."-12-31";
            $begin = new DateTime($dteD);
            $end = new DateTime($dteF);
            $interval = DateInterval::createFromDateString('1 day');
            $opt=2;
            $period = new DatePeriod($begin, $interval, $end,$opt);
            if (defined($period::INCLUDE_END_DATE)){
                $opt=DatePeriod::INCLUDE_END_DATE;
            }else{
                //$this->AddToLog(__FILE__."L".__LINE__.": La constante DatePeriod::INCLUDE_END_DATE n'est pas définit!.");
            }            
            foreach ($period as $dt) {
                $dateFinale=$dt->format("Y-m-d");
                $TxSQL="insert ignore into `".$this->Table."` (DATESERVICE) VALUE('".$dateFinale."')";
                $this->ExecUpdateSQL($TxSQL);                
            }
        }
        
        return !$this->TableIsEmpty();
    }

    /**
     * Retourne le ID correspondant à la date de la caisse
     * @param string $DateC 
     * @return int 
     */
    public function GetIdCaisse(string $DateC):int{
        $IdCaisse=0;
        if ($this->TableIsEmpty()){
            return $IdCaisse;
        }
        $vDate="";
        $Dte=new DateTime($DateC);
        if ($Dte){
            $vDate=$Dte->format("Y-m-d");
        }else{
            return $IdCaisse;
        }
        $Lst = $this->ChargeListe("DateService like '".$vDate."'");
        if($Lst->num_rows){
            $rw=$Lst->fetch_assoc();
            $IdCaisse = $rw['ID'];
        }
        return $IdCaisse ;
    }


    
}
?>