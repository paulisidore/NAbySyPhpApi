<?php
    namespace NAbySy\Lib\Sms ;

use NAbySy\ORM\xChampDB;
use NAbySy\xNAbySyGS;

/**
     * Represente un Message à envoyer.
     * Cet message sera placer dans la file d'attente du moteur SMS de NAbySy
     */
    class xMessageSMS{
        public xNAbySyGS $Main ;
        public $URL='';
        public $Expediteur='';
        public $Destinataire='';
        public $Message='';
        public $Paramtres=[];
        public $HttpHeader=[];

        public ?ISmsOperatorHelper $OSmsDLR=null ;

        public $DernierreErreur=null;

        public $IdEnvoie = 0 ;
        public $NbTentative =0 ;
        public ?\NAbySy\ORM\xORMHelper $MyRS =null ;

        /** Création du SMS en Cour */
        public const SMS_NON_TRAITE='SMS_NON_TRAITE' ;
        public const SMS_ENCOUR_ENVOIE='SMS_ENCOUR_ENVOIE';
        public const SMS_NON_ENVOYE='SMS_NON_ENVOYE';
        public const SMS_ENVOYE='SMS_ENVOYE';
        public const SMS_EN_ATTENTE='SMS_EN_ATTENTE';
        public const ERR_ENVOIE='ERR_ENVOIE';

        public function __construct(xNAbySyGS $NAbySy, $url='',$Expediteur='',$Destinataire='',$Message='',array $ListeParametre=[], ISmsOperatorHelper $oSmsDLR=null)
        {
            $this->Main=$NAbySy ;
            $this->URL=$url;
            $this->Expediteur=$Expediteur ;
            $this->Destinataire=$Destinataire ;
            $this->Message=$Message ;
            $this->Paramtres=$ListeParametre ;
            $this->OSmsDLR=$oSmsDLR ;

            $this->MyRS=new \NAbySy\ORM\xORMHelper($this->Main,null,$NAbySy::GLOBAL_AUTO_CREATE_DBTABLE,'smsenginerpt') ;
            $this->MyRS->RaiseEventTaget=$oSmsDLR ;

        }

        /**
         * Permet de traiter la reponse du fournisseur SMS après l'envoie.
         * Cette méthode est appelée par le Moteur d'envoie SMS de NAbySy
         * @param string $Reponse la réponse retournée.
         * @param string $Erreur Retourne éventuellement un message d'erreur
         * @return bool
         */
        public function TraiterReponse($Reponse,string &$Erreur=null):bool{
            $IsOK=false;
            if (isset($this->OSmsDLR)){
                $IsOK= $this->OSmsDLR->TraiterReponse($this,$Reponse,$Erreur);
            }
            return $IsOK ;
        }

        /** Vérifie si le sms est déjà envoyé à l'expéditeur
         * @return bool True si envoyé et false le cas contraire.
         */
        public function DejaEnvoyee():bool{
            $this->Refresh();            
            if ($this->MyRS->Etat==self::SMS_ENVOYE){                    
                return true ;                    
            }
            return false ;
        }

        /**
         * Charge un message sms depuis la base de donnée
         * @param int $IdMessage Id du message
         * @param ISmsOperatorHelper $SMSEngine : Gestionnaire de l'API Fournisseur SMS
         */
        public function Load($IdMessage=null,ISmsOperatorHelper $SMSEngine=null):void{
            if (!isset($IdMessage)){
                $IdMessage=$this->IdEnvoie ;
            }
           $Rep=$this->MyRS->ChargeOne((int)$IdMessage);
           if ($Rep){
               if ($Rep->num_rows){
                   //IdMessage trouvé.
                   $this->IdEnvoie=$this->MyRS->Id;                   
                   $this->URL=$this->MyRS->URL;
                   $this->Expediteur=$this->MyRS->Expediteur;
                   $this->Destinataire=$this->MyRS->Destinataire;
                   $this->Message=$this->MyRS->TextMessage;
                   $this->NbTentative=$this->MyRS->NbTentative;
                   $this->HttpHeader=json_decode($this->MyRS->HttpHeader) ;
                   $this->OSmsDLR=$SMSEngine;
                   
                   if (!isset($this->OSmsDLR)){
                       var_dump(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2));
                       exit ;
                   }

                   $this->Paramtres=$this->OSmsDLR->GetQueryParameters($this);
                   
               }
           }
           return ;
        }

        /**
         * Actualise les informations du SMS depuis la base de donnée.
         */
        public function Refresh(){
            if ($this->IdEnvoie<1){
                return ;
            }
            $this->Load($this->IdEnvoie);
            return ;
        }

        /**
         * Cette methode permet de enclancher le processus de traitement du SMS par le moteur SMS de NAbySy
         */
        public function Enregistrer():bool{
            if($this->MyRS->count() == 1){
                $this->MyRS->ChangeTypeChamps('HttpHeader','LONGTEXT','');
            }
            $rep=$this->MyRS->Enregistrer();
            if ($rep){
                $this->IdEnvoie=$this->MyRS->Id ;
                return true ;
            }
           return false;
        }

        public function Etat(){
            if ($this->IdEnvoie==0){
                return self::SMS_NON_TRAITE ;
            }
            return $this->MyRS->Etat ;
        }

        public function __debugInfo() {
            $result = get_object_vars($this);
            unset($result['Main']);
            unset($result['MyRS']);
            return $result;
        }
    }

?>