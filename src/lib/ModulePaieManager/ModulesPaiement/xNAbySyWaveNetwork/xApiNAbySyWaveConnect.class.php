<?php
namespace NAbySy\Lib\ModulePaie\Wave ;

use NAbySy\GS\Comptabilite\xCompteBancaire;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;
use xNotification;

include_once 'xCheckOutParam.class.php';

/**
 * Cette class gère l'API d'échange de donnée entre la plate-forme locale NAbySy GS et le serveur des Paiements NAbySy-Wave
 */
class xApiNAbySyWaveConnect {
    public static xNAbySyGS $Main ;
    public xORMHelper $Config ;
    public bool $IsReady;
    public static xCompteBancaire $CompteBancaire ;

    public function __construct(xNAbySyGS $NAbySy){
        self::$Main=$NAbySy ;
        $this->Config = new xORMHelper($NAbySy,1,true,"nabysywave_config") ;
        if (!$this->Config->MySQL->TableExiste($this->Config->Table)){
            //Creation de la table Config et la premiére cofiguration
            $IdC=$this->CreateNewSetup();
            if ($IdC>0){
                $this->Config = new xORMHelper($NAbySy,$IdC,true,"nabysywave_config") ;
            }
        }
        if ($this->Config->Id == 0){
            self::$Main::$Log->Write("Aucune configuration disponible pour ". __CLASS__);
            $this->IsReady=false;
            return ;
        }
        $Cpte = new xCompteBancaire(self::$Main);
        if (count($Cpte)==0){
            //Si aucun compte bancaire, on créer au moins un par défaut
            $LeCpte = $Cpte -> CreateNewCompte("WAVE",0,"WAVE_221XXXXXXX");
            $LeCpte->Defaut=1;
            $LeCpte->Enregistrer();
            self::$CompteBancaire=$LeCpte;
        }else{
            self::$CompteBancaire = $Cpte->GetCompteBancaireByName("WAVE","WAVE_221XXXXXXX");
            if(!isset(self::$CompteBancaire)){
                self::$CompteBancaire = $Cpte->CreateNewCompte("WAVE",0,"WAVE_221XXXXXXX");
            }
        }        

        $this->IsReady=true;
    }

    /**Créer une nouvelle configuration */
    private function CreateNewSetup($AppName='NAbySyGS'):int{       
        $NewConfig=new xORMHelper(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE, $this->Config->Table) ;
        $NewConfig->APPLICATION_NAME = $AppName ;
        $NewConfig->END_POINT="https://technoweb.homeip.net:8181/paiement_api_action.php";
        $NewConfig->IDCONFIG = 0; //Id correspondant à la configuration du compte marchand chez PAM.
        $NewConfig->PrefixRef =""  ;    

        $NewConfig->IdClientPAM = 0;
        $NewConfig->NOM_CLIENT =''; //Nom du client (PAM / EXCLUSIVE /PHARMACIE X etc.)
        $NewConfig->SoldeClient =0;        
        $NewConfig->API_KEY=""; //API KEY Chez PAM.

        $NewConfig->Enregistrer();
        return $NewConfig->Id ;
    }

    public function GetUniqueUUID($prefix=''):string{
        $idem_key = uniqid($prefix,true);
        return $idem_key ;
    }

    /**
     * Créer un nouvel objet de demande de paiement sans l'enregistrer dans la base de donnée.
     * @param int $Montant : Le montant de la transaction à demander
     * @param string $RefFacture : La réference de la facture qui doit etre soldée par le client
     * 
     * @return xCheckOutParam : Un objet contenant les paramètres à envoyer. cet objet n'est pas encore enregistré dans la base de donnée.
     * L'enregistrement pourrais se faire une fois la demande de paiement envoyé au serveur API de Wave
     */
    private function CreateCheckOut(int $Montant,int $IdFacture=0, string $PosteSaisie="Caisse", string $Caissier="user1"): xCheckOutParam {
        $nObj = new xCheckOutParam(self::$Main,null,self::$Main::GLOBAL_AUTO_CREATE_DBTABLE);
        $Prefix=$this->Config->PrefixRef . "".$this->Config->IDCONFIG ;
        $RefTemporaire=$this->GetUniqueUUID($Prefix);
        $RefTemporaire=substr($RefTemporaire,0,20);
        $nObj->IdClientPAM = $this->Config->IdClientPAM;
        $nObj->IDCONFIG = $this->Config->IDCONFIG;
        $nObj->IdTransaction =0;
        $nObj->Montant = $Montant ;
        $nObj->RefFactureTemp = $RefTemporaire ;
        $nObj->RefFacture = $RefTemporaire ;
        $nObj->Caisse=$PosteSaisie ;
        $nObj->Caissier=$Caissier ;
        $nObj->IdFacture = $IdFacture ;
        $nObj->IdDemandeNAbySy = 0 ;
        $nObj->DateDemande = date('Y-m-d') ;
        $nObj->HeureDemande = date('H:i:s');
        $nObj->Etat = $nObj::PAIEMENT_ENCOUR;

        return $nObj;
    }

