<?php
namespace NAbySy\Lib\ModuleExterne\OilStation ;

use NAbySy\GS\Stock\xProduit;
use xNAbySyGS;

/**
 * Module NAbySy GS pour la gestion des Hydrocarbures. Station Essence
 * Par Paul isidore A. NIAMIE
 * Cette classe represente le stock lié à un produit de la sation d'essence
 */
class xProduitLiaison extends xProduit {

    /**
     * Champ contenant le Volume en Litre des articles de la Base de donnée NAbySyGS
     */
    public const CHAMP_VOLUME = "VOLUMECARTON";

    public function __construct(xNAbySyGS $NabySy,?int $Id=null,$CreationChampAuto=true,$TableName="produits"){
        if ($TableName==''){
            $TableName="produits";
        }
        parent::__construct($NabySy,(int)$Id,$CreationChampAuto,$TableName);
        if (!$this->MySQL->ChampsExiste($this->Table,"UniteVolume")){
            $this->MySQL->AlterTable($this->Table,"UniteVolume",null,null,"Litre(s)");
        }
    }

    /**
     * Retourne un Produit NAbySyGS selon sa désignation ou son Codebar
     * @param string|null $NomPdt 
     * @param string|null $CodeBar 
     * @return null|xProduitLiaison 
     */
    public function GetPdtNAbySyGS(?string $NomPdt=null, ?string $CodeBar=null):?xProduitLiaison{
        if (!isset($NomPdt) && !isset($CodeBar)){
            return null;
        }
        $Critere="ID>0 ";
        if($NomPdt){
            if($NomPdt !==""){
                $Critere = " AND Designation like '".$NomPdt."'";
            }
        }
        if(isset($CodeBar)){
            if($CodeBar !==""){
                $vCD=$this->Main::EscapedForJSON($CodeBar);
                $Critere .=" AND (CODEBAR like '".$vCD."' OR CODEBAR2 like '".$vCD."' OR CODEBAR3 like '".$vCD."') " ;;
            }
        }
        
        $Lst=$this->ChargeListe($Critere);
        if($Lst){
            $rw=$Lst->fetch_assoc();
            return new xProduitLiaison($this->Main,$rw['ID']);
        }
        return null;
    }

}

?>