<?php
    namespace NAbySy\Lib\Evenement ;

use NAbySy\xNAbySyGS;
use xORM;  

    include_once 'xEventNotificator.class.php' ;
    /**
     * Module de gestion des notifications en temps réelle des informations provenant de différent module NAbySy
     * Auteur: Paul et Aïcha Machinerie SARL
     * Support: Paul Isidore A. NIAMIE ; paul_isidore@hotmail.com
     */
    interface IEventNotificatorHelper {
        /** Notification destiné à tous les modules */
        public const GROUPE_TOUT='TOUT';

         /** Notification destiné aux modules Ressources Humaines */
        public const GROUPE_RH='RH';

         /** Notification destiné aux module Reporting-Service */
        public const GROUPE_RS='RS';

        public function __construct(xNAbySyGS $NAbySy);

        /** Crée une nouvelle notification pour être distribuer au réseau */
        public function NouvelleNotification($Source,int $IdSource=0,$Infos=null,int $NiveauUrgence=0,
        string $MODULE_DEST_GROUPE='TOUT', array $ListeEmploye=[], string $ACTION_UI=null);

    }



    

    
    


?>