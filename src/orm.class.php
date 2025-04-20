<?php
namespace NAbySy\ORM ;
include_once 'nabysy.php' ;

use DateTime;
use NAbySy\ORM\IORM;
use Exception;
use mysqli_result;
use NAbySy\OBSERVGEN\xObservGen;
use NAbySy\xNAbySyGS;
use xDB;
use xErreur;

//#[\AllowDynamicProperties] on verra ca une autre fois
class xORMHelper implements IORM{
    public $Table ;
    public int $Id=0 ;
    private array $RS ;
    public xNAbySyGS $Main ;
    public static xNAbySyGS $xMain;

    private array $ListeChampDB ;
    public $DataBase ;
    public \xDB $MySQL ;
    public bool $AutoCreate ;
    public bool $DebugMode ;

    public array $ListeTypeChampDB ;
    public static xDBFieldType $Ctype;

    /**
     * La class d'objet sur la quelle les évenements vont être transféré.
     */
    public $RaiseEventTaget=null;

    /**
     * @param xNAbySyGS $NAbySy Objet central.
     * @param string $TableName Nom de la Table à lier.
     * @param int $Id Si fournit, l'enregistrement correspondant à l'id est chargé depuis la table.
     * @param bool $CreationChampAuto Si = vrai, tout les champs non existant dans la table seront créee automatiquement.
     * @param string $DBName : Nom de la Base de donnée. si différente de celle de la base de donnée mère de NAbySyGS
     */
    public function __construct(xNAbySyGS $NAbySy,int $Id=null,$CreationChampAuto=true,$TableName=null,$DBName=null){
        $this->Table=$TableName ;
        $this->Main=$NAbySy ;
        self::$xMain=$NAbySy ;

        $this->RS=[] ;
        $this->ListeChampDB=[];
        $this->DataBase = $this->Main->MainDataBase ;
        if (isset($NAbySy->MaBoutique)){
            $this->DataBase = $NAbySy->MaBoutique->DBase;
        }
        $this->AutoCreate=$CreationChampAuto ;
        $this->DebugMode=false ;

        $this->ListeTypeChampDB=[];
        self::$Ctype=new xDBFieldType() ;

        if (isset($this->Main)){
            $this->MySQL = new \xDB($this->Main) ;
            $this->DebugMode=$this->Main->ActiveDebug ;
        }

        if (isset($DBName)){
            $this->DataBase=$DBName;
        }

        //$this->DebugMode=false;  
        $this->LoadTypeChampInDB() ;
        if (isset($Id)){
            if ($Id>0){
                if ($this->TableExisteInDataBase()){
                    $this->ChargeOne($Id) ;
                }else{
                    $this->AddToLog("Impossible de charger l'enregistrement. La table ".$this->DataBase.".".$this->Table." n'existe pas encore.");
                }                
            }
        }
        
    }

