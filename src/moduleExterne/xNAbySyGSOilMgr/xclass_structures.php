<?php

/**
 * Structure des données utilies
 */
namespace NAbySy\Lib\ModuleExterne\OilStation\Structure ;

use DateTime;
use NAbySy\Lib\ModuleExterne\OilStation\xCuveStockageCarburant;
use NAbySy\Lib\ModuleExterne\OilStation\xPompe;
use xNotification;

 #region Statistiques
    class xInfoControlStock extends xNotification {
        public DateTime $DateDebut  ;
        public DateTime $DateFin  ;
        public ?object $Cuve =null;
        public ?object $Pompe = null ;

        // public  $Cuve =null;
        // public  $Pompe = null ;

        public ?array $InfoVente=null;
        public ?array $InfoLivraison=null;

        /** Gauge-B du matin */
        public float $StockInitial = 0;

        /** Qte Livrée dans la période */
        public float $QteLivree = 0;        

        
        /** (D)Quantité Vendu sur une période */
        public float $QteVendu = 0;

        

        /** (B)Stock Réel Jaugé à la Fin de la Journée appelé Jauge-B */
        public float $StockDeFinGaugeB = 0;

        public int $NbArrondie = 2;

        public function __construct(int $nbArrondie=2){
            $this->DateDebut=new DateTime('now');
            $this->DateFin=new DateTime("now");
            $this->NbArrondie = $nbArrondie;
            
        }

        /** (A)Stock Théorique A (Stock Total Disponible après la vente de la période) */
        public function StockTheoriqueRestant():float{
            //echo __FILE__." L".__LINE__.": StockFinalMatin = ". round($this->StockFinalMatin(),2)."</br>" ;
            return $this->StockFinalMatin() - $this->QteVendu;
        }

        /** Stock Total de la journée */
        public function StockFinalMatin():float{
            
            return $this->StockInitial + $this->QteLivree;
        }

        /** (C)Ecart entre le stock réel restant et le stock théorique restant */
        public function Ecart():float{
            $vEcart=($this->StockDeFinGaugeB - $this->StockTheoriqueRestant()) ;
            return $vEcart ;
        }

        /**
         * Pourcentange d'ecart réel entre le stock Vendu et celui réelement sortie de la cuve
         * Plus le pourcentage s'approche des 100% et mois il y a des pertes liés a des facteurs
         * comme l'évaporation.
         * @param int $Arrondi 
         * @return float 
         */
        public function TauxEcart(int $Arrondi=2):float{
            if ($this->QteVendu ==0){
                return 0;
            }
            $Taux = 0;
            $Ecart = $this->Ecart() ;
            if ($Ecart !== $this->QteVendu){
                $Taux= abs( ( $Ecart/($this->QteVendu) ) ) *100;
                $Taux = round($Taux,$Arrondi);
            }           

            // echo __FILE__." L".__LINE__.": StockInitial = ". round($this->StockInitial,2)."</br>" ;
            // echo __FILE__." L".__LINE__.": QteLivree = ". round($this->QteLivree,2)."</br>" ;
            // echo __FILE__." L".__LINE__.": Ecart() = ".round($this->Ecart(),2)."</br>" ;
            // echo __FILE__." L".__LINE__.": QteVendu = ".round($this->QteVendu,2)."</br>" ;
            // echo __FILE__." L".__LINE__.": StockDeFinGaugeB = ". round($this->StockDeFinGaugeB,2)."</br>" ;
            // echo __FILE__." L".__LINE__.": StockTheoriqueRestant() = ".round($this->StockTheoriqueRestant(),2)."</br>" ;
            // echo __FILE__." L".__LINE__.": Ecart(StockDeFinGaugeB - StockTheoriqueRestant() = ". $this->StockDeFinGaugeB - $this->StockTheoriqueRestant()."</br>" ;
            // echo __FILE__." L".__LINE__.": Ecart()/QteVendu = ".$Taux."</br>" ;
            
            return $Taux;
        }

        /**
         * Pourcentage de Conservation
         * @param int $Arrondi 
         * @return float 
         */
        public function TauxConservation(int $Arrondi=2):float{
            if ($this->QteVendu ==0){
                return 0;
            }
            $Taux=100 - abs($this->TauxEcart($Arrondi));
            return $Taux;
        }

        public function ToJSON():string{
            $Obj['DATE_DU'] = $this->DateDebut->format("Y-m-d");
            $Obj['DATE_AU'] = $this->DateFin->format("Y-m-d");
            if(isset($this->Cuve)){
                $Obj['CUVE'] = $this->Cuve->ToObject();
            }
            if(isset($this->Pompe)){
                $Obj['POMPE'] = $this->Pompe->ToObject();
            }
            $Obj['InfoVente'] = $this->InfoVente;
            $Obj['InfoLivraison'] = $this->InfoLivraison ;
            $Obj['StockInitial'] = $this->StockInitial;
            $Obj['QteLivree'] = $this->QteLivree ;
            $Obj['QteVendue'] = $this->QteVendu;
            $Obj['StockDeFinGaugeB'] = $this->StockDeFinGaugeB;
            $Obj['StockTheoriqueRestant'] = $this->StockTheoriqueRestant();
            $Obj['StockFinalMatin'] = $this->StockFinalMatin();
            $Obj['Ecart'] = round($this->Ecart(),$this->NbArrondie);
            $Obj['TauxEcart'] = $this->TauxEcart($this->NbArrondie);
            $Obj['TauxConservation'] = $this->TauxConservation($this->NbArrondie);
            
            return json_encode($Obj);
        }

        public function ToObject(){
            return json_decode($this->ToJSON());
        }



    }
 #endregion

?>