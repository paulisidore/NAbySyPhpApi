<?php
    namespace NAbySy\Lib\ModulePaie ;

use NAbySy\GS\Facture\xVente;
use NAbySy\xNAbySyGS;
use xNotification;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;
use NAbySy\GS\Panier\xCart;
use NAbySy\ORM\xORMHelper;

    //include_once 'xApiNAbySyWaveConnect.class.php';

class xOrangeMoney implements IModulePaieManager {

    private int $API_DISPONIBLE = 0;
    private string $API_TOKEN ="";
    private string $API_ENDPOINT ="";
    private string $API_AUTH ="";
    private string $API_AUTH_USER ="";
    private string $API_AUTH_PWD ="";
    private int $WAIT_API_RESPONSE = 0;
    private string $API_REFCLIENT ="";

    public xNAbySyGS $Main ;
    private static string $myName = "Orange Money";
    private static string $myDesc = "Module de paiement sur le réseau Orange MOney pour les applications PAM sarl." ;
    private bool $ready ;
    private static string $myModuleHandleName = "xOrangeMoney" ;

    //public static xApiNAbySyWaveConnect $WaveApi ;
    public xORMHelper $HistTranct ;

    public string $TableHistPaiement;
    public xORMHelper $HistPaiement ;

    private string $MyLastError;

    public function __construct(xNAbySyGS $NAbySy) {
        $this->Main = $NAbySy;
        //self::$WaveApi = new xApiNAbySyWaveConnect($this->Main);

        $this->TableHistPaiement="methodepaiehistorique";

        $this->HistTranct = new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,"orangemoney_transaction") ;
        if (!$this->HistTranct->MySQL->TableExiste($this->HistTranct->Table)){
            $this->HistTranct->FlushMeToDB();
        }

        $this->HistPaiement = new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement) ;
        if (!$this->HistPaiement->MySQL->TableExiste($this->HistPaiement->Table)){
            $this->HistPaiement->FlushMeToDB();
        }
        $this->setupVariable();
        $this->ready = true;// self::$WaveApi->IsReady ;
        $this->MyLastError="";
        
    }

    public function Api_Disponible(): int { return $this->API_DISPONIBLE; }

    public function Api_Token(): string { return $this->API_TOKEN; }

    public function Api_EndPoint(): string { return $this->API_ENDPOINT; }

    public function Api_Auth(): string { return $this->API_AUTH; }

    public function Api_Auth_User(): string { return $this->API_AUTH_USER; }

    public function Api_Auth_Pwd(): string { return $this->API_AUTH_PWD; }

    public function Wait_Api_Response(): int { return $this->WAIT_API_RESPONSE; }

    public function Api_RefClient(): string { return $this->API_REFCLIENT; }

    private function setupVariable(){
        $this->API_DISPONIBLE = 0;
        $this->API_TOKEN ="";
        $this->API_ENDPOINT ="https://technoweb.homeip.net:8181/paiement_api_action.php";
        $this->API_AUTH ="https://technoweb.homeip.net:8181/auth.php";
        $this->API_AUTH_USER ="";
        $this->API_AUTH_PWD ="";
        $this->WAIT_API_RESPONSE = 0;
        $this->API_REFCLIENT ="";        
    }

    public function UpDateTransaction(int $IdTransaction, array $MethodePaie): bool { 
        return true;
     }

    public function IsReady(): bool{
        return $this->ready ;
    }

    public function Nom(): string{
        return self::$myName;
    }

    public function UIName(): string
    {
        return "Orange Money";
    }

    public function Description(): string{
        return self::$myDesc;
    }

    public function LogoURL(): string
    {   
        $Url ="";
        $httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		//print_r($_SERVER);
		$DosTmp=$_SERVER['DOCUMENT_ROOT'].'/tmp' ;
		$sitePrefix=$httpX.$_SERVER['HTTP_HOST'] ;
        $Url=$sitePrefix."/lib/ModulePaieManager/ModulesPaiement/xOrangeMoney/OrangeMoney.png" ;
        return $Url ;
    }

    public function HandleModuleName(): string{
        return self::$myModuleHandleName;
    }

    public function GetCheckOut($Montant, array $InfosPanier): xNotification {
        //On prépare une reference de facture temporaire puis on demande un checkout au serveur distant NAbySyWave-Plateforme API
        $Demande=new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->HistTranct->Table);
        $Demande->DateEnreg = date('Y-m-d');
        $Demande->HeureEnreg =date('H:i:s');
        //$Demande->EtatDemande = 'EN COUR' ;
        foreach ($InfosPanier as $key => $value){
            $Demande->$key = $value ;
        }
        $Demande->Enregistrer();
        $Retour = new xNotification();
        $Retour->OK = 1 ;
        $Retour->Autres="API Orange Money on implémenté.";
        if ($Retour->OK == 0){
            //Erreur
            $Demande->ErreurAPI = $Retour->TxErreur ;
            $Demande->Enregistrer();
            return $Retour ;
        }
        //$Demande->orange_url = $Retour->Extra ;
        $Demande->Enregistrer();
        $Retour->Contenue =$Retour->Autres ; // $Demande->ReponseAPI ;

        $Reponse = $Retour ;
        //Si Ecran de Présentation disponible pour la Caisse utilisé alors envoyer les infos du QrCode dessus

        return $Reponse;
    }

    public function GetEtatCheckOut(xCheckOutParam $Demande): xNotification
    {
        $Rep=new xNotification();
        $Rep->OK =1;
        $Retour= $Rep ; //self::$WaveApi->GetEtatPaiement($Demande);
        return $Retour;
    }

    public function ValideTransaction(array $MethodePaie, xCart $Panier): bool
    {
        return false;
    }

    public function UpDateFacture(int $IdFacture, xCart $Panier, array $MethodePaie): bool
    {   //Mise de la Table de l'historique des paiements wave
        $HistP=new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement);
        if (!$HistP->TableIsEmpty()){
            $oMethode=new xORMHelper($this->Main,null,false,"methodepaie");
            $Lst=$oMethode->ChargeListe("Nom like '".$this->UIName()."'");
            if($Lst){
                if($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $Ret=$HistP->ChargeListe("IdFacture = '".$IdFacture."' and IdMethode = '".$rw['ID']."' ");
                    if ($Ret->num_rows){
                        $rw=$Ret->fetch_assoc();
                        $HistP=new xORMHelper($this->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement);
                    }
                }
            }
        }
        $HistP->IdFacture=$IdFacture;
        
        $HistP->NomMethode = $this->UIName();
        $IdMod=array_search($this,$this->Main::$ListeModulePaiement);
        $HistP->IdMethode=$IdMod ;
        $Facture=new xVente($this->Main,$IdFacture);
        if ($Facture->Id>0){
            $HistP->DateFacture=$Facture->DateFacture;
            $HistP->HeureFacture=$Facture->HeureFacture;
            $HistP->Montant = $MethodePaie['MONTANT'];
            return $HistP->Enregistrer();
        }else{
            $this->MyLastError="Facture ".$IdFacture." introuvable !";
        }
        return false;
    }

    public function GetDetailFacture(int $IdFacture): array
    {
        $Liste=$this->HistPaiement->ChargeListe("IdFacture ='".$IdFacture."' ");
        $LHist=[];
        if ($LHist){
            while ($rw=$Liste->fetch_assoc()){
                $LHist[]=$rw;
            }
        }
        return $LHist ;
    }

    public function RollBackFacture(int $IdFacture): bool
    {
        return true;
    }

    public function LastError(): string
    {
        return $this->MyLastError;
    }

        
}


?>