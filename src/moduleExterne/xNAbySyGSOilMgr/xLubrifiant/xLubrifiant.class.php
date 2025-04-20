<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use DateTime;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;
use xNAbySyGS;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 * 
 * Module de Gestion produits Lubrifiants
 */
class xLubrifiant extends xProduitLiaison {

    public static ?xCuveStockageCarburant $CuveMgr = null;

    /**
     * Liste des différentes famille des produits de la Station d'Essence
     * @var null|xORMHelper
     */
    public static ?xORMHelper $FamilleLubrifiant = null;

    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="produits"){
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        self::Init();
    }

    private static function Init():bool{
        self::$CuveMgr=new xCuveStockageCarburant(self::$xMain);
        self::$FamilleLubrifiant = self::$CuveMgr::GetFamillePdtStationByName(self::$CuveMgr::FAMILLE_LUBRIFIANT) ;
        return true ;
    }

    /**
     * Place un article NAbySyGS dans la catégorie des Lubrifiants
     */
    public static function AttacheToNAbySyPdt(xORMHelper $Produit):bool{
        if ($Produit->Id == 0){
            return false;
        }
        if ((int)$Produit->IdFamille !== self::$FamilleLubrifiant->Id){
            $TxJ= "Le produit ".$Produit->Designation." a été ajouté à la famille ".self::$FamilleLubrifiant->Nom ;
            $Produit->IdFamille == self::$FamilleLubrifiant->Id ;
            if ($Produit->Enregistrer()){
                $Produit->AddToJournal("MODIFICATION",$TxJ);
            }
        }       
        return true;
    }

    /**
     * Retourne la liste des Produits classés comme lubrifiant.
     * Si Désignation est fournit, la recherche sera limitée à cette information
     * @param string|null $Designation 
     * @return array | Liste d'objet xLubrifiant
     */
    public static function GetListeLubrifiant(string $Designation = null):array{
        $Pdt=new xProduit(self::$xMain);
        $ListePdt = [] ;
        $Critere = "IDFAMILLE = '".self::$FamilleLubrifiant->Id."'" ;
        if (isset($Designation)){
            if ($Designation !==""){
                $Critere  .= " and ".$Designation ;
            }
        }
        $Lst = $Pdt->ChargeListe($Critere,"DESIGNATION");
        if ($Lst){
            while($rw = $Lst->fetch_assoc()){
                $Lubr=new xLubrifiant(self::$xMain,$rw['ID'],self::$xMain::GLOBAL_AUTO_CREATE_DBTABLE);
                if ($Lubr->Id){
                    $ListePdt[] = $Lubr ;
                }
            }
        }
        return $ListePdt ;

    }

    /**
     * Retourne le volue vendu sur une période
     * @param string|null $DateD : Date de début
     * @param string|null $DateF : Date de fin
     * @param int $IdPdt
     * @param string $GroupBy
     * @return float 
     */
    public function GetVolumeVendu(string $DateD=null, string $DateF=null, int $IdPdt = null, string $GroupBy=null):float{
        $DteD=$DateD;
        $DteF=$DateF;
        if (!isset($DteD)){
            $DteD=date("Y-m-d");
        }
        if (!isset($DteF)){
            $DteF=$DteD;
        }
        $dateD=new DateTime($DteD);
        $dateF=new DateTime($DteF);
        $Critere="D.IDPRODUIT > 0 ";
        if(isset($IdPdt)){
            $Critere="D.IDPRODUIT = ".$IdPdt;
        }else{
            if ($this->Id>0){
                $Critere="D.IDPRODUIT = ".$this->Id;
            }
        }
        if ($dateD){
            if($dateF){
                $Critere .=" and (D.DATEFACTURE >='".$dateD->format("Y-m-d")."' AND D.DATEFACTURE <= '".$dateF->format("Y-m-d")."') ";
            }else{
                $Critere .=" and D.DATEFACTURE ='".$dateD->format("Y-m-d")."' ";
            }            
        }
        $ChampVolume=self::CHAMP_VOLUME ;
        $TxSQL = "select (SUM(D.QTE)*P.".$this->$ChampVolume.") as 'VOLUMEVENDU' from detailfacture D
        left outer join `".$this->DataBase."`.`".$this->Table."` P on P.ID = D.IDPRODUIT
        left outer join `".self::$xMain->DataBase."`.`rayon` R on R.ID = P.IDRAYON
        left outer join `".self::$FamilleLubrifiant->DataBase."`.`".self::$FamilleLubrifiant->Table."` F on F.ID = P.IDFAMILLE ".$Critere ;

        if (isset($GroupBy)){
            if (trim($GroupBy !=="" )){
                $TxSQL .=" Group By ".$GroupBy ;
            }
        }
        $Lst=$this->ExecSQL($TxSQL);
        $VolumeV=0.0;
        if($Lst->num_rows){
            while($rw=$Lst->fetch_assoc()){
                $VolumeV += (float)$rw['VOLUMEVENDU'];
            }
        }
        return $VolumeV ;
    }

    public static function GetStatistiqueVente(string $DateD=null, string $DateF=null, string $GroupBy = null , string $OrderBy = "R.NOM, P.DESIGNATION"){
        $DteD=$DateD;
        $DteF=$DateF;
        if (!isset($DteD)){
            $DteD=date("Y-m-d");
        }
        if (!isset($DteF)){
            $DteF=$DteD;
        }
        $dateD=new DateTime($DteD);
        $dateF=new DateTime($DteF);

        $Critere="Where P.IDFAMILLE='".self::$FamilleLubrifiant->Id."' ";
        $CritereLiv="l.IDPRODUIT > 0 " ;

        if(isset($IdPdt)){
            $Critere .="and D.IDPRODUIT = ".$IdPdt;
            $CritereLiv .=" and l.IDPRODUIT=".$IdPdt ;
        }
        if ($dateD){
            if($dateF){
                $Critere .=" and (D.DATEFACTURE >='".$dateD->format("Y-m-d")."' AND D.DATEFACTURE <= '".$dateF->format("Y-m-d")."') ";
                $CritereLiv .= " and (l.DATELIVREE >='".$dateD->format("Y-m-d")."' AND l.DATELIVREE <= '".$dateF->format("Y-m-d")."') ";
            }else{
                $Critere .=" and D.DATEFACTURE ='".$dateD->format("Y-m-d")."' ";
                $CritereLiv .= " and l.DATELIVREE ='".$dateD->format("Y-m-d")."' ";
            }
        }

        $Pdt=new xORMHelper(self::$xMain,null,false,"produits");

        $TxLiv="(select sum(l.QTE) from detailbl l where ".$CritereLiv." group by l.IDPRODUIT) ";

        $TxSQL = "select P.Designation, P.ID, P.".self::CHAMP_VOLUME.", P.UniteVolume,P.PrixVenteTTC,P.PrixAchatTTC, 
        (SUM(D.QTE)*P.".self::CHAMP_VOLUME.") as 'VOLUMEVENDU', sum(D.QTE) as 'QTEVENDUE', count(D.IDFACTURE) as 'NBFACTURE', 
        F.Nom as 'FAMILLE', R.NOM as 'RAYON', IFNULL(".$TxLiv.",0) as 'QTELIVREE', P.STOCK from detailfacture D
        left outer join `".self::$xMain->DataBase."`.`".$Pdt->Table."` P on P.ID = D.IDPRODUIT 
        left outer join `".self::$xMain->DataBase."`.`rayon` R on R.ID = P.IDRAYON
        left outer join `".self::$FamilleLubrifiant->DataBase."`.`".self::$FamilleLubrifiant->Table."` F on F.ID = P.IDFAMILLE ".$Critere ;        

        if(!isset($GroupBy)){
            $GroupBy = " P.ID " ;
        }
        if ($GroupBy !== ""){
            $TxSQL .=" Group By ".$GroupBy ;
        }

        if (isset($OrderBy)){
            if(trim($OrderBy !=="")){
                $TxSQL .=" ORDER BY ".$OrderBy ;
            }
        }       

        $Lst=$Pdt->ExecSQL($TxSQL);

        $Liste=[];
        $VolumeV=0.0;
        if($Lst->num_rows){
            while($rw=$Lst->fetch_assoc()){
                $VolumeV += (float)$rw['VOLUMEVENDU'];
                $rw['DATE_DU'] = $dateD->format("Y-m-d");
                $rw['DATE_AU'] = $dateF->format("Y-m-d");
                $Liste[]=$rw;
            }
        }
        return $Liste ;
    }
}

?>