    /**
     * Envoie une nouvelle demande de paiement au serveur NAbySy-Wave et retourne les infos à valider par le client
     */
    public function GetNewDemandePaiement($Montant,$PosteSaisie,$Caissier):xNotification{       
        $Demande=$this->CreateCheckOut($Montant,0,$PosteSaisie,$Caissier);
/*         $Headers=array(
            "Cache-Control: no-cache",
            "Authorization: Bearer ".$this->Config->API_KEY,
            "content-type:application/json;charset=utf-8",
            "NAbysy-tranctionid: ".$Demande->RefFactureTemp,
            "NAbysy-ClientID: ".$Demande->IdClientPAM,
            "NAbysy-ConfigID: ".$Demande->IDCONFIG
        ); */

        $Headers=array(
            "Cache-Control: no-cache",
            "Authorization: Bearer ".$this->Config->API_KEY,
            "content-type: multipart/form-data;charset=utf-8",
            "NAbysy-tranctionid: ".$Demande->RefFactureTemp,
            "NAbysy-ClientID: ".$Demande->IdClientPAM,
            "NAbysy-ConfigID: ".$Demande->IDCONFIG
        );

        $Retour=new xNotification;
        $Retour->OK=0;
        $Retour->Extra=0;
        $Retour->Contenue =null;

        $URL=$this->Config->END_POINT ;
        # Define the request options
        $curlOptions = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $Demande->GetDemandeArray(),
            CURLOPT_HTTPHEADER => $Headers,
        ];
        //self::$Main::$Log->Write(__FUNCTION__." Line ".__LINE__." curl= ".json_encode($curlOptions));
        self::$Main::$Log->Write(__FILE__." Line ".__LINE__." curl Paramètre ".json_encode( $Demande->GetDemandeArray() ) );
        # Execute the request and get a response
        $curl = curl_init();
        $ret=curl_setopt_array($curl, $curlOptions);
        if (!$ret){
            //Une option ne peut pas etre inscrite
            self::$Main::$Log->Write(__CLASS__.": CURL-REP-LIGNE-".__LINE__.": Une option ne peut pas etre inscrite ERR=".$ret);
        }
        $Reponse = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $Demande->ReponseHTTP = $httpcode ;

        self::$Main::$Log->Write($Reponse);
        if ($err){
            //echo "cURL Error #:" . $err;
            $MsgErr=__CLASS__.": CURL-ERR-LIGNE-".__LINE__.":".$err ;
            $this->Config->AddToJournal(__CLASS__,"SendMoney: ".$MsgErr);
            self::$Main::$Log->Write(__CLASS__.": CURL-ERR-LIGNE-".__LINE__.":".$err);
            $Demande->ErreurAPI = $err ;
            $Demande->ReponseHTTP = $httpcode ;
            $Demande->EtatEnvoie = $Demande::PAIEMENT_ANNULER ;
            $Demande->Enregistrer() ;
            $Retour->TxErreur=$err ;
            return $Retour ;   
        }

        self::$Main::$Log->Write(__CLASS__.": CURL-REP-LIGNE-".__LINE__.":".$Reponse);

        $Demande->ReponseAPI = $Reponse ;

        $Retour = new xNotification($Reponse);
        if ($Retour->OK == 0){
            //Erreur
            $Demande->ErreurAPI = $Retour->TxErreur ;
            $Demande->Enregistrer();
            return $Retour ;
        }

        $Demande->wave_url = $Retour->Extra ;
        $Demande->Enregistrer();
        $Retour->Contenue = $Demande->ReponseAPI ;

