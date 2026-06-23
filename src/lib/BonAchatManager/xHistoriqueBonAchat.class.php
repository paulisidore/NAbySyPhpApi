<?php
namespace NAbySy\Lib\BonAchat ;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/** Permet de gérer l'historique des bons d'Achat */
Class xHistoriqueBonAchat extends xORMHelper{
   
    public ?xBonAchatManager $BonAchatManager;

    public function __construct(?xNAbySyGS $NabySy = null,?int $Id=null,?bool $AutoCreateTable=true, ?string $TableName="detailbonachat", ?string $DBName=null, xBonAchatManager $BonAMgr=null)
    {
        
        parent::__construct($NabySy,$Id,$AutoCreateTable,$TableName, $DBName) ;
        $this->BonAchatManager=$BonAMgr;

    }
    
}

?>