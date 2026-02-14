<?php
    namespace NAbySy\Lib\ModulePaie ;

use NAbySy\GS\Panier\xCart;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;
use NAbySy\xNAbySyGS;
use NAbySy\xNotification;

/**
 * Interface des Modules de Paiements pour NAbySy
 */
    interface IModulePaieManager {

        public function Api_Disponible():int;
        public function Api_Token():string;
        public function Api_EndPoint():string;

        /** API d'Authentification */
        public function Api_Auth():string;

        public function Api_Auth_User():string;
        public function Api_Auth_Pwd():string;
        public function Wait_Api_Response():int;
        public function Api_RefClient():string;        

        public function __construct(xNAbySyGS $NAbySy);

        /** Indique si Oui ou Non le module est pret à etre utilisé */
        public function IsReady():bool ;

        /** Retoutrne le nom du module */
        public function Nom():string ;

        /** Nom présenté sur l'interface de Vente */
        public function UIName():string ;

        /** l'Url du logo affiché sur l'interface utilisateur */
        public function LogoURL():string ;

        /**Retourne une description du module */
        public function Description():string;

        /** Retourne le nom d'evoquation du module pour traiter les modes de reglement */
        public function HandleModuleName():string;

        /** Retourne les informations qui permettrons une validation du paiement sur l'ecran de présentation du QrCode */
        public function GetCheckOut($Montant,array $InfosPosteSaisie):xNotification ; //Retourne l'objet contenant la demande de validation à présenter au client

        /** Retourne les informations sur l'etat d'une demande de paiement */
        public function GetEtatCheckOut(xCheckOutParam $CheckOutInfo):xNotification ;

        /**
         * Valide ou non de la vente par le odule de paiement
         * @param array $MethodePaie : Le tableau contenant les Informations de la validation du paiemnt avec le mode choisit
         * @param xCart $Panier : Infos du Panier en Cour de Validation
         * @return bool
         */
        public function ValideTransaction(array $MethodePaie, xCart $Panier):bool ;

        /**
         * Permet de mettre les soldes et l'historique des methodes de paiement A jour aprés validation de la facture
         * @param int $IdFacture : Numero de la facture a modifier
         * @param xCart $Panier : Le Panier a mettre à jour.
         * @param array $MethodePaie : Le tableau contenant les informations sur la méthode de paiement
         */
        public function UpDateFacture(int $IdFacture,xCart $Panier,array $MethodePaie):bool;

        /**
         * Permet la mise à jours les soldes et l'historiques de paiement des transactions financières.
         * @param int $IdTransaction 
         * @param array $MethodePaie : Le tableau contenant les informations sur la méthode de paiement employées.
         * @return bool 
         */
        public function UpDateTransaction(int $IdTransaction, array $MethodePaie):bool;
        /**
         * Retourne les infos de la méthode de paiement ayant permit la validation d'une facture
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

    include_once 'ModulePaieLoader.php';
    


?>