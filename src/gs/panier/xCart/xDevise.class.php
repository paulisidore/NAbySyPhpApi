<?php
namespace NAbySy\GS\Panier;

    Class xDevise {
        public $Id;
        public $Nom;
        public $TauxEchange;
        
        public $TEntete ;
        public $EnteteTable;
        
        public $Boutique ;
        public $DBase ;
        public $Main;

        public $RS;
        
        
        public function __construct($Boutique=null,$Id=0){
            $this->Id=0 ;
            $this->Nom='F CFA' ;
            $this->TauxEchange=1 ;
            if ($Boutique){
                $this->Boutique=$Boutique ;
                $this->Main = $Boutique->Main ;
                $this->DBase=$Boutique->DBase ;
            }
            
            if (!$this->Main){
                //$this->Main=parent ;
                return ;
            }
            $this->Id=$Id;
            $this->TEntete=$this->EnteteTable;
            if ($this->EnteteTable==''){
                $this->EnteteTable = 'devises' ;
                $this->TEntete="devises";
            }

            if ($this->Main->TableExiste($this->TEntete)==false){
                echo "La table ".$this->TEntete." n'esixte pas.</br>" ;
                $this->Main->MAJ_DB();
            }

            if ($Id > 0) {
                if ($this->Main){	
                    $this->RS=$this->Charge($this->Id) ;
                }			
            }
            
        }

        public function Charge($IdC=null){
            $TxC="" ;
            if (isset($IdC)){
                $TxC=" AND id='".$IdC."' " ;
            }
            $TxSQL="select C.* from ".$this->DBase.".".$this->TEntete." C where id>0 ".$TxC ;
            $OK=false;
            $reponse=$this->Main->ReadWrite($TxSQL) ;
            if (!$reponse){
                $this->Id=0 ;
                $this->Nom='F CFA' ;
                $this->TauxEchange=1 ;
                return null ;
            }
                
            
            $row = $reponse->fetch_assoc() ;
            $this->Id=$row['ID'] ;
            $this->Nom=$row['NOM'] ;
            $this->TauxEchange=$row['TAUX'] ;
            
            return $row ;
            
        }

        public function Save(){		
            if ($this->Id >0){
                $sql="update ".$this->DBase.".".$this->TEntete." SET Nom='".$this->Nom."', 
                    Taux='".$this->TauxEchange."' where id='".$this->Id."' limit 1" ;
                    $this->Main->ReadWrite($sql,null,true) ;
            }else{
                //Ajout de nouveau Officine
                $sql="insert into ".$this->DBase.".".$this->TEntete." (`Nom`) VALUE('".$this->Nom."')" ;
                $NewId=$this->Main->ReadWrite($sql,null,true,0,null,$this->DBase.".".$this->TEntete,true) ;
                $this->Id=$NewId ;
                $sql="update ".$this->DBase.".".$this->TEntete." SET Nom='".$this->Nom."', 
                    Taux='".$this->TauxEchange."' where id='".$this->Id."' limit 1" ;
                    $this->Main->ReadWrite($sql,null,true) ;
            }
            return $this->Id ;
            
        }
        
        public function GetListe($Nom=null,$Taux=null){
            if ($this->Main->TableExiste($this->TEntete)==false){
                echo "La table ".$this->TEntete." n'esixte pas.</br>" ;
                $this->Main->MAJ_DB();
            }

            $TxC="" ;
            if (isset($Nom)){
                $TxC=" AND C.NOM like '%".$Nom."%' " ;
            }
            if (isset($Taux)){
                $TxC=" AND C.TAUX = '".$Taux."' " ;
            }
            $TxSQL="select C.* from ".$this->DBase.".".$this->TEntete." C 
            where C.ID>0 ".$TxC ;
            $OK=false;
            $reponse=$this->Main->ReadWrite($TxSQL) ;
            if (!$reponse)
                return null ;
            
            return $reponse ;
            
        }
        public function Supprimer($Ids=null){
            $xId=$this->Id ;
            if (isset($Ids)){
                $xId=$Ids ;
            }
            $sql="delete from ".$this->DBase.".".$this->TEntete." where ID='".$xId."' limit 1" ;
            $this->Main->ReadWrite($sql,null,true) ;

            if ($xId==$this->Id){
                $this->Id=0 ;
            }

            return true ;
        }

        public function Convertion($Montant){
            if ($Montant==''){
                $Montant=0 ;
            }
            $MtConv=$Montant ;
            if ($this->TauxEchange>1){
                $MtConv=$Montant/$this->TauxEchange ;
            }
            //$this->Main->FormatNB($MtConv) ;
            return $MtConv ; 
        }

        public function ConvertionEnMonnaieLocal($Montant){
            if ($Montant==''){
                $Montant=0 ;
            }
            $MtConv=$Montant ;
            if ($this->TauxEchange>1){
                $MtConv=$Montant*$this->TauxEchange ;
            }
            //return $this->Main->FormatNB($MtConv) ;
            return $MtConv ; 
        }

        public function GetSelectBox($ListeRow=null,$Champ='NOM', $BoxId=null,$ChoixDefaut=null, 
            $CssClass='
                class="browser-default custom-select custom-select-lg mb-3"
            '){
            if (!isset($ListeRow)){
                $ListeRow=$this->GetListe();
            }
            $TxId="";
            if (isset($BoxId)){
                $TxId="id='".$BoxId."' ";
            }
            $html="<select ".$TxId.">" ;
            $html .="<option value='-1' >Aucune Devise</option>" ;

            $DejaSelect=false;
            $Liste=$this->Main->EncodeReponseSQL($ListeRow) ;
            $Liste=$this->Main->EscapedForJSON($Liste) ;
            $TxChDef="-1";
            if (isset($ChoixDefaut)){
                $TxChDef=$ChoixDefaut ;
            }
            foreach ($Liste as $row){
                $TxSelected="";
                if ( $DejaSelect==false){
                    if ($TxChDef==$row[$Champ] or $TxChDef==$row['ID']){
                        $TxSelected="selected";
                        $DejaSelect=true;
                    }
                }
                $Ligne="<option value='".$row['ID']."' ".$TxSelected." >".$row[$Champ]."</option>" ;
                $html .=$Ligne ;
            }
            
            $html .="</select>" ;
            return $html ;
        }

        public function GetDataTable($ListeRow=null, $BoxId=null,$ChoixDefaut=null){
            $TxId='';
            if (isset($BoxId)){
                $TxId='id="'.$BoxId.'"' ;
            }
            if (!isset($ListeRow)){
                $ListeRow=$this->GetListe();
            }

            $Liste=$this->Main->EncodeReponseSQL($ListeRow) ;
            $Liste=$this->Main->EscapedForJSON($Liste) ;
            $Tableau="" ;
            foreach ($Liste as $row){
                $BttSupp="<button id='Supprimer".$row['ID']."' onClick='SupprimerDevise(".$row['ID'].");'>Supprimer</button>   " ;
                $BttEdit="<button id='Modifier".$row['ID']."' onClick='ModifierDevise(".$row['ID'].");'>Modifier</button>   " ;
                $Li="<tr>".
                            "<td>".$row['ID']."</td>".
                            "<td>".$row['NOM']."</td>".
                            "<td>".$row['TAUX']."</td>".
                            "<td>".$BttSupp."</td>".
                            "<td>".$BttEdit."</td>".
                    "</tr>" ;
                $Tableau .=$Li ;
            }
            $html='
                <table '.$TxId.' border="1" cellspacing="0" cellpadding="5">
                    <thead>
                        <tr>
                        <th scope="col">Id</th>
                        <th scope="col">Nom</th>
                        <th scope="col">Taux</th>
                        <th scope="col">M</th>
                        <th scope="col">S</th>
                        </tr>
                    </thead>
                    <tbody>'
                            .$Tableau.'
                    </tbody>
                </table> 
            ';

            return $html ;

        }

        public function GetJSON($ListeRow){
            if (!isset($ListeRow)){
                return '[]';
            }           
            $json="" ;
            $Liste=$this->Main->EncodeReponseSQL($ListeRow) ;
            $Ligne=$this->Main->EscapedForJSON($Liste) ;
            $json=json_encode($Ligne) ;

            return $json ;
                
        }
        
    }

?>