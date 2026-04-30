<?php
    namespace NAbySy\OBSERVGEN ;

use Exception;
use NAbySy\AutoLoad\xAutoLoad;
use NAbySy\xNAbySyGS;
use xNotification;

/**
 * Class Observateur
 * Cette class permet d'appeller des modules selon des évènements.
 * Exemple d'évènements :
 * 
 * SIEGE_EDIT : Se déclanche lors d'une modification dans la table SIEGE. 
 * 
 * SERVICE_ADD : Se déclanche après la création d'enregistrement dans la table service.
 * 
 * DIRECTION_DELETE : Se déclanche lors d'une suppression d'un enregistrement dans la table DIRECTION.
 * 
 * \*_ADD : Se déclanche pour toute les actions d'ajout.
 * 
 * \*_EDIT : Se déclanche pour toute les actions de modification.
 * 
 * \*_DELETE : Se déclanche pour toute les actions de suppression.
 */
    class xObservGen implements IOBSERVGEN {
        public array $ListeObservable=[];
        private bool $MyState=false ;

        public ?xNAbySyGS $Main = null ;

        public string $Nom="" ;

        

        public function __construct(xNAbySyGS $NabySyGS, string $ObserveurName=null,array $ListeObservable=[]){
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
                                throw new Exception('Il existe déjà un évènement '.$Mod[0].' nommé '.$this->Nom.' !');
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
        public function RaiseEvent(string $ClassName,string $EventType,&$EventArg){
            if(!$this->MyState){
                return false;
            }
            /* L'Action a executer */
            var_dump("* L'Action a executer Ici *") ;
            return true ;
        }

        public function Raise(xEventArg $EventArg):xEventReponse {
            $Source=$EventArg->ORMObject ;
            if(!isset($Source)){
                $Source=$EventArg->OtherObject;
            }
            $Reponse=new xEventReponse(false,null, $Source);
            if(!$this->MyState){
                if($this->Main->ActiveDebug && $this->Main::$LogLevel>2){
                    $this->Main::$Log->Write("L'observateur ".$this->Nom." est inactif, il ne traitera pas l'évènement ".$EventArg->ClassName." : ".$EventArg->EventType." : ".json_encode($EventArg->ListeArgs)." !") ;
                }
                return $Reponse ;
            }
            foreach ($this->ListeObservable as $Observable){
                if (strtolower($Observable) ===strtolower($EventArg->EventType)){
                    // Handle the observable event
                    $Reponse->StopPropagation=true ;
                    $Reponse->RaisonStopPropagation="StopPropagation is set to TRUE by default in the base class of Observateur, because you haven't implemented the Raise method on your observer. ".json_encode(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2)) ;
                    break ;
                }elseif($Observable === "*"){
                    // Handle all events
                    $Reponse->StopPropagation=true ;
                    $Reponse->RaisonStopPropagation="StopPropagation is set to TRUE by default in the base class of Observateur, because you haven't implemented the Raise method on your observer. ".json_encode(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2)) ;
                    break ;
                }elseif(str_contains(strtolower($Observable),strtolower("*"))){
                    $partern=str_replace("*","",$Observable) ;
                    if (str_contains(strtolower($EventArg->EventType),strtolower($partern))){
                        // Handle the observable event
                        $Reponse->StopPropagation=true ;
                        $Reponse->RaisonStopPropagation="StopPropagation is set to TRUE by default in the base class of Observateur, because you haven't implemented the Raise method on your observer. ".json_encode(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2)) ;
                        break ;
                    }
                }
            }
            if(!$Reponse->StopPropagation){
                $this->Main::$LastEventSuccess[] = $Reponse ;
            }else{
                $this->Main::$LastEventError[] = $Reponse ;
            }
            return $Reponse ;
        }

        /**
         * Check if event can be raised on this Obsercer
         * @param string $EventType 
         * @return bool 
         */
        public function CanBeRaised(string $EventType):bool{
            if(!$this->State()){
                return false;
            }
            foreach ($this->ListeObservable as $Observable){
                if (strtolower($Observable) === strtolower($EventType)){
                    return true ;
                }elseif($Observable === "*"){
                    return true ;
                }elseif(str_contains(strtolower($Observable),strtolower("*"))){
                    $partern=str_replace("*","",$Observable) ;
                    if (str_contains(strtolower($EventType),strtolower($partern))){
                        return true ;
                    }
                }
            }
            return false ;
        }

        /**
         * Return event list
         * @return array 
         */
        public function GetAllEvents(): array {
            return $this->ListeObservable ;
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