<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 */
class xPompe extends xORMHelper {

    public function __construct(xNAbySyGS $NabySy,?int $IdUser=null,$CreationChampAuto=true,$TableName="station_pompe"){
        if ($TableName==''){
            $TableName="station_pompe";
        }
        parent::__construct($NabySy,(int)$IdUser,$CreationChampAuto,$TableName);
    }

    public function ToObject(): ?object{
        if ($this->Id==0){return null;}
        $Cuve = new xCuveStockageCarburant($this->Main);
        $iLot=new xILot($this->Main);
        $TxSQL="select  p.IDILOT, p.*, c.NOM as 'CARBURANT', i.NOM as 'ILOT' from ".$this->Table." p 
            left outer join ".$Cuve->Table." c on c.ID = p.IdCarburant 
            left outer join ".$iLot->Table." i on i.ID = p.IdIlot ";
        $TxSQL .=" Where p.Id = ".$this->Id." limit 1";
        $Lst=$this->ExecSQL($TxSQL);
        if ($Lst){
            if($Lst->num_rows){
                $rw = $Lst->fetch_object();
                return $rw ;
            }
        }
        return null;
    }

}

?>