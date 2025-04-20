<?php
namespace NAbySy\Lib\ModuleExterne ;

use xNAbySyGS;

/**
 * Interface des Modules externes compléntaire pour les applications NAbySyRH et NAbySyRS.
 * Chaque class implémentant cette interface doit fournit les propriétés suivantes:
 */
interface IModuleExterne {
    /**
     * Constructeur du module
     * @param xNAbySyGS $NAbySyGS : l'objet principal NAbySY en cour d'utilisation
     */
    public function __construct(xNAbySyGS $NAbySy);

    /** Retourne le nom du Module */
    public function getModuleName():string;

    /** Retourne une description du Module */
    public function getModuleDescription():string;

    /**
     * Active ou Désactive le module
     * @param bool : Vrai pour activer, Faux pour désactiver
     * @return bool : Vrai si l'opération s'est bien déroulé
     */
    public function setEnable(bool $Activer=true):bool;

    /**
     * Retourne l'Etat actuel du module
     * @return bool : Vrai si le module est activé.
     */
    public function isEnable():bool;

    /** Rretourne Vrai si le module possède une interface graphique */
    public function haveUserInterface():bool;

    /** Retourne l'url de l'interface du module */
    public function getUserInterfaceUrl():string;

    /** Retourne la liste des paramètres de type list of xModuleExterneParametre à présenter dans l'interface de paramétrage du module */
    public function getUserInterfaceParam():array;

    /** Retourne la liste des modules sur lequel ce module sera disponible Ex: NAbySyRH, NAbySyRS, ... */
    public function CanWorkOnModule(array $ModuleName=null):array;

    /** Retourne le Niveau d'accès minimun requis pour l'administration du Module */
    public function getAdminUserMinimumLevel():int ;

    /** Retourne le Niveau d'accès minimun requis pour accéder au Module */
    public function getUserMinimumLevel():int;

    /** Retourne l'url de l'interface de l'administration du module */
    public function getUserAdminInterfaceUrl():string;

    /** Retourne la liste des paramètres de type list of xModuleExterneParametre à présenter dans l'interface de paramétrage du module */
    public function getUserAdminInterfaceParam():array;

}

/** Paramtre de configuration des Modules Externes */
class xModuleExterneParametre {
    public static $TYPE_NUMERIC = 'numeric';
    public static $TYPE_TEXT = 'text';
    public static $TYPE_DATE = 'date';
    public static $TYPE_URL = 'url';
    public static $TYPE_FILE = 'file';
    public static $TYPE_ARRAY = 'array';

    public static $TYPE_API_EXEC='api_exec';

    public static $MASK_ON = "MASK_ON";
    public static $MASK_OFF = "MASK_OFF";

    public string $Description ; //Permet de proposer un toolTipText sur l'UI
    public string $PlaceHolder ; //Propose un texte en place holder pour préciser a l'utilisateur le genre de donnée attendu
    public string $Nom;
    public $Valeur;

    public string $Type;
    public bool $ReadOnly;
    public string $MASK;

}
?>