<?php
namespace NAbySy\GS\Client ;

use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

Class xClient extends xORMHelper
{
	public function __construct(xNAbySyGS $NabySy,?int $IdClient=null,$CreationChampAuto=true,$TableName="client"){
		if ($TableName==''){
            $TableName="client";
        }
        parent::__construct($NabySy,(int)$IdClient,$CreationChampAuto,$TableName);
		
	}

	public function ChargeClient($IdClient){
		$this->ChargeOne($IdClient);
	}
	
	public function CrediterSolde($Montant=0){
		$SoldeP=$this->Solde ;		
		//Mode de Fonctionnement NAbySyGS
		if ($this->Id <= 0){
			return false ;
		}
		$NewSolde=$this->Solde+$Montant ;
		$sql="update ".$this->DataBase.".".$this->Table." SET Solde=Solde+'".$Montant."' where id='".$this->Id."' limit 1" ;
		// ----------------------------------
		$Tache="Crediter Solde Client" ;
		$Note="Le solde du compte client ".$this->Id." est passé de ".$SoldeP." à ".$NewSolde ;	
		$this->AddToJournal($Tache,$Note) ;		
		$this->ExecUpdateSQL($sql) ;
		$this->Solde=$NewSolde ;
		return true ;
	}

	public function DebiterSolde($Montant=0){
		$SoldeP=$this->Solde ;
		if ($this->Id <= 0){
			return false ;
		}
		$NewSolde=$this->Solde-$Montant ;
		$sql="update ".$this->DataBase.".".$this->Table." SET Solde=Solde-'".$Montant."' where id='".$this->Id."' limit 1" ;
		// ----------------------------------		
		$Tache="Debiter Solde Client" ;
		$Note="Le solde du compte client ".$this->Id." est passé de ".$SoldeP." à ".$NewSolde ;
		$this->AddToJournal($Tache,$Note) ;		
		$this->ExecUpdateSQL($sql) ;
		$this->Solde=$NewSolde ;
		
		return true ;
	}
	
	public function Save(){
		$this->Enregistrer();
		return $this->Id ;
	}

	public function ChangeSolde($NewSolde){
		$MonSoldePrec=$this->Solde ;
		if ($MonSoldePrec != $NewSolde){
			$TxSQL="update ".$this->DataBase.".".$this->Table." SET Solde='".$NewSolde."' Where id=".$this->Id." LIMIT 1" ;
			$this->Main->ReadWrite($TxSQL,null,true) ;
			$this->Solde=$NewSolde ;
			$Tache="Modification du Compte Client" ;
			$Note="Le compte client N° ".$this->Id." de ".$this->Prenom." ".$this->Nom." à vue son Solde Modifié il est passé de ".$MonSoldePrec." à ".$this->Solde ;
			$this->Boutique->AddToJournal($Tache,$Note) ;	
			$this->SoldePrec=$MonSoldePrec ;
			return true ;
		}
		return false ;
	}

	public function Supprimer():bool{
		$Tache="Suppression de Compte Client" ;
		$Note="Le compte client N° ".$this->Id." de ".$this->Prenom." ".$this->Nom." a été supprimé avec un solde de  ".$this->Solde ;
		$this->AddToJournal($Tache,$Note) ;	
		return parent::Supprimer();
	}

	public function GetListeArray($NomPrenom=null,$CodeClient=null,$IdComm=null){
		$Reponse=$this->GetListe($NomPrenom=null,$CodeClient=null,$IdComm=null);
		$Liste=$this->Main->EncodeReponseSQL($Reponse) ;
		return $Liste ;
	}

	public function GetListe($NomPrenom=null,$CodeClient=null,$IdComm=null,$IdClient=null){
		$TxSQL="select C.*, U.login, U.login as 'Commercial' from ".$this->DBase.".".$this->TEntete." C 
		left outer join utilisateur U on U.id=C.IdCommercial 
		where C.id>0 " ;
		if (isset($_SESSION['acces'])){
			if ($_SESSION['acces'] !=='Administrateur'){
				$TxSQL .=" and (C.IdCommercial=0 or C.IdCommercial=".$_SESSION['id_user'].") " ;
			}
		}
		$TxC="";
		if (isset($NomPrenom)){
			$TxC =" AND ( C.nom like '%".$NomPrenom."%' OR C.prenom like '%".$NomPrenom."%') " ;
			if (isset($CodeClient)){
				$TxC =" AND ( ( C.nom like '%".$NomPrenom."%' OR C.prenom like '%".$NomPrenom."%') OR  ( C.identifiant like '".$CodeClient."%' OR C.identifiant like '411".$CodeClient."') ) " ;
			}
		}else{
			if (isset($CodeClient)){
				$TxC .=" AND ( C.identifiant like '".$CodeClient."%' OR C.identifiant like '411".$CodeClient."') " ;
			}
		}
		if (isset($IdComm)){
			$TxSQL .=" AND C.IdCommercial = '".$IdComm."' " ;
		}
		if (isset($IdClient)){
			$TxSQL .=" AND C.ID = '".$IdClient."' " ;
		}
		$TxSQL .=$TxC ;
		//echo $TxSQL;
		//exit ;
		$OK=false;
		$reponse=$this->ExecSQL($TxSQL) ;
		if (!$reponse){
			return null ;
		}
		return $reponse ;
	}
}


?>