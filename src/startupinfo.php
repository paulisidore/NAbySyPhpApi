<?php
    namespace NAbySy ;

    class xStartUpInfo{
        /**
         * Si Vrai NAbySyGS va être executé en mode debuggage
         * Par défaut le debbugage est activé
         * @var bool
         */
        public bool $DebugMode = true ;

        public int $DisplayErrors = 1 ;
        public int $DisplayStartUpErrors = 1 ;
        public int $ErrorReporting = E_ALL ;
        /**
         * Contient les informations relatives au client PAM (Paul et Aïcha Machinerie SARL)
         */
        public ?ModuleMCP $InfoClientMCP ;

        /**
         * Contient les paraètres de connexion à la base de donnée du client PAM
         * @var xConnexionInfo
         */
        public xConnexionInfo $Connexion ;

        public function __construct(){
            $this->InfoClientMCP = null ;
            $this->Connexion = new xConnexionInfo() ;
        }
    }

    class xConnexionInfo{
        public string $Serveur = '127.0.0.1' ;
        public int $Port = 3306 ;
		public string $DBUser  ;
		public string $DBPwd ;
		public string $DB = 'nabysygs' ;
		public string $MasterDB = 'nabysygs' ;
    }
?>