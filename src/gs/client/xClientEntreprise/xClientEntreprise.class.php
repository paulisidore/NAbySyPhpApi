<?php
namespace NAbySy\GS\Client ;

use mysqli_result;
use NAbySy\Lib\BonAchat\Exclusive\xCarteBonAchatExclusive;
use NAbySy\Lib\BonAchat\xHistoriqueBonAchat;
use NAbySy\ORM\xORMHelper;
use xErreur;
use xNAbySyGS;

/**
 * Module de gestion des Entreprises clientes.
 * Ce module permet d'offrir des Bons d'Achats aux employés de l'entreprise dans le super marché
 */
    class xClientEntreprise extends xORMHelper{
        public xNAbySyGS $Main;
        public const ETAT_SUSPENDUE ='SUSPENDUE';

        /** Carte activée et utilisable sur la plate forme */
        public const ETAT_ACTIF ='ACTIF';
    
        /** Carte Bloquée */
        public const ETAT_BLOQUEE ='BLOQUEE';
        public function __construct(xNAbySyGS $NAbySy,?int $Id=null,$AutoCreateTable=false,$TableName="cliententreprise")
        {
            if ($TableName == ''){
                $TableName='cliententreprise';
            }
            parent::__construct($NAbySy,$Id,$AutoCreateTable,$TableName) ;

        }

        /**
         * Permet l'ajout d'une nouvelle carte de bon d'achat à l'entreprise
         */
        public function NewCarte(xCarteBonAchatExclusive $Carte):bool{
            if (!isset($Carte)){
                return false;
            }
            if($Carte->Id>0 && $Carte->IdEntreprise !== $this->Id){
                $EntrePrec=new xClientEntreprise($this->Main,$Carte->IdEntreprise);
                $Tache='CHANGEMENT ENTREPRISE BON ACHAT';
                $Note="La carte Id ".$Carte->Id.", Ref ".$Carte->REFCARTE." est passée de l'entreprise ".$EntrePrec->Nom." à ".$this->Nom;
                $Note .=". Le solde de la carte était de ".$Carte->Solde ;
                $this->AddToJournal($Tache,$Note);
            }
            if ($Carte->Id==0){
                $Carte->DejaUtilise=0;
            }
            $Carte->IdEntreprise=$this->Id;
            $Carte->NomEntreprise=$this->Nom;
            return $Carte->Enregistrer();
        }

        /**
         * Credite le solde de l'entreprise
         * @return int : Si l'opération s'est bien passée elle retourne le numéro d'enregistrement de la transaction
         */
        public function CrediterSolde(int $Montant,string $Libelle=null):int{
            if ($Montant==0){
                return false;
            }
            $SoldeP=$this->Solde;
            $NewSolde=$this->Solde + $Montant;
            $Tache='CREDIT SOLDE ENTREPRISE BON ACHAT';
            $Note="Le solde de (".$this->Id.") ".$this->Nom." est passée de ".$SoldeP." à ".$NewSolde;
            $this->Solde +=$Montant;
            if ($this->Enregistrer()){
                $this->AddToJournal($Tache,$Note);
                //Journalisation dans l'historique
                $Historique=new xHistoriqueBonAchat($this->Main);
                $Historique->DateOP=date("Y-m-d");
                $Historique->HeureOP=date("H:i:s");
                $Historique->IdEntreprise=$this->Id;
                $Historique->NomEntreprise=$this->Nom;
                $Historique->IdCarte=0;
                $Historique->SurCarte=0;
                $Historique->Operation='CREDIT SOLDE ENTREPRISE';
                $Historique->IsCredit=1;
                $Historique->Libelle="Solde entreprise crédité de ".$Montant." le ".$Historique->DateOP." à ".$Historique->HeureOP;
                if (isset($Libelle)){
                    $Historique->Libelle=$Libelle;
                }
                $Historique->Montant=$Montant;
                $Historique->IdUtilisateur=$this->Main->User->Id;
                $Historique->Login=$this->Main->User->Login;
                $Historique->PosteSaisie=$this->Main->NomPosteClient;
                $Historique->IdPosteSaisie=$this->Main->IdPosteClient;
                $Historique->SoldePrecedent=$SoldeP;
                $Historique->SoldeSuivant=$NewSolde;
                $Historique->Enregistrer();
                return $Historique->Id;
            }
            return false;
        }

        /**
         * Debite le solde de l'entreprise
         * @return int : Si l'opération s'est bien passée elle retourne le numéro d'enregistrement de la transaction
         */
        public function DebiterSolde(int $Montant, string $Libelle=null):int{
            if ($Montant==0){
                return false;
            }
            $SoldeP=$this->Solde;
            $NewSolde=$this->Solde - $Montant;
            $Tache='DEDIT SOLDE ENTREPRISE BON ACHAT';
            $Note="Le solde de (".$this->Id.") ".$this->Nom." est passée de ".$SoldeP." à ".$NewSolde;
            $this->Solde -=$Montant;
            if ($this->Enregistrer()){
                $this->AddToJournal($Tache,$Note);
                //Journalisation dans l'historique
                $Historique=new xHistoriqueBonAchat($this->Main);
                $Historique->DateOP=date("Y-m-d");
                $Historique->HeureOP=date("H:i:s");
                $Historique->IdEntreprise=$this->Id;
                $Historique->NomEntreprise=$this->Nom;
                $Historique->IdCarte=0;
                $Historique->SurCarte=0;
                $Historique->Operation='DEDIT SOLDE ENTREPRISE';
                $Historique->IsCredit=0;
                $Historique->Libelle="Solde entreprise débité de ".$Montant." le ".$Historique->DateOP." à ".$Historique->HeureOP;
                if (isset($Libelle)){
                    $Historique->Libelle=$Libelle;
                }
                $Historique->Montant=$Montant;
                $Historique->IdUtilisateur=$this->Main->User->Id;
                $Historique->Login=$this->Main->User->Login;
                $Historique->PosteSaisie=$this->Main->NomPosteClient;
                $Historique->IdPosteSaisie=$this->Main->IdPosteClient;
                $Historique->SoldePrecedent=$SoldeP;
                $Historique->SoldeSuivant=$NewSolde;
                $Historique->Enregistrer();
                return $Historique->Id;
            }
            return false;
        }

        /**
         * Permet de créditer un ensempble de carte appartenant à l'entreprise
         * Le solde de l' Entreprise sera débité automatiquement
         */
        public function CrediterCarte(int $Montant, array $ListeIdCarte, string $Libelle=null):xErreur{
            $Reponse=new xErreur;
            $Reponse->OK=0;
            $NbCarte=count($ListeIdCarte);

            //On vérifie si le solde de l'entreprise permet cette opération
            $TotalDepenser=$NbCarte*$Montant;
            if ($TotalDepenser > $this->Solde){
                $Reponse->TxErreur="Solde insuffisant pour effectuer cette opération.";
                return $Reponse;
            }

            if ($NbCarte==0){
                $Reponse->TxErreur="Aucune carte selectionnée.";
                return $Reponse;
            }
            if ($Montant==0){
                $Reponse->TxErreur="Montant non définit.";
                return $Reponse;
            }

            $Reponse->Extra=[];
            $Reponse->TxErreur=[];
            foreach($ListeIdCarte as $IdCarte){
                if((int)$IdCarte>0){
                    $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
                    if ($Carte->IdEntreprise==$this->Id && $Carte->Etat == $Carte::CARTE_ACTIF){
                        if ($this->Solde >= $Montant){
                            $OK[$Carte->Id]['REPONSE']="Erreur de mise à jour du solde de la carte.";
                            $IdHistorique=$Carte->CrediterSolde($Montant,$Libelle);
                            $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                            $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;
                            $OK[$Carte->Id]['IDTRANSACTION']=$IdHistorique;
                            if ($IdHistorique ){
                                $OK[$Carte->Id]['REPONSE']="Carte créditée correctement.";
                                $this->DebiterSolde($Montant);
                                $Reponse->OK=1;
                            }                           
                            $Reponse->Extra[] = $OK[$Carte->Id];
                        }else{
                            $Reponse->TxErreur="Solde insuffisant pour effectuer cette opération.";
                            return $Reponse;
                        }                        
                    }else{
                        $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." ne fait pas partie de la collection des cartes attribuées à l'entreprise ".$this->Nom." (Id-Entreprise: ".$this->Id.")";
                        if ($Carte->Etat !== $Carte::CARTE_ACTIF){
                            $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." inactif." ;
                        }
                        $OK[$Carte->Id]['IDTRANSACTION']=0;
                        $Reponse->TxErreur[] = $OK[$Carte->Id];
                    }
                }
            }

            return $Reponse;
        }
        
         /**
         * Permet de déditer un ensempble de carte appartenant à l'entreprise
         * Le Montant débité sera automatiquement rajouté au solde de l'entreprise
         */
        public function DebiterCarte(int $Montant, array $ListeIdCarte, string $Libelle=null):xErreur{
            $Reponse=new xErreur;
            $Reponse->OK=0;
            $NbCarte=count($ListeIdCarte);

            if ($NbCarte==0){
                $Reponse->TxErreur="Aucune carte selectionnée.";
                return $Reponse;
            }
            if ($Montant==0){
                $Reponse->TxErreur="Montant non définit.";
                return $Reponse;
            }
            $Reponse->Extra=[];
            $Reponse->TxErreur=[];
            foreach($ListeIdCarte as $IdCarte){
                if((int)$IdCarte>0){
                    $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
                    if ($Carte->IdEntreprise==$this->Id  && $Carte->Etat == $Carte::CARTE_ACTIF && $Carte->REFCARTE !=='' ){
                        $OK[$Carte->Id]['REPONSE']="Erreur de mise à jour du solde de la carte.";
                        $IdHistorique=$Carte->DebiterSolde($Montant,$Libelle);
                        $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                        $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;
                        $OK[$Carte->Id]['IDTRANSACTION']=$IdHistorique;
                        if ($IdHistorique ){
                            $OK[$Carte->Id]['REPONSE']="Carte débitée correctement.";
                            //$this->CrediterSolde($Montant,$Libelle);
                        }                           
                        $Reponse->Extra[] = $OK[$Carte->Id];
                    }else{
                        $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." ne fait pas partie de la collection des cartes attribuées à l'entreprise ".$this->Nom." (ID-Entreprise: ".$this->Id.")";
                        if ($Carte->Etat !== $Carte::CARTE_ACTIF){
                            $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." inactif." ;
                        }
                        $OK[$Carte->Id]['IDTRANSACTION']=0;
                        $Reponse->TxErreur[] = $OK[$Carte->Id];
                    }
                }
            }
            $Reponse->OK=1;
            return $Reponse;
        }

        /**
         * Retourne la liste des cartes de l'entreprise
         * @param string $AutreCritereSansWhere : Critere qui doit commencer par 'AND' ou 'OR'
         */
        public function GetCarte(string $AutreCritereSansWhere=null):mysqli_result{
            $Carte=new xCarteBonAchatExclusive($this->Main);
            $Critere="IdEntreprise=".$this->Id;
            if (isset($AutreCritereSansWhere)){
                if ($AutreCritereSansWhere !==''){
                    $Critere .=" ".$AutreCritereSansWhere ;
                }
            }
            $Lst=$Carte->ChargeListe($Critere);
            return $Lst ;
        }

        /**
         * Retourne le nombre de carte attribué à l'entreprise
         * @param string $AutreCritereSansWhere : Critere qui doit commencer par 'AND' ou 'OR'
         */
        public function NbCarte(string $AutreCritereSansWhere=null):int{
            $Carte=new xCarteBonAchatExclusive($this->Main);
            $TxSQL="select count(Id) as 'NB' from ".$Carte->Table." ";
            $Critere="IdEntreprise=".$this->Id;
            if (isset($AutreCritereSansWhere)){
                if ($AutreCritereSansWhere !==''){
                    $Critere .=" ".$AutreCritereSansWhere ;
                }
            }
            $TxSQL .=$Critere ;

            $Lst=$Carte->ExecSQL($TxSQL);
            $Nb=0;
            if ($Lst->num_rows>0){
                $rw=$Lst->fetch_assoc();
                $Nb=$rw['NB'];
            }
            return $Nb ;
        }

        /**
         * Suspend une ou plusieur cartes de bon d'achat
         */
        public function SuspendreCarte ( array $ListeIdCarte):xErreur{
            $Reponse=new xErreur;
            $Reponse->OK=0;
            $NbCarte=count($ListeIdCarte);
            $Reponse->Extra=[];
            $Reponse->TxErreur=[];
            $Tache='SUSPENSION CARTE BON ACHAT';
            
            foreach($ListeIdCarte as $IdCarte){
                if((int)$IdCarte>0){
                    $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
                    $Note="La carte numero ".$this->Id." REF: [".$Carte->REFCARTE."] a été suspendue par ".$this->Main->User->Login;
                    if ($Carte->Etat !== $Carte::CARTE_NONLIEE && $Carte->REFCARTE !==''){
                        if ($Carte->IdEntreprise==$this->Id){
                            if ($Carte->Etat == $Carte::CARTE_SUSPENDUE || $Carte->Etat == $Carte::CARTE_BLOQUEE){
                                $OK[$Carte->Id]['REPONSE']="Carte déja suspendue ou bloquée. Etat trouvé: ".$Carte->Etat;
                                $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                $Reponse->TxErreur[] = $OK[$Carte->Id];
                            }else{
                                $Carte->Etat=$Carte::CARTE_SUSPENDUE ;
                                if ($Carte->Enregistrer()){
                                    $this->AddToJournal($Tache,$Note);
                                    $OK[$Carte->Id]['REPONSE']="Carte suspendue correctement.";
                                    $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                    $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                    $Reponse->Extra[] = $OK[$Carte->Id];
                                }else{
                                    $OK[$Carte->Id]['REPONSE']="Impossible de suspendre la carte.";
                                    $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                    $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                    $Reponse->Extra[] = $OK[$Carte->Id];
                                }
                                $Reponse->OK=1;                                                      
                            }
                                                
                        }else{
                            $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." ne fait pas partie de la collection des cartes attribuées à l'entreprise ".$this->Nom." (Id-Entreprise: ".$this->Id.")";
                            $OK[$Carte->Id]['IDTRANSACTION']=0;
                            $Reponse->TxErreur[] = $OK[$Carte->Id];
                        }
                    }
                    
                }
            }

            return $Reponse;
        }

        /**
         * Permet d'Activer des cartes suspendues ou bloquées
         */
        public function ActiverCarte ( array $ListeIdCarte):xErreur{
            $Reponse=new xErreur;
            $Reponse->OK=0;
            $NbCarte=count($ListeIdCarte);
            $Reponse->Extra=[];
            $Reponse->TxErreur=[];
            $Tache='ACTIVATION CARTE BON ACHAT';
            
            foreach($ListeIdCarte as $IdCarte){
                if((int)$IdCarte>0){
                    $Carte=new xCarteBonAchatExclusive($this->Main,$IdCarte);
                    $Note="La carte numero ".$this->Id." REF: [".$Carte->REFCARTE."] a été activée par ".$this->Main->User->Login;
                    if ($Carte->Etat !== $Carte::CARTE_NONLIEE && $Carte->REFCARTE !==''){
                        if ($Carte->IdEntreprise==$this->Id){
                            if ($Carte->Etat == $Carte::CARTE_ACTIF){
                                $OK[$Carte->Id]['REPONSE']="Carte déja activée: ".$Carte->Etat;
                                $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                $Reponse->TxErreur[] = $OK[$Carte->Id];
                            }else{
                                $Carte->Etat=$Carte::CARTE_ACTIF ;
                                if ($Carte->Enregistrer()){
                                    $this->AddToJournal($Tache,$Note);
                                    $OK[$Carte->Id]['REPONSE']="Carte activée correctement.";
                                    $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                    $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                    $Reponse->Extra[] = $OK[$Carte->Id];
                                }else{
                                    $OK[$Carte->Id]['REPONSE']="Impossible d'activer la carte.";
                                    $OK[$Carte->Id]['ID-CARTE']=$Carte->Id;
                                    $OK[$Carte->Id]['REF-CARTE']=$Carte->REFCARTE ;                          
                                    $Reponse->Extra[] = $OK[$Carte->Id];
                                }
                                $Reponse->OK=1;                                                      
                            }
                                                
                        }else{
                            $OK[$Carte->Id]['REPONSE']="ID-CARTE ".$IdCarte." ne fait pas partie de la collection des cartes attribuées à l'entreprise ".$this->Nom." (Id-Entreprise: ".$this->Id.")";
                            $OK[$Carte->Id]['IDTRANSACTION']=0;
                            $Reponse->TxErreur[] = $OK[$Carte->Id];
                        }
                    }
                }
            }

            return $Reponse;
        }
    }
?>