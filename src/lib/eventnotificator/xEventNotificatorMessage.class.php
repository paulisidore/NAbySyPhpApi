<?php 
    namespace NAbySy\Lib\Evenement ;
    
use xNAbySyGS;

    /**
     * Message envoyé aux gestion des évènements de notification aux usagers.
     */
    class xEventNotificatorMessage {
        public static xNAbySyGS $Main ;

        public \NAbySy\ORM\IORM $Message ;
        public xEventNotificator $Notificator ;      

        public int $Id =0;
        /**Liste des employés destinataires. Si aucun destinaiare le message est délivré à tout le monde */
        public $ListeDestinataire=[];
        public $UI_ACTION=null;
        public $DateEnvoie=null;
        public $HeureEnvoie=null;
        public $MODULEDEST='TOUT';

        public const URGENCE_NORMAL=0;
        public const URGENCE_MOYEN=1;
        public const URGENCE_ELEVE=2;
        public const URGENCE_TRESELEVE=3;
        public const URGENCE_CRITIQUE=4;

        public const DEST_MODULE_TOUT='TOUT';
        public const DEST_MODULE_RH='RH';
        public const DEST_MODULE_RS='RS';

        public function __construct(xEventNotificator $Notificator,int $IdSource=0, string $Source=null,$Information=null, int $Urgence=0 , 
            string $MODULE_DEST_GROUPE='TOUT', array $EmployeDestinataire=[], $UI_ACTION=null){

            self::$Main=$Notificator::$Main ;
            $this->Message=new \NAbySy\ORM\xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,'notificator_message');

            $this->MODULEDEST=$MODULE_DEST_GROUPE;
            if (isset($UI_ACTION)){
                $this->UI_ACTION=$UI_ACTION;
            }
            $this->Message->DateCreation=date('Y-m-d') ;
            $this->Message->HeureCreation=date('H:i:s');
            $this->Message->DateEnvoie=date('Y-m-d') ;
            $this->Message->HeureEnvoie=date('H:i:s');
            $this->Message->MODULE_DEST=$this->MODULEDEST;

            if (isset($Source)){
                $this->Message->Source=$Source;
                $this->Message->IdSource=$IdSource;
                if (isset($Information)){                    
                    $TxInfo=$Information;
                    if (is_object($Information)){
                        $TxInfo=json_encode($Information,JSON_FORCE_OBJECT);
                    }elseif(is_array($Information)){
                        $TxInfo=json_encode($Information);
                    }
                    $this->Message->Information=$TxInfo;
                    $this->Urgence=(int)$Urgence;
                    if (isset($EmployeDestinataire)){
                        if (count($EmployeDestinataire)){
                            $this->ListeDestinataire=$EmployeDestinataire;
                            if (get_class($EmployeDestinataire[0])=="NAbySy\RH\Personnel\xEmplpoye"){
                                $this->Message->ListeDestinataire=[];
                                foreach($EmployeDestinataire as $Emp){
                                    $this->Message->ListeDestinataire[]=$Emp->Id;
                                }
                            }
                            $this->Message->ListeDestinataire = json_encode($this->Message->ListeDestinataire) ;
                        }
                    }
                    if (isset($this->UI_ACTION)){
                        $this->Message->UI_ACTION=$this->UI_ACTION;
                    }

                    $this->Message->IsDistribue=0 ;
                    if ($this->Message->Enregistrer()){
                        $this->Id=$this->Message->Id;
                    }
                }
            }

        }

    }
?>