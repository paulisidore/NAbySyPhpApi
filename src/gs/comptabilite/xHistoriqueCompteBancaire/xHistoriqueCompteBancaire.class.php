<?php
namespace NAbySy\GS\Comptabilite ;

use Exception;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
 * Module de Gestion de Compte Bancaire
 * @package NAbySy\GS\Comptabilite
 */
Class xHistoriqueCompteBancaire extends xORMHelper
{
	public function __construct(?xNAbySyGS $NabySy = null,?int $Id=null, ?bool $CreationChampAuto=true, ?string $TableName="transaction", ?string $DBName=null){
		if ($TableName==''){
            $TableName="transaction";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName, $DBName);
		
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