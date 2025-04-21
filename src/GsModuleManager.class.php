<?php
/*
 * (c) Paul Isidore A. NIAMIE <paul.isidore@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

 namespace NAbySy ;

use Exception;
use Throwable;
use xNAbySyCustomListOf;

 include_once 'nabysy.php' ;

 /**
  * Gestion des Modules intégrés à NAbySyGS
  * 
  */
 class xGSModuleManager{
   /**
    * Active le debbuguage dans le fichier log de l'application hôte
    * @var bool
    */
   public static bool $DebugToLog = false ;

   public static xNAbySyGS $Main ;
   public static xNAbySyCustomListOf $Categories  ; //List Of xGSModuleCategory
   public static xNAbySyCustomListOf $CategoriesHote  ; //List Of xGSModuleCategory on Host App

   public function __construct(xNAbySyGS $NAbySy){
      //Chargement de la liste des dossier catégories
      self::$Main = $NAbySy;
      $dossierGs= self::$Main::ModuleGSFolder().DIRECTORY_SEPARATOR ;
      self::$Categories = new xNAbySyCustomListOf(xGSModuleCategory::class) ;
      self::$CategoriesHote = new xNAbySyCustomListOf(xGSModuleCategory::class) ;

      $rep=scandir($dossierGs) ;
      if(count($rep)>0){
         foreach ($rep as $key => $value) {
            //On ne prend pas en compte les fichiers spéciaux . et ..
               //echo "<br>Dossier : ".$dossierGs.$value." ? ".is_dir($dossierGs.$value)."</br>" ;
               if ($value != '.' && $value != '..' && is_dir($dossierGs.$value)){
                  $cat=new xGSModuleCategory( $value,  $dossierGs.$value.DIRECTORY_SEPARATOR) ;
                  
                  //Pour chaque catégorie on y ajoute la liste de ses modules
                  $repModule=scandir($cat->Dossier) ;
                  if(count($repModule)>0){
                     foreach ($repModule as $key => $value) {
                        $dos_cat = $cat->Dossier.$value ;
                        //echo "<br>Dossier cat: ".$dos_cat."</br>";
                        if ($value != '.' && $value != '..' && is_dir($dos_cat)){
                           $vraieRepModule = $dos_cat ;
                           $lstMod=scandir($vraieRepModule) ;
                           //echo "<br>Liste sous Dossier cat: ".var_dump($lstMod)."</br>";
                           if(count($lstMod)){
                              foreach ($lstMod as $key => $value) {
                                 //C'est un fichier, on vérifie s'il s'agit d'un module NAbySyGS
                                 if ($value != '.' && $value != '..'){
                                    $exp = explode(".class.",$value);
                                    if(count($exp)>0){
                                       $className = str_replace(".class.php","",$value) ;
                                       //echo "<br>Module trouvé dans le sous Dossier cat: ".$className."</br>";
                                       $module=new xGSModuleCategory( $className, $vraieRepModule.$value) ;
                                       $cat->Modules[]=$module ;
                                    }
                                 }
                              }
                           }
                           
                        }else{
                           //Fichier d'action peut être
                        }
                     }
                  }
                  self::$Categories[]=$cat ;
               }
         }
      }
   }

   /**
    * Crée et génère automatiquement une catégorie NAbySyGS
    * @param string $NomCategorie 
    * @param bool $CreateApiAction | Si Vrai, le fichier action sera crée automatiquement pour cette catégorie.
    * @param bool $CreateORMClass | Si Vrai, une class xORM sera crée automatiquement avec le nom de la catégorie
    * @param string $Table | Si la création de l'ORM est activé, ce paramètre déterminera le nom de l'objet NAbySyGS
    * @return bool 
    * @throws Exception 
    * @throws Throwable 
    */
   public static function CreateCategorie(string $NomCategorie, bool $CreateApiAction = true, bool $CreateORMClass = true, ?string $Table=null){
      $DossierGS = self::$Main::CurrentFolder(true)."gs".DIRECTORY_SEPARATOR ;
      $DossierCateg = $DossierGS.DIRECTORY_SEPARATOR . $NomCategorie ;

      $cat=new xGSModuleCategory( $NomCategorie,  $DossierCateg) ;
      $CanAdd=true;
      if(isset(self::$CategoriesHote)){
         foreach (self::$CategoriesHote as $key => $value) {
            if($value->Nom == $NomCategorie && $value->Dossier == $DossierCateg){
               $CanAdd=false;
               break;
            }
         }
      }
      if(!$CanAdd){
         return true;
         throw new Exception("La catégorie ".$NomCategorie." existe déjà dans le dossier ".$DossierCateg, ERR_SYSTEM);
      }

      try {
         if(!is_dir($DossierGS)){
            mkdir($DossierGS, 0777, true) ;
         }
      } catch (\Throwable $th) {
         throw $th;
      }
      $DossierCategorie = $DossierGS.$NomCategorie ;
      try {
         if(!is_dir($DossierCategorie)){
            mkdir($DossierCategorie, 0777, true) ;
         }
      } catch (\Throwable $th) {
         throw $th;
      }
      if ($CreateApiAction){
         //On va générer l'action depuis un template d'action
         $Rep=self::GenerateActionAPIFile($NomCategorie);
         if ($Rep){
            if($Rep instanceof xErreur){
               if($Rep->OK == 0){
                  return false;
               }
            }
         }
      }

      if($CreateORMClass && isset($Table) && trim($Table) !==''){
         $NomClass="x".strtoupper(substr($NomCategorie, 0, 1)).substr($NomCategorie, 1) ;
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("Création de la class xORMHelper ".$NomClass." dans le dossier ".$DossierCategorie . " pour la table ".$Table." en cour...") ;
         }
         $Rep = self::GenerateORMClass($NomClass, $DossierCategorie, trim($Table));
         if ($Rep){
            if($Rep->OK == 0){
               return false;
            }
         }
      }

      if (self::$Main::AddModuleGS($NomCategorie,true)){
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("La catégorie ".$NomCategorie." a été inscrit correctement dans les module à chargement dynamique.") ;
         }
         self::$CategoriesHote[]=$cat ;
         return true;
      }
      return false;
   }

   /**
    * Génère le fichier d'action qui prendra en charge les requettes HTTP de la catégorie
    * @param string $NomCategorie 
    * @return bool|xNotification 
    * @throws Throwable 
    * @throws Exception 
    */
   public static function GenerateActionAPIFile(string $NomCategorie):bool|xNotification{
      $Rep=new xNotification();
      $Rep->OK = 0;
      $DossierFinal=null ;
      $DossierGS = self::$Main::CurrentFolder(true)."gs".DIRECTORY_SEPARATOR.$NomCategorie.DIRECTORY_SEPARATOR ;
      try {
         if(!is_dir($DossierGS)){
            mkdir($DossierGS, 0777, true) ;
         }
      } catch (\Throwable $th) {
         throw $th;
      }
      if(self::$DebugToLog){
         self::$Main::$Log->AddToLog("Création de la catégorie ".$NomCategorie." dans le dossier ".$DossierGS." ...") ;
      }
      $DossierCategorie = $DossierGS ;
      try {
         if(!is_dir($DossierCategorie)){
            mkdir($DossierCategorie, 0777, true) ;
         }
         $DossierFinal = $DossierCategorie ;
      } catch (\Throwable $th) {
         throw $th;
      }
      if(!isset($DossierFinal)){
         throw new \Exception("Impossible de créer la catégorie ".$NomCategorie." pour le dossier ".$DossierCategorie) ;
         return false;
      }

      $fichier_action=$DossierCategorie.$NomCategorie."_action.php" ;
      if(file_exists($fichier_action)){
         return true ;
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("ERREUR: le fichier existe déjà: ".$fichier_action);
         }
         throw new \Exception("Erreur impossible de créer l'action pour l'api ".$NomCategorie.". Le fichier action existe déjà", 0);
      }
      if(self::$DebugToLog){
         self::$Main::$Log->AddToLog("Fichier Action en création... ".$fichier_action);
      }

      $templatePath =self::$Main::CurrentFolder() . 'templates/template_action.php';
      if(!file_exists($templatePath)){
         //Fichier template absent !
         throw new Exception("Impossible de trouver le fichier template ".$templatePath, ERR_FILE_SYSTEM);
      }
      $outputDir = $DossierFinal ;

      // Lire le contenu du template
      $template = null;
      try {
         $template = file_get_contents($templatePath);
      } catch (\Throwable $th) {
         //throw $th;
      }
      if(!isset($template)){
         //Fichier template vérroillé !
         throw new Exception("Impossible de lire le fichier template ".$templatePath.". Vérifier ces droits ", ERR_FILE_SYSTEM);
      }

      // Remplacer dynamiquement des morceaux
      $updated = str_replace([
         '{CATEGORIE}',
         'l{CATEGORIE}',
         'u{CATEGORIE}',
         '{DATE}',
      ], [
         $NomCategorie,
         strtolower($NomCategorie),
         strtoupper($NomCategorie),
         date('d/M/Y H:i:s'),
      ], $template);

      // Créer le dossier si nécessaire
      if (!is_dir($outputDir)) {
         mkdir($outputDir, 0777, true);
      }

      try {
         // Écrire dans un nouveau fichier
         file_put_contents($fichier_action, $updated);
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("Création du fichier d'action ".$fichier_action.".") ;
         }
      } catch (\Throwable $th) {
         throw $th;
      }
      $Rep->Source = $fichier_action ;
      $Rep->OK = 1;
      try {
         chmod($fichier_action, 0774);
         return $Rep;
      } catch (\Throwable $th) {
         $Rep->TxErreur='Attention: Impossible de modifier les droits sur le fichier '.$fichier_action.". Exception: ". $th->getMessage() ;
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog('Attention: Impossible de modifier les droits sur le fichier '.$fichier_action, $th->getMessage()) ;
         }
         return $Rep ;
      }
      return false;
   }

   /**
    * Crée un fichier de class NAbySyGS de type xORM dans le dossier catégorie spécifié
    * @param string $ClassName 
    * @param string $DossierCategorie 
    * @param string $Table | Nom de la table de la base de donnée associée à la class
    * @return bool|xNotification
    * @throws Throwable 
    */
   public static function GenerateORMClass(string $ClassName, string $DossierCategorie, string $Table):bool|xNotification{
      $Rep=new xNotification();
      $Rep->OK = 0;
      $DossierFinal=null ;
      try {
         if(!is_dir($DossierCategorie)){
            mkdir($DossierCategorie, 0777, true) ;
         }
         $DossMod = $DossierCategorie.DIRECTORY_SEPARATOR ;
         $DossMod = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR,$DossMod);
         $DossMod = $DossMod.$ClassName.DIRECTORY_SEPARATOR ;
         if(!is_dir($DossMod)){
            mkdir($DossMod, 0777, true) ;
         }
         $DossierFinal = $DossMod ;
      } catch (\Throwable $th) {
         throw $th;
      }
      if(!isset($DossierFinal)){
         throw new \Exception("Impossible de créer le module ".$ClassName." dans la catégorie ".$DossierFinal) ;
         return false;
      }

      $fichier_module=$DossierFinal.$ClassName.".class.php" ;
      if(file_exists($fichier_module)){
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("Attention le fichier ".$fichier_module." existe déjà.");
         }
         $Rep->OK=1;
         return $Rep;
         throw new \Exception("Erreur impossible de créer le module. Le fichier ".$fichier_module." existe déjà", 0);
      }

      $templatePath =self::$Main::CurrentFolder() . 'templates/'.N_TYPE_ORM.'/'.N_TYPE_ORM.'Template.class.php';
      $outputDir = $DossierFinal ;
      $newClassName = $ClassName;
      $newTableName = $Table;

      // Lire le contenu du template
      $template = file_get_contents($templatePath);

      // Remplacer dynamiquement des morceaux
      $updated = str_replace([
         'ModelTemplate',
         'ModelTable',
         '{DATE}',
      ], [
         $newClassName,
         $newTableName,
         date('d/M/Y H:i:s'),
      ], $template);

      // Créer le dossier si nécessaire
      if (!is_dir($outputDir)) {
         mkdir($outputDir, 0777, true);
      }

      try {
         // Écrire dans un nouveau fichier
         file_put_contents($fichier_module, $updated);
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog("Class xORMHelper ".$ClassName." générée dans le dossier ".$fichier_module) ;
         }
      } catch (\Throwable $th) {
         throw $th;
      }
      $Rep->Source = $fichier_module ;
      $Rep->OK = 1;
      try {
         chmod($fichier_module, 0774);
         return $Rep;
      } catch (\Throwable $th) {
         $Rep->TxErreur='Attention: Impossible de modifier les droits sur le fichier '.$fichier_module.". Exception: ". $th->getMessage() ;
         if(self::$DebugToLog){
            self::$Main::$Log->AddToLog('Attention: Impossible de modifier les droits sur le fichier '.$fichier_module, $th->getMessage()) ;
         }
         return $Rep ;
      }
      return false;
   }

   public function __debugInfo() {
      $liste = array (self::$Categories ) ;
      return $liste ;
   }

 }

 class xGSModuleCategory{
    public string $Nom ;
    public string $Dossier ;
    public xNAbySyCustomListOf $Modules  ;
    public function __construct(string $Nom="", string $Dossier=""){
        $this->Nom = $Nom ;
        $this->Dossier = $Dossier ;
        $this->Modules = new xNAbySyCustomListOf(xGSModuleCategory::class) ;
    }
    public function __debugInfo() {
      $Lst=[];
      if($this->Modules){
         foreach ($this->Modules as $Mod) {
            $Lst[$Mod->Nom] = $Mod->Dossier ;
         }
      }
      return array(
			'Nom' => $this->Nom,
			'Path' => $this->Dossier,
			'Modules' => $Lst
      );
   }
 }
?>