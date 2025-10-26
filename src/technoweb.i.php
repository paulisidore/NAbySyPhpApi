<?php
namespace NAbySy\Lib\ModuleExterne\TechnoWEB ;

use NAbySy\ORM\xORMHelper;

/**
 * Module TechnoWEB
 * @package NAbySy\Lib\ModuleExterne\TechnoWEB
 */
interface ITechnoWEB {

    /**
     * Indique que le module est prêt à travailler
     * @return bool 
     */
    public static function Ready():bool ;

    /**
     * Retourne les information d'un client TechnoWEB grâce à son identifiant TechnoWeb
     * @param string $IdTechnoWeb
     * @return \NAbySy\ORM\xORMHelper|null
     */
    public static function GetClientTechnoWeb(string $IdTechnoWeb):xORMHelper|null ;

    /**
     * Crée un Nouveau Client TechnoWEB
     * @param string $RaisonSociale
     * @param string $Pays
     * @param string $Region
     * @return \NAbySy\ORM\xORMHelper|null
     */
    public static function CreateNewClient(string $RaisonSociale, string $Pays, string $Region):xORMHelper|null ;

    /**
     * Génère une nouvelle base de donnée pour les Client CLOUD de TechnoWEB
     * @param \NAbySy\ORM\xORMHelper $Clt
     * @return bool
     */
    public static function GenerateNewDBaseClient(xORMHelper $Clt):bool ;

}
?>