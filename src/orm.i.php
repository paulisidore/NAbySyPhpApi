<?php
    namespace NAbySy\ORM ;

use ArrayAccess;
use Countable;
use Iterator;
use mysqli_result;
use NAbySy\xNAbySyGS;

    /**
     * Class ORM (Objet Relationnal Mapping)
     * Ce module permet l'interfaçage avec une base de donnée.
     * 
     */
    interface IORM extends ArrayAccess, Iterator, Countable{
        /**
         * Constructeur de la classe ORM (Objet Relationnal Mapping)
         * @param xNAbySyGS $NAbySyGS Objet centrale NAbySyGS.
         * @param string $NomTable Nom de la table auque l'ORM est liée.
         */
        public function __construct(xNAbySyGS $NabySyGS,int $Id=null,$CreationChampAuto=true,$NomTable=null);

         /**
         * Retourne le nom complet de la Table
         * sous le format: `mabase`.`mabase` (Avec la présence des griffes)
         * @return string 
         */
        public function FullTableName():string;

        public function __get($NomChamp);
        public function __set($NomChamp,$Valeur);
        public function Enregistrer():bool;
        public function Supprimer():bool ;

        public function ChargeOne(int $Id):?\mysqli_result;
        public function ChargeListe(string $Critere):?\mysqli_result ;

        /**
         * Modifie de type du Champs dans la Table
         * @param xChampDB $NewChamps : Le champ concerné. la Propriété Type dot contenir le nouveau type
         * @return bool 
         */
        public function AlterTable(xChampDB $NewChamps):bool ;

        /**
         * Modifie le Type du Champs dans la Table
         * @param string $NomChamps 
         * @param string $NewType : Le nom du nouveau Type. Ex: varchar(255) , int(11), TEXT, JSON ...
         * @return bool 
         */
        public function ChangeTypeChamps(string $NomChamps, string $NewType):bool ;

        /**
         * Ajoute une table de jointure
         * @param xORMHelper $TargetOrm La la Table cible
         * @property string $Alias Permet d'attribuer une alias à la table jointe
         * @param string $cleJointeSrc La clé sur laquelle la table cible sera joint à cet objet
         * @param string $cleJointeEtrangere La clé étrangère de l'objet cible qui sera lié à l'objet en cour
         * @param string $type 'LEFT JOIN' | INNER JOIN | 'RIGHT JOIN'
         * @return xORMHelper 
         */
        public function JoinTable(xORMHelper $TargetOrm, string $Alias=null,string $cleJointeSrc, string $cleJointeEtrangere='ID', $type = 'LEFT JOIN'):xORMHelper ;


        /**
         * Execute une requette sql SELECT avec éventuellement des jointure de table ajoutées précédemment à l'objet en cour
         * @param string|null $Critere 
         * @param mixed $Ordre 
         * @param string $SelectChamp En cas de jointure, la table principale est nommée T1 et les tables joint seront soit 
         *  nommée via leurs alias définit lors de leurs ajout ou bien un alias automatique commençant par j+numro d'ordre
         *  exemple j1, j2, j3, ...
         * @param mixed $GroupBy 
         * @param null|string $Limit 
         * @return null|mysqli_result 
         */
        public function JointureChargeListe(string $Critere=null,$Ordre=null,$SelectChamp="*", $GroupBy=null, ?string $Limit=null):?mysqli_result ;

        /**
         * Convertir l'objet en cour au format JSON
         */
        public function ToJSON():string ;

        /**
         * Retourne le nombre de ligne dans la base de donnée.
         * @return int 
         */
        public static function TotalLines():int ;
        
    }

    class xChampDB {
        const NUMERIC ='int(11)';
        const STRING ='varchar(255)';
        const TEXT ='text';
        const BINNARY ='BLOB';
        const DATE ='DATE' ;
        const TIME ='varchar(10)';
        const DATETIME ='DATETIME' ;
        const JSON = 'JSON';

        public string  $Nom ; 
        public $Valeur ;
        public string $Type ;

        public function __construct($Nom=null,$Val=null,$TypeChamp='varchar(255)'){
            $this->Nom='';
            $this->Valeur='';
            $this->Type=self::STRING;
            if (isset($Nom)){
                $this->Nom=$Nom ;
                if (isset($Val)){
                    $this->Valeur=$Val ;
                }
            }
            if (isset($TypeChamp)){
                $this->Type=$TypeChamp ;
            }
        }

        public function GetTypeChamp():string{
            $Typ=self::STRING ;
            $ch=strtolower($this->Nom) ;
            if (substr($ch,0,2)=='id' || substr($ch,0,strlen('total'))=='total' || substr($ch,0,strlen('montant'))=='montant' || substr($ch,0,strlen('prix'))=='prix' 
            || substr($ch,0,strlen('nb'))=='nb' || substr($ch,0,strlen('qte'))=='qte' || substr($ch,0,strlen('solde'))=='solde'
            || substr($ch,0,strlen('solde'))=='solde' || substr($ch,0,strlen('quantite'))=='quantite' ){
                $Typ=self::NUMERIC ;
            }
            if (substr($ch,0,strlen('date'))=='date' ){
                $Typ=self::DATE ;
                if(isset($this->Valeur)){
                    if(strlen($this->Valeur)>10){
                        $Typ = self::DATETIME ;
                    }
                }
            }elseif(substr($ch,0,strlen('heure'))=='heure'){
                $Typ=self::TIME;
            }

            if (substr($ch,0,strlen('text'))=='text' ){
                $Typ=self::TEXT ;
            }
            if (substr($ch,0,strlen('json'))=='json' ){
                $Typ=self::JSON ;
            }

            return $Typ ;
        }

    }

    class xDBFieldType{
        //numerics
            const BIT= 16;
            const TINYINT= 1;
            const BOOL= 1;
            const SMALLINT= 2;
            const MEDIUMINT= 9;
            const INTEGER= 3;
            const BIGINT= 8;
            const SERIAL= 8;
            const FLOAT= 4;
            const DOUBLE= 5;
            const DECIMAL= 246;

        //dates
            const DATE= 10;
            const DATETIME= 12;
            const TIMESTAMP= 7;
            const TIME= 11;
            const YEAR= 13;

        //strings & binary
            const CHAR= 254;
            const VARCHAR= 253;
            const ENUM= 254; 
            const SET= 254;
            const BINARY= 254;
            const VARBINARY= 253;
            const TINYBLOB= 252;
            const BLOB= 252;
            const MEDIUMBLOB= 252;
            const TINYTEXT= 252;
            const TEXT= 252;
            const MEDIUMTEXT= 252;
            const LONGTEXT= 252;

            public static array $TypeChampStringBinaire ;
            public static array $TypeChampNumeric ;
            public static array $TypeChampDateTime ;
            public static array $ListeType;

            public static array $ListeTypeName ;

            public function __construct(){
                self::$ListeType=[];

                self::$TypeChampNumeric=[];
                self::$TypeChampNumeric['1']=self::BOOL ;
                self::$TypeChampNumeric['2']=self::SMALLINT ;
                self::$TypeChampNumeric['3']=self::INTEGER ;
                self::$TypeChampNumeric['4']=self::FLOAT ;
                self::$TypeChampNumeric['5']=self::DOUBLE ;
                self::$TypeChampNumeric['8']=self::BIGINT ;
                self::$TypeChampNumeric['9']=self::MEDIUMINT ;
                self::$TypeChampNumeric['16']=self::BIT ;
                self::$TypeChampNumeric['246']=self::DECIMAL ;                
                self::$ListeTypeName[self::BOOL]="BOOL";
                self::$ListeTypeName[self::SMALLINT]="SMALLINT";
                self::$ListeTypeName[self::INTEGER]="INTEGER";
                self::$ListeTypeName[self::FLOAT]="FLOAT";
                self::$ListeTypeName[self::DOUBLE]="DOUBLE";
                self::$ListeTypeName[self::BIGINT]="BIGINT";
                self::$ListeTypeName[self::MEDIUMINT]="MEDIUMINT";
                self::$ListeTypeName[self::BIT]="BIT";
                self::$ListeTypeName[self::DECIMAL]="DECIMAL";
                
                self::$TypeChampDateTime=[];
                self::$TypeChampDateTime['7']=self::TIMESTAMP ;
                self::$TypeChampDateTime['10']=self::DATE ;
                self::$TypeChampDateTime['11']=self::TIME ;
                self::$TypeChampDateTime['12']=self::DATETIME ;
                self::$TypeChampDateTime['13']=self::YEAR ;
                self::$ListeTypeName[self::TIMESTAMP]="TIMESTAMP";
                self::$ListeTypeName[self::DATE]="DATE";
                self::$ListeTypeName[self::TIME]="TIME";
                self::$ListeTypeName[self::DATETIME]="DATETIME";
                self::$ListeTypeName[self::YEAR]="YEAR";

                self::$TypeChampStringBinaire=[];
                self::$TypeChampStringBinaire['252']=self::TEXT ;
                self::$TypeChampStringBinaire['253']=self::VARCHAR ;
                self::$TypeChampStringBinaire['254']=self::BINARY ;
                self::$ListeTypeName[self::TEXT]="TEXT";
                self::$ListeTypeName[self::VARCHAR]="VARCHAR";
                self::$ListeTypeName[self::BINARY]="BINARY";

                foreach(self::$TypeChampNumeric as $IdType => $Val){
                    self::$ListeType[$IdType]=$Val ;
                }
                foreach(self::$TypeChampStringBinaire as $IdType => $Val){
                    self::$ListeType[$IdType]=$Val ;
                }
                foreach(self::$TypeChampDateTime as $IdType => $Val){
                    self::$ListeType[$IdType]=$Val ;
                }
                                

            }

            public function GetTypeName(int $TypeNumber, string $DefautType='VARCHAR(255)'):string{
                if(isset(self::$ListeTypeName[$TypeNumber])){
                    return self::$ListeTypeName[$TypeNumber] ;
                }
                return $DefautType;
            }
        
    }

?>