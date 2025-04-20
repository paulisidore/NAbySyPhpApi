<?php
    namespace NAbySy\AutoLoad ;
    
    interface IAutoLoad{
        public function __construct(\xNAbySyGS $NabySyGS,$Categorie,$RepertoirParent);

        /**
         * Charge un Module NAbySy en Mémoire
         */
        public function Load($ClassName) : bool;

        /**
         * Enregistre le chargeur dans le moteur PHP
         */
        public function Register() ;
    }
?>