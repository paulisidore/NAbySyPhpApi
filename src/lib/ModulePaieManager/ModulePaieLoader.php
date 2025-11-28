<?php
namespace NAbySy\Lib\ModulePaie ;

use NAbySy\MethodePaiement\xMethodePaie;
use NAbySy\xNAbySyGS;

/**
 * Permet de charger tous les modules de paiements du dossier Modules
 */

 class PaiementModuleLoader{

    /**
     * Liste des Modules de Paiements
     * @var IModulePaieManager[]
     */
    public static $ListeModule=[] ;
    public static xNAbySyGS $Main ;
    public static string $DossierModulePaie ;

    public function __construct(xNAbySyGS $NAbySy){
        self::$Main=$NAbySy;
        self::$DossierModulePaie =__DIR__ . "/ModulesPaiement";
        self::LoadModulePaiement();
    }

    public static function LoadModulePaiement(){

        $RepWork=self::$DossierModulePaie ;
        if (!is_dir($RepWork)){
            self::$Main::$Log->Write("Création du dossier ".$RepWork);
            mkdir($RepWork,0777,true);
            if (is_dir($RepWork)){
                self::$Main::$Log->Write("Dossier ".$RepWork." crée !");
            }else{
                self::$Main::$Log->Write("Impossible de créer le dossier ".$RepWork." !");
            }
        }
       
        $ListeR= self::GetListeDossier() ;
        //self::$Main::$Log->Write( json_encode($ListeR));
        $LstObs=[] ;

        $AutoLoad=new \NAbySy\AutoLoad\xAutoLoad(self::$Main,"ModulesPaiement",__DIR__);
        $AutoLoad->Register($LstObs,1) ;
        $ListeR = $AutoLoad->ListeModule;
        //var_dump($ListeR);
        //echo "Ligne ".__LINE__."</br>";
        //var_dump(__NAMESPACE__ ) ;
        // $nameS= __NAMESPACE__ ;
        /*          $VMod=new xNAbySyWaveNetwork (self::$Main);
         self::$ListeModule[]=$VMod ;
         self::$Main::$ListeModulePaiement=self::$ListeModule ; */
        //var_dump($VMod);
        //return;
        foreach ($ListeR as $methodePaie){
            //var_dump($methodePaie);
            //include_once $methodePaie[1]."/".$methodePaie[0].".class.php";
            $ModName="NAbySy\Lib\ModulePaie\\".$methodePaie[0] ;
            $Mod=new $ModName(self::$Main);
            self::$ListeModule[]=$Mod ;
            self::$Main::$ListeModulePaiement=self::$ListeModule ;

            if($Mod instanceof IModulePaieManager){
                //Si l'alias du Module n'existe pas dans la liste des Méthode de PAiement on le rajoute
                if(!xMethodePaie::MethodeExiste($Mod->UIName())){
                    self::$Main::$Log->Write("Ajout du module de paiement ".$Mod->UIName()." Comme méthode de paiement.");
                    $nMeth = xMethodePaie::CreateMethode($Mod->UIName(), $Mod->Description(), $Mod->HandleModuleName());
                    if($nMeth->Logo == '' && trim($Mod->LogoURL()) !== ''){
                        //On va mettre à jour le logo
                        $nMeth->Logo = $Mod->LogoURL();
                    }
                    if($nMeth->API_DISPONIBLE !== $Mod->Api_Disponible()){
                        $nMeth->API_DISPONIBLE = $Mod->Api_Disponible();
                    }
                    $nMeth->Enregistrer();
                }else{
                    //La méthode existe pour le module, on va essayer de faire certaines mise a jour
                    $nMeth = xMethodePaie::GetMethodeByName($Mod->UIName());
                }
                if($nMeth){
                    if($nMeth->Logo == '' && trim($Mod->LogoURL()) !== ''){
                        //On va mettre à jour le logo
                        $nMeth->Logo = $Mod->LogoURL();
                    }
                    if($nMeth->API_DISPONIBLE !== $Mod->Api_Disponible()){
                        $nMeth->API_DISPONIBLE = $Mod->Api_Disponible();
                    }
                    if($nMeth->API_ENDPOINT !== $Mod->Api_EndPoint()){
                        $nMeth->API_ENDPOINT = $Mod->Api_EndPoint();
                    }
                    $nMeth->Enregistrer();
                }
            }
        }
        //self::$Main::$Log->Write( count(self::$ListeModule) . " module(s) de paiement inscrit(s) sur la plate-forme.");

    }

    /**
     * Retourne la liste des dossiers Modules présents dans module de paiement
     */
    public static function GetListeDossier():array{
        $Liste=[];
        $scan = scandir(self::$DossierModulePaie);
        foreach($scan as $dossier) {            
            if (is_dir(self::$DossierModulePaie . "/".$dossier)) {
                if ($dossier !="." && $dossier !=".."){
                    $FichierModule=self::$DossierModulePaie . "/".$dossier."/".$dossier.".class.php";
                    if (is_file($FichierModule)){
                        $Liste[]=$dossier ;
                    }                    
                }                
            }
        }
        return $Liste;
    }

 }



?>