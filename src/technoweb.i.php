<?php
namespace NAbySy\TechnoWEB ;

use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
 * Module TechnoWEB
 * @package NAbySy\TechnoWEB
 */
interface ITechnoWEB {

    public function  __construct(xNAbySyGS $nabysy ) ;

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
     * Retourne un client TechnoWEB seln son ID
     * @param int $IdClient 
     * @return xORMHelper|null 
     */
    public static function GetClientTechnoWebByID(int $IdClient):?xORMHelper ;

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

    /**
     * Retourne les Informations de facturation du client TechnoWEB
     * @param xORMHelper $CltTechnoWEB 
     * @return null|xORMHelper 
     */
    public static function GetClientBillingInfos(xORMHelper $CltTechnoWEB):?xORMHelper;

    /**
     * Indique si le client TechnoWEB à un abonnement Actif
     * @param xORMHelper $CltTechnoWEB 
     * @return bool 
     */
    public static function BillingIsOK(xORMHelper $CltTechnoWEB):bool;

    /**
     * Retourne le montant de l'abonnement au service TechnoWEB
     * @param xORMHelper $CltTechnoWEB 
     * @return float 
     */
    public static function GetMontantAbonnement(xORMHelper $CltTechnoWEB):float;

    /**
     * Retourne la durée normale d'un abonnement TechnoWEB
     * @param xORMHelper $CltTechnoWEB 
     * @return int 
     */
    public static function GetDureeAbonnement(xORMHelper $CltTechnoWEB):int ;

    /**
     * Indique si OUI/NON la facturation du service est soumise à la TVA
     * @param xORMHelper|null $CltTechnoWEB
     * @return bool 
     */
    public static function PriseEnChargeTVA(?xORMHelper $CltTechnoWEB = null):bool ;

    /**
     * Retourne le Taux de la TVA appliquée selon le Client TechnoWEB
     * @param xORMHelper $CltTechnoWEB 
     * @return float 
     */
    public static function GetTauxTVA(xORMHelper $CltTechnoWEB):float ;

    /**
     * Retourne le montant de la TVA si Applicable
     * @param float $Montant 
     * @return float 
     */
    public static function GetMontantTVA(float $Montant):float;

    /**
     * Retourne le montant TTC de l'abonnement
     * @param float $Montant 
     * @return float 
     */
    public static function GetMontantTTC(float $Montant):float;

    /**
     * Retourne le montant HT de l'abonnement
     * @param float $Montant 
     * @return float 
     */
    public static function GetMontantHT(float $Montant):float;

    /**
     * Retourne la Table de Tarification concernant un client TechnoWEB
     * @param xORMHelper $CltTechnoWEB 
     * @return null|xORMHelper 
     */
    public static function GetTarification(xORMHelper $CltTechnoWEB):?xORMHelper;

    /**
     * Retourne la liste des Factures d'un Client
     * @param xORMHelper $CltTechnoWEB 
     * @return xORMHelper[] 
     */
    public static function GetListeFacture(xORMHelper $CltTechnoWEB):array;

}
?>