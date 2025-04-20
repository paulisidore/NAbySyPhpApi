<?php
namespace NAbySy\Lib\ModulePaie\Wave;

use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Objet de Demande de Session de paiement
 */
class xCheckOutParam extends xORMHelper {

    /** Indique que le client a effectivement payé la transaction */
    public const PAIEMENT_VALIDER = 'PAIEMENT_VALIDER' ;

    /** Indique que le client a refusé de payer la transaction */
    public const PAIEMENT_REFUSER = 'PAIEMENT_REFUSER' ;

    /** Indique que le systeme a annulé la transaction */
    public const PAIEMENT_ANNULER = 'PAIEMENT_ANNULER' ;

    /** Indique que la transaction est en cour de traitement */
    public const PAIEMENT_ENCOUR = 'PAIEMENT_ENCOUR' ;

    /** Indique que la demande de paiement a expirée */
    public const PAIEMENT_EXPIRER = 'PAIEMENT_EXPIRER' ;

    /** Indique que la transaction à été remboursée */
    public const PAIEMENT_REMBOURSER ='PAIEMENT_REMBOURSER' ;


    public function __construct(xNAbySyGS $NAbySy,?int $Id=null,$AutoCreateTable=false,$TableName="nabysywave_transation")
    {
            if ($TableName == ''){
                $TableName='nabysywave_transation';
            }
            parent::__construct($NAbySy,$Id,$AutoCreateTable,$TableName) ;
            if (!$this->MySQL->TableExiste($this->Table)){
                $this->FlushMeToDB();
            }
    }  

    /**
     * Retourne la demande au Format JSON
     */
    public function GetDemandeJSON(string $Action = "DEMANDE_PAIEMENT"):string{
        $json=json_encode($this->GetDemandeArray($Action));
        return $json;
    }

    /**
     * Retourne la demande au format Tableau
     */
    public function GetDemandeArray(string $Action = "DEMANDE_PAIEMENT"):array{
        if ($Action != ''){
            $Demande['Action']=$Action ;
        }
        $Demande['ID']=$this->ID;
        $Demande['IDDEMANDE'] = $this->IdDemandeNAbySy;
        $Demande['MONTANT']=$this->Montant;
        $Demande['REFFACTURETEMP']=$this->RefFactureTemp;
        $Demande['REFFACTURE']=$this->RefFacture;
        $Demande['IDFACTURE']=$this->IdFacture;
        $Demande['IDCLIENT']=$this->IdClientPAM;
        $Demande['IDCONFIG']=$this->IDCONFIG;
        return $Demande;
    }

}
?>