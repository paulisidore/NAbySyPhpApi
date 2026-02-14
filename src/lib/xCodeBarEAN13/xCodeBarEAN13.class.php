<?php
    namespace NAbySy\GS\CodeBar ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\xDB;
use xNAbySyLabelPrinter;

    //require_once 'xNAbySyLabelPrinter.class.php' ;

    Class xCodeBarEAN13{
        public $Boutique ;
        public $PrefixCode ;
        public $Main ;
        public $TEntete ;
        public $Active;
        public $RS ;
        public $DBase ;

        public function __construct(xBoutique $Boutique,$dbname="nabysygs")
        {
            $this->Boutique = $Boutique ;
            $this->Main=$Boutique->Main ;
            $this->DBase=$dbname ;
            $this->TEntete="xcodebarean13" ;
            $this->Active=false ;
            $this->Init() ;
        }
        private function Init(){
            $nabysy=$this->Main ;
            $MySQL=new xDB($this->Main) ;
            $MySQL->DebugMode=false ;
            if ($MySQL->TableExiste($this->TEntete,$this->DBase)){
                $this->Active=true ;
                $this->Charge() ;
            }
        }

        public function Charge($Id=null){
            if (!$this->Active){
                return false ;
            }
            $nabysy=$this->Main ;
            $Table=$this->DBase.".".$this->TEntete ;
            $TxSQL="select * from ".$Table." where ID>0 " ;
            if (isset($Id)){
                $TxSQL .=" AND ID=".$Id ;
            }
            $Reponse=$nabysy->ReadWrite($TxSQL) ;
            if ($Reponse->num_rows>0){
                $rw=$Reponse->fetch_assoc() ;
                $this->RS=$rw ;
                $this->PrefixCode=$rw['PreFixCode'] ;
            }
            return true ;
        }

        public function IsCodePdt($TxCodeBar){
            
            if (!$this->Active){
                return false ;
            }
            
            if (!$this->RS){
                return false ;
            }
            $Longeur=strlen($TxCodeBar);
            if (strlen($this->PrefixCode)>$Longeur){
                return false ;
            }
            //Il y a des lecteur qui renvoie le CheckSum du Code EAN13 doonc on aura soit 13 ou 12 chiffres
            if ($Longeur<>13){
                if ($Longeur<>12){
                    return false ;
                }
            }
            $Prefix=substr($TxCodeBar,0,strlen($this->PrefixCode)) ;
            if ($Prefix != $this->PrefixCode){
                return false ;
            }
            
            return true ;

        }

        public function GetCodePdt($TxCodeBar){
            if (!$this->Active){
                return null ;
            }
            if (!$this->IsCodePdt($TxCodeBar)){
                return null ;
            }
            $Longeur=strlen($TxCodeBar);
            $StartP=strlen($this->PrefixCode) ;
            if ($Longeur==12){
                $MaxL=$Longeur-$StartP ;
            }else{
                $MaxL=$Longeur-$StartP-1 ;
            }
            $Code=substr($TxCodeBar,$StartP,$MaxL) ;
            //var_dump($Code) ;
            //exit;
            $Code=(int)$Code ;
            return $Code ;
        }
        public function GetMCPEAN13Code($CodePdt){
            if (!$this->Active){
                return null ;
            }
            
            $Longeur=strlen($CodePdt);
            $StartP=strlen($this->PrefixCode) ;
            if ($Longeur>=12){
                return $CodePdt ;
            }else{
                $MaxL=$Longeur-$StartP-1 ;
            }
            $LongP=strlen($this->PrefixCode) ;
            $NbZero=12-($LongP+$Longeur) ;
            $Zero=str_repeat('0',$NbZero) ;
            $Code=$this->PrefixCode.$Zero.$CodePdt ;
            //$Code=(int)$Code ;
            return $Code ;
        }

        public function ImprimeCodeBarEAN13(xProduit $Article,$CodeB,$NbEtiquette=1){
            if (!isset($Article)){
                return false ;
            }
            /* $PrtNab=new xNAbySyLabelPrinter($this) ;
            $PrtNab->PrintTitre() ;
            $PrtNab->PrintArticleName($Article->Nom) ;
            $PosY=$PrtNab->PrintBarCode($CodeB)+6 ;
            $PrtNab->PrintPrixArticle($Article->PrixU." F CFA",$PosY) ;
            $PrtNab->Imprimer() ; */

        }

    }
?>