<?php
namespace NAbySy\GS\Comptabilite ;

use Exception;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module de Gestion de Compte Bancaire
 * @package NAbySy\GS\Comptabilite
 */
Class xHistoriqueCompteBancaire extends xORMHelper
{
	public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="transaction"){
		if ($TableName==''){
            $TableName="transaction";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
		
	}

    /**
     * Enregistre une transaction dans l'historique
     * @param xTransactionInfos $Infos 
     * @return xHistoriqueCompteBancaire 
     */
    public function EnregistrerInfoTransaction(xTransactionInfos $Infos):xHistoriqueCompteBancaire{
        $Data=$Infos->ListeChampDB ;
        $NewInfo=new xHistoriqueCompteBancaire($this->Main) ;
        $NewInfo->ListeChampDB = $Infos->ListeChampDB;
        $NewInfo->Enregistrer();
        return $NewInfo ;        
    }

}