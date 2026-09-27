<?php
    namespace NAbySy\Lib\Mail ;

    $frameworkAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    if (file_exists($frameworkAutoload)) {
        require_once $frameworkAutoload;
    }
use Exception;
use NAbySy\xNAbySyGS;
use PHPMailer\PHPMailer\PHPMailer;

    class xMailEngine extends \NAbySy\ORM\xORMHelper implements IMailOperatorHelper {
        /** Nom de l'Opérateur Mobile SMS */
        public const OPERATOR_NAME = 'NAbySY EMAIL Engine';

        public ?PHPMailer $MailEngine ;

        /** Adresse e-mail de l'expéditeur */
        public $SENDER_MAIL ='paulvb@groupe-pam.net' ;

        public function __construct(?xNAbySyGS $NabySy = null,?int $Id=null,?bool $CreateChampAuto=false, ?string $NomTable='mailrpt', ?string $DBName=null, 
            ?string $SenderAdress='nabysy@groupe-pam.net', 
            ?string $Password="", 
            ?string $SmtpServer="",
            ?int $SmtpPort = 465,
            ?string $SmtpSecureType = PHPMailer::ENCRYPTION_SMTPS ){

            parent::__construct($NabySy,$Id,$CreateChampAuto,$NomTable, $DBName);            
            $this->SENDER_MAIL=$SenderAdress ;

            $this->MailEngine = new PHPMailer(true);

            // Mode débogage pour voir tout ce qui se passe entre votre PC et OnetSolutions
            // 0 = off, 1 = messages client, 2 = client et serveur (idéal pour le dev)
            if(xNAbySyGS::$LogLevel>4){
                //$this->MailEngine->SMTPDebug = 2;
            }else{
                $this->MailEngine->SMTPDebug = 0;
            }

            $this->MailEngine->isSMTP();
            $this->MailEngine->Host       = $SmtpServer; 
            $this->MailEngine->SMTPAuth   = $SenderAdress !='' ? true : false ;
            $this->MailEngine->Username   = $SenderAdress; // Votre adresse pro
            $this->MailEngine->Password   = $Password;
            $this->MailEngine->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;       // Chiffrement SSL
            $this->MailEngine->Port       = $SmtpPort;
        }

        public function EnvoieMail(array $AdresseDest, string $Sujet, string $Message): array
        {
            $ret=false ;
            $ListeReponse=[];
            $this->MailEngine->setFrom($this->MailEngine->Username, $this->Main->MODULE->Nom);
            $this->MailEngine->isHTML(true);
            $this->MailEngine->Subject = $Sujet;
            $this->MailEngine->Body = $Message;

            foreach($AdresseDest as $Dest){
                $MyRS=new \NAbySy\ORM\xORMHelper($this->Main,0,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->Table);
                $MyRS->Expediteur=$this->MailEngine->Username;
                $MyRS->Objet=$Sujet;
                if (is_array($Dest)){
                    $MyRS->Destinataire = json_encode($Dest) ;
                }else{
                    $MyRS->Destinataire=$Dest;
                }
                
                $MyRS->TextMessage=$Message;
                $MyRS->Etat="EN COUR";
                $MyRS->Enregistrer();
                $ret=false;
                try{
                    if (!xNAbySyGS::TEST_MODE){
                        if (is_array($Dest)){
                            foreach ($Dest as $unDestinataire) {
                                $this->MailEngine->addAddress($unDestinataire);
                                if(xNAbySyGS::$LogLevel>3){
                                    xNAbySyGS::$Log->AddToLog("Envoie de Mail a plusieurs vers ".$unDestinataire);
                                }
                            }
                        }else{
                            $this->MailEngine->addAddress($Dest);
                            if(xNAbySyGS::$LogLevel>3){
                                xNAbySyGS::$Log->AddToLog("Envoie de Mail vers ".$Dest);
                            }
                        }
                        $ret = $this->MailEngine->send() ;
                        if($ret == false){
                            xNAbySyGS::$Log->AddToLog("Mail non envoyé. ".json_encode($ret));
                        }
                    }else{
                        $ret=true ;
                    }
                }catch(Exception $ex){
                    xNAbySyGS::$Log->AddToLog("ERR: Mail non envoyé. ".json_encode($ex));
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