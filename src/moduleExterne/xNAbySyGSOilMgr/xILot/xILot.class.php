<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE.
 * Gestion ILot de Station.
 */
class xILot extends xORMHelper {
    public static xPompe $PompeMgr ;
    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="station_ilot"){
        if ($TableName==''){
            $TableName="station_ilot";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        self::$PompeMgr = new xPompe($this->Main);
    }

    public function Existe(string $NomILot, ?array $IgnoreId = []):bool{
        if (!$this->TableExiste()){
            return false;
        }
        if ($this->TableIsEmpty()){
            return false;
        }
        $TxSQL="Nom like '".$NomILot."'";
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
     * Vérifie l'existence d'une pompe/piston dans l'ilot en cour
     * @param string $NomPompe 
     * @return bool 
     */
    public function PompeExiste(string $NomPompe):bool{
        if ($this->Id ==0){return false;}
        if ($NomPompe == ""){return false;}
        if (self::$PompeMgr->TableIsEmpty()){return false;}
        $Lst=self::$PompeMgr->ChargeListe("IdILot = ".$this->Id." and Nom like'".$NomPompe."' ");
        if ($Lst){
            if($Lst->num_rows>0){
                return true;
            }
        }
        return false;        
    }

    /**
     * Ajoute une dans pompe/piston dans l'ilot en cour
     * @param xPompe $Pompe 
     * @return bool 
     */
    public function AjoutPompe(xPompe $Pompe):bool{
        if ($this->Id == 0){return false;}
        if ($Pompe->Nom == ""){return false;}
        if ($this->PompeExiste($Pompe->Nom)){
            $Pompe->Enregistrer(); //Enregistre les modifications évntuelles opérée depuis l'appel api
            return true;
        }
        $Pompe->IdILot=$this->Id;
        if((int)$Pompe->IdCarburant==0){
            $Carb=new xCuveStockageCarburant($this->Main);
            $Carburant=$Carb->GetDefautCarburant();
            if ($Carburant){
                $Pompe->IdCarburant = $Carburant->Id;
            }
        }
        return $Pompe->Enregistrer();
    }

    /**
     * Ajoute une Pompe/Piston au présent iLot. Si la pompe n'existe pas pour cet ilot elle serra créee.
     * @param string $NomPompe : Nom donnée à la pompe
     * @param int $IdCuveStockage : 
     * @return bool 
     */
    public function AjoutPompeByName(string $NomPompe, ?int $IdCuveStockage=null):bool{
        $IdPompe=0;
        if ($this->PompeExiste($NomPompe)){
            $Lst=self::$PompeMgr->ChargeListe("Nom like '".$NomPompe."'");
            if ($Lst){
                if ($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $IdPompe=$rw['ID'];
                }
            }
        }
        $NewPompe=new xPompe($this->Main,$IdPompe);
        if ($IdPompe ==0){
            $NewPompe->IndexEnCour = 0;
            $NewPompe->DateLastReleve = date("Y-m-d H:i:s");
            $Carburant=new xCuveStockageCarburant($this->Main,$IdCuveStockage);
            if ($Carburant->Id==0){
                //On va prendre le premier carburant trouvé
                $Carburant = $Carburant->GetDefautCarburant();
            }
            $NewPompe->IdCarburant = $Carburant->Id;
        }
        return $this->AjoutPompe($NewPompe);
    }
}

?>