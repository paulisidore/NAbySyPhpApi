<?php
    namespace NAbySy\Lib\BonAchat ;

use NAbySy\GS\Panier\xCart;
use NAbySy\xNAbySyGS;

    include_once 'xBonAchatManager.class.php';
    include_once 'xHistoriqueBonAchat.class.php' ;
    include_once 'xCarteBonAchatExclusive.class.php';

    interface IBonAchatManager {
        public function __construct(xNAbySyGS $NAbySy);

        /** Indique si Oui ou Non le module est pret à etre utilisé */
        public function IsReady():bool ;

        /** Retoutrne le nom du module */
        public function Nom():string ;

        /**Retourne une description du module */
        public function Description():string;

        /** Retourne le nom d'evoquation du module pour traiter les Bon de reduction ou Bon d'Achat */
        public function HandleModuleName():string;

        /**
         * Autorise ou non le Bon d'Achat
         * @param array $BonAchat : Le tableau contenant les identifiants de la carte de Bon d'Achat et le Montant à déduire
         * @param xCart $Panier : Infos du Panier en Cour de Validation
         * @return bool
         */
        public function AutoriseTransaction(array $BonAchat, xCart $Panier):bool ;

        /**
         * Permet de mettre les soldes et l'historique des Bons d'Achat Ajour aprés validation de la facture
         * @param int $IdFacture : Numero de la facture a modifier
         * @param xCart $Panier : Le Panier a mettre à jour.
         * @param array $BonAchat : Le tableau contenant les informations du Bon d'Achat
         */
        public function UpDateFacture(int $IdFacture,xCart $Panier,array $BonAchat):bool;


        /**
         * Retourne les infos du bon d'achat ayant permit la validation d'une facture
         * @return array
         */
        public function GetDetailFacture(int $IdFacture):array;
        /**
         * Retourne le Solde utilisé dans la facture *
         * @param int $IdFacture : Le Numero de la Facture pour retourner le bon dans la carte
         */
        public function RollBackFacture(int $IdFacture):bool;
        
        /** Retourne la dernière erreur rencontrée */
        public function LastError():string ;    
    }


?>