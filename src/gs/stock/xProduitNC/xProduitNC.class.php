<?php
    /**
     * Module de gestion des Produits Non Enregistrés dans la base de donnée du stock
     * mais vendu sous forme générique tel que les fruits et légume et autre articles pas encore crée mais disponible
     * dans la salle de vente.
     */
    namespace NAbySy\GS\Stock ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\ORM\xChampDB;
use NAbySy\xNAbySyGS;

    class xProduitNC extends xProduit {
        public xCodeBarShema $CodeBarShema;

        private array $MaListeChamp;
        public bool $IsReady;
        public function __construct(xNAbySyGS $NAbySy,int $Id=null,$AutoCreateTable=false,$TableName='produitsnc', 
            xBoutique $Boutique=null, $CodeBar=null){
            if (!isset($TableName)){
                $TableName="produitsnc";
            }
            if ($TableName==""){
                $TableName="produitsnc";
            }
            
            parent::__construct($NAbySy,null,$AutoCreateTable,"produitsnc",$Boutique);
            $this->CodeBarShema=new xCodeBarShema($NAbySy);
            $this->MaListeChamp=[];
            $this->IsReady=true;
            if (isset($CodeBar)){
                if ($this->IsPdtNC($CodeBar)){
                    $this->ChargeCodeBar($CodeBar);
                }
            }
        }

        /**
         * Indique si un CodeBar désigne un produit non classé
         */
        public function IsPdtNC($CodeB):bool{
            //On vérifie s'il s'agit un CodeBar NC
            $ListeC=" LENGTH(CODEBAR) AS LongCB, ".$this->CodeBarShema->Table.".* ";
            $Lst=$this->CodeBarShema->ChargeListe(null,null,$ListeC);
            
            if ($Lst->num_rows==0){
                return false;
            }
            while ($rw=$Lst->fetch_assoc()){
                $NcCode=$rw['CODEBAR'];
                $IdShema=$rw['ID'];
                $LongCB=$rw['LongCB'];
                $Critere="SUBSTRING('".$CodeB."', 1, ". $LongCB.") like CODEBAR and Id=".$IdShema;
                $Rep=$this->CodeBarShema->ChargeListe($Critere);
                //var_dump($Critere);
                if ($Rep->num_rows>0){
                    //On a trouvé
                    //var_dump($Rep);
                    if ((int)$rw['NBCHARMAX']<>0){
                        // on vérifie la taille du codebar pour plus de précision
                        //var_dump($rw);
                        if (strlen($CodeB) == (int)$rw['NBCHARMAX']){
                            return true;
                        }
                    }else{
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Retourne Shema correspondat au codebar non classé
         */
        public function GetShemaCodeBar($CodeB):?xCodeBarShema{
            //On vérifie s'il s'agit un CodeBar NC
            $ListeC=" LENGTH(CODEBAR) AS LongCB , ".$this->CodeBarShema->Table.".* ";
            $Lst=$this->CodeBarShema->ChargeListe(null,null,$ListeC);
            if ($Lst->num_rows==0){
                return null;
            }
            while ($rw=$Lst->fetch_assoc()){
                $NcCode=$rw['CODEBAR'];
                $IdShema=$rw['ID'];
                $LongCB=$rw['LongCB'];
                $Critere="SUBSTRING('".$CodeB."', 1, ". $LongCB.") like CODEBAR and Id=".$IdShema;
                $Rep=$this->CodeBarShema->ChargeListe($Critere);
                if ($Rep->num_rows>0){
                    //On a trouvé
                    if ((int)$rw['NBCHARMAX']<>0){
                        // on vérifie la taille du codebar pour plus de précision
                        if (strlen($CodeB) == (int)$rw['NBCHARMAX']){
                            $this->CodeBarShema=new xCodeBarShema($this->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE);
                            return $this->CodeBarShema;;
                        }
                    }else{
                        $this->CodeBarShema=new xCodeBarShema($this->Main,$rw['ID'],$this->Main::GLOBAL_AUTO_CREATE_DBTABLE);
                        return $this->CodeBarShema;
                    }
                }
            }
            return null;
        }

        public function ChargePdtNC($CodeB):bool{
            //var_dump($CodeB);
            if ($CodeB==''){
                return false;
            }
            $this->GetShemaCodeBar($CodeB);
            if ($this->CodeBarShema->Id<1){
                return false;
            }
            //var_dump($CodeB);
            $LenCode=strlen($this->CodeBarShema->CODEBAR);
            $PosPrix=$LenCode;
            if ($this->CodeBarShema->POSPRIX>$LenCode){
                if ($this->CodeBarShema->POSPRIX>0){
                    $PosPrix=$this->CodeBarShema->POSPRIX ;
                }
            }
            //var_dump(substr($CodeB,$PosPrix));
            $PrixVente=substr($CodeB,$PosPrix);
            $OffsetLen=(int)$this->CodeBarShema->NBCHARPRIX - (int)$this->CodeBarShema->NBCHARCHECKSUM;
            //var_dump($OffsetLen);
            if ($OffsetLen>0){
                $PrixVente=(int)substr($CodeB,$PosPrix,$OffsetLen);
            }            
            $this->MaListeChamp=[];

            $Chx=new xChampDB("ISCLOWN",1);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("IDCLOWN",$this->CodeBarShema->Id);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("CodeBar",$CodeB);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("PrixVenteTTC",$PrixVente);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("PrixAchatTTC",0);
            $this->MaListeChamp[]=$Chx;
            

            $Chx=new xChampDB("Designation",$this->CodeBarShema->Libelle);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("TVA",(int)$this->CodeBarShema->TVA);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("RETIRER_TVA",(int)$this->CodeBarShema->RETIRER_TVA);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("ID",(-1 * $this->CodeBarShema->Id));
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("IdProduit",(-1 * $this->CodeBarShema->Id));
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("NbUnite",1);
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("UniteD","pcs");
            $this->MaListeChamp[]=$Chx;

            $Chx=new xChampDB("UniteC","carton");
            $this->MaListeChamp[]=$Chx;

            foreach($this->MaListeChamp as $Champ){
                $NomChamp=$Champ->Nom;
                $this->$NomChamp = $Champ->Valeur;
            }

            $this->IsReady=true;
            return true ;
        }

        public function ChargeCodeBar($CodeB)
        {
            $this->ChargePdtNC($CodeB);
        }

        
        public function __get($NomChamp){
           
            //var_dump($this->CodeBarShema->Libelle);
            //var_dump($this->CodeBar);
            //var_dump($this->PrixVenteTTC);
            if (strtolower($NomChamp)==strtolower("Designation")){
                //On retourne la désignation du produits non classé
                
                //echo __CLASS__." ligne ".__LINE__." Il cherche le libellé ";
                //var_dump($this->CodeBarShema->Libelle);
                //return $this->CodeBarShema->Libelle;
            }
            if (strtolower($NomChamp)==strtolower("STOCK")){
                //On retourne le stock du produits non classé
                return 0;
            }
            if (strtolower($NomChamp)==strtolower("IMAGE")){
                //On retourne le nom du fichier image du produits non classé
                return "";
            }
            if (strtolower($NomChamp)==strtolower("SeuilCritique")){
                //On retourne le seuil critique du produits non classé
                return 0;
            }
           
            if (strtolower($NomChamp)==strtolower("ETAT")){
                //On retourne l'Etat du produits non classé
                return "A";
            }
            if (strtolower($NomChamp)==strtolower("VENTEDETAILLEE")){
                //On retourne l'Etat de vente au détail du produits non classé
                return "NON";
            }

            if (!isset($this->MaListeChamp)){
                $ListeChamp=$this->ToArray();
                foreach ($ListeChamp as $Champ){
                    if (strtolower($Champ['Nom'])==strtolower($NomChamp)){
                        return $Champ['Valeur'];
                    }
                }
                return '';
            }
            
            foreach ($this->MaListeChamp as $Champ){
                if (strtolower($Champ->Nom)==strtolower($NomChamp)){
                    //echo __CLASS__.": Line ".__LINE__." ".$NomChamp."=".$Champ->Valeur."</br>";
                    return $Champ->Valeur;
                }
            }

            //Retourne pour tous les autres
            //var_dump($NomChamp);
            //echo __CLASS__.": Line ".__LINE__." ".$NomChamp." Introuvable ici. Nombre de champ: ".count($this->MaListeChamp)."</br>";
            return "0";
            
        }

        public function Enregistrer(): bool{
            //On a pas besoin d'enregirter les données dans la base de stock

            return true;
        }
    }
?>