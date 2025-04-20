<?php
namespace NAbySy\GS\Comptabilite ;

use Exception;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module de Gestion de Compte Bancaire
 * @package NAbySy\GS\Comptabilite
 * Ce module maintient la structure des champs pour enregistrer une transaction bancqire
 */
Class xTransactionInfos extends xORMHelper {
    private static $ChampDispo = [];

	public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="trans_config"){
		if ($TableName==''){
            $TableName="trans_config";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        if(count(self::$ChampDispo)==0){
            if ($this->TableIsEmpty()){
                $ListeTableCompatible=[];
                $ListeTableCompatible[]="transaction";
                foreach($ListeTableCompatible as $nomTable){
                    if (!$this->MySQL->TableExiste($nomTable,$this->Main->DataBase)){
                        $Trans=new xORMHelper($this->Main,null,false,$nomTable);
                        foreach($Trans->ListeChampDB as $champ){
                            $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                            self::$ChampDispo[]=$Ch->Nom;
                            $this->$Ch->Nom = $Ch->GetTypeChamp() ;
                        }
                        //$this->Enregistrer();
                        break;
                    }
                }
            }
        }
	}

}