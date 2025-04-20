<?php
namespace NAbySy\Lib\ModuleExterne ;

use DateTime;
use NAbySy\Lib\ModuleExterne\OilStation\xProduitLiaison;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

include_once("xclass_structures.php");
/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 */
class xNAbySyGSOilMgr implements IModuleExterne {

    public static xNAbySyGS $xMain ;
    public xNAbySyGS $Main ;
    public static string $DossierModule ;
    public static $ListeModule=[] ;

    private static bool $_IsEnable = true;

    public const CATEGORIE_CARBURANT = "CARBURANT";
    public const CATEGORIE_LUBRIFIANT = "LUBRIFIANT";

    public const FAMILLE_CARBURANT = "Carburant";
    public const FAMILLE_LUBRIFIANT= "Lubrifiant";

    public const CARBURANT_ESSENCE = "Essence";
    public const CARBURANT_GASOIL = "Gasoil";
    public const CARBURANT_GAZ = "Gaz";
    public const CARBURANT_KEROZENE_JET_A1 = "JET-A1";
    
    /**
     * Liste des nom d'articles pour la liaison avec NAbySyGS
     * @var array
     */
    public static array $FamilleProduitStation=[];

       /**
     * Liste des produits de la sation par famille
     * ListePdtStation['CARBURANT']
     * ListePdtStation['LUBRIFIANT']
     * @var mixed
     */
    public static $ListePdtStation =null;


    public function __construct(\xNAbySyGS $NAbySy){
        self::$xMain=$NAbySy ;
        $this->Main = self::$xMain;
        self::$DossierModule = __DIR__ . "";
        self::LoadModule();
        if (count(self::$FamilleProduitStation) == 0){
            self::$FamilleProduitStation[]=self::FAMILLE_CARBURANT;
            self::$FamilleProduitStation[]=self::FAMILLE_LUBRIFIANT;
        }
        self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_ESSENCE;
        self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_GASOIL;
        self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_GAZ;
        self::$ListePdtStation['CARBURANT'][] = self::CARBURANT_KEROZENE_JET_A1;
        self::$ListePdtStation['LUBRIFIANT'] = []; //Les lubrifiants sont dynamic
        //Création des Catégories
        self::CreateFamilleProduitStation();
    }

    #region "Fonctions Obligatoires"
        public function setEnable(bool $Activer = true): bool { 
            self::$_IsEnable = $Activer;
            return self::$_IsEnable;
        }

        public function isEnable(): bool { return self::$_IsEnable; }

        public function haveUserInterface(): bool { return false; }

        public function getUserInterfaceUrl(): string { return "#"; }

        public function getUserInterfaceParam(): array { return [];}

        public function CanWorkOnModule(?array $ModuleName = null): array { 
            $liste=[];
            return $liste;
        }

        public function getAdminUserMinimumLevel(): int { return 4; }

        public function getUserMinimumLevel(): int { return 1; }

        public function getUserAdminInterfaceUrl(): string {return "#"; }

        public function getUserAdminInterfaceParam(): array { return []; }

        public function getModuleName(): string{
            return "Module de Gestion de Station Service et Lubrifiants";
        }

        public function getModuleDescription(): string{
            return "Gestion de Station Service et Lubrifiants";
        }
    #endregion

    public static function LoadModule(){

        $RepWork=self::$DossierModule ;
        if (!is_dir($RepWork)){
            self::$xMain::$Log->Write("Création du dossier ".$RepWork);
            mkdir($RepWork,0777,true);
            if (is_dir($RepWork)){
                self::$xMain::$Log->Write("Dossier ".$RepWork." crée !");
            }else{
                self::$xMain::$Log->Write("Impossible de créer le dossier ".$RepWork." !");
            }
        }
       
        $ListeR= self::GetListeDossier() ;
        //self::$xMain::$Log->Write( json_encode($ListeR));
        $LstObs=[] ;

        $AutoLoad=new \NAbySy\AutoLoad\xAutoLoad(self::$xMain,"",__DIR__);
        $AutoLoad->Register($LstObs,1) ;
        $ListeR = $AutoLoad->ListeModule;
        //echo "Ligne ".__LINE__."</br>";
        //var_dump(__NAMESPACE__ ) ;
        // $nameS= __NAMESPACE__ ;
/*          $VMod=new xNAbySyWaveNetwork (self::$Main);
         self::$ListeModule[]=$VMod ;
         self::$Main::$ListeModulePaiement=self::$ListeModule ; */
        //var_dump($VMod);
        //return;
        foreach ($ListeR as $OilStationModule){
            //var_dump($methodePaie);
            //include_once $methodePaie[1]."/".$methodePaie[0].".class.php";
            $ModName="NAbySy\Lib\ModuleExterne\OilStation\\".$OilStationModule[0] ;
            $Mod=new $ModName(self::$xMain);
            self::$ListeModule[]=$Mod ;
            self::$xMain::$ListeModulePaiement=self::$ListeModule ;
        }
        //self::$Main::$Log->Write( count(self::$ListeModule) . " module(s) de paiement inscrit(s) sur la plate-forme.");

    }

