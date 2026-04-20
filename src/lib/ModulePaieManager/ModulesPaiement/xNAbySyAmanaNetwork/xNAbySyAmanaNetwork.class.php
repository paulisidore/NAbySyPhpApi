<?php

use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Panier\xCart;
use NAbySy\Lib\ModulePaie\Amana\xApiNAbySyAmanaConnect;
use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;
use NAbySy\Media\xMediaRessource;
use NAbySy\MethodePaiement\xMethodePaie;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;
use NAbySy\xNotification;

class xNAbySyAmanaNetwork implements IModulePaieManager {
    private int $API_DISPONIBLE = 1;
    private string $API_TOKEN ="";
    private string $API_ENDPOINT ="";
    private string $API_AUTH ="";
    private string $API_AUTH_USER ="paulvb";
    private string $API_AUTH_PWD ="";
    private int $WAIT_API_RESPONSE = 0;
    private string $API_REFCLIENT ="";

    public xNAbySyGS $Main ;
    private static string $myName = "NAbySyAmana Network";
    private static string $myDesc = "Module de paiement sur le réseau Amana pour les applications compatibles NAbySy et TechnoPharm." ;
    private bool $ready ;
    private static string $myModuleHandleName = "xNAbySyAmanaNetwork" ;
    private static string $my_log_name = "Amana.png";

    public static xApiNAbySyAmanaConnect $AmanaApi ;
    public xORMHelper $HistTranct ;

    public string $TableHistPaiement;
    public xORMHelper $HistPaiement ;

    private string $MyLastError;

    public function __construct(xNAbySyGS $NAbySy) {
        $this->Main = $NAbySy;
        self::$AmanaApi = new xApiNAbySyAmanaConnect($this->Main);

        $this->TableHistPaiement="methodepaiehistorique";

        $this->HistTranct = new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,"amana_transaction") ;
        if (!$this->HistTranct->MySQL->TableExiste($this->HistTranct->Table)){
            $this->HistTranct->FlushMeToDB();
        }