    /**
     * Fonction à Implémentation Obigatoire
     */
    #Region Fonctions Importées
        public function count(): int {
            //Retourne le nombre de ligne dans la base de donnée
            if (!isset($this->MySQL)){
                return 0 ;
            }
            if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
                return 0 ;
            }
            $resultat =null;
            $Tabl=$this->DataBase.".".$this->Table ;
            $TxSQL ="select COUNT(ID) as 'NB' from ".$Tabl ;
            try{
                $resultat = $this->Main->ReadWrite($TxSQL) ;
                if (!isset($resultat)){
                    return 0;
                }
                if ($resultat->num_rows>0){
                    $rw=$resultat->fetch_assoc();
                    return (int)$rw['NB'];
                }
                
            }catch(Exception $ex){
                $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
                $this->Main::$Log->Write($Note) ;
            }
            return 0;
        }

        public function current(): mixed {
            if (!isset($this->MySQL)){
                throw new Exception("Aucune base de donnée attachée", 1);
                return null ;
            }
            if ($this->TableIsEmpty()){
                //Aucune donnée dans la base de donnée
                return null;
            }
            $DataORM=null;
            $Limit=$this->position. " , 1";
            $Lst=$this->ChargeListe(null,null,"*",null,$Limit);
            if ($Lst){
                if($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $DataORM=new xORMHelper($this->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE,$this->Table,$this->DataBase);
                }
            }        
            return $DataORM;
        }

        public function next(): void { 
            $this->position +=1;;
        }

        public function key(): mixed {
            return $this->position;
        }

        public function valid(): bool { 
            if (!isset($this->MySQL)){
                throw new Exception("Aucune base de donnée attachée", 1);
                return false ;
            }
            if ($this->TableIsEmpty()){
                //Aucune donnée dans la base de donnée
                return false;
            }
            $DataORM=null;
            $Limit=$this->position. " , 1";
            $Lst=$this->ChargeListe(null,null,"*",null,$Limit);
            if ($Lst){
                if($Lst->num_rows){
                    return true;
                }
            }
            return false;
        }

        public function rewind(): void {
            $this -> position = 0;
        }

        public function offsetExists(mixed $NChamp): bool { 
            if (!is_string($NChamp)){
                throw new Exception("Le nom du champ doit etre de type string. Type trouvé: ".gettype($NChamp), 1);
                return false;
            }
            $ValeurTrouve = $this->__get($NChamp);
            if(isset($ValeurTrouve)){
                if ($ValeurTrouve !== null){
                    return true;
                }
            }
            return false;
        }

        public function offsetGet(mixed $NChamp): mixed {
            if (!is_string($NChamp)){
                throw new Exception("Le nom du champ doit etre de type string. Type trouvé: ".gettype($NChamp), 1);
                return null;
            }
            $ValeurTrouve = $this->__get($NChamp);
            return $ValeurTrouve;
        }

        public function offsetSet(mixed $NChamp, mixed $value): void { 
            if (!is_string($NChamp)){
                throw new Exception("Le nom du champ doit etre de type string. Type trouvé: ".gettype($NChamp), 1);
                return;
            }
            $this->__set($NChamp,$value);
        }

        public function offsetUnset(mixed $NChamp): void { 
            if (!is_string($NChamp)){
                throw new Exception("Le nom du champ doit etre de type string. Type trouvé: ".gettype($NChamp), 1);
                return;
            }
            if (!is_string($NChamp)){
                return;
            }
            $NomChamp=trim($NChamp);
            if (strtoupper($NomChamp) == "ID"){
                return;
            }
            if ($this->offsetExists($NChamp)){
                //On enleve du tableau sans supprimer dans la Table;
                $NewListe=[];
                foreach ($this->ListeChampDB as  $champ){
                    $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                    if (strtolower($Ch->Nom) !== strtolower($NomChamp)){
                        $NewListe[]=$champ ;
                    }
                }
                $this->ListeChampDB = $NewListe;
            }
        }
    #endregion


    /**
     * Recharge la liste des Types pour chaque champs de la Table
     */
    private function LoadTypeChampInDB(){
        if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
            return false ;
        }
        $this->ListeTypeChampDB=[];
        $Tabl="`".$this->DataBase."`.`".$this->Table."`" ;
        $query = "SELECT * from " . $Tabl . " limit 1";
        //var_dump($query);
        if($result =$this->Main::$db_link->query($query)){
            // Get field information for all columns
            while ($column_info = $result->fetch_field()){
                //var_dump($column_info);
                $Ch['NOM']=$column_info->name ;
                $Ch['TYPE_NUM']=$column_info->type ;
                $this->ListeTypeChampDB[]=$Ch ;
            }
        }
    }

    /**
     * Retourne le type de champs tel que définit dans la table.
     * @param string $NomChamp : Le nom du champs dans la base de donnée
     * @return int : Toute valeur de type reconnue par MySQL. voir la class xDBFieldType pour obtenir leurs définitions littérales
     */
    public function GetTypeChampInDB($NomChamp):int{
        
        if ($NomChamp==''){
            return self::$Ctype::VARCHAR ;
        }
        if (count($this->ListeTypeChampDB)==0){
            return self::$Ctype::INTEGER ;
        }
        foreach ($this->ListeTypeChampDB as $Champ){
            if (strtolower($Champ['NOM']) == strtolower($NomChamp) ){
                if (isset(self::$Ctype::$ListeType[$Champ['TYPE_NUM']])){
                    return self::$Ctype::$ListeType[$Champ['TYPE_NUM']] ;
                    break;
                }
            }
        }
        return self::$Ctype::VARCHAR ;
    }

    /**
     * Indique si Oui ou Non un champs dans la table est numeric ou non.
     * @param string $NomChamp : Le nom du champs dans la base de donnée
     * @return bool
     */
    public function IsTypeChampNumeric($NomChamp):bool{
        if ($NomChamp==''){
            return false ;
        }
        if (count($this->ListeTypeChampDB)==0){
            return false ;
        }
        $TypeCh=$this->GetTypeChampInDB($NomChamp);
        if (isset(self::$Ctype::$TypeChampNumeric[$TypeCh])){
            return true ;
        }
        return false ;
    }

    /**
     * Indique si Oui ou Non un champs dans la table est au format Date ou non.
     * @param string $NomChamp : Le nom du champs dans la base de donnée
     * @return bool
     */
    public function IsTypeChampDate($NomChamp):bool{
        if ($NomChamp==''){
            return false ;
        }
        if (count($this->ListeTypeChampDB)==0){
            return false ;
        }
        $TypeCh=$this->GetTypeChampInDB($NomChamp);
        if (isset(self::$Ctype::$TypeChampDateTime[$TypeCh])){
            return true ;
        }
        return false ;
    }

    /**
     * Provoque la création de la Table dans la base de donnée
     */
    public function FlushMeToDB(){
         /* Creation automatique de la Table */
         $this->MySQL->DebugMode=false ;
         if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
             /* on la crée */
             $this->MySQL->CreateTable($this->Table,$this->DataBase) ;
             if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
                 /* en cas d'erreur */
                 throw new Exception("Impossible de créer la table ".$this->Table) ;
                 return false ;
             }
         }
         return true ;
    }

    public function __get($NomChamp){

        if ($this->AutoCreate){
            /* Creation automatique de la Table */
            $this->MySQL->DebugMode=false ;
            if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
                /* on la crée */
                $this->MySQL->CreateTable($this->Table,$this->DataBase) ;
                if (!$this->MySQL->TableExiste($this->Table,$this->DataBase)){
                    /* en cas d'erreur */
                    throw new Exception("Impossible de créer la table ".$this->Table) ;
                    return null ;
                }
            }
        }

        /* On vérifie si le champ existe dans la table */
        $this->MySQL->DebugMode=$this->DebugMode ;
        if ($this->MySQL->ChampsExiste($this->Table,$NomChamp,$this->DataBase)){
            //on charge la valeur puis on retourne
            foreach ($this->ListeChampDB as  $champ){
                $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                if (strtolower($Ch->Nom) == strtolower($NomChamp)){
                    return $Ch->Valeur ;
                }
            }
            /* Le champ existe mais encore chargé dans l'objet ou enregistrement vide */
            $Ch=new xChampDB($NomChamp,'') ;
            $this->ListeChampDB[]=$Ch ;
            return $Ch->Valeur;
        }

        if ($this->AutoCreate){
            /* Creation automatique du champ */
            $Ch=new \NAbySy\ORM\xChampDB($NomChamp,'') ;
            $TypeChamp=strtolower($Ch->GetTypeChamp());
            $ValDefaut='' ;
            switch ($TypeChamp) {
                case 'int(11)':
                    $ValDefaut=0;
                    break;
                case 'text' :
                    $ValDefaut='' ;
                    break;
                case 'date' :
                    $ValDefaut='2020-01-01' ;
                    if (isset($Ch->Valeur)){
                        $Dte = new DateTime($Ch->Valeur);
                        if($Dte){
                            //$ValDefaut = $Dte->format('Y-m-d');
                        }
                    }
                    break;
                case 'time' :
                    $ValDefaut='00:00:00' ;
                    break;
                case 'datetime' :
                    $ValDefaut='2020-01-01 00:00:00' ;
                    if (isset($Ch->Valeur)){
                        $Dte = new DateTime($Ch->Valeur);
                        if($Dte){
                            $ValDefaut = $Dte->format('Y-m-d H:i:s');
                        }
                    }
                    break;
                default:
                    $ValDefaut='' ;
                    break;
            }

            if ($TypeChamp==strtolower($Ch::NUMERIC)){
                $ValDefaut=(int)$Ch->Valeur ; 
            }
            //$this->MySQL->DebugMode=true;
            $this->MySQL->DebugMode=$this->DebugMode ;
            $this->MySQL->AlterTable($this->Table,$Ch->Nom,$TypeChamp,'ADD',$ValDefaut,$this->DataBase);
            /* On vérifie si le champ existe dans la table */
            if (!$this->MySQL->ChampsExiste($this->Table,$NomChamp,$this->DataBase)){
                
                throw new Exception("Impossible de créer le champ ".$Ch->Nom.' de type '.$TypeChamp.' automatiquement dans la table '.$this->Table) ;

            }

            /* On recharge le nouveau champ dans la liste des champs */
            if ($this->Id){
                $this->ChargeOne($this->Id) ;
                //on charge la valeur puis on retourne
                foreach ($this->ListeChampDB as  $champ){
                    $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                    if (strtolower($Ch->Nom) == strtolower($NomChamp)){
                        return $Ch->Valeur ;
                    }
                }
            }
        
        }

        if ($this->MySQL->ChampsExiste($this->Table,$NomChamp,$this->DataBase)){
            //on charge la valeur puis on retourne
            foreach ($this->ListeChampDB as  $champ){
                $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                if (strtolower($Ch->Nom) == strtolower($NomChamp)){
                    return $Ch->Valeur ;
                }
            }
            return '';
        }

        return null ;            
    }

    public function __set($NomChamp,$Valeur){
        /* rappel la précédente valeur en vue de sa création automatique s'il n'existe pas */
        $PrecValeur=$this->__get($NomChamp) ;
        if (!isset($PrecValeur)){
            //Impossible d'affecter la valeur au champ
            throw new Exception("Impossible d\'affecter la valeur ".$Valeur." au champ ".$NomChamp) ;
            return ;
        }
        $NewListe=[];
        $Existant=false ;
        foreach ($this->ListeChampDB as  $champ){
            $Ch=new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
            if (strtolower($Ch->Nom) == strtolower($NomChamp)){
                $Existant=true ;
                $Ch->Valeur = $Valeur ;
            }
            $NewListe[]=$Ch ;
        }

        if (!$Existant){
            $Ch=new \NAbySy\ORM\xChampDB($NomChamp,$Valeur) ;
            $NewListe[]=$Ch ;
        }
        $this->ListeChampDB=$NewListe ;

    }

    /**
     * Permet l'enregistrement effectif de l'objet en cour dans la base de donnée.
     */
    public function Enregistrer() : bool{
        if (!isset($this->ListeChampDB)){
            return false ;
        }
        //var_dump($this->ListeChampDB) ;
        if ($this->Id>0){
            //On fait un update
            return $this->UpDate() ;
        }else{
            // On créer un nouvel enregistrement
            return $this->SaveNew() ;
        }
        //return false ;
    }

    private function SaveNew():bool{
        $Tabl=$this->DataBase.".".$this->Table ;
        $TxSQL=$this->GetInsertSQLString() ;
        if ($TxSQL){
            //var_dump($TxSQL) ;
            //exit ;
            $LastId=$this->Main->ReadWrite($TxSQL,true,$Tabl,$this->DebugMode);
            if ($LastId){
                $this->Id=$LastId ;
                //echo 'NewID='.$LastId.'</br>' ;
                //On déclenche un eventuel evenement
                $Cible=$this;
                if (isset($this->RaiseEventTaget)){
                    $Cible=$this->RaiseEventTaget ;                     
                }
                $ActualClass=get_class($Cible) ;
                $Sep=explode("\\",$ActualClass);
                $nbT=count($Sep);
                if ($nbT>0){
                    $ActualClass=$Sep[$nbT-1];
                }
                $Arg[0]=$ActualClass.'_ADD' ;
                $Arg[1]=$LastId ;
                //var_dump($Arg);
                $this->Main->RaiseEvent($ActualClass,$Arg) ;

                return true ;
            }
        }
        return false ;
    }

    private function UpDate() : bool{
        $Tabl=$this->DataBase.".".$this->Table ;
        $TxSQL=$this->GetUpDateSQLString() ;
        if ($TxSQL !== ""){
            $this->Main->ReadWrite($TxSQL,true,null,$this->DebugMode);
            $Cible=$this;
            if (isset($this->RaiseEventTaget)){
                $Cible=$this->RaiseEventTaget ;                     
            }
            $ActualClass=get_class($Cible) ;
            $Sep=explode("\\",$ActualClass);
            $nbT=count($Sep);
            if ($nbT>0){
                $ActualClass=$Sep[$nbT-1];
            }
            $Arg[0]=$ActualClass.'_EDIT' ;
            $Arg[1]=$this->Id ;
            $Arg[2]=$this ;
            //var_dump($Arg);
            $this->Main->RaiseEvent($ActualClass,$Arg) ;
            return true ;            
        }
        if ($TxSQL == ""){
            //Aucune Modification trouvée.
            return true ;
        }
        return false ;
    }

    /**
     * Retourne la requette SQL permettant d'exécuter une insertion dans la table en cour
     * @param bool $IgnoreTableShema Permet d'ignorer les nom de colonne de la table en cour.
     * @param bool $OnlyTableShema Retourne uniquement les colonnes de la table en cour.
     * @param string $TargetDataBase : Si fournit, le script sera généré avec cette base de donnée comme cible
     * @param bool $IgnoreID : Si Oui ignore le champ ID
     * @return string La requette compatible SQL.
     */
    public function GetInsertSQLString($IgnoreTableShema=false,$OnlyTableShema=false,$TargetDataBase=null, bool $IgnoreID=false) : string{
        $Tabl=$this->DataBase.".".$this->Table ;
        if (isset($TargetDataBase)){
            if ($TargetDataBase !==''){
                $Tabl = $TargetDataBase.".".$this->Table;
            }
        }
        $TxSQL="" ;        

        if (!$IgnoreTableShema){
            $TxSQL="insert into ".$Tabl." (" ;
            $i=1 ;
            foreach ($this->ListeChampDB as $Champ){
                $TypeChamp=get_class($Champ);
                if ($TypeChamp =='NAbySy\ORM\xChampDB'){
                    if (!is_object($Champ->Valeur)){
                        $CanAdd = true;
                        if ($IgnoreID && strtoupper($Champ->Nom) =="ID"){
                            $CanAdd = false;
                        }
                        if ($CanAdd){
                            $Ch=new xChampDB($Champ->Nom,$Champ->Valeur) ;
                            $col="`".$Ch->Nom."`" ;
                            if ($i>1){
                                $col =','.$col ;
                            }
                            $TxSQL .=$col ;
                            $i++;
                        }                        
                    }
                }
            }
            if ($OnlyTableShema){
                $TxSQL .= ')' ;
                return $TxSQL ;
            }
            $TxSQL .=") value(" ;
                        
        }else{
            $TxSQL="(" ;
        }

        $i=1 ;
        foreach ($this->ListeChampDB as $Champ){
            $TypeChamp=get_class($Champ);
            if ($TypeChamp =='NAbySy\ORM\xChampDB'){
                if (!is_object($Champ->Valeur)){
                    $Ch=new xChampDB($Champ->Nom,$Champ->Valeur) ;
                    $CanAdd = true;
                    if ($IgnoreID && strtoupper($Champ->Nom) =="ID"){
                        $CanAdd = false;
                    }
                    if ($CanAdd){
                        $val="'".addslashes($Ch->Valeur)."'" ;
                        if ($Ch->GetTypeChamp()==$Ch::NUMERIC || $this->IsTypeChampNumeric($Champ->Nom)){
                            $val="'".(int)$Ch->Valeur."'" ; 
                            if ($this->IsTypeChampNumeric($Champ->Nom)==self::$Ctype::DOUBLE ||
                                $this->IsTypeChampNumeric($Champ->Nom)==self::$Ctype::FLOAT ||
                                $this->IsTypeChampNumeric($Champ->Nom)==self::$Ctype::DECIMAL  ){
                                    
                                $val="'".(float)$Ch->Valeur."'" ; 
                            }
                        }
                        if ($i>1){
                            $val =','.$val ;
                        }
                        $TxSQL .=$val ;
                        $i++;
                    }
                    
                }
            }            
        }
        $TxSQL .=")" ;

        return $TxSQL ;
    }

    /**
     * Retourne la requette SQL permettant d'exécuter une mise à jour de l'objet en cour dans la base de donnée.
     * @return string La requette compatible SQL.
     */
    public function GetUpDateSQLString ():string{
        $Tabl=$this->DataBase.".".$this->Table ;
        $TxSQL="" ;
        $PrecVal=new xORMHelper($this->Main,$this->Id,$this->AutoCreate,$this->Table,$this->DataBase);
        $TxSQL="update ".$Tabl." SET " ;
        $i=1 ;
        $NbChamp=0;
        foreach ($this->ListeChampDB as $Champ){
            $TypeChamp=get_class($Champ);
            //var_dump($TypeChamp);
            if ($TypeChamp =='NAbySy\ORM\xChampDB'){                    
                if (!is_object($Champ->Valeur) && !is_array($Champ->Valeur)){
                    $CanUpDate=true;
                    $Ch=new xChampDB($Champ->Nom,$Champ->Valeur) ;                    
                    $NomChamp=$Champ->Nom;
                    if ($Ch->GetTypeChamp()==$Ch::NUMERIC){
                       if((int)$PrecVal->$NomChamp == (int)$Champ->Valeur){
                        $CanUpDate=false;
                       }
                    }else{
                        if($PrecVal->$NomChamp == $Champ->Valeur){
                            $CanUpDate=false;
                        }
                    }
                    if($CanUpDate){
                        $NbChamp++;
                        $col="`".$Ch->Nom."` = '".addslashes($Ch->Valeur)."' " ;
                        if ($Ch->GetTypeChamp()==$Ch::NUMERIC){
                            $col="`".$Ch->Nom."` = '".(int)$Ch->Valeur."' " ;
                        }
                        if ($i>1){
                            $col =','.$col ;
                        }
                        $TxSQL .=$col ;
                        $i++;
                    }
                    
                }                                        
            }                
        }
        if($NbChamp>0){
            $TxSQL .=" where Id='".(int)$this->Id."' limit 1" ;
        }else{
            $TxSQL="" ;
        }
        
        return $TxSQL ;
    }

    /**
     * Supprime l'objet en cour dans la base de donnée.
     * @return bool 
     */
    public function Supprimer() : bool {
        try {
            $Tabl=$this->DataBase.".".$this->Table ;
            $TxSQL="delete from ".$Tabl." where Id='".(int)$this->Id."' limit 1" ;
            $this->Main->ReadWrite($TxSQL,true,null,$this->DebugMode);
            //Remise de l'auto increment;
            $Tx="ALTER TABLE ".$Tabl." AUTO_INCREMENT=0" ;
            $this->Main->ReadWrite($Tx,true,null,$this->DebugMode);

            $Cible=$this;
            if (isset($this->RaiseEventTaget)){
                $Cible=$this->RaiseEventTaget ;                     
            }
            $ActualClass=get_class($Cible) ;
            $Sep=explode("\\",$ActualClass);
            $nbT=count($Sep);
            if ($nbT>0){
                $ActualClass=$Sep[$nbT-1];
            }
            $Arg[0]=$ActualClass.'_DEL' ;
            $Arg[1]=$this->Id ;
            $Arg[2]=$this ;
            //var_dump($Arg);
            $this->Main->RaiseEvent($ActualClass,$Arg) ;
            return true ;
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main->Log->Write($Note) ;
            throw new Exception($Note) ;
        } 
        return false ;       
    }

    /**
     * Charge un enregistrement dans l'objet en cour.
     * @param int $Id Identifiant unique dans la base de donnée.
     * @return mysqli_result 
     */
    public function ChargeOne(int $Id): ?\mysqli_result {
        $resultat = null;
        $TxSQL = 'select * from '.$this->DataBase.".".$this->Table." where Id='".(int)$Id."' limit 1" ;
        //var_dump($TxSQL);
        try{
            $retour=$this->Main->ReadWrite($TxSQL);
            if ($retour !== null){
                $resultat=$retour ;
            }
            // on charge les champs
            if ($resultat){
                $finfo = $resultat->fetch_fields();
                $this->ListeChampDB=[];
                foreach ($finfo as $val) {
                    $Ch=new xChampDB($val->name);
                    $this->ListeChampDB[]=$Ch;

                    /*
                    printf("Name:      %s\n",   $val->name);
                    printf("Table:     %s\n",   $val->table);
                    printf("Max. Len:  %d\n",   $val->max_length);
                    printf("Length:    %d\n",   $val->length);
                    printf("charsetnr: %d\n",   $val->charsetnr);
                    printf("Flags:     %d\n",   $val->flags);
                    printf("Type:      %d\n\n", $val->type);
                    */
                    
                }
                if ($resultat->num_rows>0){
                    //On charge les champs
                    $ListeChampDBSuivant=[];
                    while ($row = $resultat->fetch_assoc()) {
                        
                        foreach ($row as $Champ => $Valeur){
                            //var_dump($Champ."=".$Valeur."</br>") ;
                            if (strtolower($Champ)=='id'){
                                $this->Id=$Valeur ;
                            }
                            $Ch=new xChampDB($Champ,$Valeur) ;
                            $ListeChampDBSuivant[]=$Ch ;
                        }
                    }
                    $this->ListeChampDB=$ListeChampDBSuivant ;                    
                }else{
                    $this->Id=0;
                }
            }            
            return $resultat ;
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main::$Log->Write($Note) ;
        }
        
        return $resultat ;
    }

    /**
     * Retourne la liste des enregistrements correspondants aux critères sous forme de resultat MySQLi
     * @param string $Critere: Le(s) critère(s) sans le mot clé WHERE
     * @param string $Ordre: l'ordre de chargement de la requette sans le mot clé ORDER BY
     * @param string $SelectChamp : Liste de Champ Personnalisée
     * @param string $GroupBy : Champ de groupage SQL sans le mot clé 'GROUP BY'
     * @param ?string $Limit : Nombre maximal de ligne souhaitée.
     * @return mysqli_result La reponse sous forme d'objet MySQLi_Result
     */
    public function ChargeListe(string $Critere=null,$Ordre=null,$SelectChamp="*", $GroupBy=null, ?string $Limit=null):?mysqli_result {
        $resultat =null;
        $Tabl=$this->DataBase.".".$this->Table ;
        if(!isset($SelectChamp)){
            $SelectChamp="*";
        }
        $TxSQL ="select ".$SelectChamp." from ".$Tabl." where Id>0 " ;
        if (isset($Critere)){
            if (substr(strtolower($Critere),0,strlen('where '))=='where '){
                $Critere=substr($Critere,0,strlen('where ')) ;
            }
            if (substr(strtolower($Critere),0,strlen('and '))=='and '){
                $Critere=substr($Critere,0,strlen('and ')) ;
            }
            $TxSQL .=" AND ".$Critere ;
        }

        if (isset($GroupBy)){
            if ($GroupBy !==''){
                $TxSQL .=' GROUP BY '.$GroupBy ;
            }
        }

        if (isset($Ordre)){
            if (substr(strtolower($Ordre),0,strlen('order by '))=='order by '){
                $Ordre=substr($Ordre,0,strlen('order by ')) ;
            }
            $TxSQL .=" ORDER BY ".$Ordre ;
        }
        if (isset($Limit)){
            if (substr(strtolower($Limit),0,strlen('Limit '))=='Limit '){
                $Limit=substr($Limit,0,strlen('Limit ')) ;
            }
            $TxSQL .=" LIMIT ".$Limit ;
        }
        //var_dump($TxSQL);
        try{
            $resultat = $this->Main->ReadWrite($TxSQL) ;
            return $resultat ;
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main::$Log->Write($Note) ;
        }

        return $resultat ;
        
    }

    public function ExecSQL($TxSQL):?mysqli_result {
        $resultat=null;
        try{
            $resultat = $this->Main->ReadWrite($TxSQL) ;
            return $resultat ;
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main::$Log->Write($Note) ;
        }
        return $resultat ;
    }

    /**
     * Permet l'exécution d'une requette SQL d'ajout, modification ou suppression.
     * @param string $TxSQL : La requette SQL (insert, update ou delete)
     * @param string $InsertTable : Si InsertTable est définit alors, la fonction retournera le dernier Id enregistré de cette table
     */
    public function ExecUpdateSQL($TxSQL,$InsertTable=null) {
        $resultat=false;
        try{
            $resultat = $this->Main->ReadWrite($TxSQL,true,$InsertTable,$this->DebugMode) ;
            return $resultat ;
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main::$Log->Write($Note) ;
        }
        return $resultat ;
    }
    

    /**
     * Ajoute une entrée dans le journal système
     * @param string $Tache : Dénomination de la Tâche
     * @param string $Note : Note à inscrire
     * @return bool
     */
    public function AddToJournal(string $Tache,string $Note):bool{
        if ($Tache=='' || $Note==''){
            return false;
        }
        $UserN='SYS_ORM';
        $IdU=0;
        if ($this->Main->ValideUser(false)){
            $UserN=$this->Main->User->Login;
            $IdU=$this->Main->User->Id;
        }
        if (!isset($UserN)){
            $UserN='SYS_ORM';
        }
        $this->Main->AddToJournal($UserN,$IdU,$Tache,$Note);
        $this->Main->MaBoutique->AddToJournal($UserN,$IdU,$Tache,$Note);
        return true;        
    }

    /**
     * Ajoute une entrée dans le journal CSV des évènements systèmes
     * @param string $Note : Note à inscrire
     * @return bool
     */
    public function AddToLog(string $Note):bool{
        if ($Note==''){
            return false;
        }
        $UserN='SYS_ORM';
        $IdU=0;
        if ($this->Main->ValideUser(false)){
            $UserN=$this->Main->User->Login;
            $IdU=$this->Main->User->Id;
        }
        $Tx=__CLASS__.';'.$UserN.';'.$IdU.';'.$Note ;
        $this->Main::$Log->Write($Tx) ;
        return true;        
    }

    /**
     * Indique Oui ou Non si la table est vide. En cas d' erreur dans la base de donnée NON sera retourné.
     * @return bool
     */
    public function TableIsEmpty():bool{
        if (!$this->TableExisteInDataBase()){
            return true;
        }
        $resultat =null;
        $Tabl=$this->DataBase.".".$this->Table ;
        $TxSQL ="select * from ".$Tabl." where Id>0 limit 1" ;       

        try{
            $resultat = $this->Main->ReadWrite($TxSQL) ;
            if (!isset($resultat)){
                return true;
            }
            if ($resultat->num_rows>0){
                return false;
            }
            if ($resultat->num_rows==0){
                return true;
            }
            
        }catch(Exception $ex){
            $Note=__CLASS__.':'.$this->Table.'::'.__FUNCTION__.';Erreur:'.$ex->getMessage() ;
            $this->Main::$Log->Write($Note) ;
        }
        return false ;        
    }

    /**
     * Vérivie l'existance de la Table dans la Base de donnée.
     * @return bool 
     */
    public function TableExiste():bool{
        return $this->MySQL->TableExiste($this->Table,$this->DataBase) ;
    }

    /**
     * Convertir un resultat MySQLi au format JSON
     */
    public function EncodeReponseSQLToJSON(mysqli_result $Reponse){
		$Liste=$this->Main->EncodeReponseSQL($Reponse);
		if ($Reponse){
			while ($row = $Reponse->fetch_assoc()) {
				//array_push($Liste,$row);
				$Liste[]=$row;
			}
		}
        $Retour=json_encode($Liste);
		return $Retour ;		
	}

    public function ToJSON($TableStructure=false, $RemoveFieldList=[]): string {
        $JSonText="{" ;
        if (!$TableStructure){        
            $i=1 ;
            foreach ($this->ListeChampDB as $Champ){
                $Ch=new xChampDB($Champ->Nom,$Champ->Valeur) ;
                $AddToReponse = true;
                if ( !is_array($Champ->Valeur) && !is_object($Champ->Valeur) ){
                    foreach ($RemoveFieldList as $ChampSup){
                        if (!is_array($ChampSup) || !is_object($ChampSup)){
                            if (strtoupper($Champ->Nom) == strtoupper($ChampSup)){
                                $AddToReponse = false;
                            }
                        }
                    }

                    if ($AddToReponse){
                        if ($Ch->GetTypeChamp()==$Ch::NUMERIC ){
                            $col='"'.$Ch->Nom.'":'.(int)$Ch->Valeur.'' ;
                        }else{
                            $col='"'.$Ch->Nom.'":"'.$Ch->Valeur.'"' ;
                        }
                        if ($i>1){
                            $col =','.$col ;
                        }
                        $JSonText .=$col ;
                        $i++;
                    }
                    
                }
                
            }
            $JSonText .="}" ;
        }else{
            try{
                $JSonText=json_encode($this->ListeChampDB) ;
                $JSonText ;
            }catch(Exception $ex){
                $Err=new xErreur ;
                $Err->OK=0 ;
                $Err->TxErreur=$ex->getMessage() ;
                $Err->Source=__CLASS__.':'.$this->Table.'::'.__FUNCTION__ ;
                $JSonText=json_encode($Err) ;
            }
        }
        
       return $JSonText ;
        
    }

    /**
     * Transforme l'objet en Tableau avec Key numeroté à partir de 0
     */
    public function ToArray($RemoveFieldList=[]):array{
        $Tableau=[];
        $AddToReponse = true;
        if (count($this->ListeChampDB)){
            foreach ($this->ListeChampDB as $Champ){
                foreach ($RemoveFieldList as $ChampSup){
                    if (!is_array($ChampSup) || !is_object($ChampSup)){
                        if (strtoupper($Champ->Nom) == strtoupper($ChampSup)){
                            $AddToReponse = false;
                        }
                    }
                }
                if ($AddToReponse) {
                    $xCh['Nom'] = $Champ->Nom;
                    $xCh['Valeur'] = $Champ->Valeur;
                    $Tableau[] = $xCh;
                }
            }
        }
        return $Tableau;
    }

    /**
     * Convertit l'objet en cour en Objet Tableau avec Key le nom du champ
     * @param array $RemoveFieldList 
     * @return array 
     */
    public function ToArrayAssoc($RemoveFieldList=[]):array{
        $Tableau=[];
        $AddToReponse = true;
        if (count($this->ListeChampDB)){
            foreach ($this->ListeChampDB as $Champ){
                foreach ($RemoveFieldList as $ChampSup){
                    if (!is_array($ChampSup) || !is_object($ChampSup)){
                        if (strtoupper($Champ->Nom) == strtoupper($ChampSup)){
                            $AddToReponse = false;
                        }
                    }
                }
                if ($AddToReponse) {
                    $xCh['Nom'] = $Champ->Nom;
                    $xCh['Valeur'] = $Champ->Valeur;
                    $Tableau[$Champ->Nom] = $Champ->Valeur;
                }
            }
        }
        return $Tableau;
    }

    /**
     * Retourne un Object Php correspondant à cet objet en cour.
     * La fonction retourne l'équivalent de sa forme litterale JSON en Objet Php.
     */
    public function ToObject():object|null{
        //$Tbl=$this->ToJSON();
        //var_dump($Tbl)."</br>";
        $json=$this->ToJSON();
        //var_dump($json);
        $Obj=json_decode($json);
        //var_dump($Obj);
        return $Obj;
    }
    
    /**
     * Permet de cloner un enregistrement
     * @param int $IdProforma : :e ID de la proforma à cloner
     * @param $TargetDataBase : Si fournit, le clone sera disponible dans cette base de donnée
     * @param $IgnoreID : Si Oui, ne clone pas l'ID de l'enregistrement en cour. Dans le cas où la base de donnée en cour est la même que
     * $TargetDataBase, $IgnoreID n'aura aucun effet et l'ID ne sera pas copié.
     * @return xORMHelper : l'Enregistrement cloné
     */
    public function Clone(string $TargetDataBase=null, bool $IgnoreID=false):xORMHelper|null{
        $InsertTable=$this->DataBase.".".$this->Table;
        $CloneDB=$this->DataBase;
        $CloneORM = null;
        if (isset($TargetDataBase)){
            if (strtoupper($TargetDataBase) == strtoupper($this->DataBase)){
                $IgnoreID = true;
            }else{
                $CloneDB=$TargetDataBase;
                $InsertTable=$TargetDataBase.".".$this->Table;
            }
        }

        //On va crée les champs eventuellements manquant dans la base cible
        $CibleDB = new xDB($this->Main);
        if (!$CibleDB->TableExiste($this->Table,$CloneDB)){
            $CloneTable = new xORMHelper($this->Main, null, true, $this->Table, $CloneDB);
            $CloneTable->FlushMeToDB();
        }
        foreach ($this->ListeChampDB as $Champ){
            $TypeChamp=get_class($Champ);
            if ($TypeChamp =='NAbySy\ORM\xChampDB'){
                if (!is_object($Champ->Valeur)){
                    $CanAdd = true;
                    if (strtoupper($Champ->Nom) =="ID"){
                        $CanAdd = false;
                    }
                    if ($CanAdd){
                        $Ch=new xChampDB($Champ->Nom,$Champ->Valeur) ;
                        //var_dump($CibleDB->ChampsExiste($this->Table, $Champ->Nom, $CloneDB));
                        if (!$CibleDB->ChampsExiste($this->Table,$Champ->Nom,$CloneDB)){
                            $CibleDB->AlterTable($this->Table, $Champ->Nom, $Ch->GetTypeChamp(), "ADD", $Champ->Valeur,$CloneDB);
                        }
                    }                        
                }
            }
        }

        $TxSQL=$this->GetInsertSQLString(false,false,$TargetDataBase,$IgnoreID);
        try{
            $ReponseID=$this->ExecUpdateSQL($TxSQL,$InsertTable);
            if (isset($ReponseID)){
                if (is_int($ReponseID)){
                    $CloneORM = new xORMHelper($this->Main, $ReponseID, $this->Main::GLOBAL_AUTO_CREATE_DBTABLE, $this->Table, $CloneDB);
                }
            }
        }catch(Exception $ex){
            $this->Main::$Log->Write(__CLASS__." ".__FUNCTION__." ERROR: ".$ex->getMessage());
        }

        return $CloneORM;

    }

    /**
     * Permet de vider la table en cour. ATTENTION A CETTE OPERATION, AUCUNE SAUVEGARDE N'EST FAITE AVANT LA COPIE.
     * @return bool
     */
    public function ViderTable():bool{
        if ($this->MySQL->TableExiste($this->Table,$this->DataBase)){
            $TxSQL = "truncate table " . $this->DataBase . "." . $this->Table;
            $this->ExecUpDateSQL($TxSQL);
            return $this->MySQL->TableExiste($this->Table,$this->DataBase);
        }
        return false;
    }

    /**
     * Indique si Oui ou Non la table existe déjà dans la base de donnée
     * @return bool
     */
    public function TableExisteInDataBase():bool{
        return $this->MySQL->TableExiste($this->Table, $this->DataBase);
    }

    /**
     * Vérfie l'existence d'une base de donnée.
     * @param string|null $DBName Nom de la base de donnée. Si non fournit, le nom de la base de donnée en cour sera utilisé.
     * @return bool 
     */
    public  function DBExiste(string $DBName =null):bool{
        if(!isset($DBName)){
            $DBName=$this->DataBase;
        }
        return $this->MySQL->DBExiste($DBName);
    }

    /**
     * Indique si Oui ou Non un champ est déjà crée dans la table en cour
     * @param string $Champ
     * @return bool
     */
    public function ChampsExisteInTable(string $Champ):bool{
        return $this->MySQL->ChampsExiste($this->Table,$Champ, $this->DataBase);
    }

    /**
     * Permet de rafraichir les données de l'objet
     */
    public function Refresh():bool{
        if ($this->Id==0){
            return false;
        }
        $this->ChargeOne($this->Id);
        return true;
    }

    /**
     * Alias de la fonction Refresh qui permet le rafraissement des donnée à partir de la base de donnée.
     */
    public function Actualise():bool{
        return $this->Refresh();
    }

    public function AlterTable(xChampDB $NewChamps):bool{
        $NewListe=[];
        $NomChamps = $NewChamps->Nom ;
        $IsOK=false;
        foreach ($this->ListeChampDB as  $champ){
            $TypeCls=get_class($champ);
            if ($TypeCls =='NAbySy\ORM\xChampDB'){
                $Ch = $champ ; //new \NAbySy\ORM\xChampDB($champ->Nom,$champ->Valeur) ;
                if (strtolower($Ch->Nom) !== strtolower($NomChamps)){
                    $NewListe[]=$champ ;
                }else{
                    if($Ch->Type !== $NewChamps->Type){
                        $IsOK=$this->MySQL->AlterTable($this->Table,$NomChamps,$NewChamps->Type,'CHANGE',$NewChamps->Valeur,$this->DataBase) ;
                        if($IsOK){
                            $NewListe[]=$NewChamps ;
                        }else{
                            $NewListe[] = $champ ;
                        }
                    }
                }
                $this->ListeChampDB = $NewListe ;
            }
        }
        return $IsOK ;
    }

    public function ChangeTypeChamps(string $NomChamps, string $NewType, string $ValDefaut=null):bool{
        $PrecChamp = null ;
        $NType=$NewType ;
        //var_dump($this->ListeChampDB);
        //var_dump($this->ListeTypeChampDB);
        $TCh = new xDBFieldType();
        
        foreach ($this->ListeTypeChampDB as  $champ){
            if(strtolower($NomChamps) == strtolower($champ['NOM']) ){
                $Ch=new xChampDB($champ['NOM']);
                $TypePrec=$TCh->GetTypeName($this->GetTypeChampInDB($Ch->Nom));
                //echo __FILE__." Ligne: ".__LINE__.": ".$Ch->Nom." Type Prec: ".$Ch->Nom." => ".$TypePrec."</br>" ;
                //echo __FILE__." Ligne: ".__LINE__.": ".$Ch->Nom." Type Suiv: ".$Ch->Nom." => ".$NewType."</br>" ;
                if( $NewType !=="" && $TypePrec !== $NewType){
                    $IsDifferent=false;
                    if(str_contains($NewType,'(')){
                        $pos =strpos($NewType,'(') ;
                        $NType = substr($NewType,0,$pos);
                    }
                    if(strlen($NType) >= strlen($TypePrec)){
                        if(substr($TypePrec,0,strlen($NType)) !== $NType ){
                            $IsDifferent = true;
                        }
                    }else{
                        //var_dump($NType);
                        //var_dump(substr($NType,0,strlen($TypePrec)));
                        if(substr($NType,0,strlen($TypePrec)) !== $TypePrec ){
                            $IsDifferent = true;
                        }
                    }
                    if($IsDifferent){
                        $nChamp=$NewType ;
                        //var_dump($NewType);
                        $IsOK=$this->MySQL->AlterTable($this->Table,$NomChamps,$nChamp,'CHANGE',$ValDefaut,$this->DataBase) ;
                        return $IsOK;
                    }
                    //$PrecChamp->Type = $NewType ;
                    //var_dump("Change Type de champ de ".$PrecChamp->Type." à ". $NewType);
                   // $this->AddToJournal("DEBUG","Change Type de champ de ".$PrecChamp->Type." à ". $NewType) ;
                    //return $this->AlterTable($PrecChamp);
                }
            }
        }
        
        return false;
    }
}

?>