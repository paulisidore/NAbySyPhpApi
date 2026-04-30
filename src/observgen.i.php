<?php
    namespace NAbySy\OBSERVGEN ;

use NAbySy\ORM\IORM;
use NAbySy\xNAbySyGS;
use xNotification;

/**
 * Class Observateur
 * Cette class permet d'appeller des modules selon des évènements.
 * SIEGE_EDIT : Se déclanche lors de la modification des informations du Siège
 * SERVICE_ADD : Se déclanche lors de la modification d'un service
 * DIRECTION_ADD : Se déclanche lors d'un ajout d'une nouvelle direction
 * DIRECTION_EDIT : Se déclanche lors de la modification d'une direction
 * SERVICE_ADD : Se déclanche lors de la modification d'un service
 * SERVICE_EDIT : Se déclanche lors de la modification d'un Service
 * MVT_AFFECTATION :
 */
    interface IOBSERVGEN {

        public const SIEGE_EDIT='SIEGE_EDIT' ;

        public const DIRECTION_ADD='DIRECTION_ADD' ;
        public const DIRECTION_EDIT='DIRECTION_EDIT' ;
        
        public const SERVICE_ADD='SERVICE_ADD' ;
        public const SERVICE_EDIT='SERVICE_EDIT' ;

        public const MVT_AFFECTATION='MVT_AFFECTATION' ;

        /**
         * Constructeur de la classe Observateur
         * @param xNAbySyGS $NAbySyGS Objet centrale NAbySyGS.
         * @param string $ObserveurName: Nom attribué à l'observateur
         * @param array $ListeObservable Liste des points d'interrêt à observer.
         * DIRECTION_ADD ,
         * DIRECTION_EDIT ,
         * SERVICE_ADD ,
         * MVT_AFFECTATION 
         */
        public function __construct(xNAbySyGS $NabySyGS,string $ObserveurName=null,array $ListeObservable=[]);

        /**
         * Cette méthode permet de différencier les observateurs et les autres class
         */
        public function __invoke($arg=null) ;

        /**
         * Cette méthode est appelée à chaque évènement correspondant à la liste des observables du module
         * @param array $EventArg contiendra la listes des paramètres passée par le moteur des évènements.
         * @return void
         */
        public function RaiseEvent(string $ClassName,string $EventType, &$EventArg);

        /**
         * Déclenche un évènement correspondant à l'objet xEventArg
         * @param xEventArg $EventArg 
         * @return xEventReponse : Contiendra la réponse de l'observateur suite au traitement de l'évènement. Si $StopPropagation est VRAI alors les Observateurs suivants ne seront plus executés 
         */
        public function Raise(xEventArg $EventArg):xEventReponse;

        /**
         * Attibut ou retourne l'état actuel de l'objet observateur.
         * @param bool $NewState Si founit, l'etat sera changé.
         * @return bool : Retourne Vrai si l'observation est actif et prêt pour les prochains évènements et Faux s'il sera ignoré.
         */
        public function State(bool $NewState=null):bool;

    }

    /**
     * Objet passé en Argument pour la configuration des évènements
     * @package NAbySy\OBSERVGEN
     */
    class xEventArg {
        public string $ClassName ;
        public string $EventType ;
        public ?IORM $ORMObject ;
        public ?object $OtherObject ;
        public ?array $ListeArgs ;

        public function __construct(string $ClassName, string $EventType,?IORM $ORMObject, ?object $OtherObject=null, ?array $ListeArgs=null){
            $this->ClassName=$ClassName ;
            $this->EventType=$EventType ;
            $this->ORMObject=$ORMObject ;
            $this->OtherObject=$OtherObject ;
            $this->ListeArgs=$ListeArgs ;
        }
    }

    /**
     * Reponse retourner suite au traitement effectué par un Observateur.
     * Si $StopPropagation est VRAI alors les Observateurs suivants ne seront plus executés
     * @package NAbySy\OBSERVGEN
     */
    class xEventReponse {
        public bool $StopPropagation=false ;
        public ?string $RaisonStopPropagation=null ;
        public ?object $ObjetSource=null ;

        public function __construct(bool $StopPropagation, ?string $RaisonStopPropagation=null, ?object $ObjetSource=null){
            $this->StopPropagation=$StopPropagation ;
            $this->RaisonStopPropagation=$RaisonStopPropagation ;
            $this->ObjetSource=$ObjetSource ;
        }
    }


?>