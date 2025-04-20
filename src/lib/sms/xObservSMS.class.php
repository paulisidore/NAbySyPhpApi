<?php
    namespace NAbySy\Lib\Sms ;
    use NAbySy\xNAbySyGS;


/**
 * Module de Traitement de la fil d'Attente des SMS pour le Moteur SMS de NAbySy
 * Auteur: Paul & Aicha Machinerie
 * Support: paul_isidore@hotmail.com}
 */
class xObservSMS extends \NAbySy\OBSERVGEN\xObservGen  {
    protected $LObs =[] ;
    public const xMessageSMS_ADD='xMessageSMS_ADD' ;
    public const xMessageSMS_EDIT='xMessageSMS_EDIT' ;
    public const xMessageSMS_DEL='xMessageSMS_DEL' ;

    public function __construct(xNAbySyGS $NAbySy,$ObserveurName="SMS ENGINE OBSERVER",$ListeObservable=[])
    {
        $Lst[]=self::xMessageSMS_ADD ;
        $Lst[]=self::xMessageSMS_EDIT ;
        $Lst[]=self::xMessageSMS_DEL ;
        $this->LObs=$Lst ;
        parent::__construct($NAbySy,$ObserveurName,$Lst) ;

    }

    public function RaiseEvent($ClassName,$EventType,&$EventArg){
        /* L'Action a executer */
        $ListeMsg=[];
        if (is_array($EventArg)){
            foreach ($EventArg as $Msg){
                if ($Msg instanceof \NAbySy\Lib\Sms\xMessageSMS){
                    $ListeMsg[]=$Msg ;
                }
            }                
        }

        if ($EventType=self::xMessageSMS_ADD){
            //Est déclanché après l'ajour d'un nouveau message en préparation pour l'envoie
            //On ajoute le SMS Dans la file d'Attente
            $Mes=new xMessageSMS($this->Main);
            foreach ($ListeMsg as $Msg){
                $TxErr=null ;
                $Msg->MyRS->Etat=$Msg::SMS_EN_ATTENTE ;
                $Msg->Enregistrer();
            }
            //parent::$Main::$SMSEngine::TraiterFileEnvoie();
                          
        }

        if ($EventType=self::xMessageSMS_EDIT){
            //Est déclanché après une modification
            foreach ($ListeMsg as $Message){
                    $Message->Refresh() ;
                    $Msg=new \NAbySy\Lib\Sms\xMessageSMS($this->Main) ;
                    if ($Message->MyRS->Etat==xMessageSMS::SMS_EN_ATTENTE){
                        $Message->MyRS->Etat==xMessageSMS::SMS_ENCOUR_ENVOIE;
                        $reponse=$this->Main::$SMSEngine::EnvoieRequette($Message->URL,$Message->Paramtres,$Message->HttpHeader,CURLOPT_POST,$Message->TextMessage);
                        if (!$Message->TraiterReponse($reponse,$TxErr)){
                            //Erreur d'envoie
                            $Message->DernierreErreur=$TxErr ;
                            $Message->MyRS->TextErreur=$reponse ;
                            $Message->MyRS->Etat=xMessageSMS::SMS_NON_ENVOYE;
                            $Message->MyRS->NbTentative +=1 ;                               
                            $NewListe[]=$Message ;
                        }else{
                            //Envoie reussit.
                            $Msg=new xMessageSMS($this->Main);
                            $Message->MyRS->Etat=xMessageSMS::SMS_ENVOYE ;
                            $Message->MyRS->DateEnvoie=date('Y-m-d');
                            $Message->MyRS->HeureEnvoie=date('H:i:s');
                            $Message->MyRS->TextReponse=$reponse ;
                            $ListeEnvoyee[]=$Message ;                    
                        }
                        $Message->MyRS->Enregistrer() ;

                        //On laisse un delais entre SMS pour éviter dénvoyer trops de sms groupé à un seul operateur
                        // Stoppe pour 0.3 secondes (Ca fera 0.3x5 = 1.5 secondes ce qui est est au dessus des 5 sms/sec de Orange)
                        usleep(300000);
        
                    }
            }

            $this->Main::$SMSEngine::$ListeMessageEnvoie=$NewListe ;

            if (count($ListeEnvoyee)){
                $ClassName="SMS_ENGINE" ;
                if ($pos = strrpos($ClassName, '\\')) {
                    $ClassName= substr($ClassName, $pos + 1);
                }                       
                self::$Main::RaiseEvent($ClassName,xMessageSMS::SMS_ENVOYE,$ListeEnvoyee);
            }

            if (count($this->Main::$SMSEngine::$ListeMessageEnvoie)){
                $ClassName="SMS_ENGINE" ;
                if ($pos = strrpos($ClassName, '\\')) {
                    $ClassName= substr($ClassName, $pos + 1);
                }                       
                self::$Main::RaiseEvent($ClassName,xMessageSMS::SMS_NON_ENVOYE,$this->Main::$SMSEngine::$ListeMessageEnvoie);
            }


        }  
            
                          
    }
}

?>