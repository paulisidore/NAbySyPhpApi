<?php
    namespace NAbySy\Lib\Sms ;

use xNAbySyGS;
use xORM;
    include_once 'xMessageSMS.class.php';
    include_once 'xSMSEngine.class.php';
    //include_once 'xObservSMS.class.php';
    include_once 'xSMSOrange.class.php';
    
    //include_once 'SMSOperator.i.php';

    /**
     * Module permettant l'envoie et la réception d'SMS
     * Auteur: Paul et Aïcha Machinerie SARL
     * Support: Paul Isidore A. NIAMIE ; paul_isidore@hotmail.com
     */
    interface ISmsOperatorHelper {
        /** Nom de l'Opérateur Mobile SMS */
        /**
         * OBLIGATOIRE
         * public const OPERATOR_NAME = 'MON OPERATEUR MOBILE';
         */
        

        /** Le end-point ou seront reçus et traité les accusés de reception */
        /* public const DELIVERY_REPORT_ENDPOINT ='https://{{dev_host}}:443/{{OPERATOR_NAME}}/smsdr.php' ; */

        public function __construct(xNAbySyGS $NAbySy);

        /** Méthode permettant l'ajout du message sms dans la file d'attentepour être traité */
        public function EnvoieSms($DestPhoneNumber, string $Message):bool;

        /** Méthode qui sera appelé pour l'accusée de reception par le Moteur d'envoie SMS de NAbySy.
         * @param string $api_reponse Le contenue de l'accusée de reception retourné par le fournisseur
         * @return bool Vrai si la function s'est bien exécutée ou Faux le cas échéant.
         * ATTENTION: Retourner Vrai même s'il n'y a pas d'accusé de reception.
        */
        public function CallBack(string $api_reponse):bool ;

        /** Fonction de traitement de la reponse du SMS envoyé. Cette méthode est appelée par le Moteur d'envoie SMS de NAbySy.
         * @param string $send_reponse le corps du message retourné par le fournisseur
         * @return bool Retourne true si l'envoie s'est passé correctement.
         */
        public function TraiterReponse(xMessageSMS $Message, string $send_reponse,string $erreur=null):bool ;

        /**
         * Retourne éventuellement la liste des paramètres POST ou GET à ajouter à la requete vers l'API du fournisseur
         * selon l'état du Message à traiter en cour.
         * @param xMessageSMS $Message : Le message à traiter.
         * @return array 
         */
        public function GetQueryParameters(xMessageSMS $Message):array;

        /**
         * Permet de renouveller le Token en cas d'expiration
         */
        public function TokenRefresh();

        /**
         * Retourne la balance SMS disponible chez l'opérateur
         */
        public function GetSMSBalance();


    }

    

    
    


?>