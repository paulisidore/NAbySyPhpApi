<?php
    namespace NAbySy\Lib\Sms ;

    use xNAbySyGS ;

    /**
     * Module permettant l'envoie et la réception d'SMS
     * Auteur: Paul et Aïcha Machinerie SARL
     * Support: Paul Isidore A. NIAMIE ; paul_isidore@hotmail.com
     */
    class xSMSOperator implements ISmsOperatorHelper {
         /** Nom de l'Opérateur Mobile SMS */
         public const OPERATOR_NAME = 'Orange SN';        

         /** Le numéro de téléphone expéditeur */
         public $ORIG_PHONE_NUMBER = '+221';
 
         public static $SENDER_NAME ='';
 
         /** Le end-point ou seront reçus et traité les accusés de reception */
         public const DELIVERY_REPORT_ENDPOINT ='https://{{dev_host}}:443/{{OPERATOR_NAME}}/smsdr.php' ;
 
         public static xNAbySyGS $Main ;
 
         public static $TOKEN_AUTH='' ;
         public $APP_TOKEN='' ; //"Basic WkdpYjk5ZzhJM2syZXMzVm1kbGc3VXRuOHdZdG5Velo6ZEdkSWJRUE5SUXJGcFE2Uw==" sur le site de l'opérateur
 
         public $Ready=false ;
 
         public static \NAbySy\ORM\xORMHelper $MyRS ;
         protected static xObservOrangeSMS $Observateur ;
 
         /** Le numéro de téléphone expéditeur */
         public function __construct(xNAbySyGS $NAbySy){
             $this::$Main=$NAbySy ;
             $AppToken='' ;
             $OriginePhoneNumber='' ;
 
             $IdConfig=1;
             self::$MyRS=new \NAbySy\ORM\xORMHelper(self::$Main,$IdConfig,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,"orangesn") ;
             if (self::$MyRS->Id){
                 $AppToken=self::$MyRS->AppToken ;
                 $OriginePhoneNumber=self::$MyRS->ExpediteurPhone ;
                 self::$TOKEN_AUTH=self::$MyRS->TOKEN_AUTH ;
                 self::$SENDER_NAME=self::$MyRS->SenderName ;
             }
             $this->ORIG_PHONE_NUMBER=$OriginePhoneNumber ;
             $this->APP_TOKEN=$AppToken ;
             //On recherche le Token dÁuthentification si l'on souhaite
             
 
             self::$MyRS->ExpediteurPhone=$this->ORIG_PHONE_NUMBER ;
             self::$MyRS->TOKEN_AUTH=self::$TOKEN_AUTH ;
             self::$MyRS->AppToken=$this->APP_TOKEN ;
             self::$MyRS->SenderName=self::$SENDER_NAME ;
             self::$MyRS->Enregistrer();
 
             if (self::$MyRS->AppToken !=='' &&  $this->ORIG_PHONE_NUMBER !=='' ){
                 if (self::$TOKEN_AUTH==''){
                     $this->GetToken("https://api.orange.com/oauth/v3/token",$this->APP_TOKEN);
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
                 self::$Observateur=new xObservSMS(self::$Main,"Orange SMS Observer",null,$this);
                 //Deja ajouté dans les Observateurs par le constructeur
                 //self::$Main->AddToObserveurListe($Observateur);
             }
             
 
         }
 
         public function EnvoieSms($DestPhoneNumber, string $Message): bool
         {
             if (!$this->Ready){
                 //var_dump($this->Ready) ;
                 return false;
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
                 
             $URL="https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B".$this->ORIG_PHONE_NUMBER."/requests";
             $URL="https://api.orange.com/smsmessaging/v1/outbound/tel".urlencode(':'.$this->ORIG_PHONE_NUMBER)."/requests";
             $Msg=new xMessageSMS($this::$Main,$URL,$this->ORIG_PHONE_NUMBER,$DestPhoneNumber,$Data,$Parametres,$this);
             $Msg->HttpHeader=json_encode($Headers) ;
             $Err=null ;            
             return $this::$Main::$SMSEngine->SendSMS($Msg,$Err) ;
             if (isset($Err)){
                 var_dump($Err);
             }
  
         }
 
 
 
         /** Fontion de rappel pour le traitement des Accusés de réceptions */
         public function CallBack(string $api_reponse): bool
         {
             $IsOK=false ;
 
             return $IsOK ;
         }
         
         public function TraiterReponse(xMessageSMS $Message, string $send_reponse, ?string $erreur = null): bool
         {
             $IsOK=false ;
             //var_dump($send_reponse);
             //var_dump($erreur);
             $Err=json_decode($send_reponse);
             //var_dump($Err) ;
 
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
                 $IsOK=false ;
             }
             
             if (property_exists($Err,"code")){
                 if ($Err->code==42){
                     // Si le token à expiré, on obtient le nouveau Token puis on remet le message dans la file en changant son Etat et en enregistrant
                     //$this->TokenRefresh();
                     $Message->OSmsDLR->TokenRefresh();
                     $Message->MyRS->Etat=xMessageSMS::SMS_EN_ATTENTE ;
                     $Message->Enregistrer() ;
                     $IsOK= true ;                
                 }else{
                     //Autre erreur
                     $Message->MyRS->TextErreur=$send_reponse ;
                     $Message->Enregistrer() ;
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
 
             $Rep=self::$Main::$SMSEngine::EnvoieRequette($AuthURL,[],$Headers,CURLOPT_POST,$Data);
             $data=json_decode($Rep);
             //var_dump($data);
             if (isset($data)){
                 self::$TOKEN_AUTH=$data->access_token;
                 self::$MyRS->TOKEN_AUTH=self::$TOKEN_AUTH ;
                 $this->Ready=true;
                 self::$MyRS->Enregistrer();                
                 return self::$TOKEN_AUTH ;
             }
 
             //Gestion des Erreur
             $Err=$Rep ;           
             
         }
 
         public function TokenRefresh(){
             return $this->GetToken("https://api.orange.com/oauth/v3/token",$this->APP_TOKEN);
         }
 
         public  function GetSMSBalance(){
             if (!$this->Ready){
                 $Err=new \xErreur ;
                 $Err->OK=0;
                 $Err->TxErreur="Module pas prêt !";
                 $Err->Source=__CLASS__ ;
                 $Ret=json_encode($Err);
                 echo $Ret ;
                 return 'Module pas prêt !';
             }
             $URL="https://api.orange.com/sms/admin/v1/contracts" ;
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
                 if ($data->code==42){
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

    }

    


?>