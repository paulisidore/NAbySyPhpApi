<?php
    namespace  NAbySy\MethodePaiement ;

use Exception;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
 * Module de Gestion des Modes de Paiement
 */
    class xMethodePaie extends xORMHelper{
        public function __construct(xNAbySyGS $NAbySyGS,?int $IdMethode=null,$AutoCreateTable=true,$TableName='methodepaie'){
            parent::__construct($NAbySyGS,$IdMethode,$AutoCreateTable,$TableName) ;
            $this->GenerateNewMethodeEspece();
        }

        private function GenerateNewMethodeEspece():bool{
            if (!$this->TableIsEmpty()){
                //$this->AddToJournal("DEBUG","Les méthodes de paiement existe déjà.");
                return true;
            }
            $this->FlushMeToDB();
            $this->AddToJournal("DEBUG","Création de la méthode de paiement Espèce.");
            $this->Nom = "Espece";
            $this->Description = "Paiement en Espèce";
            $this->Active = true;
            return $this->Enregistrer();
        }

        /**
         * Crée une nouvelle méthode de paiement ou met à jour le Handle d'une méthode exisante.
         * @param string $NomMethode | Nom de la méthode. Exemple Wave, Orange Money, Nita, Amana ...
         * @param string $Description | DEscription de la méthode de paiement
         * @param string $ModulePaiementHandle | Un identidiant unique permettant d'ouvrir un module pour valider un paiement
         * @return null|xMethodePaie 
         * @throws Exception 
         */
        public static function CreateMethode(string $NomMethode, string $Description ="", string $ModulePaiementHandle=""):?xMethodePaie{
            
            $Meth = new xMethodePaie(self::$xMain);

            if(self::MethodeExiste($NomMethode)){
                $Meth = self::GetMethodeByName($NomMethode);
                if($ModulePaiementHandle !== ''){
                    if(trim($ModulePaiementHandle) !== trim($Meth->ModulePaiementHandle)){
                        //On a mit a jour le Handle
                        $Meth->AddToJournal("DEBUG","Mise à jour du Hanlde de la méthode de paiement ".$NomMethode.".");
                        $Meth->ModulePaiementHandle = trim($ModulePaiementHandle) ;
                        $Meth->Enregistrer();
                    }
                }
                return $Meth;
            }

            $Meth->AddToJournal("DEBUG","Création de la méthode de paiement ".$NomMethode.".");
            $Meth->Nom = trim($NomMethode);
            $Meth->Description = trim($Description);
            $Meth->ModulePaiementHandle = trim($ModulePaiementHandle) ;
            $Meth->Active = true;
            if($Meth->Enregistrer()){
                return $Meth;
            }
            return null;
        }

        /**
         * Véfirie la présence d'une méthode de paiement
         */
        public static function MethodeExiste(string $NomMethode):bool{
            //On vérifie si le nom existe déja
            $Meth = new xMethodePaie(self::$xMain);
            if($Meth->TableIsEmpty()){
                return false;
            }
            if (!$Meth->ChampsExisteInTable("Nom")){
                return false;
            }
            if ($NomMethode==''){
                return false;
            }
            
            $Lst=$Meth->ChargeListe("Nom like '".$NomMethode."' ");
            if ($Lst){
                if ($Lst->num_rows>0){
                    return true;
                }
            }
            return false;
        }

        /**
         * Retourne une Méthode de paiement à partir de son Nom
         */
        public static function GetMethodeByName(string $NomMethode):?xMethodePaie{
            if ($NomMethode==''){
                return null;
            }
            $nMethode=null;
            $Meth = new xMethodePaie(self::$xMain);
            if($Meth->TableIsEmpty()){
                return null;
            }
            $Lst=$Meth->ChargeListe("Nom like '".$NomMethode."' ");
            if ($Lst){
                if ($Lst->num_rows>0){
                    $rw=$Lst->fetch_assoc();
                    $nMethode=new xMethodePaie(self::$xMain,$rw['ID'],$Meth->AutoCreate);
                }
            }
            return $nMethode;
        }
    }

?>