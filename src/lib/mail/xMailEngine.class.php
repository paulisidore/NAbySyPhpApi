<?php
    namespace NAbySy\Lib\Mail ;

use Exception;
use xNAbySyGS;

    class xMailEngine extends \NAbySy\ORM\xORMHelper implements IMailOperatorHelper {
        /** Nom de l'Opérateur Mobile SMS */
        public const OPERATOR_NAME = 'NAbySY EMAIL Engine';

        /** Adresse e-mail de l'expéditeur */
        public $SENDER_MAIL ='paulvb@groupe-pam.net' ;

        public function __construct(xNAbySyGS $NAbySy,int $Id=null,$CreateChampAuto=false,$NomTable='mailrpt',$SenderAdress='nabysy@groupe-pam.net'){
            parent::__construct($NAbySy,$Id,$CreateChampAuto,$NomTable);            
            $this->SENDER_MAIL=$SenderAdress ;
        }

        public function EnvoieMail(array $AdresseDest, string $Sujet, string $Message): array
        {
            $ret=false ;
            $ListeReponse=[];
            foreach($AdresseDest as $Dest){
                $MyRS=new \NAbySy\ORM\xORMHelper($this->Main,0,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->Table);
                $MyRS->Expediteur=$this->SENDER_MAIL;
                $MyRS->Objet=$Sujet;
                $MyRS->Destinataire=$Dest;
                $MyRS->TextMessage=$Message;
                $MyRS->Etat="EN COUR";
                $MyRS->Enregistrer();
                $ret=false;
                try{
                    if (!$this->Main::TEST_MODE){
                        $ret=mail($Dest,$Sujet,$Message) ;
                    }else{
                        $ret=true ;
                    }
                    
                }catch(Exception $ex){
                    var_dump($ex) ;
                }                
                $Rep["DESTINATAIRE"]=$Dest ;
                $Rep["REPONSE"]=$ret ;
                $ListeReponse[]=$Rep ;
                if ($ret){
                    $MyRS->Etat="ENVOYE";
                }else{
                    $MyRS->Etat="NON ENVOYE";
                }
                $MyRS->Enregistrer();
                usleep(20000);
            }            
            return $ListeReponse ;
        }
    }
?>