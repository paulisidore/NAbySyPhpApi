<?php
/**
 * @file ModelTemplate.class.php
 * Contains Generique Observer Class Module for NAbySyGS
 * Author: 
 * Mail: 
 * Date: {DATE}
 * Version: 1.0.0
 */
namespace NAbySy\OBSERVGEN ;

use Exception;
use NAbySy\OBSERVGEN\xObservGen;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

    class ModelTemplate extends \NAbySy\OBSERVGEN\xObservGen  {
        public array $ListeObservable=[];
        private bool $MyState=false ;

        public string $Nom="" ;

        private array $TableauObservable=[
            'xModelTable'.xORMHelper::EVENTS_EDIT,
            'xModelTable'.xORMHelper::EVENTS_ADD, 
            'xModelTable'.xORMHelper::EVENTS_DELETE,
            'xModelTable'.xORMHelper::EVENTS_BEFORE_ADD,
            'xModelTable'.xORMHelper::EVENTS_BEFORE_EDIT, 
            'xModelTable'.xORMHelper::EVENTS_BEFORE_DELETE
        ] ;

        public function __construct(xNAbySyGS $NabySyGS, string $ObserveurName=null, array $ListeObservable=[]){
            $this->ListeObservable=$ListeObservable ;
            $ObserveurName='ModelTemplate' ;
            if(count($this->ListeObservable)==0){
                $this->ListeObservable=$this->TableauObservable ;
            }
            $this->Nom=$ObserveurName ;
            parent::__construct($NabySyGS,$ObserveurName, $this->ListeObservable);
            $this->MyState = true;
        }

        public function __invoke($arg = null)
        {
            echo "I'm live</br>" ;
        }

        public function RaiseEvent(string $ClassName,string $EventType,&$EventArg){
            if(!$this->MyState){
                return false;
            }
            /* L'Action a executer prit en charge par NAbySyGsPhpApi version < 1.3.0 */
            /** Action déjà executé avec Raise */
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
                if (strtolower($Observable) === strtolower($EventArg->EventType)){
                    // Handle the observable event
                    // Exemple d'évènement traité :
                    // if ($EventArg->EventType=="MATABLE_EDIT"){ // Je traite l'évènement de modification de ma table};
                    /**
                     * EVENT TRAITÉ ICI
                     * Vous pouvez faire ce que vous voulez ici, comme par exemple :
                     * - Modifier la base de données
                     * - Appeler une API externe
                     * - Envoyer un email
                     * - etc...
                     * N'oubliez pas de retourner une réponse si nécessaire, et de gérer la propagation de l'évènement si besoin.
                    */
                    switch ($EventArg->EventType) {
                        case 'xModelTable'.xORMHelper::EVENTS_BEFORE_ADD: //Invoked before create new record in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            //Do anything you want before Saving Information. Please do not use Save or Enregistrer on OrmObjet cause of Loop infinite
                            
                            if(isset($EventArg->ORMObject)){
                                $oOrm = $EventArg->ORMObject ;
                                //You can edit or replace any field information here
                                //Exemple:
                                //$oOrm->DateCreation=date('Y-m-d H:i:s');
                                //WARNING: NEVEVER CALL $oOrm->Save() or $oOrm->Enregistrer() from here cause of infinite loop
                            }
                            //If you want to stop Save Operation, you can write code like this:
                            //$Reponse->StopPropagation=true ;
                            //$Reponse->RaisonStopPropagation="We are not ok with you.";
                            
                            return $Reponse ;
                            break;

                        case 'xModelTable'.xORMHelper::EVENTS_ADD: //Invoked after save new record in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            return $Reponse ;
                            break;
                        
                        case 'xModelTable'.xORMHelper::EVENTS_BEFORE_EDIT: //Invoked before edit record in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            //Do anything you want before Saving Information. Please do not use Save or Enregistrer on OrmObjet cause of Loop infinite
                            
                            if(isset($EventArg->ORMObject)){
                                $oOrm = $EventArg->ORMObject ;
                                //You can edit or replace any field information here
                                //Exemple:
                                //$oOrm->DateEdited=date('Y-m-d H:i:s');
                                //WARNING: NEVEVER CALL $oOrm->Save() or $oOrm->Enregistrer() from here cause of infinite loop
                            }
                            //If you want to stop Save Operation, you can write code like this:
                            //$Reponse->StopPropagation=true ;
                            //$Reponse->RaisonStopPropagation="We are not ok with you.";
                            
                            return $Reponse ;
                            break;

                        case 'xModelTable'.xORMHelper::EVENTS_EDIT: //Invoked after save edited record in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            return $Reponse ;
                            break;

                         case 'xModelTable'.xORMHelper::EVENTS_BEFORE_DELETE: //Invoked before deleterecord in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            //Do anything you want before Saving Information. Please do not use Delete() or Supprimer() on OrmObjet cause of Loop infinite
                            
                            if(isset($EventArg->ORMObject)){
                                $oOrm = $EventArg->ORMObject ;
                                //You can edit or replace any field information here
                                //Exemple:
                                //You can log information or call other API here
                                //WARNING: NEVEVER CALL $oOrm->Delete() or $oOrm->Supprimer() from here cause of infinite loop
                            }
                            //If you want to stop Save Operation, you can write code like this:
                            //$Reponse->StopPropagation=true ;
                            //$Reponse->RaisonStopPropagation="We are not ok with you.";
                            
                            return $Reponse ;
                            break;

                        case 'xModelTable'.xORMHelper::EVENTS_DELETE: //Invoked after record is deleted in database
                            # code...
                            $Reponse->ObjetSource = $EventArg->ORMObject ;
                            return $Reponse ;
                            break;

                        default:
                            # code...
                            break;
                    }
                    
                    break ;
                }elseif($Observable === "*"){
                    // Handle all events
                    // Par exemple, si $Observable est "*", alors il traitera tous les évènements.
                    /**
                     * EVENT TRAITÉ ICI
                     * Vous pouvez faire ce que vous voulez ici, comme par exemple :
                     * - Modifier la base de données
                     * - Appeler une API externe
                     * - Envoyer un email
                     * - etc...
                     * N'oubliez pas de retourner une réponse si nécessaire, et de gérer la propagation de l'évènement si besoin.
                     */
                    break ;
                }elseif(str_contains(strtolower($Observable),strtolower("*"))){
                    // Handle all events that match the pattern
                    // Par exemple, si $Observable est "*_ADD", alors il traitera tous les évènements qui se terminent par "_ADD"
                    $partern=str_replace("*","",$Observable) ;
                    if (str_contains(strtolower($EventArg->EventType),strtolower($partern))){
                        // Handle the observable event
                        /**
                         * EVENT TRAITÉ ICI
                         * Vous pouvez faire ce que vous voulez ici, comme par exemple :
                         * - Modifier la base de données
                         * - Appeler une API externe
                         * - Envoyer un email
                         * - etc...
                         * N'oubliez pas de retourner une réponse si nécessaire, et de gérer la propagation de l'évènement si besoin.
                         */
                        
                        break ;
                    }
                }
            }
            return $Reponse ;
        }

        public function State(?bool $NewState = null): bool {
            if (isset($NewState)){
                $this->MyState=$NewState ;
            }
            $Etat=$this->MyState;
            return $Etat ;
        }
    }
    

?>