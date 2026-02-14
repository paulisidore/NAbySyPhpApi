<?php
namespace NAbySy\Lib\Pdf ;

    include_once 'fpdf.php';
    include_once 'rotation.php';

    class xPDF extends PDF_Rotate { 

        var $javascript;
            var $n_js;
    
            function Footers()
        {
            // Positionnement à 1,5 cm du bas
            $this->SetY(-15);
            $this->SetTextColor(0, 0,0);
            $this->SetDrawColor(0,0,128);
            $this->SetLineWidth(0.5);
            $this->Line(0,100,220,100);
        }
            
            function IncludeJS($script) {
                $this->javascript=$script;
            }
        function _putjavascript() {
/*                 $this->_newobj();
                $this->n_js=$this->n;
                $this->_out('<<');
                $this->_out('/Names [(EmbeddedJS) '.($this->n+1).' 0 R ]');
                $this->_out('>>');
                $this->_out('endobj');
                $this->_newobj();
                $this->_out('<<');
                $this->_out('/S /JavaScript');
                $this->_out('/JS '.$this->_textstring($this->javascript));
                $this->_out('>>');
                $this->_out('endobj'); */
            }
    
            function _putresources() {
                parent::_putresources();
                if (!empty($this->javascript)) {
                    $this->_putjavascript();
                }
            }
    
            function _putcatalog() {
                parent::_putcatalog();
                if (isset($this->javascript)) {
                    //$this->_out('/Names <</JavaScript '.($this->n_js).' 0 R>>');
                }
            }
                function AutoPrint($dialog=false, $nb_impr=1)
        {
            //Ajoute du JavaScript pour lancer la boîte d'impression ou imprimer immediatement
            $param=($dialog ? 'true' : 'false');
            $script=str_repeat("print();",$nb_impr);
        $this->IncludeJS($script);
        }
    
    
        //Fonctions de Rotation
        function RotatedText($x,$y,$txt,$angle)
        {
            //Rotation du texte autour de son origine
            $this->Rotate($angle,$x,$y);
            $this->Text($x,$y,$txt);
            $this->Rotate(0);
        }
    
        function RotatedImage($file,$x,$y,$w,$h,$angle)
        {
            //Rotation de l'image autour du coin supérieur gauche
            $this->Rotate($angle,$x,$y);
            $this->Image($file,$x,$y,$w,$h);
            $this->Rotate(0);
        }
    
    
    //fin js
    }

?>