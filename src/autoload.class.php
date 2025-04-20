<?php
    namespace NAbySy\AutoLoad ;
    use Exception;
use NAbySy\xNAbySyGS;

    /**
     * Chargeur de Module pour NAbySY RH & RS.
     * Auteur: P&A Machinerie (dev@groupe-pam.net)
     * Crée le 23/05/2022
     */
    class xAutoLoad implements IAutoLoad{

        public xNAbySyGS $Main ;
        public $Categorie ;
        public $ModuleFolder ;
        public array $ListeModule=[] ;
        public array $ListeDossier=[] ;
        public int $DebugLevel=0 ;
        public int $NbModule=0 ;
        public array $ListeObservation=[] ;

        /**Chargeur de Module NAbySy RH-RS
         * @param xNAbySyGS $N : Objet principal NAbySyGS
         * @param String $Categorie : Le Nom de la categorie du Module. La categorie represente le dossier d'installation des modules de cette catégorie
         */
        public function __construct(xNAbySyGS $NAbySy,$Categorie,$RepertoirParent=null){
            $this->Main = $NAbySy ;
            $this->Categorie = $Categorie ;
            $this->ModuleFolder=$Categorie ;
            if (isset($RepertoirParent)){
                $this->ModuleFolder =$RepertoirParent.DIRECTORY_SEPARATOR.$this->ModuleFolder ;
            }
            $this->ModuleFolder=str_replace('\\', DIRECTORY_SEPARATOR, $this->ModuleFolder) ;
            
            
        }

        public function Register($ListeObserv=null, int $DebugLev=0){
            if (isset($ListeObserv)){
                $this->ListeObservation=$ListeObserv ;
            }
            $this->DebugLevel=$DebugLev ;
            $this->GetListeDossier() ;

            try{
                spl_autoload_register(array($this,'Load'));                
            }catch(Exception $ex){
                var_dump($ex->getMessage());
            }
        }

        public function Load($ClassName):bool{            
            if ($this->NbModule==0){
                if ($this->DebugLevel>1){
                    foreach ( $this->ListeObservation as $ClassToObserv){
                        if ($ClassToObserv == $ClassName){
                            echo $ClassName.' : AutoLoad NbModule = 0 dans '.$this->ModuleFolder.'</br>' ;
                        }
                    }                   
                }
                return false ;
            }
            if (class_exists($ClassName,false)){
                return false ;
            }
            //var_dump($ClassName.'</br>') ;

            try{
                
                $Nb=-1;
                $Lst=explode('\\', $ClassName);
                if ($Lst){
                    $Nb=count($Lst) -1 ;
                }
                if ($Nb<0){
                    return false ;
                }
                $vDos=$Lst[$Nb] ;

                if (strlen($vDos)==1){
                    return false ;
                }

                if (class_exists($vDos,false)){
                    return false ;
                }

                if ($this->DebugLevel>1){
                    foreach ( $this->ListeObservation as $ClassToObserv){
                        if ($ClassToObserv == $vDos){
                            echo $ClassName.' : AutoLoad dans '.$this->ModuleFolder.'</br>' ;
                        }
                    }                   
                }
                
                if ($this->DebugLevel){
                    foreach ( $this->ListeObservation as $ClassToObserv){
                        if ($ClassToObserv == $vDos){
                            if ($this->DebugLevel>1){
                                echo '<h6>'.$vDos.'</h6>Recherche de la class dans le dossier '.$this->ModuleFolder.'</br>' ;
                            }
                        }
                    }
                }
                //var_dump($this->ListeDossier);
                foreach ($this->ListeDossier as $nClass){
                    //var_dump($nClass);
                    //print_r($vDos) ;

                    if ($vDos==$nClass[0]){
                        if ($this->DebugLevel){
                            foreach ( $this->ListeObservation as $ClassToObserv){
                                if ($ClassToObserv == $vDos){
                                    echo '<h5>'.$vDos.' trouvée dans '.$nClass[1].'</h5>' ;
                                }
                            }
                        }
                        //Chargement
                        //var_dump(__NAMESPACE__.'</br>') ;
                        $fichier=$nClass[1].DIRECTORY_SEPARATOR.$nClass[0].'.class.php' ;
                        if ($this->DebugLevel){
                            foreach ( $this->ListeObservation as $ClassToObserv){
                                if ($ClassToObserv == $vDos){
                                    if ($this->DebugLevel>1){
                                        echo '<h5>'.$ClassName.': Chargement de '.$fichier.'</h5>' ;
                                        if (!file_exists($fichier)){
                                            echo 'Le '.$fichier.' n\'existe pas</br>' ;
                                        }else{
                                            echo 'Le fichier '.$fichier.' existe :)</br>' ;
                                        }
                                    }                                    
                                }
                            }
                        }
                        if (file_exists($fichier)){
                            include_once $fichier;
                        }

                        return true ; 
                        
                    }                  
                }
                if ($this->DebugLevel){  
                    foreach ( $this->ListeObservation as $ClassToObserv){
                        if ($ClassToObserv == $vDos){
                            if ($this->DebugLevel>1){
                                echo $vDos.'Impossible de trouver '.$vDos.'</br>' ;
                            }                             
                        }
                    }
                }
                return false ;
                             
            }catch (\Exception $ex){
                if ($this->Main->ActiveDebug){
                    $this->Main::$Log->Write('AutoLoad '.$this->ModuleFolder.' Error;'.$ex->getMessage());
                }
            }
            if ($this->DebugLevel){
                echo 'AutoLoad Terminé dans '.$this->ModuleFolder.'</br>' ;
            }
            return false ;
            
        }

        /**
         * Retourne la liste des Dossiers sous forme de Tableau de liste a 2 dimensions.
         * @param string $rep Chemin d'accès au dossier.
         * @return array Tableau de resultat sour la forme Mod[x] ou x<=1.
         *  
         * Exemple d'un element du Tableau Mod: Mod[0] = Le Nom du Dossier (Nom du Module) , Mod[1] = Le chemin d'accès complet du dossier du module
         */
        public function GetListeDossier($rep=null): array  
        {  
            if (!isset($rep)){
                $rep=$this->ModuleFolder ;
            }
            $rep=str_replace('\\', DIRECTORY_SEPARATOR, $rep) ;
            //$rep="./main/rh/zoneaffectation" ;
            $this->ListeDossier=[] ;
            if ($this->DebugLevel>1){
                echo 'Repertoire '.$rep.' ? ' ;
            }            
            if($this->Main::IsDirectory($rep)){  
                if ($this->DebugLevel>1){
                    echo 'OUI</br>' ;
                }
                if($iteration = opendir($rep)){  
                    
                    while(($dos = readdir($iteration)) !== false)  
                    {  
                        if($dos != "." && $dos != ".." && $dos != "Thumbs.db")  
                        {  
                            $pathfile=$rep.DIRECTORY_SEPARATOR.$dos ;
                            if ($this->DebugLevel>1){
                                echo 'Repertoire Module '.$pathfile.' ? ' ;
                            }
                            if (is_dir($pathfile)){
                                $this->NbModule ++;
                                if ($this->DebugLevel>1){
                                    echo 'Module trouvé: '.$dos.'</br>' ;
                                }
                                //Repertoir nom de module
                                if ($this->DebugLevel>1){
                                    echo 'OUI</br>' ;
                                }
                                $Mod=[];
                                $Mod[0]=$dos ;
                                $Mod[1]=$pathfile ;
                                $this->ListeDossier[]=$Mod ;
                                
                            }else{
                                if ($this->DebugLevel>1){
                                    echo 'NON</br>' ;
                                }
                            }
                        }
                    }
                    closedir($iteration);  
                }  
            }else{
                if ($this->DebugLevel>1){
                    echo 'NON</br>' ;
                }
            }
            $this->ListeModule=$this->ListeDossier ;
            return $this->ListeDossier ;
        } 


        

    }
?>