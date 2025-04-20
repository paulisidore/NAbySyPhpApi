<?php
    namespace NAbySy\GS\CodeBar ;
    require_once 'PDF_EAN13.class.php';    
    
    Class xNAbySyLabelPrinter{
        public $xCodeBar ;
        public $Pdf ;
        public $Largeur ;
        public $Hauteur ;
        private $w ;

        public function __construct(xCodeBarEAN13 $xCodeB,$Larg=60,$Haut=60)
        {
            $this->xCodeBar = $xCodeB ;
            $this->Largeur=$Larg ;
            $this->Hauteur=$Haut ;
            $this->Init() ;
        }
        private function Init(){
            $this->Pdf=new PDF_EAN13('P','mm',array($this->Hauteur,$this->Largeur)) ;
            $this->Pdf->SetMargins(0,0);
            $this->Pdf->AliasNbPages();
            $this->Pdf->AddPage();
            $this->Pdf->SetCreator('Paul Isidore');
            $this->Pdf->SetAuthor('Micro Computer Programme');
            $this->Pdf->SetSubject('Codebarre EAN13');
            $this->Pdf->SetTitle('Code Barre EAN-13');
            $this->w=$this->Pdf->PageWidth() ;

        }
        
        //Impression du Titre (Nom de l'Entreprise)
        public function PrintTitre($Titre="",$PosY=5){
            $pdf=$this->Pdf ;            
            $pdf->SetFont('Arial','B','8');
            if ($Titre==''){
                $Titre=$this->xCodeBar->RS['NomPharmacie'] ;
            }
            $mid_x = $this->w / 2;
            $PosX=$mid_x - $pdf->GetStringWidth($Titre)/2 ;
            $pdf->SetXY($PosX,$PosY);
            $pdf->Text($PosX,$PosY,$Titre) ; 
            return $PosY ;
        }

        //Impression du Nom de l'article
        public function PrintArticleName($NomArticle="",$PosY=8){
            $pdf=$this->Pdf ;            
            $pdf->SetFont('Arial','B','8');            
            $mid_x = $pdf->PageWidth() / 2;
            $PosX=$mid_x - $pdf->GetStringWidth($NomArticle)/2 ;
            $pdf->SetXY($PosX,$PosY);
            $pdf->Text($PosX,$PosY,$NomArticle) ; 
            return $PosY ;
        }

        //Impression du Nom du Code Barre EAN13
        public function PrintBarCode($CodeBar,$PosX=null,$PosY=9,$LargB=.35,$HautB=5){
            $pdf=$this->Pdf ;  
            $mid_x = $pdf->PageWidth() / 2;
            if (!isset($PosX)){
                $PosX=$mid_x-5 - ($pdf->GetStringWidth($CodeBar)/2) ; 
                if ($PosX<=4)  {
                    $PosX=5 ;
                }
            }                   
            $pdf->EAN13($PosX,$PosY,$CodeBar,$HautB,$LargB,true) ;
            $PosY +=$HautB ;
            return $PosY ;
        }

        //Impression du Nom de l'article
        public function PrintPrixArticle($Montant=" - F CFA",$PosY=35){
            $pdf=$this->Pdf ;            
            $pdf->SetFont('Arial','B','12');            
            $mid_x = $pdf->PageWidth() / 2;
            $PosX=$mid_x - $pdf->GetStringWidth($Montant)/2 ;
            $pdf->SetXY($PosX,$PosY);
            $pdf->Text($PosX,$PosY,$Montant) ; 
            return $PosY ;
        }

        //Lancer la sortie Pdf a l'ecran pour etre imprimé
        public function Imprimer($NbPage=1){
            $pdf=$this->Pdf ; 
            $pdf->AutoPrint(true,$NbPage);
            $pdf->Output() ;
        }
        

    }