        $this->HistPaiement = new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement) ;
        if (!$this->HistPaiement->MySQL->TableExiste($this->HistPaiement->Table)){
            $this->HistPaiement->FlushMeToDB();
        }
        $this->setupVariable();
        $this->ready = self::$AmanaApi->IsReady ;
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
        $this->API_DISPONIBLE = 1;
        $this->API_TOKEN = self::$myModuleHandleName;
        $this->API_ENDPOINT =$this->BaseURL()."/checkout.php";
        $this->API_AUTH = $this->BaseURL()."/auth.php";
        $this->API_AUTH_USER ="";
        $this->API_AUTH_PWD ="";
        $this->WAIT_API_RESPONSE = 0;
        $this->API_REFCLIENT =""; //API Token Auth
        //Copie du logo dans les médias de NAbySyGS s'il n'existe pas.
        $MediaR=new xMediaRessource($this->Main);
        $MediaR->SaveMedia(__DIR__.DIRECTORY_SEPARATOR.self::$my_log_name,self::$my_log_name);

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
        return "Amana";
    }

    public function Description(): string{
        return self::$myDesc;
    }

    public function LogoURL(): string
    {   
        $MediaR=new xMediaRessource($this->Main);
        $Url = $MediaR->GetMediaURL(self::$my_log_name,true);
        return $Url;
    }

    public function HandleModuleName(): string{
        return self::$myModuleHandleName;
    }

    public function GetCheckOut($Montant, array $InfosPanier): xNotification {
        // On prépare la transaction locale avant d'appeler l'API AmanaTa
        $Demande = new xORMHelper($this->Main, null, $this->Main::GLOBAL_AUTO_CREATE_DBTABLE, $this->HistTranct->Table);
        $Demande->DateEnreg  = date('Y-m-d');
        $Demande->HeureEnreg = date('H:i:s');
        $Demande->Etat       = xCheckOutParam::PAIEMENT_ENCOUR;
        foreach ($InfosPanier as $key => $value) {
            $Demande->$key = $value;
        }
        $Demande->Enregistrer();

        // Paramètres AmanaTa - externalReference = RefFacture fourni par l'app cliente
        $externalReference = $Demande->RefFacture    ?? self::$AmanaApi->GetUniqueReference();
        $description       = $Demande->DescriptionPaiement ?? 'Paiement ' . $externalReference;
        $telephonePayeur   = $Demande->TelephonePayeur      ?? '';
        $fraisInclus       = $Demande->FraisInclus          ?? xApiNAbySyAmanaConnect::FRAIS_NON_INCLUS;
        $webhookUpdate     = $Demande->WebhookUpdate        ?? '';

        $Reponse = self::$AmanaApi->FairePaiement(
            (int) $Montant,
            $telephonePayeur,
            $externalReference,
            $description,
            $fraisInclus,
            $webhookUpdate
        );

        $this->Main::$Log->Write(__FILE__ . " L" . __LINE__ . " Retour GetCheckOut:" . json_encode($Reponse->Contenue));

        // Si la demande a réussi, on stocke la référence AmanaTa retournée
        // pour pouvoir vérifier l'état de la transaction ultérieurement
        if ($Reponse->OK == 1 && isset($Reponse->Contenue['reference']['reference_transaction'])) {
            $Demande->RefAmanata = $Reponse->Contenue['reference']['reference_transaction'];
            $Demande->Enregistrer();
        }

        return $Reponse;
    }

    public function GetEtatCheckOut(xCheckOutParam $Demande): xNotification
    {
        // On utilise la référence AmanaTa stockée lors du GetCheckOut
        $RefAmanata = $Demande->RefAmanata ?? '';
        if ($RefAmanata === '') {
            $Retour = new xNotification();
            $Retour->OK = 0;
            $Retour->TxErreur = 'RefAmanata manquante pour vérifier le statut de la transaction';
            return $Retour;
        }
        $Retour = self::$AmanaApi->VerifierStatutPaiement($RefAmanata);
        return $Retour;
    }

    public function ValideTransaction(array $MethodePaie, xCart $Panier): bool
    {
        return false;
    }

    public function UpDateFacture(int $IdFacture, xCart $Panier, array $MethodePaie): bool
    {   //Mise de la Table de l'historique des paiements wave
        $HistP=new xORMHelper($this->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement);
        $IdMethode=xMethodePaie::GetMethodeIDinDB($this->UIName());
        if($IdMethode == false){
            $IdMethode=array_search($this,$this->Main::$ListeModulePaiement);
        }
        if (!$HistP->TableIsEmpty()){           
            if($IdMethode){                    
                $Ret=$HistP->ChargeListe("IdFacture = '".$IdFacture."' and IdMethode = '".$IdMethode."' ");
                if ($Ret->num_rows){
                    $rw=$Ret->fetch_assoc();
                    $HistP=new xORMHelper($this->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->TableHistPaiement);
                }
            }            
        }
        $HistP->IdFacture=$IdFacture;
        
        $HistP->NomMethode = $this->UIName();
        $HistP->IdMethode=$IdMethode ;
        $Facture=new xVente($this->Main,$IdFacture);
        if ($Facture->Id>0){
            $HistP->DateFacture=$Facture->DateFacture;
            $HistP->HeureFacture=$Facture->HeureFacture;
            $HistP->Montant = $MethodePaie['MONTANT'];
            //On met à jour la table facture pour être conforme aux app desktop PAM
            $Facture->PaymentModule_METHODE = $this->UIName();
            $Facture->IDPaymentModule_METHODE = $IdMethode;
            $Facture->Enregistrer();
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

    /**
     * Retourne l'URL de la racine du Site
     * @return string 
     */
    public function BaseURL(): string {   
        $Url ="";
        $httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		$sitePrefix=$httpX.$_SERVER['HTTP_HOST'] ;
        return $sitePrefix ;
    }

}
?>