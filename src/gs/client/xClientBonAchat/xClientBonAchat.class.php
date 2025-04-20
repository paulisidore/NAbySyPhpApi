<?php
/**
 * Gestion des Clients Utilisateurs des bons d'Achat.
 * Copyright paul_isidore@hotmail.com / PAM sarl
 */
namespace NAbySy\GS\Client ;

use mysqli_result;
use NAbySy\Lib\BonAchat\Exclusive\xCarteBonAchatExclusive;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
 * Gestion des Clients Utilisateurs des bons d'Achat
 */
class xClientBonAchat extends xORMHelper {
    public xORMHelper $CartesClient;
    public array $Cartes =[];

    public const ETAT_ACTIF='ACTIF';
    public const ETAT_INACTIF='INACTIF';
    public const ETAT_SUSPENDU='SUSPENDU';

    public function __construct(xNAbySyGS $NabySy,?int $IdUser=null,$CreationChampAuto=true,$TableName="clientBonAchat",$Tel=null){
        if ($TableName==''){
            $TableName="clientBonAchat";
        }
        parent::__construct($NabySy,(int)$IdUser,$CreationChampAuto,$TableName,$NabySy->MaBoutique->DBName);

        if (!$this->MySQL->TableExiste($this->Table)){
            $this->FlushMeToDB();
        }

        $this->CartesClient = new xORMHelper($NabySy,null,$NabySy::GLOBAL_AUTO_CREATE_DBTABLE,'listeCarteClientBonAchat');
        if (!$this->CartesClient->MySQL->TableExiste($this->CartesClient->Table)){
            $this->CartesClient->IDCLIENT_BA=0;
            $this->CartesClient->IDCARTE=0;
            $this->CartesClient->FlushMeToDB();
        }
        if (isset($Tel)){
            $IdC=$this->GetIdClientBonAchatByTel($Tel);
            if ($IdC>0){
                parent::__construct($NabySy,(int)$IdC,$CreationChampAuto,$TableName,$NabySy->MaBoutique->DBName);
            }
        }
        $this->Init();
        
    }

    /**
     * Chargement initiale des données du client
     */
    private function Init(){
        $this->Cartes=[];
        if ($this->Id>0){
            $rep = $this->GetListeCarte();
            if ($rep->num_rows){
                while($rw = $rep->fetch_assoc()){
                    $xCarte=new xCarteBonAchatExclusive($this->Main,$rw['IDCARTE']);
                    if ($xCarte->Id){
                        $this->Cartes[] = $xCarte;
                    }
                }
            }
        }
    }

    /**
     * Retourne le IDCLIENT_BA du client selon son numero de téléphone
     */
    public function GetIdClientBonAchatByTel($Tel):int{
        if (!isset($Tel)){
            return 0;
        }
        if ($this->TableIsEmpty()){
            return 0;
        }
        $rep=$this->ChargeListe("TEL = '".$Tel."' ",null,"*");
        if ($rep->num_rows){
            $rw=$rep->fetch_assoc();
            return $rw['ID'] ;
        }
        return 0;
    }

    /**
     * Vérifie si l'utilisateur à déjá la carte dans son portefeuille
     */
    public function CarteExisteForClient(xCarteBonAchatExclusive $Carte):bool{
        if ($this->CartesClient->TableIsEmpty()){
            return false;
        }
        $rep=$this->CartesClient->ChargeListe("IDCARTE = '".$Carte->Id."' and IDCLIENT_BA='".$this->Id."' ");
        if ($rep->num_rows){
            return true;
        }
        return false;
    }

    /**
     * Retourne la liste des cartes du clients
     */
    public function GetListeCarte():mysqli_result {
        $rep=$this->CartesClient->ChargeListe("IDCLIENT_BA = '".$this->Id."' ");
        return $rep ;
    }

    /**
     * Ajoute une carte à la collection de carte du client
     * @param xCarteBonAchatExclusive $Carte: La carte à ajouter
     * @return int : vrai si terminé correctement
     */
    public function AjoutCarte(xCarteBonAchatExclusive $Carte):bool{
        if ($this->Id<1){
            return false;
        }
        if ($this->CarteExisteForClient($Carte)){
            return false;
        }
        $CarteClt=new xORMHelper($this->CartesClient->Main,null,$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->CartesClient->Table);
        $CarteClt->IDCLIENT_BA = $this->Id;
        $CarteClt->IDCARTE = $Carte->Id;
        $CarteClt->DATEENREG = date("Y-m-d H:i:s");
        
        if ($CarteClt->Enregistrer()){
            return true ;
        }
        return false;
    }

    /**
     * Modifie le libellé d'une carte pour un client
     */
    public function UpdateInfoCarte(xCarteBonAchatExclusive $Carte, string $Libelle){
        //var_dump($this->Id);
        if ($this->Id==0){
            return false;
        }
        if (!$this->CarteExisteForClient($Carte)){
            return false;
        }
        $Lst=$this->CartesClient->ChargeListe("IDCARTE = ".$Carte->Id." and IDCLIENT_BA=".$this->ID);
        if ($Lst->num_rows){
            $rw=$Lst->fetch_assoc();
            $LaCarte =new xORMHelper($this->CartesClient->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->CartesClient->Table);
            if ($LaCarte->LIBELLE !== $Libelle){
                $LaCarte->LIBELLE =$Libelle ;
                $LaCarte->Enregistrer();
                $this->Init();
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne une carte contenue dans le portefeuille de carte du client
     */
    public function GetCarte(int $IdCarte): ?xCarteBonAchatExclusive{
        $this->Init();
        foreach ($this->Cartes as $CarteClt){
            if ($CarteClt->Id == $IdCarte){
                return $CarteClt ;
            }
        }
        return null;
    }

    /**
     * Supprime une carte du portefeuille du client
     */
    public function RemoveCarte(int $IdCarte):bool{
        $this->Init();
        $DejaSup=false;
        $ExisteDedans=false ;
        foreach ($this->Cartes as $CarteClt){
            if ($CarteClt->Id == $IdCarte){
                $ExisteDedans=true ;
                $Lst=$this->CartesClient->ChargeListe("IDCARTE = ".$IdCarte." and IDCLIENT_BA=".$this->ID);
                if ($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $LaCarte =new xORMHelper($this->CartesClient->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->CartesClient->Table);
                    if ($LaCarte->Supprimer()){
                        $this->Init();
                        $ExisteDedans=false ;
                        return true;
                    }
                }else{
                    $ExisteDedans=false ;
                    $DejaSup=true ;
                }
                
            }
        }
        if (!$ExisteDedans){
            $DejaSup=true ;
        }
        return $DejaSup;
    }
}

?>