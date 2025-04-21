<?php
/*
 * (c) Paul Isidore A. NIAMIE <paul.isidore@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

 namespace NAbySy ;

use xNAbySyCustomListOf;

 include_once 'nabysy.php' ;

 /**
  * Gestion des Modules intégrés à NAbySyGS
  * 
  */
 class xGSModuleManager{
   public  static xNAbySyGS $Main ;
   public static xNAbySyCustomListOf $Categories  ; //List Of xGSModuleCategory
    
   public function __construct(xNAbySyGS $NAbySy){
      //Chargement de la liste des dossier catégories
      self::$Main = $NAbySy;
      $dossierGs= self::$Main::ModuleGSFolder() ;
      self::$Categories = new xNAbySyCustomListOf(xGSModuleCategory::class) ;
      $rep=scandir($dossierGs) ;
      if(count($rep)>0){
         foreach ($rep as $key => $value) {
            //On ne prend pas en compte les fichiers spéciaux . et ..
               echo "<br>Dossier : ".$dossierGs.$value." ? ".is_dir($dossierGs.$value)."</br>" ;
               if ($value != '.' && $value != '..' && is_dir($dossierGs.$value)){
                  $cat=new xGSModuleCategory( $value,  $dossierGs.$value.DIRECTORY_SEPARATOR) ;
                  self::$Categories[]=$cat ;
                  //Pour chaque catégorie on y ajoute la liste de ses modules
                  $repModule=scandir($cat->Dossier) ;
                  if(count($repModule)>0){
                     foreach ($repModule as $key => $value) {
                        if ($value != '.' && $value != '..' && !is_dir($cat->Dossier.$value)){
                           //C'est un fichier, on vérifie s'il s'agit d'un module NAbySyGS
                           $fichMod = $value."class.php" ;
                           if(file_exists($cat->Dossier.$fichMod)){
                              $className = str_replace(".class.php","",$cat->Dossier.$fichMod) ;
                              $module=new xGSModuleCategory( $className, $cat->Dossier.$fichMod) ;
                              $cat->Modules[]=$module ;
                           }
                        }
                     }
                  }
               }
         }
      }
   }

   public function __debugInfo() {
      var_dump(self::$Categories) ;
      $liste = array (self::$Categories ) ;
      $dossierGs= self::$Main::ModuleGSFolder() ;
      $rep=scandir($dossierGs) ;
      echo "<br>Dossier des modules NAbySyGS : ".$dossierGs."</br>" ;
      echo "<br>Cagtégorie NAbySyGS : ".count($rep)."</br>" ;
      echo "<br>Liste des Catégorie : ".var_dump($rep)."</br>" ;
      $liste = $rep;
      return $liste ;
   }

 }

 class xGSModuleCategory{
    public string $Nom ;
    public string $Dossier ;
    public array $Modules = [] ;
    public function __construct(string $Nom="", string $Dossier=""){
        $this->Nom = $Nom ;
        $this->Dossier = $Dossier ;
        $this->Modules = [] ;
    }
 }
?>