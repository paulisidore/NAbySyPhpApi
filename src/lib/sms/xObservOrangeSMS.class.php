<?php
    namespace NAbySy\Lib\Sms ;
    use NAbySy\xNAbySyGS;


/**
 * Module de Traitement de la fil d'Attente des SMS pour le Moteur SMS de NAbySy
 * Auteur: Paul & Aicha Machinerie
 * Support: paul_isidore@hotmail.com}
 */
class xObservOrangeSMS extends \NAbySy\OBSERVGEN\xObservGen  {
    protected $LObs =[] ;
    /** Se déclanche après ajout d'un sms dans la file d'attente */
    public const xMessageSMS_ADD='xSMSOrange_ADD' ;

    /** Se déclanche à chaque modification de l'Etat du traitement du SMS */
    public const xMessageSMS_EDIT='xSMSOrange_EDIT' ;

    /** Se déclanche à la suppression d'un sms dans la base de donnée */
    public const xMessageSMS_DEL='xSMSOrange_DEL' ;

    protected static ?xSMSOrange $OrangeSmsEngine =null;

    /**
     * Observateur des SMS qui seronts traités par le service API d'Orange.
     * @param string $ObserveurName Nom de l'Observateur (Obligatoir)
     * @param array $ListeObservable: Liste d'observable additionnelle
     */
    public function __construct(xNAbySyGS $NAbySy,$ObserveurName="Orange SMS Observer",$ListeObservable=[],xSMSOrange &$OrangeSmsEngine=null)
    {
        self::$OrangeSmsEngine=$OrangeSmsEngine ;

        $Lst[]=self::xMessageSMS_ADD ;
        $Lst[]=self::xMessageSMS_EDIT ;
        $Lst[]=self::xMessageSMS_DEL ;
        if ($ListeObservable){
            if (count($ListeObservable)){
                foreach($ListeObservable as $NObs){
                    if (strtolower($NObs) !==self::xMessageSMS_ADD && 
                            strtolower($NObs) !==self::xMessageSMS_EDIT &&
                                strtolower($NObs) !==self::xMessageSMS_DEL ){
                        $Lst[]=$NObs ;
                    }
                }
            }
        }        
        $this->LObs=$Lst ;
        parent::__construct($NAbySy,$ObserveurName,$Lst) ;

    }

    public function RaiseEvent($ClassName,$EventType,&$EventArg){
        /* L'Action a executer */
        //var_dump($EventType);
        //var_dump($EventArg);
        $ListeMsg=[];
        $TxErr=null ;

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
            $IdEnvoie=(int)$EventArg ;
            $Mes=new xMessageSMS($this->Main);
            $Mes->Load($IdEnvoie,self::$OrangeSmsEngine);            
            
            if ($Mes->IdEnvoie){
                $Mes->MyRS->Etat=$Mes::SMS_EN_ATTENTE ;
                //var_dump($Mes);
                $Mes->Enregistrer();
            }
            //parent::$Main::$SMSEngine::TraiterFileEnvoie();
                          
        }

        //var_dump($EventType);

        if ($EventType=self::xMessageSMS_EDIT){
            //Est déclanché après une modification
            $NewListe=[];
            $ListeEnvoyee=[];
            $IdEnvoie=(int)$EventArg ;
            $Message=new xMessageSMS($this->Main);
            $Message->Load($IdEnvoie,self::$OrangeSmsEngine);
            //var_dump($Message);
            //exit;

            if ($Message->IdEnvoie==$IdEnvoie){
                //var_dump($Message->MyRS->Etat);
                if ($Message->MyRS->Etat==xMessageSMS::SMS_EN_ATTENTE){
                    $Message->MyRS->Etat==xMessageSMS::SMS_ENCOUR_ENVOIE;
                    $reponse=$this->Main::$SMSEngine::EnvoieRequette($Message->URL,$Message->Paramtres,$Message->HttpHeader,CURLOPT_POST,$Message->Message);
                    if (!$Message->TraiterReponse($reponse,$TxErr)){
                        //Erreur d'envoie
                        $Message->DernierreErreur=$TxErr ;
                        $Message->MyRS->TextErreur=$reponse ;
                        $Message->MyRS->Etat=xMessageSMS::SMS_NON_ENVOYE;
                        $Message->MyRS->NbTentative +=1 ;                               
                        $NewListe[]=$Message ;
                    }else{
                        //Envoie reussit.
                        //$Msg=new xMessageSMS($this->Main);
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
                $ClassName=get_class(self::$OrangeSmsEngine) ;
                if ($pos = strrpos($ClassName, '\\')) {
                    $ClassName= substr($ClassName, $pos + 1);
                }
                $LstArg=[];
                $LstArg[]=xMessageSMS::SMS_ENVOYE;
                $LstArg[]=$ListeEnvoyee;
                $this->Main::RaiseEvent($ClassName,$LstArg);
            }

            if (count($this->Main::$SMSEngine::$ListeMessageEnvoie)){
                $ClassName=get_class(self::$OrangeSmsEngine) ;
                if ($pos = strrpos($ClassName, '\\')) {
                    $ClassName= substr($ClassName, $pos + 1);
                } 
                $LstArg=[];
                $LstArg[]=xMessageSMS::SMS_NON_ENVOYE;
                $LstArg[]=$this->Main::$SMSEngine::$ListeMessageEnvoie;
                $this->Main::RaiseEvent($ClassName,$LstArg);
            }


        }  
            
                          
    }
}

?>