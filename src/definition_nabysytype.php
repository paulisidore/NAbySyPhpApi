<?php
/**
 * @file
 * Contains Definition of type of NAbySyGS Class/Module Type
 */

// This file is part of NAbySyGS package/*
    /** Définit les objects de type xORM de NAbySyGS */
    define('N_TYPE_ORM','xORMHelper');

    /** Définit les Objets de type Module de Paiement pour NAbySyGS */
    define('N_TYPE_MODULE_PAIE','IModulePaieManager');

    /** Définit les Objets de typeOpérateurs de messagerie SMS pour NAbySyGS */
    define('N_TYPE_SMS_OPERATOR','ISmsOperatorHelper');

    /** Définit les Objets de type Observateur pour les évènements liées aux bases de données gérées dans NAbySyGS */
    define('N_TYPE_EVENT_OBSERVER','\NAbySy\OBSERVGEN\xObservGen');
    
    
?>