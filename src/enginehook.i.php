<?php
namespace NAbySy\Events\Engine\Hook ;

use NAbySy\xNAbySyGS;
use NAbySy\xStartUpInfo;

/**
 * Module TechnoWEB
 * @package NAbySy\Lib\ModuleExterne\TechnoWEB
 */
interface IEngineHook {

    public function  __construct(xNAbySyGS $nabysy ) ;

    /**
     * Si cette fonction retourne FALSE, tous les events associés ne seront pas executés
     * @return bool 
     */
    public function canRaise():bool ;

    /**
     * Est executé avant la connexion au serveur de Base de donnée
     * @param xStartUpInfo &$StartInfo | Information qui sera utilisé pour lancer la connexion. 
     * Elle peut être modifié par le module qui implémente cette interface
     * @return xStartUpInfo | Le startup infos finale qui sera retenue par NAbySy pour la connexion vers la base de donnée
     */
    public function onBeforeConnect(xStartUpInfo &$StartInfo):xStartUpInfo ;

    /**
     * Est executé après la connexion établit par NAbySy vers le Serveur de base de donnée
     * @param xStartUpInfo $xStartUpInfo 
     * @return void 
     */
    public function onAfterConnect(xStartUpInfo $xStartUpInfo):void ;

    

}
?>