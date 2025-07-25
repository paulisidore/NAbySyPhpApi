<?php
    namespace NAbySy\Lib\Sms ;

use DOMDocument;
use Httpful\Httpful;
use NAbySy\ORM\xORMHelper;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;
use UConverter;

include_once 'xObservOrangeSMS.class.php';

    /**
     * Module permettant l'envoie et la réception d'SMS
     * Auteur: Paul et Aïcha Machinerie SARL
     * Support: Paul Isidore A. NIAMIE ; paul_isidore@hotmail.com
     * VErsion PHP supporté >= 8.1
     */
    class xSMSOrange implements ISmsOperatorHelper {
        /** Nom de l'Opérateur Mobile SMS */
        public const OPERATOR_NAME = 'Orange SN';   

        /** Le numéro de téléphone expéditeur */
        public $ORIG_PHONE_NUMBER = '+221775618816';

        public static $SENDER_NAME ='YOUR_SENDER_NAME';

        /** Le end-point ou seront reçus et traité les accusés de reception */
        public const DELIVERY_REPORT_ENDPOINT ='https://{{dev_host}}:443/{{OPERATOR_NAME}}/smsdr.php' ;

        public static xNAbySyGS $Main ;

        public static $TOKEN_AUTH='' ;
        public $APP_TOKEN='' ; //"Basic WkdpYjk5ZzhJM2syZXMzVm1kbGc3VXRuOHdZdG5Velo6ZEdkSWJRUE5SUXJGcFE2Uw==" sur le site de l'opérateur

        public int $Pam_IdClient=1 ;

        public $Ready=false ;

        /** Indique si le Module est utilisable ou désactivé */
        public int $Active=1;

        public static \NAbySy\ORM\xORMHelper $MyRS ;
        protected static xObservOrangeSMS $Observateur ;

        public static bool $useSonatelProvider = true;
        public static string $SonatelPrivateKey = "__YOUR_SMS_PPROVIDER_TOKEN"; //__YOUR_SMS_PPROVIDER_TOKEN
        public static string $SonatelToken = "dc7d4e37f94ffcce0f8fca6fc1a64224" ;
        public static string $SonatelSmsEndPoint = "https://{{smsapi_host}}:443/{{OPERATOR_NAME}}";
        public static string $SonatelSMSLogin = "pauletaicha" ;

        /** Le numéro de téléphone expéditeur */
        public function __construct(xNAbySyGS $NAbySy){
            $this::$Main=$NAbySy ;
            $AppToken='' ;
            $OriginePhoneNumber='' ;

            $IdConfig=1; //A définir selon l'API PAM-SMS
            self::$MyRS=new xORMHelper(self::$Main,$IdConfig,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,"orangesn") ;
            if (self::$MyRS->Id){
                $AppToken=self::$MyRS->AppToken ;
                $OriginePhoneNumber=self::$MyRS->ExpediteurPhone ;
                self::$TOKEN_AUTH=self::$MyRS->TOKEN_AUTH ;
                self::$SENDER_NAME=self::$MyRS->SenderName ;
                $this->Pam_IdClient=self::$MyRS->IdClientPAM ;
                $this->Active=(int)self::$MyRS->Active ;
                if(!self::$MyRS->ChampsExisteInTable("useSonatelProvider")){
                    //self::$MyRS->Enregistrer();
                    self::$MyRS->SonatelSmsEndPoint = self::$SonatelSmsEndPoint ;
                    //var_dump(self::$MyRS->SonatelSmsEndPoint );
                    self::$MyRS->useSonatelProvider = 1; // self::$useSonatelProvider;
                    self::$MyRS->SonatelPrivateKey = "__YOUR_SMS_PPROVIDER_TOKEN" ;// self::$SonatelPrivateKey ;
                    self::$MyRS->SonatelToken = self::$SonatelToken ;
                    self::$MyRS->SonatelSmsLogin = self::$SonatelSMSLogin ;
                    self::$MyRS->Enregistrer();
                }
            }

            self::$useSonatelProvider=self::$MyRS->useSonatelProvider;
            self::$SonatelSmsEndPoint = self::$MyRS->SonatelSmsEndPoint ;
            self::$SonatelPrivateKey = self::$MyRS->SonatelPrivateKey ;
            self::$SonatelToken = self::$MyRS->SonatelToken ;
            self::$SonatelSMSLogin = self::$MyRS->SonatelSmsLogin ;

            $this->ORIG_PHONE_NUMBER=$OriginePhoneNumber ;
            $this->APP_TOKEN=$AppToken ;
            //On recherche le Token dÁuthentification si l'on souhaite
            //echo "Auth Token: ".self::$TOKEN_AUTH ;
            
            self::$MyRS->IdClientPam=$this->Pam_IdClient ;
            self::$MyRS->ExpediteurPhone=$this->ORIG_PHONE_NUMBER ;
            self::$MyRS->TOKEN_AUTH=self::$TOKEN_AUTH ;
            self::$MyRS->AppToken=$this->APP_TOKEN ;
            self::$MyRS->SenderName=self::$SENDER_NAME ;
            self::$MyRS->Active=$this->Active ;
            self::$MyRS->Enregistrer();

            if (self::$MyRS->AppToken !=='' &&  $this->ORIG_PHONE_NUMBER !=='' ){
                if (self::$TOKEN_AUTH==''){
                    self::$Main::$Log->Write("Orange SMS: Demande de renouvellement du Token...");
                    $this->GetToken("https://{{smsapi_host}}:443/{{OPERATOR_NAME}}oauth/v3/token",$this->APP_TOKEN);
                }else{
                    $this->Ready=true;
                }
            }else{
                $TxErreur="Module Orange SMS (Erreur de configuration): " ;
                $TxErreur.="<h5>l'API ".self::OPERATOR_NAME." n'est pas configuré correctement. " ;
                if ( $this->ORIG_PHONE_NUMBER==''){
                    $TxErreur .="N° de téléphone expéditeur absent !</br>";
                }
                if ( $this->APP_TOKEN==''){
                    $TxErreur .="APP TOKEN absent !</br>";
                }
                $TxErreur .='</h5>';
                self::$Main::$Log->Write($TxErreur);
            }

            if ($this->Ready){
                /**
                 * Ajout dans de la class Observable pour les SMS Orange                 * 
                 */
                self::$Observateur=new xObservOrangeSMS(self::$Main,"Orange SMS Observer",null,$this);
                //Deja ajouté dans les Observateurs par le constructeur
                //self::$Main->AddToObserveurListe($Observateur);
            }
            

        }

        public function EnvoieSms($DestPhoneNumber, string $Message, bool $OnlyForTest = false): bool {
            if ($this->Active==0){
                return false ;
            }
            if (!$this->Ready){
                //var_dump($this->Ready) ;
                $Err=new xErreur();
                $Err->OK=0;
                $Err->TxErreur='API Orange pas prêt.';
                $Err->Source=__CLASS__ ;
                $Err->Extra="Vérifiez la configuration";
                self::$Main::$Log->Write(json_encode($Err));
                return false;
            }

            if(self::$useSonatelProvider){
                return $this->EnvoieSMSBySonatel("NAbySy",self::$SENDER_NAME,$DestPhoneNumber,$Message, $OnlyForTest);
            }


            $Headers=array(
                "Cache-Control: no-cache",
                "Authorization: Bearer ".self::$TOKEN_AUTH,
                "content-type:application/json;charset=utf-8"
            ) ;

            $Parametres=array();
            
            $Msg1['message']=$Message;
            $TxSMS['address']='tel:'.$DestPhoneNumber ;
            $TxSMS['senderAddress']='tel:'.$this->ORIG_PHONE_NUMBER ;
            if (self::$SENDER_NAME !==''){
                $TxSMS['senderName']=self::$SENDER_NAME;
            }            

            $TxSMS['outboundSMSTextMessage']['message']=$Msg1['message'];

            $BodyData['outboundSMSMessageRequest']=$TxSMS;

            /* $BodyData['outboundSMSMessageRequest']=array(
                "address: tel:".$DestPhoneNumber,
                "senderAddress: tel:".$this->ORIG_PHONE_NUMBER,
                "outboundSMSTextMessage: ".json_encode($TxSMS) ); */
            
            $Data=json_encode($BodyData);
                
            $URL="https://{{smsapi_host}}:443/{{OPERATOR_NAME}}smsmessaging/v1/outbound/tel%3A%2B".$this->ORIG_PHONE_NUMBER."/requests";
            $URL="https://{{smsapi_host}}:443/{{OPERATOR_NAME}}smsmessaging/v1/outbound/tel".urlencode(':'.$this->ORIG_PHONE_NUMBER)."/requests";
            $Msg=new xMessageSMS($this::$Main,$URL,$this->ORIG_PHONE_NUMBER,$DestPhoneNumber,$Data,$Parametres,$this);
            $Msg->HttpHeader=json_encode($Headers) ;
            $Err=null ;            
            return $this::$Main::$SMSEngine->SendSMS($Msg,$Err) ;            
 
        }

        function GetParamSMSBySonatel (string $subject,string $signature,string $TelDest,string $MsgText){
            
            //self::$Main::$Log->AddToLog("[Liste des Encodeur de Charactère] => ". json_encode( UConverter::getAvailable()) );

            $Token = self::EncodeTextTo("UTF-8", self::$SonatelToken);
            $esubject = self::EncodeTextTo("UTF-8", $subject);
            $esignature = self::EncodeTextTo("UTF-8", $signature);
            $eTelDest = self::EncodeTextTo("UTF-8", $TelDest);
            $eMsgText = self::EncodeTextTo("UTF-8", $MsgText);

            $esignature = $signature;
            $eTelDest = $TelDest;
            $eMsgText = $MsgText;
            
            $timestamp=time();
            $etimestamp = self::EncodeTextTo("UTF-8", $timestamp);
            $msgToEncrypt=$Token . $esubject . $esignature . $eTelDest . $eMsgText . $etimestamp;

            $key=hash_hmac('sha1', $msgToEncrypt, self::$SonatelPrivateKey);

            $params = array(
            'token' => $Token,
            'subject' => $esubject,
            'signature' => $esignature,
            'recipient' => $eTelDest,
            'content' => $eMsgText,
            'timestamp' => $etimestamp,
            'key' => $key
            );
            return $params ;
        }

        function EnvoieSMSBySonatel (string $subject,string $signature,string $TelDest,string $MsgText, bool $OnlyForTest=false){
            if($TelDest !== ""){
                $TelDest = trim($TelDest);
                $pos = strpos($TelDest,'+');
                if($pos !== false){
                    if($pos==0){
                        $TelDest=str_replace('+','',$TelDest) ;
                    }
                }
            }

            $params = $this->GetParamSMSBySonatel($subject,$signature,$TelDest,$MsgText);

            // $timestamp=time();
            // $msgToEncrypt=self::$SonatelToken . $subject . $signature . $TelDest . $MsgText . $timestamp;
            // $key=hash_hmac('sha1', $msgToEncrypt, self::$SonatelPrivateKey);

            /* $params = array(
            'token' => self::$SonatelToken,
            'subject' => $subject,
            'signature' => $signature,
            'recipient' => $TelDest,
            'content' => $MsgText,
            'timestamp' => $timestamp,
            'key' => $key
            ); */

            if($OnlyForTest){
                self::$Main::$Log->AddToLog("PARAMTRE POUR LE TEST D'ENVOIE SMS: ");
                self::$Main::$Log->AddToLog("Params: ".json_encode($params));
                return true;
            }
            
            $uri = self::$SonatelSmsEndPoint ; // 'https://{{smsapi_host}}:443/{{OPERATOR_NAME}}';
            
            $Msg=new xMessageSMS($this::$Main,$uri,$this->ORIG_PHONE_NUMBER,$TelDest,$MsgText,$params,$this);
            if($subject !==''){
                $Msg->Sujet = trim($subject) ;
            }
            $Msg->UseHttpFull = true ;
                       
            $Err=null ;
            return $this::$Main::$SMSEngine->SendSMS($Msg,$Err) ;
        }

        /** Fonction de rappel pour le traitement des Accusés de réceptions */
        public function CallBack(string $api_reponse): bool {
            $IsOK=false ;

            return $IsOK ;
        }
        
        public function TraiterReponse(xMessageSMS $Message, string $send_reponse, ?string $erreur = null): bool {
            $IsOK=false ;
            if(self::$useSonatelProvider){
                if(trim($send_reponse)==""){
                    return false;
                }
                $doc = new DOMDocument();
                $Message->MyRS->TextReponse=$send_reponse ;
                $doc->loadHTML($send_reponse);
                //var_dump($doc->firstChild);
                $body = $doc->getElementsByTagName('body');
                if ( $body && 0<$body->length ) {
                    $body = $body->item(0);
                    //var_dump($body);
                    $listeNode = $body->getElementsByTagName('small');
                    $lstReponse=[];
                    if($listeNode){
                        foreach ($listeNode as $item) {
                            $valeur = $item->nodeValue ;
                            $val = explode(":",$valeur,2);
                            if(count($val)>1){
                                $lstReponse[trim($val[0])]=trim($val[1]);
                            }
                        }
                    }
                    $Message->MyRS->Etat=xMessageSMS::SMS_NON_ENVOYE ;
                    $IsOK=false ;
                    if(count($lstReponse)){
                        if(isset($lstReponse['STATUS_CODE'])){
                            if((int)$lstReponse['STATUS_CODE'] == 200){
                                $Message->MyRS->Etat=xMessageSMS::SMS_ENVOYE ;
                                $Message->MyRS->TextReponse = json_encode($lstReponse) ;
                                if ((int)$Message->MyRS->IdClientPAM==0){
                                    $Message->MyRS->IdClientPAM=$this->Pam_IdClient ;
                                }
                                $IsOK=true ;
                                //var_dump($Message->MyRS->GetUpDateSQLString());
                                $Message->Enregistrer() ;
                                //exit;
                                //var_dump($Message->MyRS->ToObject());
                            }else{
                                $MsgErr = json_encode($lstReponse);
                                $Message->MyRS->TextErreur=$MsgErr ;
                                $Message->Enregistrer() ;
                                $IsOK=false;
                            }
                        }
                    }
                    //var_dump($Message->MyRS->ToJSON());
                    return $IsOK ;
                }
                return false ;
            }
            
            $Err=json_decode($send_reponse);
            //var_dump($Err) ;
            if ((int)$Message->MyRS->IdClientPAM==0){
                $Message->MyRS->IdClientPAM=$this->Pam_IdClient ;
                $Message->Enregistrer();
            }            

            if (isset($Err)){
                if (!is_array($Err) && !is_object($Err)){
                    self::$Main::$Log->Write(self::OPERATOR_NAME." ".$this->ORIG_PHONE_NUMBER." Erreur :".$send_reponse.". ".$erreur);
                    return false ;
                }elseif (is_array($Err) && !is_object($Err)) {
                    self::$Main::$Log->Write(self::OPERATOR_NAME." ".$this->ORIG_PHONE_NUMBER." Erreur :".$send_reponse.". ".$erreur);
                    return false ;
                }
            }
            
            if (!is_object($Err)){
                self::$Main::$Log->Write(self::OPERATOR_NAME." ".$this->ORIG_PHONE_NUMBER." Erreur :".$send_reponse.". ".$erreur);
                return false ;
            }

            $ReponseAPI=get_class($Err);
            if (property_exists($Err,"requestError")){
                //var_dump($ReponseAPI);
                self::$Main::$Log->Write("Orange SMS Erreur: ".$ReponseAPI);
                $IsOK=false ;
            }

            self::$Main::$Log->Write("Orange SMS Reponse: ".$send_reponse);
            
            
            if (property_exists($Err,"code")){
                if ($Err->code==42 || $Err->code==41 ){
                    // Si le token à expiré, on obtient le nouveau Token puis on remet le message dans la file en changant son Etat et en enregistrant
                    //$this->TokenRefresh();
                    self::$TOKEN_AUTH='';
                    $this->Ready=false;
                    $Message->OSmsDLR->TokenRefresh();
                    $Message->MyRS->TOKEN_AUTH='';
                    $Message->MyRS->Etat=xMessageSMS::SMS_EN_ATTENTE ;
                    $Message->MyRS->Enregistrer() ;
                    $Msg=json_decode($Message->Message);
                    $Text=$Message->Message;
                    if (is_object($Msg)){
                        $Text=$Msg->outboundSMSMessageRequest->outboundSMSTextMessage->message ;
                    }elseif (is_array($Msg)){
                        $Text=$Msg['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] ;
                    }                    
                    return $this->EnvoieSms($Message->Destinataire,$Text);               
                }else{
                    //Autre erreur
                    $Message->MyRS->TextErreur=$send_reponse ;
                    $Message->Enregistrer() ;
                    self::$Main::$Log->Write("Orange SMS Erreur: ".$send_reponse);
                    //Recherche de la Balance
                    $Balance=$this->GetSMSBalance();
                    self::$Main::$Log->Write("Orange SMS Erreur: ".json_encode($Balance));
                    $IsOK=false ;
                }
            }

            //outboundSMSMessageRequest
            $Reponse=$Err;
            //var_dump($Reponse);
            if (property_exists($Reponse,"outboundSMSMessageRequest")){
                $Message->MyRS->Etat=xMessageSMS::SMS_ENVOYE ;
                $Message->MyRS->TextReponse=$send_reponse ;
                $Message->Enregistrer() ;
                $IsOK=true ;
            }
            return $IsOK ;
        }

        public function GetToken($AuthURL='',$AuthorizationToken=''){
            $Headers=array(
                "Cache-Control: no-cache",
                "Authorization: ".$AuthorizationToken,
                "content-type:application/x-www-form-urlencoded;charset=utf-8"
            ) ;
            $Data="grant_type=client_credentials" ;

            self::$Main::$Log->AddToLog("Envoie de la demande de Token vers... ".$AuthURL) ;
            self::$Main::$Log->AddToLog("Http Header: ".json_encode($Headers));
            $Rep=self::$Main::$SMSEngine::EnvoieRequette($AuthURL,[],$Headers,CURLOPT_POST,$Data);
            self::$Main::$Log->AddToLog("Reponse API: ".$Rep);
            $data=json_decode($Rep);
            if (isset($data)){
                self::$TOKEN_AUTH=$data->access_token;
                self::$MyRS->TOKEN_AUTH=self::$TOKEN_AUTH ;
                self::$MyRS->TOKEN_REFRESH=date('Y-m-d h:i:s');
                $this->Ready=true;
                self::$MyRS->Enregistrer();
                self::$Main::$Log->Write("Orange SMS: Nouveau Token reçu.");
                return self::$TOKEN_AUTH ;
            }

            //Gestion des Erreur
            $Err=$Rep ;
            self::$Main::$Log->Write("Orange SMS: Erreur du renouvellement Token. ".json_encode($Err));
            
        }

        public function TokenRefresh(){
            self::$Main::$Log->Write("Orange SMS Token Refresh.");
            return $this->GetToken("https://{{smsapi_host}}:443/{{OPERATOR_NAME}}oauth/v3/token",$this->APP_TOKEN);
        }

        public  function GetSMSBalance(){
            if (!$this->Ready){
                $Err=new xErreur ;
                $Err->OK=0;
                $Err->TxErreur="Module pas prêt !";
                $Err->Source=__CLASS__ ;
                $Ret=json_encode($Err);
                echo $Ret ;
                return $Err;
            }
            $URL="https://{{smsapi_host}}:443/{{OPERATOR_NAME}}sms/admin/v1/contracts" ;
            $Headers=array(
                "Cache-Control: no-cache",
                "Authorization: Bearer ".self::$TOKEN_AUTH,
                "content-type: application/json"
            ) ;
            $Rep=self::$Main::$SMSEngine::EnvoieRequette($URL,[],$Headers,0,'');
            //var_dump(self::$TOKEN_AUTH);
            $data=json_decode($Rep);
            //var_dump($data);
            
            if (property_exists($data,"code")){
                if ($data->code==42 && $data->code==41){
                    self::$TOKEN_AUTH='';
                    $this->Ready=false;
                    // Si le token à expiré, on obtient le nouveau Token puis on remet le message dans la file en changant son Etat et en enregistrant
                    //$this->TokenRefresh();
                    $this->TokenRefresh();
                    if ($this->Ready){
                        $this->GetSMSBalance();
                    }
                }
            }

            $Balance=[];
            if (property_exists($data,"partnerContracts")){
                $partnerContracts=$data->partnerContracts;
                //var_dump($partnerContracts->contracts);
                $ListeContrat=$partnerContracts->contracts ;
                foreach ($ListeContrat as $contrat){
                    //var_dump($contrat);
                    foreach ($contrat->serviceContracts as $ServiceContrat ){
                        //var_dump($ServiceContrat);
                        $Balance['Pays']=$ServiceContrat->country ;
                        $Balance['Service']=$ServiceContrat->service ;
                        $Balance['IdContrat']=$ServiceContrat->contractId ;
                        $Balance['CreditRestant']=$ServiceContrat->availableUnits ;
                        $Balance['DateExp']=$ServiceContrat->expires ;
                        $Balance['Note']="Votre credit expire le ".$Balance['DateExp'] ;
                        $SepPos=strpos($Balance['DateExp'],"T");
                        if ($SepPos>0){
                            $vDt=explode("T",$Balance['DateExp']);
                            $EnDt=$vDt[0];
                            $Heure=$vDt[1];
                            $vDt=explode("-",$EnDt);
                            $Dte=$vDt[2]."/".$vDt[1]."/".$vDt[0] ;                            
                            $Balance['Note']="Votre credit ".$Balance['Service']." expire le ".$Dte." ".$Heure ;
                        }
                        //var_dump($Balance);
                        return $Balance ;

                    }
                    
                }
                

            }


        }

        public function ErrorManager($ErrString=''){
            $Err=json_decode($ErrString);
        }

        public function GetQueryParameters(xMessageSMS $Message): array
        {
            $Parametre=[];
            // il n'y a pas de paramètre à envoyer avec Orange
            return $Parametre ;
        }

        /**
         * Change l'ecodage du texte entré
         * @param mixed $EncodedTarget 
         * @param mixed $Text 
         * @param string|null $VarNameToLog : Si fournit, le traitement est inscrit dans le journal
         * @return string|false 
         */
        public static function EncodeTextTo(string $EncodedTarget = 'UTF-8', string $Text='', string $VarNameToLog=null){
            $listeEnc[]="BASE64";
            $listeEnc[]="Windows-1251";
            $listeEnc[]="Windows-1252";
            $listeEnc[]="ISO-8859-1";
            $listeEnc[]="CP932";
            $listeEnc[]="CP51932";
            $listeEnc[]="UTF-8";
            $listeEnc[]="ASCII";
            $EncodindFind = 'ISO-8859-1'; // mb_detect_encoding($Text, $listeEnc,true);
            //$eText = iconv($EncodindFind,$EncodedTarget, $Text);
            $eText = UConverter::transcode($Text,$EncodedTarget,$EncodindFind);
            $EncodindResult = mb_detect_encoding($eText, $listeEnc,true);
            if($VarNameToLog){
                self::$Main::$Log->AddToLog($VarNameToLog." était de type ".$EncodindFind. " il est maintenant de type ". $EncodindResult);
                self::$Main::$Log->AddToLog("[Valeur]".$Text ." => ". $eText);
            }
            return $eText;
        }

    }

    


?>