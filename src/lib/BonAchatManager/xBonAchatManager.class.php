<?php
    namespace NAbySy\Lib\BonAchat ;

use Exception;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Panier\xCart;
use NAbySy\Lib\BonAchat\Exclusive\xCarteBonAchatExclusive;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;

    /**
     * Module de Gestions des Bons d'Achat Generic
     * Ce module peux se connecter a son prore serveur d'API
     * CORPS doit etres activé
     */
    class xBonAchatManager implements IBonAchatManager {
        public xNAbySyGS $Main ;

        /** Ce module est la passerelle API qui gère les comptes de tous les clients avec Bon de reduction */
        public static xNAbySyGS $RemoteNAbySy ;
        public xHistoriqueBonAchat $HistoriqueBonAchat ;
        public xCarteBonAchatExclusive $BonAchatExclusive ;

        public const MODULE_NAME="xBonAchatManager" ;

        public string $lastError ;

        /** Contient la configuration du Module pour l'enregistrement reelle des transactions sur le serveur distant
         * Via API.
         * Localement la meme transaction sera stocké en guise d'Archive. seule la sauvegarde distante avec un accès via API REST 
         * reste authentique.
         */
        public static $CONFIG ;

        public static string $API_URL;

        public function __construct(xNAbySyGS $NAbySyGS) {
            $this->Main = $NAbySyGS ;
            $this->lastError="";
            //var_dump($NAbySyGS::$CURL);
            //On charge l'adresse de la gestion des bons depuis un fichier json
            $this->LoadApiConfigFromFile();
            if ($this->IsReady()){
                $this->HistoriqueBonAchat=new xHistoriqueBonAchat($this->Main);
                $this->BonAchatExclusive=new xCarteBonAchatExclusive($this->Main);
                $NAbySyGS->RegisterBonAchatManager($this);
            }
        }

        public function IsReady():bool{
            //var_dump(self::$CONFIG);
            if (isset($this->Main) && isset(self::$CONFIG)){
                //On fait le test d'appel REST API
                if (self::$CONFIG->ServeurOriginal==1){
                    //Nous somme déja sur l'original alors
                    //var_dump(self::$CONFIG->ServeurOriginal);
                    return true;
                }
                //return true ;

                $Param = array(
                    'Action' => 'BONACHAT_SERVICE_TEST',
                    'Token' => $this->Main->UserToken,
                );

                $Url=self::$CONFIG->Connexion->Serveur.self::$CONFIG->Connexion->bonachat_action ;
                self::$API_URL=$Url ;

                $AuthorizationToken=$this->Main->UserToken;
                $Headers=array(
                    "Cache-Control: no-cache",
                    "Authorization: ".$AuthorizationToken,
                    "content-type:application/x-www-form-urlencoded;charset=utf-8"
                ) ;
                $Data="grant_type=client_credentials" ;
                
                //return true;
                $Reponse=$this->Main::$CURL->EnvoieRequette($Url,$Param,[],CURLOPT_POST,$Data);
                //var_dump($Reponse);
                
                //echo "</br>".__FILE__." Ligne ".__LINE__."</br>" ;
                //var_dump($Url);
                //exit;

                $Rep=json_decode($Reponse);
                if ((int)$Rep->OK>0){
                    return true;
                }
                return false;
            }
            return false;
        }

        /**
         * Autorise ou non le Bon d'Achat
         * @param array $BonAchat : Le tableau contenant les identifiants de la carte de Bon d'Achat et le Montant à déduire
         * @param xCart $Panier : Infos du Panier en Cour de Validation
         * @return bool
         */
        public function AutoriseTransaction(array $BonAchat, xCart $Panier):bool{
            //var_dump(self::$CONFIG);
            //exit;
            if (!isset($BonAchat['MODULE'])){
                return false;
            }
            if (strtolower($BonAchat['MODULE']) !== strtolower(self::MODULE_NAME)  ){
                return false;
            }
            //var_dump(self::$CONFIG);
            //exit;
            if (self::$CONFIG->ServeurOriginal==0){
                //Nous alons demander á l'original
                //var_dump($_REQUEST);
                $url=self::$CONFIG->Connexion->Serveur."panier_action.php";
                //var_dump($url);
                //var_dump($_REQUEST);
                //exit;
                $Reponse=$this->Main::$CURL->EnvoieRequette($url,$_REQUEST,null,CURLOPT_POST);
                //var_dump($Reponse);
                $Rep=null;
                /* if (($Reponse,'Operation timed out after') ){
                    
                    var_dump($Reponse);
                    $Rep=new xErreur;
                    $Rep->OK=0;
                    $Rep->TxErreur=$Reponse;
                    $Rep->Source=$url;
                    $Rep->Extra=$_REQUEST;
                    $this->lastError=$Rep->TxErreur;
                    //echo json_encode($Rep);
                    return false;
                    
                } */
                $Rep=json_decode($Reponse);
                
                if (!isset($Rep)){
                    //var_dump(json_last_error_msg());
                    $Rep=new xErreur;
                    $Rep->OK=0;
                    $Rep->TxErreur=json_last_error_msg();
                    $Rep->Source=$url;
                    $Rep->Extra=json_last_error();
                    $this->lastError=$Rep->TxErreur;
                    //echo json_encode($Rep);
                    return false;
                }
                //var_dump($Rep);
                try{
                    if (!property_exists($Rep,'OK')){
                        $Err=new xErreur;
                        $Err->OK=0;
                        $Err->TxErreur=$Rep->TxErreur;
                        $Err->Source=$Rep->Source;
                        $Err->Extra=$Rep->Extra;
                        $Err->Autres=$Rep->Autres;
                        $this->lastError=$Rep->TxErreur ;
                       // echo json_encode($Err);
                        return false;
                      
                    }
                }catch(Exception $ex){
                    $this->lastError=$ex->getMessage();
                }
                return true;
            }

            if (!isset($BonAchat['REFCARTE'])){
                $this->lastError="Absence de la reference de la carte à puce pour la prise en charge des bon d'achat. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            if (!isset($BonAchat['MontantBon'])){
                $this->lastError="Montant du bon d'achat absent. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            $RefCarte=$BonAchat['REFCARTE'];
            if (!$this->BonAchatExclusive->MySQL->ChampsExiste($this->BonAchatExclusive->Table,'REFCARTE')){
                $this->lastError="Aucune carte disponible. Voire Configuration initiale. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            // On va retrouver les informations de la carte
            $Critere="REFCARTE like '".$RefCarte."' ";
            $Lst=$this->BonAchatExclusive->ChargeListe($Critere,null,"Id");
            if ($Lst->num_rows==0){
                $this->lastError="Carte introuvable. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            $IdC=$Lst->fetch_assoc();
            $IdCarte=$IdC['Id'];
            $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
            if ($Carte->Etat ==$Carte::CARTE_SUSPENDUE || $Carte->Etat ==$Carte::CARTE_BLOQUEE){
                if ($Carte->Etat ==$Carte::CARTE_SUSPENDUE){
                    $this->lastError="Carte suspendue. ".json_encode($BonAchat);
                }
                if ($Carte->Etat ==$Carte::CARTE_BLOQUEE){
                    $this->lastError="Carte Bloquée. ".json_encode($BonAchat);
                }
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }

            $MontantFacture=$Panier->getTotalPriceCart();
            $MontantRemise=$Panier->TotalRemise;
            $MtBon=$BonAchat['MontantBon'];
            if ($MtBon > $Carte->Solde){
                $this->lastError="Solde insuffisant pour valider l'opération. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }

            return true;
        }


        public function UpDateFacture(int $IdFacture,xCart $Panier,array $BonAchat ):bool{       
            
            if (self::$CONFIG->ServeurOriginal==0){
                //Nous alons demander á l'original
                //var_dump(self::$API_URL) ;
                $Action="Action=BONACHAT_DEBIT_CARTE";
                $Param[]=$Action;
                $RefCarte="REFCARTE=".$BonAchat['REFCARTE'];
                $Param[]=$RefCarte;

                $Reponse=$this->Main::$CURL->EnvoieRequette(self::$API_URL,$Param,null,null);
                $Rep=json_decode($Reponse);
                //var_dump($Rep);
                try{
                    if (property_exists($Rep,'OK')){
                        $Err=new xErreur;
                        $Err=$Rep;
                        $Err->Source="REMOTE API VALIDATION" ;
                       if( $Err->OK>0){
                        return true;
                       }
                       $Err->Source="REMOTE API: ".self::$API_URL ;
                       $this->lastError=$Err->TxErreur;
                        return false;
                    }
                }catch(Exception $ex){

                }
                echo $Reponse;                
                return true;
            }

            $RefCarte=$BonAchat['REFCARTE'];
            if (!$this->BonAchatExclusive->MySQL->ChampsExiste($this->BonAchatExclusive->Table,'REFCARTE')){
                $this->lastError=__FUNCTION__.": Aucune carte disponible. Voire Configuration initiale. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            // On va retrouver les informations de la carte
            $Critere="REFCARTE='".$RefCarte."' ";
            $Lst=$this->BonAchatExclusive->ChargeListe($Critere,null,"Id");
            //var_dump($Critere);
            if ($Lst->num_rows==0){
                $this->lastError=__FUNCTION__.": Carte introuvable. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }
            $IdC=$Lst->fetch_assoc();
            $IdCarte=$IdC['Id'];
            $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
            if ($Carte->Etat ==$Carte::CARTE_SUSPENDUE || $Carte->Etat ==$Carte::CARTE_BLOQUEE){
                if ($Carte->Etat ==$Carte::CARTE_SUSPENDUE){
                    $this->lastError=__FUNCTION__.": Carte suspendue. ".json_encode($BonAchat);
                }
                if ($Carte->Etat ==$Carte::CARTE_BLOQUEE){
                    $this->lastError=__FUNCTION__.": Carte Bloquée. ".json_encode($BonAchat);
                }
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }

            $Vente=new xVente($this->Main,$IdFacture);

            $MontantFacture=$Vente->TotalFacture;
            $MontantRemise=$Vente->MontantRemise;
            $MtBon=$BonAchat['MontantBon'];
            if ($MtBon > $Carte->Solde){
                $this->lastError=__FUNCTION__.": Solde insuffisant pour valider l'opération. ".json_encode($BonAchat);
                $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                return false;
            }

            $NewHist=new xHistoriqueBonAchat($this->Main,null,true);
            $NewHist->DateOP=$Panier->DateFacture();
            $NewHist->HeureOP=date("H:i:s");
            $NewHist->IdCarte=$Carte->Id;
            $NewHist->SurCarte=1;
            $NewHist->RefCarte=$Carte->RefCarte ;
            $NewHist->SoldePrecedent=(float)$Carte->Solde;
            $NewHist->Operation="DEBIT SOLDE CARTE";
            $NewHist->Libelle="DEBIT sur Facture n°".$Panier->IdFacture;
            $NewHist->IsCredit=0;

            $NewHist->MODULE=$this->HandleModuleName();
            $NewHist->Montant=$MtBon;
            $NewHist->IdFacture=$Panier->IdFacture;
            $NewHist->TotalFacture=$MontantFacture;
            $NewHist->TotalRemise=$MontantRemise ;
            $NewHist->TotalBonAchat=$BonAchat['MontantBon'];
            $NewHist->TotalReduction=$Panier->TotalReduction;
            $NewHist->TotalNet=$NewHist->TotalFacture - $NewHist->TotalRemise - $NewHist->TotalBonAchat;
            $NewHist->MontantVerse=$Panier->MontantVerse;
            $NewHist->MontantRendu=$Panier->MontantRendu ;

            $NewHist->IdUtilisateur=$this->Main->User->Id;
            $NewHist->Login=$this->Main->User->Login;
            $NewHist->PosteSaisie=$this->Main->NomPosteClient;
            $NewHist->IdPosteSaisie=$this->Main->IdPosteClient;

            if ($NewHist->Enregistrer()){
                //On met a jour le solde de la carte
                //var_dump((float)$Carte->Solde);                
                //var_dump((float)$NewHist->SoldePrecedent);
                $Carte->Solde -= (float)$BonAchat['MontantBon'];
                
                $NewHist->SoldeSuivant=(float)$Carte->Solde;
                //var_dump((float)$Carte->Solde);

                if (!$Carte->Enregistrer()){
                    $NewHist->Supprimer();
                    //Enregistrement échoué donc on annule tout                    
                    $this->lastError=__FUNCTION__.": Erreur systeme. Impossible de mettre à jour le solde de la carte. ".json_encode($BonAchat);
                    $this->Main::$Log->Write($this->lastError) ;
                    $this->HistoriqueBonAchat->AddToJournal(__CLASS__,"ERREUR: ".$this->lastError);
                    return false;
                }
                //Envoi d'UN SMS de Notification
                if ($Carte->TelClient !==''){
                    //$this->Main->SMSEngine->Send
                }
                $NewHist->Enregistrer();
                //************************************************* */
                return true;
            }
            return false;
        }
                
        public function RollBackFacture($IdFacture):bool{
            if (self::$CONFIG->ServeurOriginal==1){
                //Nous alons demander á l'original
                $Reponse=$this->Main::$CURL->EnvoieRequette(self::$API_URL,$_REQUEST,[],"");
                $Rep=json_decode($Reponse);
                try{
                    if (property_exists($Rep,'OK')){
                        $Err=new xErreur;
                        $Err=$Rep;
                        echo json_encode($Err);
                        //return false;
                        exit ;
                    }
                }catch(Exception $ex){

                }
                echo $Reponse;                
                return true;
            }

            $Lst=$this->HistoriqueBonAchat->ChargeListe("IdFacture=".$IdFacture);
            if ($Lst->num_rows ==0){
                return true ;
            }
            $row=$Lst->fetch_assoc();
            $IdHist=$row['Id'];
            $Hist=new xHistoriqueBonAchat($this->Main,$IdHist);
            $Montant=$Hist->TotalBonAchat ;
            $Carte=new xCarteBonAchatExclusive($this->Main,$Hist->IdCarte);
            if ($Carte->Id>0){
                $SoldeP=$Carte->Solde ;
                $Carte->Solde += $Hist->TotalBonAchat ;
                $SoldeSuiv=$Carte->Solde;
                if ($Carte->Enregistrer()){
                    $Ope="MISE A JOUR FACTURE AVEC BON ACHAT";
                    $Note="Suite à une modification/suppression de la facture n°".$IdFacture.", Le solde de la carte n°".$Carte->Id." 
                    est passé de ".$SoldeP." à ".$SoldeSuiv. " Valeur du Bon=".$Montant;
                    $this->HistoriqueBonAchat->AddToJournal($Ope,$Note);
                    if ($Hist->Supprimer() == false){
                        $Ope="MISE A JOUR FACTURE AVEC BON ACHAT";
                        $Note="Impossible de supprimer l'historique de ligne d'achat n°".$Hist->Id." de la carte n°".$Carte->Id;
                        $this->HistoriqueBonAchat->AddToJournal($Ope,$Note);
                    }
                    return true;
                }
            }
            return false;
        }

        public function Nom(): string
        {
            return "BON D'ACHAT HYPERMARCHE EXCLUSIVE";
        }

        public function Description(): string
        {
            return "BON D'ACHAT DU RESEAU HYPERMARCHE EXCLUSIVE SENEGAL";
        }

        public function LastError(): string
        {
            $ErrTx="";
            if ($this->lastError !==""){
                $ErrTx= $this->lastError;
                $this->lastError="";
            }
            return $ErrTx;
        }

        public function HandleModuleName(): string
        {
            return self::MODULE_NAME;
        }
      
        public function GetDetailFacture(int $IdFacture): array
        {
            if (self::$CONFIG->ServeurOriginal==1){
                //Nous alons demander á l'original
                $Reponse=$this->Main::$CURL->EnvoieRequette(self::$API_URL,$_REQUEST,[],"");
                $Rep=json_decode($Reponse);
                try{
                    if (property_exists($Rep,'OK')){
                        $Err=new xErreur;
                        $Err=$Rep;
                        echo json_encode($Err);
                        //return false;
                        exit ;
                    }
                }catch(Exception $ex){

                }
                if(is_array($Rep)){
                    return $Rep;
                }
                echo $Reponse;                
                return $Rep;
            }

            $Rep=[];
            $Hist=new xHistoriqueBonAchat($this->Main);
            $Lst=$Hist->ChargeListe("IdFacture=".$IdFacture);
            if ($Lst->num_rows){
                $rw=$Lst->fetch_assoc();
                $Rep=$rw;
            }
            return $Rep;
        }

        public function LoadApiConfigFromFile(){
            if (!isset(self::$RemoteNAbySy)){
                //On Récupère la configuration dans un fichier s'il existe
                $FichierConfig=$this->Main->CurrentFolder(true).self::MODULE_NAME.'-parametre.json';
                if (!file_exists($FichierConfig)){
                    //On le crée
                    $Config='{
                    "Connexion": {
                        "Serveur":"'.'https://hypermarcheexclusive.com/bonachat/'.'",
                        "bonachat_action":"'."bonachat_action.php".'",
                        "api_user":"'."hypermar_pharmcp".'",
                        "api_pwd":"'."pharmcp2022".'",
                        "Active":"'."true".'",
                        "MasterDB":"'."hypermar_nabysygs".'"
                        },
                    "Module": {
                        "Nom":"'.self::MODULE_NAME." pour ".$this->Main->MODULE->Nom.'",
                        "MCP_CLIENT":"'.$this->Main->MODULE->MCP_CLIENT.'",
                        "MCP_ADRESSECLT":"'.$this->Main->MODULE->MCP_ADRESSECLT.'",
                        "MCP_TELCLT":"'.$this->Main->MODULE->MCP_TELCLT.'"
                        },
                    "ServeurOriginal":"1",
                    "DebugMode":"true"
                    }';
                    try {
                        $F= fopen($FichierConfig, 'w');			
                        $TxT=$Config ;
                        $TxT .="\r\n" ;				
                        fputs($F, $TxT);
                        fclose($F);
                    }catch(Exception $e){
                        $this->Main::$Log->Write('Erreur création du fichier de configuration du module '.self::MODULE_NAME.': '.$e->getMessage());
                        //echo 'Erreur création du fichier de configuration du module '.self::MODULE_NAME.': '.$e->getMessage();
                    }
                }                
			
                //On récupere la configuration
                $string = file_get_contents($FichierConfig);                
                $Parametre = json_decode($string, false);
                //var_dump($Parametre);
                if (isset($Parametre)){
                    if (is_object($Parametre)){
                        self::$CONFIG=$Parametre; 
                        return true;
                    }
                }else{
                    //On est sur le serveur original de gestion des Bon d'Achat
                    $Config='{
                        "Connexion": {
                            "Serveur":"'.'https://hypermarcheexclusive.com/bonachat/'.'",
                            "bonachat_action":"'."bonachat_action.php".'",
                            "api_user":"'."hypermar_pharmcp".'",
                            "api_pwd":"'."pharmcp2022".'",
                            "Active":"'."true".'",
                            "MasterDB":"'."hypermar_nabysygs".'"
                            },
                        "Module": {
                            "Nom":"'.self::MODULE_NAME." pour ".$this->Main->MODULE->Nom.'",
                            "MCP_CLIENT":"'.$this->Main->MODULE->MCP_CLIENT.'",
                            "MCP_ADRESSECLT":"'.$this->Main->MODULE->MCP_ADRESSECLT.'",
                            "MCP_TELCLT":"'.$this->Main->MODULE->MCP_TELCLT.'"
                            },
                        "ServeurOriginal":"1",
                        "DebugMode":"true"
                        }';
                    $Parametre = json_decode($Config, false);
                    
                    if (isset($Parametre)){
                        if (is_object($Parametre)){
                            self::$CONFIG=$Parametre;                             
                            return true;
                        }
                    }
                }
            }
            return true;            
        }
    
}
?>