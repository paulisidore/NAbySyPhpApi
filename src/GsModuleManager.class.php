<?php
/*
 * (c) Paul Isidore A. NIAMIE <paul.isidore@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

 namespace NAbySy ;

use N;

 include_once 'nabysy.php' ;

 /**
  * Gestion des Modules intégrée à NAbySyGS
  * 
  */
 class xGSModuleManager{
    public static array $Categories = [] ; //List Of xGSModuleCategory
    
    public function __construct(){
      //Chargement de la liste des dossier catégories
      $dossierGs= N::ModuleGSFolder() ;
      $rep=scandir($dossierGs) ;
      if(count($rep)>0){
         foreach ($rep as $key => $value) {
               if ($value != '.' && $value != '..' && is_dir($dossierGs.$value)){
                  $cat=new xGSModuleCategory() ;
                  $cat->Nom=$value ;
                  $cat->Dossier=$dossierGs.$value.DIRECTORY_SEPARATOR ;
                  self::$Categories[$value]=$cat ;
               }
         }
      }
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