<?php
namespace NAbySy\Lib\BonAchat ;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/** Permet de gérer l'historique des bons d'Achat */
Class xHistoriqueBonAchat extends xORMHelper{
   
    public ?xBonAchatManager $BonAchatManager;

    public function __construct(xNAbySyGS $NAbySy,?int $Id=null,$AutoCreateTable=true,$TableName="detailbonachat", xBonAchatManager $BonAMgr=null)
    {
        
        parent::__construct($NAbySy,$Id,$AutoCreateTable,$TableName) ;
        $this->BonAchatManager=$BonAMgr;

    }
    
}

?>