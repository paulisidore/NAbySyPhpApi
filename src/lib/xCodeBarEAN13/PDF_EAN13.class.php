<?php

if (defined("FPDF_VERSION")==null ){
    require_once 'vues/fpdf/fpdf.php';
    require_once 'vues/rotation.php';
}

//include_once '../nabysy/nabysy_start.php';

class PDF_EAN13 extends PDF_Rotate
{
    var $javascript;
    var $n_js;
    function IncludeJS($script) {
        $this->javascript=$script;
    }

    function _putjavascript() {
            $this->_newobj();
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
            $this->_out('endobj');
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
            $this->_out('/Names <</JavaScript '.($this->n_js).' 0 R>>');
        }
    }
    
    function AutoPrint($dialog=false, $nb_impr=1)
    {
        //Ajoute du JavaScript pour lancer la boîte d'impression ou imprimer immediatement
        $param=($dialog ? 'true' : 'false');
        $script=str_repeat("print(true);",$nb_impr);
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

    function EAN13($x, $y, $barcode, $h=16, $w=.35,$ImpFoot=true)
    {
        $this->Barcode($x,$y,$barcode,$h,$w,13,$ImpFoot);
    }

    function UPC_A($x, $y, $barcode, $h=16, $w=.35,$ImpFoot=true)
    {
        $this->Barcode($x,$y,$barcode,$h,$w,12,$ImpFoot);
    }

    function GetCheckDigit($barcode)
    {
        //Compute the check digit
        $sum=0;
        for($i=1;$i<=11;$i+=2)
            $sum+=3*$barcode[$i];
        for($i=0;$i<=10;$i+=2)
            $sum+=$barcode[$i];
        $r=$sum%10;
        if($r>0)
            $r=10-$r;
        return $r;
    }

    function TestCheckDigit($barcode)
    {
        //Test validity of check digit
        $sum=0;
        for($i=1;$i<=11;$i+=2)
            $sum+=3*$barcode[$i];
        for($i=0;$i<=10;$i+=2)
            $sum+=$barcode[$i];
        return ($sum+$barcode[12])%10==0;
    }

    function Barcode($x, $y, $barcode, $h, $w, $len,$ImpFoot=true)
    {
        //Padding
        $barcode=str_pad($barcode,$len-1,'0',STR_PAD_LEFT);
        if($len==12)
            $barcode='0'.$barcode;
        //Add or control the check digit
        if(strlen($barcode)==12)
            $barcode.=$this->GetCheckDigit($barcode);
        elseif(!$this->TestCheckDigit($barcode))
            $this->Error('Incorrect check digit');
        //Convert digits to bars
        $codes=array(
            'A'=>array(
                '0'=>'0001101','1'=>'0011001','2'=>'0010011','3'=>'0111101','4'=>'0100011',
                '5'=>'0110001','6'=>'0101111','7'=>'0111011','8'=>'0110111','9'=>'0001011'),
            'B'=>array(
                '0'=>'0100111','1'=>'0110011','2'=>'0011011','3'=>'0100001','4'=>'0011101',
                '5'=>'0111001','6'=>'0000101','7'=>'0010001','8'=>'0001001','9'=>'0010111'),
            'C'=>array(
                '0'=>'1110010','1'=>'1100110','2'=>'1101100','3'=>'1000010','4'=>'1011100',
                '5'=>'1001110','6'=>'1010000','7'=>'1000100','8'=>'1001000','9'=>'1110100')
            );
        $parities=array(
            '0'=>array('A','A','A','A','A','A'),
            '1'=>array('A','A','B','A','B','B'),
            '2'=>array('A','A','B','B','A','B'),
            '3'=>array('A','A','B','B','B','A'),
            '4'=>array('A','B','A','A','B','B'),
            '5'=>array('A','B','B','A','A','B'),
            '6'=>array('A','B','B','B','A','A'),
            '7'=>array('A','B','A','B','A','B'),
            '8'=>array('A','B','A','B','B','A'),
            '9'=>array('A','B','B','A','B','A')
            );
        $code='101';
        $p=$parities[$barcode[0]];
        for($i=1;$i<=6;$i++)
            $code.=$codes[$p[$i-1]][$barcode[$i]];
        $code.='01010';
        for($i=7;$i<=12;$i++)
            $code.=$codes['C'][$barcode[$i]];
        $code.='101';
        //Draw bars
        for($i=0;$i<strlen($code);$i++)
        {
            if($code[$i]=='1')
                $this->Rect($x+$i*$w,$y,$w,$h,'F');
        }
        //Print text uder barcode
        if (!isset($ImpFoot)){
            $ImpFoot=true ;
        }
        if ($ImpFoot){
            $this->SetFont('Arial','',8);
            $mid_x = ($this->PageWidth() / 2)-5;
            $CodeNorm=substr($barcode,-$len) ;
            $PosX=$mid_x - $this->GetStringWidth($CodeNorm)/2 ;
            $this->Text($PosX,$y+$h+8/$this->k,substr($barcode,-$len));
        }
        
    }

    public function PageWidth()
    {
        $width = $this->w;
        $leftMargin = $this->lMargin;
        $rightMargin = $this->rMargin;
        return $width-$rightMargin-$leftMargin;
    }
}
?>