<?php
    include_once 'mod_ext/rb.php' ;   
    use RedBeanPHP\SimpleModel;
    
    /**
     * Objet Relation Manager: Gère la connexions aux différents base de données relationnelle
     * Auteur: Paul & Aïcha Machinerie
     * Support: paul_isidore@hotmail.com
     */
    Class xORM {
        public $RS;
        public $Table ;
        public $DataBase ;

        public function __construct(xNAbySyGS $nabysy, $DBase,$NomTable,$ID=null){
            
            $this->DataBase=strtolower($DBase) ;

            if (!R::hasDatabase(strtolower($this->DataBase))){
                R::addDatabase($this->DataBase,"mysql:host=".$nabysy->db_serveur.";dbname=".strtolower($this->DataBase),$nabysy->db_user,$nabysy->db_pass) ;
            }

            R::selectDatabase($this->DataBase) ;
            $TxDB=$NomTable ;
            if ($this->DataBase !== ''){
                $TxDB=$NomTable ;
            }
            if ($NomTable==''){
                return null ;
            }
            $this->Table=$TxDB ;

            $xRS=R::dispense($NomTable );
            $this->RS=$xRS;

            //echo $TxDB ;

            if (isset($ID)){
                $this->Charge($ID) ;
            }
        }

        public function Save(){
            $id = R::store($this->RS);
            return $id ;
        }

        public function Charge($ID){
            //retrouver
            $this->RS = R::load( $this->Table, $ID );
            //$this->RS=R::findOne( $this->Table, ' id = ? ', [ $ID ] ) ;
        }

        public function Delete(xORM $ROW){
            if (isset($ROW->RS)){
                R::trash($ROW->RS);
                return true ;
            }
        }
    }


    