        return $Retour ;

    }

    /**
     * Retourne l'etat d'une demande de paiement
     */
    public function GetEtatPaiement(xCheckOutParam $Demande):xNotification{
        $Headers=array(
            "Cache-Control: no-cache",
            "Authorization: Bearer ".$this->Config->API_KEY,
            "content-type:application/json;charset=utf-8",
            "NAbysy-tranctionid: ".$Demande->RefFactureTemp,
            "NAbysy-ClientID: ".$Demande->IdClientPAM,
            "NAbysy-ConfigID: ".$Demande->IDCONFIG
        );

        $Retour=new xNotification;
        $Retour->OK=0;
        $Retour->Extra=0;
        $Retour->Contenue =null;

        $URL=$this->Config->END_POINT ;
        $PostField['Action']='ISPAIEMENT_OK' ;
        $PostField['IDCONFIG']=$Demande->IDCONFIG ;
        $PostField['IDDEMANDE']=$Demande->IdDemandeNAbySy ;
        # Define the request options
        $curlOptions = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $Demande->GetDemandeJSON('ISPAIEMENT_OK'),
            CURLOPT_HTTPHEADER => $Headers,
        ];
        self::$Main::$Log->Write(__FUNCTION__."Line ".__LINE__." curl= ".json_encode($curlOptions));

        # Execute the request and get a response
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $Reponse = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::$Main::$Log->Write($Reponse);
        if ($err){
            //echo "cURL Error #:" . $err;
            $MsgErr=__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err ;
            $this->Config->AddToJournal(__CLASS__,"SendMoney: ".$MsgErr);
            self::$Main::$Log->Write(__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err);
            $Retour->TxErreur=$err ;
            return $Retour ;   
        }
        $Rep=new xNotification($Reponse);
        return $Rep ;
    }

    /**
     * Permet l'annulation d'un paiement NAbySy-Wave et le remboursement du client
     */
    public function AnnuleTransaction(xCheckOutParam $Transaction):xNotification{
        $Headers=array(
            "Cache-Control: no-cache",
            "Authorization: Bearer ".$this->Config->API_KEY,
            "content-type:application/json;charset=utf-8",
            "NAbysy-tranctionid: ".$Transaction->RefFactureTemp,
            "NAbysy-ClientID: ".$Transaction->IdClientPAM,
            "NAbysy-ConfigID: ".$Transaction->IDCONFIG
        );

        $Retour=new xNotification;
        $Retour->OK=0;
        $Retour->Extra=0;
        $Retour->Contenue =null;

        $URL=$this->Config->END_POINT ;
        $PostField['Action']='DEMANDE_REFUND' ;
        $PostField['IDCONFIG']=$Transaction->IDCONFIG ;
        $PostField['IDTRANSACTION']=$Transaction->IdDemandeNAbySy ;
        # Define the request options
        $curlOptions = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($PostField),
            CURLOPT_HTTPHEADER => $Headers,
        ];
        self::$Main::$Log->Write(__FUNCTION__."Line ".__LINE__." curl= ".json_encode($curlOptions));

        # Execute the request and get a response
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $Reponse = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::$Main::$Log->Write($Reponse);
        if ($err){
            //echo "cURL Error #:" . $err;
            $MsgErr=__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err ;
            $this->Config->AddToJournal(__CLASS__,"SendMoney: ".$MsgErr);
            self::$Main::$Log->Write(__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err);
            $Retour->TxErreur=$err ;
            return $Retour ;   
        }
        $Rep=new xNotification($Reponse);
        return $Rep ;
    }

    /**
     * Retourne le solde en cour du Compte Wave Marchand
     */
    public function GetBalance():xNotification{
        $Headers=array(
            "Cache-Control: no-cache",
            "Authorization: Bearer ".$this->Config->API_KEY,
            "content-type:application/json;charset=utf-8",
            "NAbysy-ClientID: ".$this->Config->IdClientPAM,
            "NAbysy-ConfigID: ".$this->Config->IDCONFIG
        );

        $Retour=new xNotification;
        $Retour->OK=0;
        $Retour->Extra=0;
        $Retour->Contenue =null;

        $URL=$this->Config->END_POINT ;
        $PostField['Action']='GET_SOLDE' ;
        $PostField['IDCONFIG']=$this->Config->IDCONFIG ;
        # Define the request options
        $curlOptions = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($PostField),
            CURLOPT_HTTPHEADER => $Headers,
        ];
        self::$Main::$Log->Write(__FUNCTION__."Line ".__LINE__." curl= ".json_encode($curlOptions));

        # Execute the request and get a response
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $Reponse = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        self::$Main::$Log->Write($Reponse);
        if ($err){
            //echo "cURL Error #:" . $err;
            $MsgErr=__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err ;
            $this->Config->AddToJournal(__CLASS__,"SendMoney: ".$MsgErr);
            self::$Main::$Log->Write(__CLASS__."CURL-ERR-LIGNE-".__LINE__.":".$err);
            $Retour->TxErreur=$err ;
            return $Retour ;   
        }
        $Rep=new xNotification($Reponse);
        return $Rep ;
    }
}

?>