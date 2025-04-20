<?php
    namespace NAbySy\OBSERVGEN ;

use Exception;
use NAbySy\AutoLoad\xAutoLoad;
use NAbySy\xNAbySyGS;

/**
 * Class Observateur
 * Cette class permet d'appeller des modules selon des évènements.
 * SIEGE_EDIT : Se déclanche lors de la modification des informations du Siège. 
 * 
 * SERVICE_ADD : Se déclanche lors de la modification d'un service.
 * 
 * DIRECTION_ADD : Se déclanche lors d'un ajout d'une nouvelle direction.
 * 
 * DIRECTION_EDIT : Se déclanche lors de la modification d'une direction.
 * 
 * SERVICE_ADD : Se déclanche lors de la modification d'un service.
 * 
 * SERVICE_EDIT : Se déclanche lors de la modification d'un Service.
 * 
 * MVT_AFFECTATION : Se déclanche à chaque modification des affectations.
 */
    class xObservGen implements IOBSERVGEN {
        public $ListeObservable=[];
        private bool $MyState=false ;

        public ?xNAbySyGS $Main = null ;

        public $Nom="" ;

        public function __construct(xNAbySyGS $NabySyGS,$ObserveurName=null,$ListeObservable=[]){
            $this->ListeObservable=$ListeObservable ;
            $this->Nom=$ObserveurName ;
            //var_dump(__CLASS__);
            if (isset($NabySyGS)){
                $this->Main=$NabySyGS ;
                foreach ($NabySyGS::$ListeModuleAutoLoader as $AutoLoad ){
                    foreach ($AutoLoad->ListeModule as $Mod){
                        //var_dump(method_exists($Mod[0],'RaiseEvent')) ;
                        if (__CLASS__ == $Mod[0]){
                            if ($Mod->Nom==$this->Nom){
                                //L'evenement du meme nom existe déjà
                                throw new Exception('Il existe déjà un évènement '.$Mod[0].' au nom '.$this->Nom.' !');
                                return ;
                            }
                        }                        		
                    }
                }

                //On ajoute dans la liste des Observateurs ;
                $NabySyGS::AddToObserveurListe($this);
            }
            

        }

        public function __invoke($arg = null)
        {
            echo "Oui je suis observateur !!!</br>" ;
        }
        public function RaiseEvent($ClassName,$EventType,&$EventArg){
            /* L'Action a executer */
            var_dump("* L'Action a executer Ici *") ;
            //$this->Main::RaiseEvent(get_class($this),$EventArg);
        }

        public function State(?bool $NewState = null): bool
        {            
            if (isset($NewState)){
                $this->MyState=$NewState ;
            }
            $Etat=$this->MyState;
            return $Etat ;
        }
    }

?>