    /**
     * Retourne la liste des dossiers Modules présents dans module de paiement
     */
    public static function GetListeDossier():array{
        $Liste=[];
        $scan = scandir(self::$DossierModule);
        foreach($scan as $dossier) {            
            if (is_dir(self::$DossierModule . "/".$dossier)) {
                if ($dossier !="." && $dossier !=".."){
                    $FichierModule=self::$DossierModule . "/".$dossier."/".$dossier.".class.php";
                    if (is_file($FichierModule)){
                        $Liste[]=$dossier ;
                    }                    
                }                
            }
        }
        return $Liste;
    }

    /**
     * Retourne la Famille d'Hydrocarbure si elle existe.
     * @param mixed $NomFamille 
     * @return null|xORMHelper 
     */
    public static function GetFamillePdtStationByName($NomFamille):?xORMHelper{
        
        $Famille=new xORMHelper(self::$xMain,null,true,"famille");
        //foreach (self::$FamilleProduitStation as $FamilleTr){
            $Critere="Nom like '".$NomFamille."'";
            $Lst=$Famille->ChargeListe($Critere,null,null,null,"1");
            if ($Lst){
                if ($Lst->num_rows > 0){
                    $rw=$Lst->fetch_assoc();
                    $IdFamille= $rw['ID'];
                    $FTrouve=new xORMHelper(self::$xMain,$IdFamille,true,"famille");
                    return $FTrouve;
                }
            }
        //}
        return null;
    }

    /**
     * Créer les groupes de famille des produits d'hydrocarbure qui seront liée au module
     * @return bool 
     */
    public static function CreateFamilleProduitStation():bool{
        $Categ=new xORMHelper(self::$xMain,null,true,"famille");
        foreach (self::$FamilleProduitStation as $Famille){
            $Critere="Nom like '".$Famille."'";
            $Lst=$Categ->ChargeListe($Critere,null,null,null,"1");
            if ($Lst){
                if ($Lst->num_rows ==0){
                    $Categ->Nom = $Famille;
                    $Categ->Enregistrer();
                }
            }
        }
        return self::CreateProduitDefaut();
    }

    /**
     * Créer les articles par défaut pour le module d'hydrocarbure.
     * La lisaison est faite via le champ CODEBAR3 des articles NAbySyGS
     * @return bool 
     */
    public static function CreateProduitDefaut():bool{
        $PdtLiaison=new xProduitLiaison(self::$xMain);
        if(!$PdtLiaison->ChampsExisteInTable("CODEBAR3")){
            $PdtLiaison->MySQL->AlterTable($PdtLiaison->Table, "CODEBAR3");
        }
        $Categ=new xORMHelper(self::$xMain,null,true,"famille");

        foreach (self::$FamilleProduitStation as $NomFamille){
            $Critere="Nom like '".$NomFamille."'";
            $Lst=$Categ->ChargeListe($Critere,null,null,null,"1");
            if ($Lst){
                if ($Lst->num_rows){
                    while($rw = $Lst->fetch_assoc()){
                        $Famille=new xORMHelper(self::$xMain,(int)$rw['ID'],true,"famille");
                        if (strtolower($Famille->Nom) == strtolower('Carburant')){
                            foreach (self::$ListePdtStation['CARBURANT'] as $NomCarburant){
                                $LstP = $PdtLiaison->ChargeListe("CODEBAR3 like '".$NomCarburant."_".$Famille->Nom."' ");
                                if($LstP->num_rows==0){
                                    //On l'ajoute
                                    $PdtLiaison=new xProduitLiaison(self::$xMain);
                                    $Famille->AddToLog("Création de ".$NomCarburant);
                                    $PdtLiaison->Designation = $NomCarburant ;
                                    $PdtLiaison->CODEBAR3 = $NomCarburant."_".$Famille->Nom ;
                                    $PdtLiaison->IDFAMILLE = $Famille->Id;
                                    $PdtLiaison->Enregistrer();
                                }
                            }
                        }elseif (strtolower($Famille->Nom) == strtolower('Lubrifiant')){
                            foreach (self::$ListePdtStation['LUBRIFIANT'] as $NomLubrifiant){
                                $LstP = $PdtLiaison->ChargeListe("CODEBAR3 like '".$NomLubrifiant."_".$Famille->Nom."' ");
                                if($LstP->num_rows==0){
                                    //On l'ajoute
                                    $PdtLiaison=new xProduitLiaison(self::$xMain);
                                    $PdtLiaison->Designation = $NomLubrifiant ;
                                    $PdtLiaison->CODEBAR3 = $NomLubrifiant."_".$Famille->Nom ;
                                    $PdtLiaison->IDFAMILLE = $Famille->Id;
                                    $PdtLiaison->Enregistrer();
                                }
                            }
                        }
                    }
                    
                }
            }
        }
        return true;
    }

    public function GetStatistiqueCarburant(DateTime $DateDu, DateTime $DateFin){
        
    }

}

?>