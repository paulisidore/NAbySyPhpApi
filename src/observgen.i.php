<?php
    namespace NAbySy\OBSERVGEN ;

use NAbySy\xNAbySyGS;

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
        public function __construct(xNAbySyGS $NabySyGS,$ObserveurName=null,$ListeObservable=[]);

        /**
         * Cette méthode permet de différencier les observateurs et les autres class
         */
        public function __invoke($arg=null) ;

        /**
         * Cette méthode est appelée à chaque évènement correspondant à la liste des observables du module
         * @param array $EventArg contiendra la listes des paramètres passée par le moteur des évènements.
         * @return void
         */
        public function RaiseEvent($ClassName,$EventType,&$EventArg);

        /**
         * Attibut ou retourne l'état actuel de l'objet observateur.
         * @param bool $NewState Si founit, l'etat sera changé.
         * @return bool : Retourne Vrai si l'observation est actif et prêt pour les prochains évènements et Faux s'il sera ignoré.
         */
        public function State(bool $NewState=null):bool;

    }


?>