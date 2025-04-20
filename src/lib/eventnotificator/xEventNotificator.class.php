<?php 
    namespace NAbySy\Lib\Evenement ;
    include_once 'xEventNotificatorMessage.class.php';

use xNAbySyGS;

    /**
     * Moteur de gestion des notifications générales
     */
    class xEventNotificator implements IEventNotificatorHelper {
        public static xNAbySyGS $Main ;
        public static \NAbySy\ORM\IORM $Config ;
        public static \NAbySy\ORM\IORM $Suscriber ;
        public static \NAbySy\ORM\IORM $MessageDelivre ;
      
        
        public $Grp ='TOUT';
        public $Src ='';
        public $Nom ="INFORMATIONS GENERALES";

        /**Liste des Socket des clients connectés à la file de notification */
        public $LISTE_SUSCRIBER_SOCKET=[];

        public function __construct(xNAbySyGS $NAbySy,string $Nom='INFORMATIONS GENERALES', string $Groupe='TOUT'){
            self::$Main=$NAbySy ;
            self::$Config=new \NAbySy\ORM\xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,'notificator_config');
            self::$Suscriber=new \NAbySy\ORM\xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,'notificator_suscriber');
            self::$MessageDelivre=new \NAbySy\ORM\xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,'notificator_messagedelivre');

            $this->Nom=$Nom;
            $this->Grp=$Groupe;
            //$this->Src=$Source;
            
            $LstConf=self::$Config->ChargeListe("Id>0","Id","Id");
            if ($LstConf->num_rows==0){
                self::$Config->NbJourANotifier=30 ;   //Permet de notifier les 30 derniers jours des evenements sauvegardés dans la base de donnée
                self::$Config->NiveauAccesMinimum ;  //Le niveau d'accès minimum a qui les notifications seront déservit
                self::$Config->Enregistrer();
            }
        }

        public function NouvelleNotification($Source,int $IdSource=0,$Infos=null,int $NiveauUrgence=0,
            string $MODULE_DEST_GROUPE='TOUT', array $ListeEmploye=[], string $ACTION_UI=null){
            $Msg=new xEventNotificatorMessage($this,$IdSource,$Source,$Infos,$NiveauUrgence,$MODULE_DEST_GROUPE,$ListeEmploye,$ACTION_UI);
            //Un observeur devra se charger d'envoyer le message aux personnes conercée lorsqu'elle seront connectées.

        }

    }
?>