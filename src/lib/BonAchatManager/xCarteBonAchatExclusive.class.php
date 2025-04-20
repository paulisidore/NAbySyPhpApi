<?php
namespace NAbySy\Lib\BonAchat\Exclusive ;
use NAbySy\GS\Client\xClientEntreprise;
use NAbySy\Lib\BonAchat\xHistoriqueBonAchat;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/** Gère les cartes à puce pour les Bons d'Achat Exclusive*/
Class xCarteBonAchatExclusive extends xORMHelper{
     /** Carte dont l'etat est marqué Suspendue */
     public const CARTE_SUSPENDUE ='CARTE_SUSPENDUE';

     /** Carte activée et utilisable sur la plate forme */
     public const CARTE_ACTIF ='CARTE_ACTIF';
 
     /** Carte Bloquée */
     public const CARTE_BLOQUEE ='CARTE_BLOQUEE';

     /** Carte en entente de liaison initiale à une puce RFID */
     public const CARTE_NONLIEE = 'CARTE_NON_LIEE';

    /** Le libelle donné par chaque client et définit dans chacun son portefeuille. */
    public string $LIBELLE =''; //Le libelle donné par chaque client et définit dans chacun son portefeuille.

    public function __construct(xNAbySyGS $NAbySy,?int $Id=null,$AutoCreateTable=true,$TableName="cartebonachatexclusive",string $RefCarte=null)
    {
        if (!isset($TableName)){
            $TableName="cartebonachatexclusive";
        }
        if ($TableName==""){
            $TableName="cartebonachatexclusive";
        }
        //var_dump($TableName);
        parent::__construct($NAbySy,$Id,$AutoCreateTable,$TableName) ;
        if (isset($RefCarte)){
            if ($RefCarte !==""){
                $Lst=$this->ChargeListe("REFCARTE like '".$RefCarte."' ");
                if ($Lst){
                    if ($Lst->num_rows){
                        $rw=$Lst->fetch_assoc();
                        parent::__construct($NAbySy,$rw['ID'],$AutoCreateTable,$TableName) ;
                    }
                }
            }
        }
    }

     /**
     * Credite le solde de la carte
     */
    public function CrediterSolde(int $Montant,string $Libelle=null):int{
        if ($Montant==0 || $this->Id==0){
            return false;
        }
        $SoldeP=$this->Solde;
        $NewSolde=$this->Solde + $Montant;
        $Tache='CREDIT SOLDE CARTE BON ACHAT';
        $Note="Le solde de la carte numero ".$this->Id." REF.".$this->REFCARTE." est passée de ".$SoldeP." à ".$NewSolde;
        $this->Solde +=$Montant;
        if ($this->Enregistrer()){
            $this->AddToJournal($Tache,$Note);
            $Entreprise=new xClientEntreprise($this->Main,$this->IdEntreprise);
            //Journalisation dans l'historique
            $Historique=new xHistoriqueBonAchat($this->Main);
            $Historique->DateOP=date("Y-m-d");
            $Historique->HeureOP=date("H:i:s");
            $Historique->IdEntreprise=$this->IdEntreprise;
            $Historique->NomEntreprise=$Entreprise->Nom;
            $Historique->IdCarte=$this->Id;
            $Historique->RefCarte=$this->REFCARTE ;
            $Historique->SurCarte=1;
            $Historique->Operation='CREDIT SOLDE CARTE';
            $Historique->IsCredit=1;
            $Historique->Libelle="Carte ID ".$this->Id." crédité de ".$Montant." le ".$Historique->DateOP." à ".$Historique->HeureOP." [".$this->Main->User->Login."]";
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
     * Debite le solde de la carte
     */
    public function DebiterSolde(int $Montant, string $Libelle=null):int{
        if ($Montant==0){
            return false;
        }

        if ($this->Etat == self::CARTE_NONLIEE || $this->Etat == self::CARTE_SUSPENDUE || $this->Etat == self::CARTE_BLOQUEE ){
            $Tache='ERREUR DEDIT SOLDE CARTE';
            $Note="La carte numero ".$this->Id." REF.".$this->REFCARTE." est dans un etat (".$this->Etat.") limitant le débit du montant de ".$Montant ;
            $this->AddToJournal($Tache,$Note);
            return false ;
        }
        if ($this->REFCARTE=='' ){
            $Tache='ERREUR DEDIT SOLDE CARTE';
            $Note="La carte numero ".$this->Id." non appairée à une puce. Impossible de la débiter du montant de ".$Montant ;
            $this->AddToJournal($Tache,$Note);
            return false ;
        }

        $SoldeP=$this->Solde;
        $NewSolde=$this->Solde - $Montant;
        $Tache='DEDIT SOLDE CARTE BON ACHAT';
        $Note="Le solde de la carte numero ".$this->Id." REF.".$this->REFCARTE." est passée de ".$SoldeP." à ".$NewSolde;
        $this->Solde -=$Montant;
        if ($this->Enregistrer()){
            $this->AddToJournal($Tache,$Note);
            $Entreprise=new xClientEntreprise($this->Main,$this->IdEntreprise);
            //Journalisation dans l'historique
            $Historique=new xHistoriqueBonAchat($this->Main);
            $Historique->DateOP=date("Y-m-d");
            $Historique->HeureOP=date("H:i:s");
            $Historique->IdEntreprise=$this->IdEntreprise;
            $Historique->NomEntreprise=$Entreprise->Nom;
            $Historique->IdCarte=$this->Id;
            $Historique->RefCarte=$this->REFCARTE ;
            $Historique->SurCarte=1;
            $Historique->Operation='DEDIT SOLDE CARTE';
            $Historique->IsCredit=0;
            $Historique->Libelle="Carte ID ".$this->Id." débité de ".$Montant." le ".$Historique->DateOP." à ".$Historique->HeureOP." [".$this->Main->User->Login."]";
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
     * Retourne les données imprimées dans le QrCode de la carte
     * @return string 
     */
    public function GetQRCodeString($ConvertToObjet=false){
        $nObj['ID']=$this->Id;
        $nObj['IDENTREPRISE']=$this->IdEntreprise;
        $nObj['IDCARTE']=$this->Id;
        $nObj['APP']="NABYSYGS";
        $nObj['APPIDC']=1;
        $nObj['DATEGEN']=date('d/m/Y');
        $nObj['HEUREGEN']=date('H:i:s');
        $nObj['AUTOGEN']=$this->Main->User->Id;
        if($ConvertToObjet){
            $json = $nObj;
        }else{
            $json=json_encode($nObj) ;
        }
        return $json ;
    }
    
}

?>