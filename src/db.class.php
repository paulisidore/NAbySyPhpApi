<?php
namespace NAbySy ;
    Class xDB{

        public $Main ;
        public $DebugMode ;
        public function __construct(xNAbySyGS $NAbySyGS){
            $this->Main = $NAbySyGS ;
            $this->DebugMode=$NAbySyGS->ActiveDebug ;
        }

        function ToArray($Reponse){
            $Liste=array();
            if ($Reponse){
                while ($row = $Reponse->fetch_assoc()) {
                    //array_push($Liste,$row);
                    $Liste[]=$row;
                }
            }
            return $Liste ;		
        }

        function ToJSON($ReponseMyQLi=null,$TableauArray=null){
            $Liste=array() ;
            $Liste = json_encode($Liste) ;
            if (isset($ReponseMyQLi)){
                $Liste=$this->ToArray($ReponseMyQLi);
                json_encode($Liste) ;
            }elseif (isset($TableauArray)) {
                $Liste = json_encode($TableauArray) ;
            }            
            return $Liste  ;		
        }

        public function utf8ize( $mixed ) {
            if (is_array($mixed)) {
                foreach ($mixed as $key => $value) {
                    $mixed[$key] = $this->utf8ize($value);
                }
            } elseif (is_string($mixed)) {
                return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
            }
            return $mixed;
        }
        public function EscapedForJSON($value){
            $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
            $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");

            return $this->utf8ize($value) ;
        }

        public function TableExiste($Table,$DBaseName=null){
            $TxSQL="SHOW TABLES like '".$Table."' " ;
            if (isset($DBaseName)){
                if(trim($DBaseName) !==''){
                    $TxSQL="SHOW TABLES FROM ".$DBaseName." like '".$Table."' " ;
                }
            }
            if ($this->DebugMode){
                //echo '</br>Vérifions la Présence de la Table '.$Table.': '.$TxSQL ;
            }               
            $reponse=$this->Main->ReadWrite($TxSQL,true,null,false);
            if (count($reponse->fetch_all())>=1){
                if ($this->DebugMode){
                    //echo '...Présent' ;
                }                    
                return true;
            }
            if ($this->DebugMode){
                $Tx=$Table;
                if (isset($DBaseName)){
                    $Tx=$DBaseName.".".$Table ;
                }
                $this->Main::$Log->Write('Absence de la table '.$Tx);
            }
            return false;
        }

        public function CreateTable($NomTable,$DBaseName=null){
            $ChampID="ID";
            if (isset($DBaseName)){
                $NomTable="`".$DBaseName."`.".$NomTable." " ;
            }
            $TxSQL="CREATE TABLE ".$NomTable." (
                ".$ChampID." INT(11) AUTO_INCREMENT PRIMARY KEY 
                );" ;
            if ($this->DebugMode)
                echo '</br>Création de la Table '.$NomTable.': ' ;
            $ok=$this->Main->ReadWrite($TxSQL,true,null,false) ;
            if ($ok>=1){
                if ($this->DebugMode)
                    echo '...OK' ;
            }else{
                if ($this->DebugMode)
                    echo '...ERREUR' ;
            }            
            return $ok ;
        }

        public function ChampsExiste($Table,$Champ,$DBaseName=null){
            //SHOW COLUMNS FROM bd_depot.`utilisateur` LIKE 'id'
            $TxSQL="SHOW COLUMNS FROM ".$Table." like '".$Champ."' "  ;
            if (isset($DBaseName)){
                $TxSQL="SHOW COLUMNS FROM ".$DBaseName.".".$Table." like '".$Champ."' " ;
            }
            if ($this->DebugMode){
                /*
                echo '</br>Vérifions la présence du champ '.$Table.': '.$Champ ;
                */
                //$this->Main::$Log->Write('Vérifions la présence du champ '.$Table.': '.$Champ." Avec ".$TxSQL);
            }                
            $reponse=$this->Main->ReadWrite($TxSQL,true,null,false);
            if (count($reponse->fetch_all())>=1){
                if ($this->DebugMode){
                    /* echo '...Présent</br>' ; */                    
                    //$this->Main::$Log->Write($Table.': '.$Champ.' présent');
                } 
                return true;               
            }
            if ($this->DebugMode){
                $Tx=$Table;
                if (isset($DBaseName)){
                    $Tx=$DBaseName.".".$Table ;
                }
                //$this->Main::$Log->Write('Absence du champ '.$Tx.".".$Champ);
            }
               
            return false;
        }

        public function AlterTable($NomTable,$NomChamp,$TypeVar='VARCHAR(255)',$AddOrChange='ADD',$ValDefaut='',$DBaseName=null){
            if (isset($DBaseName)){
                $NomTable="`".$DBaseName."`.`".$NomTable."` " ;
            }
            if (!isset($TypeVar)){
                $TypeVar = 'VARCHAR(255)';
            }
            if (!isset($AddOrChange)){
                $AddOrChange = 'ADD';
            }
            if (strtoupper(trim($AddOrChange)) == 'CHANGE'){
                $AddOrChange = "CHANGE COLUMN `".$NomChamp."` ";
            }
            if (!isset($ValDefaut)){
                $ValDefaut = '';
            }
            $TxSQL="ALTER TABLE ".$NomTable." ".$AddOrChange." `".$NomChamp."` ".$TypeVar." NOT NULL DEFAULT '".$ValDefaut."' " ;
            $TxSQL = str_replace("``","`",$TxSQL) ;

            $ok=$this->Main->ReadWrite($TxSQL,true,null,false) ;
            if ($ok>=1){
                if ($this->DebugMode){
                    //echo '...OK' ;
                }                   
            }else{
                if ($this->DebugMode){
                    //echo '...ERREUR' ;
                    $this->Main::$Log->Write("ERR: Impossible de créer le champ avec ".$TxSQL);
                    //return false;
                }                    
            }
            return true;
        }
        
        /**
         * Vérfie l'existence d'une base de donnée.
         * @param string|null $DBaseName Nom de la base de donnée. Si non fournit, le nom de la base de donnée par défaut sera utilisé.
         * @return bool 
         */
        public function DBExiste(string $DBaseName=null):bool{
            if(!isset($DBaseName)){
               return false;
            }
            if (trim($DBaseName)==""){
                return false;
            }
            $TxSQL="SHOW DATABASES like '".$DBaseName."' " ;            
            // if ($this->DebugMode)
            //     echo '</br>Vérifions la Présence de la Table '.$Table.': '.$TxSQL ;
            $reponse=$this->Main->ReadWrite($TxSQL,null,null,null,null,null,false);
            if($reponse->num_rows>0){
                return true;
            }            
            return false;
        }
    }

?>