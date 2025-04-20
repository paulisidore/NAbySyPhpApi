<?php
    namespace NAbySy\Lib\Mail ;

use xNAbySyGS;
use xORM;  

    include_once 'xMailEngine.class.php' ;
    /**
     * Module permettant l'envoie de mail
     * Auteur: Paul et Aïcha Machinerie SARL
     * Support: Paul Isidore A. NIAMIE ; paul_isidore@hotmail.com
     */
    interface IMailOperatorHelper {
        /** Nom de l'Opérateur Mobile SMS */
        /* public const OPERATOR_NAME = 'NAbySY EMAIL Engine';  A Réactiver avec PHP8 */

        /** Adresse e-mail de l'expéditeur */
        public const SENDER_MAIL ='paulvb@groupe-pam.net' ;

        public function __construct(xNAbySyGS $NAbySy);

        /** Méthode permettant l'envoie de mail */
        public function EnvoieMail(array $AdresseDest, string $Sujet, string $Message): array;


    }

    

    
    


?>