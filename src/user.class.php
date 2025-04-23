<?php

namespace NAbySy;

use Firebase\JWT\JWT;
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Facture\xVente;

Class xUser extends \NAbySy\ORM\xORMHelper {
    //public $Login ;
    //public $Nom ;
    //public $Prenom ;
    public $Acces ;
    public xBoutique $Boutique ;
    public $PagesInterdites ;
    public $TablePageInterdite ;
	public $DBase ;
    public xNAbySyGS $Main;
    public $TEntete ;
    public $EnteteTable;
    private $_Signature ;
    
    public function __construct(xNAbySyGS $NabySy,?int $IdUser=null,$CreationChampAuto=true,$TableName="utilisateur",$UserN=null) 
    {
        if ($TableName==''){
            $TableName="utilisateur";
        }
        parent::__construct($NabySy,(int)$IdUser,$CreationChampAuto,$TableName,$NabySy->MaBoutique->DBName);
        //$this->Table="utilisateur" ;
        $this->TEntete=$this->Table;
		if ($this->EnteteTable==''){
			$this->EnteteTable = 'utilisateur' ;
			$this->TEntete="utilisateur";
        }
        
        $this->TablePageInterdite="pageinterdite" ;
        //$this->Main = $NabySy ;
        $this->DBase=$NabySy->MaBoutique->DBName ;
        $this->Boutique = $NabySy->MaBoutique;

        $MySQL=new xDB($this->Main) ;
        $this->Boutique->DBName ;

        $MySQL->DebugMode=false ;
        if (!$MySQL->ChampsExiste($this->TEntete,'Signature',$this->DBase)){
            $MySQL->AlterTable($this->TEntete,'Signature','TEXT','ADD','',$this->DBase) ;
        }
        if (!$MySQL->ChampsExiste($this->TEntete,'IdEmploye',$this->DBase)){
            $MySQL->AlterTable($this->TEntete,'IdEmploye','INT(11)','ADD','0',$this->DBase) ;
        }

        $this->CompteEmploye=null;

        if (isset($UserN)){
            $this->GetUserByLogin($UserN) ;
        }elseif($IdUser){            
            $this->GetUserById($IdUser) ;
            //var_dump($this->RS);
            $this->ChargeUser() ;
        }

        if (isset($this->RS)){
            $this->ChargeUser() ;
        }
        
    }

    public function ChargeUser(){
        if (isset($this->RS)){
            $this->Acces=$this->NiveauAcces;
        }
        if(!$this->ChampsExisteInTable('Signature') && $this->Id>0){
            $this->AutoCreate=true;
            $this->_Signature='';
            $this->Signature($this->_Signature);
        }
        if($this->ChampsExisteInTable('Signature')){
            $TxSQL='select Signature from `'.$this->DataBase.'`.`'.$this->TEntete.'` where id="'.$this->Id.'" limit 1' ;
            $Ret=$this->Main->ReadWrite($TxSQL,false,null) ;
            if ($Ret->num_rows>0){
                $Sign=$Ret->fetch_assoc() ;
                $this->_Signature=$Sign['Signature'];
            }
        }
    }

    public function AddPageInterdite($Titre,$Lien){
        if (!isset($this->RS)){
            return false ;
        }
        /* $pageinterdite=R::dispense($this->TablePageInterdite) ;
        $pageinterdite->Titre=$Titre ;
        $pageinterdite->Lien=$Lien ;
        $pageinterdite->DateAjoutee=date('Y-m-d') ; */
       return true;
    }

    public function GetPageInterdite(){
        //$this->SiteInterdit=R::dispense($this->TablePageInterdite) ;
        $ListePageInterdite='' ; //$this->RS['PageInterditeList'] ;
        return  $ListePageInterdite ;
    }

    public function CheckIfPageInterdite($Titre=null,$Lien=null){
        /* R::selectDatabase($this->Boutique->DBName) ;
        if (isset($Titre)){            
            $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TablePageInterdite."` where _titre like '".$Titre."' AND utilisateur_id =".$this->Id ;
            $Ret=$this->Main->ReadWrite($TxSQL) ;
            if ($Ret->num_rows>0){
                return true ;
            }
            $Page=R::findOne($this->TablePageInterdite,' _titre like ? AND utilisateur_id = ? ',[ $Titre, $this->Id ]) ;
        }
        if (isset($Lien)){
            $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TablePageInterdite."` where _lien like '".$Lien."' AND utilisateur_id =".$this->Id ;
            $Ret=$this->Main->ReadWrite($TxSQL) ;
            if ($Ret->num_rows>0){
                return true ;
            }
            $Page=R::findOne($this->TablePageInterdite,' _lien like ? AND utilisateur_id = ? ',[ $Lien, $this->Id ]) ;
            //var_dump($Page) ;
            //echo "</br>".$TxSQL ;
        }
        if(isset($Page)){
            return true;
        } */
        return false ;
    }

    public function RemovePageInterdite($Id=null,$Titre=null,$Lien=null){
        $Page=null;
        

        if (isset($Id)){
            $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TablePageInterdite."` where _id like '".$Id."' AND utilisateur_id =".$this->Id ;
            $Ret=$this->Main->ReadWrite($TxSQL,false,null) ;
            if ($Ret->num_rows>0){
                $Page=$Ret->fetch_assoc() ;
            }
            //$Page=R::load($this->TablePageInterdite,$Id) ;
        }
        if (isset($Titre)){
            echo "Je recherche Page avec Titre=".$Titre ;
            $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TablePageInterdite."` where _titre like '".$Titre."' AND utilisateur_id =".$this->Id ;
            $Ret=$this->Main->ReadWrite($TxSQL,false,null) ;
            if ($Ret->num_rows>0){
                $Page=$Ret->fetch_assoc() ;
            }
            //$Page=R::findOne($this->TablePageInterdite,' _titre like ? ',[ $Titre ]) ;
            
        }
        if (isset($Lien) && $Page==null){
            //echo "Je recherche Page avec Lien=".$Lien ;
            $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TablePageInterdite."` where _lien like '".$Lien."' AND utilisateur_id =".$this->Id ;
            $Ret=$this->Main->ReadWrite($TxSQL,false,null) ;
            if ($Ret->num_rows>0){
                $Page=$Ret->fetch_assoc() ;
            }            
            //$Page=R::findOne($this->TablePageInterdite,' _lien like ? ',[ $Lien ]) ;
        }

        //var_dump($Page) ;

        if(isset($Page)){
            //echo "Je supprime Page avec Id=".$Page->id ;
            //echo "ReadBean Page=$Page" ;
            echo "NAbySy Page=".var_dump($Page) ;
            //exit ;
            //$site=R::load($this->TablePageInterdite,$Page->id) ;
            //if (isset($site)){                            
                //var_dump($site);
                /*
                echo "ReadBean Suppression ..." ;
                
                unset($this->RS->xownPageInterditeList[$Page->id]) ;
                unset($this->PagesInterdites[$Page->id]) ;
                R::trash( $site );
                R::store($this->RS) ;
                echo "ReadBean Supprimé." ;
                */
                echo "NAbySy Suppression ..." ;
                $TxSup="delete from ".$this->Boutique->DBName.".".$this->TablePageInterdite." where _titre like '".$Page['_titre']."' and utilisateur_id='".(int)$this->Id."' limit 1";
                echo "MySQLi: ".$TxSup ;
                $this->Main->ReadWrite($TxSup,null,true,$this->DebugSave) ;
                echo "NAbySy Suppression Terminée." ;
                return true;
            //}
        }else{
            echo "<script>console.log('ReadBean Page='+$Page)</script>" ;
        }

        return false ;
    }

    public function GetUserByLogin($Username=null){
        if (!isset($Username)){
            $Username=$this->Login ;
        }
        $TxSQL='select * from '.$this->Boutique->DBName.'.'.$this->TEntete.' where Login like "'.$Username.'" ';
        $Rep=$this->Main->ReadWrite($TxSQL,false,null);
        
        if (isset($Rep)){
            if ($Rep->num_rows>0){
                $xRS=$Rep->fetch_assoc();
                $ChmpID='Id';
                if (isset($xRS['ID'])){
                    $ChmpID='ID';
                }
                if (isset($xRS['Id'])){
                    $ChmpID='Id';
                }
                if (isset($xRS['id'])){
                    $ChmpID='id';
                }
                if (isset($xRS['iD'])){
                    $ChmpID='iD';
                }
                $this->ChargeOne($xRS[$ChmpID]);                
                //var_dump($xRS);
                $this->RS=$xRS;
                return $xRS ;
            }
        }
    }

    public function GetUserById($IdU=null){
        if (!isset($IdU)){
            $IdU=$this->Id ;
        }
        $TxSQL='select * from '.$this->Boutique->DBName.'.'.$this->Table.' where Id="'.$IdU.'" ';
        $Rep=$this->Main->ReadWrite($TxSQL,false,null);
        if (isset($Rep)){
            if ($Rep->num_rows>0){
                $xRS=$Rep->fetch_assoc();
                $ChmpID='Id';
                if (isset($xRS['ID'])){
                    $ChmpID='ID';
                }
                if (isset($xRS['Id'])){
                    $ChmpID='Id';
                }
                if (isset($xRS['id'])){
                    $ChmpID='id';
                }
                if (isset($xRS['iD'])){
                    $ChmpID='iD';
                }
                $this->ChargeOne($xRS[$ChmpID]);                
                //var_dump($xRS);
                $this->RS=$xRS;
                //var_dump($this->RS);
                return $xRS ;
            }
        }
    }

    public function GetListe($Nom=null,$Acces=null,$IdRegion=null,$IdDepartement=null,$IdZone=null){
		$Table=$this->DBase.".".$this->TEntete ;
		$TxC="" ;
		if (isset($Nom)){
			$TxC .=" AND (C.Nom like '%".$Nom."%' OR C.Prenom like '%".$Nom."' OR C.Login like '%".$Nom."'" ;
        }
        if (isset($Acces)){
			$TxC .=" AND C.acces like '".$Acces."' " ;
		}

		$TxSQL="select C.* from ".$Table." C ".$TxC ;
		$OK=false;
		$reponse=$this->Main->ReadWrite($TxSQL,false,null) ;
		if (!$reponse)
			return null ;
		
		return $reponse ;
		
    }    

    public function CanUseModule($ModuleName,$YesNo=null){
        if (substr($ModuleName,0,strlen("ACCES_BOUTIQUE_"))=="ACCES_BOUTIQUE_"){
            $IdBoutiqueR=(int)substr($ModuleName,strlen("ACCES_BOUTIQUE_")) ;
            return $this->CanAccesBoutique($IdBoutiqueR,$YesNo);
        }
        $DB=new xDB($this->Main) ;
        
        $Champ='CanUseMod_'.$ModuleName ;
        $Table=$this->Boutique->DBName.".".$this->TEntete ;
        $OK=false;
        $DB->DebugMode=false ;
        
        if ($DB->ChampsExiste('utilisateur',$Champ,$this->Boutique->DBName)){
            if (isset($YesNo)){
                $Autorise=$YesNo;
                if (strtolower($YesNo)=='yes'){$Autorise=1;}
                if (strtolower($YesNo)=='oui'){$Autorise=1;}
                if (strtolower($YesNo)=='ok'){$Autorise=1;}
                if ($YesNo==true){$Autorise=1;}
                $TxSQL="update ".$Table." SET ".$Champ." = ".$Autorise." where id=".$this->Id." limit 1" ;
                $this->Main->ReadWrite($TxSQL,true,null,$this->DebugUpDate) ;
                return true ;
            }
            //Le Module existe, on va le configurer
            $TxSQL="select ".$Champ." as 'CH' from ".$Table." U where id=".$this->Id ;
            //var_dump($TxSQL) ;
            $reponse=$this->Main->ReadWrite($TxSQL,true,null) ;
            if (!$reponse){
                return false ;
            }
            if ($reponse->num_rows){
                $rw=$reponse->fetch_assoc();
                if ($rw['CH']==1){
                    return true ;
                }else{
                    return false ;
                }
            }else{
                return false ;
            }
            
        }else{
            $DB->AlterTable($this->TEntete,$Champ,"INT",'ADD','1',$this->Boutique->DBName);
            if ($DB->ChampsExiste('utilisateur',$Champ,$this->Boutique->DBName)){
                return $this->CanUseModule($ModuleName,$YesNo) ;
            }
            return false ;
        }

    }

    /**
     * Indique si l'utilisateur a accès à une boutique
     * @param int $BoutiqueId
     * @param bool $YesNo
     * @return bool
     */
    public function CanAccesBoutique($BoutiqueId,$YesNo=null){
        $DB=new xDB($this->Main) ;
        $DB->DebugMode=false;
        $Champ='UtilisateurInterdit' ;

        $Table=$this->Main->MasterDataBase.".boutique" ;
        $OK=false;
        $Lst=[];

        if ($DB->ChampsExiste('boutique',$Champ,$this->Main->MasterDataBase)){
            $TxSQL="select ".$Champ." from ".$Table." where Id='".$BoutiqueId."' " ;
            $ListeI=$this->Main->ReadWrite($TxSQL,false,null) ;
            if ($ListeI->num_rows>0){
                $row=$ListeI->fetch_assoc() ;
                $Lst=json_decode($row[$Champ]);                
            }
            if (isset($YesNo)){
                $Autorise=$YesNo;
                if (strtolower($YesNo)=='yes'){$Autorise=1;}
                if (strtolower($YesNo)=='oui'){$Autorise=1;}
                if (strtolower($YesNo)=='ok'){$Autorise=1;}
                if ($YesNo==true){$Autorise=1;}
                $Acc['IdU']=$this->Id ;
                $Acc['Login']=$this->Login ;
                $Acc['BoutiqueOrig']=$BoutiqueId ;
                $NewListe=[];
                if (isset($Lst)){
                    foreach($Lst as $Interdit){
                        if ($Interdit->Login !==$this->Login or $Interdit->BoutiqueOrig !==$BoutiqueId  ){                            
                            $NewListe[]=$Interdit ; 
                        }                    
                    }                    
                }                
                if ($Autorise !== 1){
                    $NewListe[]=$Acc ;
                }
                //echo 'NewListe= </br>' ;
                //print_r($NewListe) ;

                $Sauv=json_encode($NewListe) ;
                $TxSQL="update ".$Table." SET ".$Champ." = '".$Sauv."' where Id='".$BoutiqueId."' limit 1" ;
                $this->Main->ReadWrite($TxSQL,true,null,$this->DebugUpDate) ;
                return true ;
            }
            //Le champ existe
            //print_r($this->Boutique->Id);
            //exit ;
            if ($Lst==null){
                return true ;
            }
            foreach($Lst as $Interdit){
                if ($Interdit->Login == $this->Login){
                    //echo "Login trouvé </br>" ;
                    if ($Interdit->BoutiqueOrig == $BoutiqueId){
                        //echo "IdBoutique trouvé </br>" ;
                    }
                }

                if ($Interdit->Login ==$this->Login && $Interdit->BoutiqueOrig == $BoutiqueId  ){                    
                    return false ; 
                }
            }
            return true ;
        }else{
            $DB->AlterTable($this->Main->MasterDataBase."boutique",$Champ,"TEXT",'ADD','[]',$this->Main->MasterDataBase);
            if ($DB->ChampsExiste($this->Main->MasterDataBase."boutique",$Champ,$this->Main->MasterDataBase)){
                return $this->CanAccesBoutique($BoutiqueId,$YesNo) ;
            }
           
        }
        return true ;
    }

    public function CheckPassword($pwd_to_check){
        //R::selectDatabase($this->Boutique->DBName) ;       
        $TxSQL="select * from `".$this->Boutique->DBName."`.`".$this->TEntete."` where login like '".$this->Login."' 
        AND (password like '".$pwd_to_check."' or password like MD5('".$pwd_to_check."') )  " ;
        //echo $TxSQL;
        $Ret=$this->ChargeListe(" (password like '".$pwd_to_check."' or password like MD5('".$pwd_to_check."') )");
        //var_dump($Ret);
        if ($Ret){
            if ($Ret->num_rows>0){
                return true ;
            }
        }
        return false;
    }

    /**
     * Modifie ou Obtient la Signature de l'Utilisateur
     * @param string $NewSignature : Si fournit, la fonction Modifie la Signature de l'utilisateur.
     * @return bool|string 
     */
    public function Signature($NewSignature=null){
        if(isset($NewSignature)){
            //On va modifier la signature de l'utilisateur
            $this->Signature=$this->Main::$db_link->escape_string($NewSignature);
            return $this->Enregistrer() ;
        }else{
            //On retourne la signature de l'utilisateur
            //var_dump($this->_Signature) ;
            return $this->Signature ;
        }
    }

    /**
     * Permet de signer une facture numeriquement. Pour le moment la signature est une chaine de caractère. A long therme ca sera une image
     * @param int $IdFacture : Le numero de la facture
     * @return int|bool [-3 ; -1] (-3 -> Parametre IdFacture manquant ou null, -2 -> Pas autorisé, -1 Facture introuvable dans la base de donnée) ou
     * false -> facture deja signée ou true -> facture signée correctement.
     */
    public function SigneFacture($IdFacture){
        if (!isset($IdFacture)){
            return -3 ;
        }
        if (!$this->CanUseModule('CanSigneFacture') ){
            return -2 ;
        }
        
        $Vente=new xVente($this->Main);
        $Facture=new xVente($this->Main,$IdFacture);
        $ListeFacture = $Vente->GetVente($IdFacture);
        
        if ($Facture->IdSignature>0){
            //Facture déja signée
            return false ;
        }
        if ($this->Signature==''){
            $this->Signature='Signée par '.$this->Login ;
        }
        $Facture->IdSignature=(int)$this->Id;
        $Facture->FactureSignee==$this->Main::$db_link->escape_string($this->Signature);
        
        return $Facture->Enregistrer();

    }    

    public function GetToken(){
        if (trim($this->Etat) ==''){
            $this->Etat='Actif' ;
        }
        $Auth=new xAuth($this->Main,3600, $this) ;
        $jwt=$Auth->GetToken($this);
        return $jwt ;
    }
}