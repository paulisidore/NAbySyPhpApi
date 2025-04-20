<?php
    namespace NAbySy\Lib\Sms ;
    use xNAbySyGS;

    /**
     * Moteur de gestion des Envoies d'SMS.
     * Cette class déclenche des évenements observable avec le nom de class SMS_ENGINE
     * 
     */
    class xSMSEngine{
        public static xNAbySyGS $Main ;
        public static $ListeMessageEnvoie =[];
        public static \NAbySy\ORM\IORM $SmsRS ;
        public static int $NB_TENTATIVE=3;

        public function __construct(xNAbySyGS $NAbySy){
            self::$Main=$NAbySy ;
            self::$SmsRS=new \NAbySy\ORM\xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,'smsenginerpt');
        }

        /**
         * Cette Méthode ajoute un SMS à la file d'envoie
         * @param xMessageSMS $SmsForSend Le message
         * @param string $Erreur Erreur éventuelle.
         * @return bool True quand le message est parfaitement ajouté dans la file.
         */
        public function SendSMS(xMessageSMS &$SmsForSend,&$Erreur=null):bool{
            if ($SmsForSend->URL==''){
                $Erreur="Impossible de traiter la demande sans URL valide.";
                return false ;
            }
            if (isset($SmsForSend)){
                if ($SmsForSend->Expediteur==''){
                    $Erreur="Impossible de traiter la demande sans expéditeur valide.";
                    return false ;
                }                
            }
            if ($SmsForSend->Destinataire==''){
                $Erreur="Impossible de traiter la demande sans Destinataire valide.";
                return false ;
            }
            if ($SmsForSend->Message==''){
                $Erreur="Impossible de traiter la demande sans Message valide.";
                return false ;
            }

            $PrecNb=count(self::$ListeMessageEnvoie);
            self::$ListeMessageEnvoie[]=$SmsForSend ;
            $Nb=count(self::$ListeMessageEnvoie);
            if ($PrecNb<$Nb){
                $SmsForSend->MyRS->DateEnreg=date('Y-m-d');
                $SmsForSend->MyRS->HeureEnreg=date('H:i:s');
                $SmsForSend->MyRS->Etat=$SmsForSend::SMS_EN_ATTENTE ;
                $SmsForSend->MyRS->Expediteur=$SmsForSend->Expediteur ;
                $SmsForSend->MyRS->Destinataire=$SmsForSend->Destinataire ;
                $SmsForSend->MyRS->URL=$SmsForSend->URL ;
                $SmsForSend->MyRS->TextMessage=$SmsForSend->Message ;
                $SmsForSend->MyRS->TextReponse='';
                $SmsForSend->MyRS->NbTentative=0 ;
                $SmsForSend->MyRS->HttpHeader=$SmsForSend->HttpHeader ;
                /*var_dump($SmsForSend);
                exit ;
                */
                $SmsForSend->Enregistrer();
                return true ;
            }
            $Erreur='Erreur inconnue.';
            return false ;
        }

        public static function TraiterFileEnvoie(){
            if (!isset(self::$ListeMessageEnvoie)){return ;};

            //On retire les messages deja envoyés.
            $NewListe=[] ;
            $ListeNbEssaiDepassé=[];
            $Msg=new xMessageSMS(self::$Main);

            foreach (self::$ListeMessageEnvoie as $Message){
                $Message->Refresh();
                if (!$Message->DejaEnvoyee()){
                    if ($Message->MyRS->Etat==xMessageSMS::SMS_NON_ENVOYE){
                        if ($Message->NbTentative<self::$NB_TENTATIVE){
                            $Message->MyRS->Etat=$Message::SMS_EN_ATTENTE ;
                            $NewListe[]=$Message ;                        
                        }else{
                            //Le message a dépassé la tentative d'envoie
                            $Message->MyRS->Etat=xMessageSMS::ERR_ENVOIE ;
                            $ListeNbEssaiDepassé[]=$Message ;
                        }  
                        $Message->Enregistrer() ; 
                    }
                                    
                }
                
            }

            self::$ListeMessageEnvoie=$NewListe ;

            //Déclanchement des évènements d'envoie échoué après NB_TENTATIVE
            if (count($ListeNbEssaiDepassé)){
                $ClassName="SMS_ENGINE" ;
                if ($pos = strrpos($ClassName, '\\')) {
                    $ClassName= substr($ClassName, $pos + 1);
                }         
                self::$Main::RaiseEvent($ClassName,xMessageSMS::ERR_ENVOIE,$ListeNbEssaiDepassé);
            }

        }


         /**
         * Fonction permettant l'envoie de requette à un serveur web distant
         * @param string $url : le site internet ciblé
         * @param array $ListeParametre : liste des paramètres a envoiyer sous la forme de tableau de pair de donnée
         * @param array $Headers : Liste d'éventuel paramètre header
         * @param CURLOPT_POST|null Envoie les données via le paramètre POST au lieu de GET
         * exemple: array('name' => 'Robert', 'id' => '1')
         * 
         * @return string : reponse obtenue du serveur.
         */
        public static function EnvoieRequette($url, $ListeParametre=[],array $Headers=null,$Method=CURLOPT_POST,$BodyData=''):string{
            
            $ch = curl_init() ;
            if (isset($ListeParametre)){
                if (count($ListeParametre)){
                    $postdata = http_build_query($ListeParametre);
                    $url .= "?" . $postdata;
                }
            }
           
            curl_setopt($ch,CURLOPT_URL, $url);
            if ($Method==CURLOPT_POST){
                curl_setopt($ch,CURLOPT_POST, true);
                curl_setopt($ch,CURLOPT_POSTFIELDS, $BodyData);
            }else{
                curl_setopt($ch,CURLOPT_HTTPGET, true);
            }
            
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

            if (isset($Headers)){
                curl_setopt($ch, CURLOPT_HTTPHEADER,$Headers);
            }

            //Delais pour l'établissement de la connexion (20sec)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); 

            //Delais maximum du script CURL dans sa globalité (2mn)
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $NAbySyVersion='NAbySy/'.self::$Main->MODULE->Version ;
            curl_setopt($ch, CURLOPT_USERAGENT, $NAbySyVersion);

            curl_setopt($ch,CURLINFO_HEADER_OUT,true);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            //var_dump($result);
            $EnteteEnvoie=curl_getinfo($ch,CURLINFO_HEADER_OUT ) ;
            //var_dump($EnteteEnvoie);
            //var_dump($BodyData);
            if (curl_errno($ch)) {
                $result = curl_error($ch).'<>'.$result;
            }else{
                $info = curl_getinfo($ch);
                //var_dump($info);
            }
            //var_dump($result);
            return $result ;
        }

    }


?>