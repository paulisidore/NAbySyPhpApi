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
      $dossierGs= self::$Main::ModuleGSFolder().DIRECTORY_SEPARATOR ;
      self::$Categories = new xNAbySyCustomListOf(xGSModuleCategory::class) ;
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
                                 $exp = explode(".class.",$value);
                                 if(count($exp)>0){
                                    $className = str_replace(".class.php","",$value) ;
                                    echo "<br>Module trouvé dans le sous Dossier cat: ".$className."</br>";
                                    $module=new xGSModuleCategory( $className, $vraieRepModule.$value) ;
                                    $cat->Modules[]=$module ;
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