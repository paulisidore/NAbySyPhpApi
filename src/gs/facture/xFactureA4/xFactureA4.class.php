<?php
namespace NAbySy\GS\Facture\Impression ;

use DateTime;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Facture\xVente;
use xNAbySyGS;
use NAbySy\Lib\Pdf\xPDF;
use xFormat;

/** Gestion des impressions de facture au Forma A4 */
class xFactureA4 {
    public  xNAbySyGS $Main;
    public xPDF $Pdf ;
    public int $IdFacture ;

    public xFormat $Format ;

    private int $i;

    public function __construct(xNAbySyGS $NAbySyGS, int $IdFacture=0,$orientation='P', $unit='mm', $format='A4'){
        $this->Main=$NAbySyGS;
        $this->Pdf=new xPDF($orientation,$unit,$format);
        $this->Format=new xFormat($NAbySyGS);
        $this->IdFacture=$IdFacture;
        
    }

    /** Génère une facture A4 au PDF */
    public function ImprimeFacture(int $IdFact=null){
        if (!isset($IdFact)){
            $IdFact=$this->IdFacture;
        }
        $Vente=new xVente($this->Main,$IdFact);
        if ($Vente->Id==0){
            return "Facture introuvable";
        }
        if ($Vente->IdClient>0){
           if (!isset($Vente->Client)){
            $Vente->Client=new xClient($this->Main,$Vente->IdClient);
           }
        }

        //Ecriture de l'entete
        $this->SendEntete($Vente);
        $this->SendBody($Vente);
        $this->SendPiedPqge($Vente);
        
        $this->Pdf->AutoPrint(false,1);
        $this->Pdf->Output();
        ob_end_flush();
    }


    private function SendEntete(xVente $Vente){
        $this->Pdf->AliasNbPages();
        $this->Pdf->AddPage();
        $this->Pdf->SetCreator('PHP');
        $this->Pdf->SetAuthor('Paul Isidore A. NIAMIE');
        $this->Pdf->SetSubject('Facture');

        $this->Pdf->SetXY(60,20);
        $this->Pdf->SetTitle('Facture');
        $this->Pdf->SetFont('Arial','B','11');
        //$this->Pdf->Image("../images/logo.jpg", '20','12','48','33');
        $this->Pdf->SetAlpha(0.1);
        //$this->Pdf->Image("../images/logo.jpg", '45','115','130','83');
        $this->Pdf->SetAlpha(1);
        //var_dump($Panier);
        $date=date('d/m/Y');
        $xDate=new DateTime($Vente->DateFacture);
        if ($xDate !==false){
            $date=$xDate->format('d/m/Y');
        }
        //Coordonnées dynamique des Boutiques
        $PosY=$this->Main->MaBoutique->GetEntetePDF($this->Pdf) ;
        if ($Vente->Client){
            $telcli=$Vente->Client->Tel ;
        }
        //-----------------------------------------------------------------------

        $PosY+=2 ;
        $this->Pdf->SetFont('Arial','','14');
        $this->Pdf->SetLineWidth(0.2);
        $this->Pdf->SetXY(80,$PosY);
        $TxTitre="FACTURE" ;
        if ((int)$Vente->DejaImprimee==1){
            $TxTitre=$TxTitre." (DUPLICATA)" ;
            $this->Pdf->Cell(60,10,$TxTitre,1,0,'C');
        }else{
            $this->Pdf->Cell(45,10,$TxTitre,1,0,'C');
        }
        $this->Pdf->SetFont('Arial','I','11');

        $this->Pdf->Text(22,'60',utf8_decode('Numéro Client: '.$Vente->Client->Id));
        if ($Vente->IdClient>2){
            $this->Pdf->Text(22,'68',utf8_decode("Nom Client: ".$Vente->Client->Prenom."   ".$Vente->Client->Nom));
            $this->Pdf->Text(22,'74',utf8_decode("Adresse Client: ".$Vente->Client->Adresse."  Téléphone: ".$Vente->Client->Tel));
        }else{
            $this->Pdf->Text(22,'68',utf8_decode("Nom Client: ".$Vente->NomBeneficiaire));
            //$this->Pdf->Text(22,'74',utf8_decode("Adresse Client: ".$Vente->Client->Adresse."  Téléphone: ".$Vente->Client->Tel));
        }
       
        //$this->Pdf->Text(22,'81',utf8_decode());
        $this->Pdf->Text(140,'60',utf8_decode("Numéro: ".$Vente->Id));
        $this->Pdf->Text(140,'66',utf8_decode('Facturé le '.$date." à ".$Vente->HeureFacture));
        //$this->Pdf->Text(165,'75',utf8_decode("Doit"));
        $this->Pdf->SetXY(5,80);
        $str = utf8_decode('Désignation');
        $this->Pdf->SetFont('Arial','B','11');
        $this->Pdf->Cell(30,6,utf8_decode("Quantité"),1,0,'C');
        $this->Pdf->Cell(102,6,$str,1,0,'C');
        $this->Pdf->Cell(30,6,"Prix Unitaire",1,0,'C');
        $this->Pdf->Cell(40,6," ".utf8_decode("Total"),1,0,'L');
        $this->Pdf->SetFont('Arial','','10');
        $this->Pdf->SetLineWidth(0.2);
        $this->Pdf->SetXY(5,90);        
    }

    private function SendBody(xVente $Vente){
        $this->i=80;
        $c_page=1;
        $nb_page=1;
        //$i=90;
        $k=0;
        //On détermine le nombre de Page
        $Nb=count($Vente->DetailVente->ListeProduits);
        if($Nb){
            $taille_result=$Nb;
            $nb_page=(int)($taille_result/30);
            if($taille_result % 30)
                $nb_page++;
        }
        $this->Pdf->Text(90,285,utf8_decode("Page ".$c_page)." / ".$nb_page);

        foreach ($Vente->DetailVente->ListeProduits as $art1)
        {
            if ($this->i > 254 ){
                $this->Pdf->AddPage();
                $this->SendEntete($Vente);
                $this->i=80;
                $c_page++;
                $this->Pdf->Text(90,285,utf8_decode("Page ".$c_page)." / ".$nb_page);
            }

            $this->i +=6;
            /*$artv=$line['code_article'];
            $art="select * from GB_article where GB_article.code_article='$artv';";
            $art1=mysql_fetch_array(mysql_query($art));*/
            $nomart=$art1['Designation'];
            $tot=$art1['Qte']*$art1['PrixVente'];
            $prix=" ".$this->Format->money_format2("%.2n",$art1['PrixVente']);
            $qte=$this->Format->format("%.2n",$art1['Qte']);

            $tot=" ".$this->Format->money_format2("%.2n",$tot);
            if($qte<9)
            $qte="00".$qte;
            elseif($qte<99)
            $qte="0".$qte;

            if($art1['VenteDetaillee']=="NON"){
                //$qte=utf8_decode($qte." ".$art1['unitec']);
            }                
            else{
                //$qte=utf8_decode($qte." ".$art1['united']);
            }            
            //$pin=$art1['pin'];
            $pin="";
            if ($pin!="")
                $pin="ID: ".$pin;

            $this->Pdf->SetXY(5,$this->i);
            $str = " ".utf8_decode($nomart);
            $this->Pdf->SetFont('Arial','','10');
            $this->Pdf->Cell(30,6,$qte,1,0,'C');
            //$this->Pdf->Cell(54,10,$str,1,0,'C');
            //MultiCell(float w, float h, string txt [, mixed border [, string align [, boolean fill]]])
            /*if ($pin!="")
            $this->Pdf->MultiCell(54,5,$str."\n$pin",1,'L');
            else*/
            $this->Pdf->Cell(102,6,$str,1,0,'L');
            //$this->Pdf->SetXY(105,$i);
            $this->Pdf->Cell(30,6,$prix,1,0,'L');
            $this->Pdf->Cell(40,6,$tot,1,0,'L');
            $k++;
            if($k==16){
                $Nb=$Nb+1;
                $k=0;
            }
        }

        
    }

    private function SendPiedPqge(xVente $Vente){
        $lettre=$this->Format->chifre_en_lettre($Vente->TotalFacture,'Francs CFA'); 

        $total=$this->Format->money_format2("%.2n", $Vente->TotalFacture);
        //$i=260;
        $file="FactureA4.pdf";
        $this->i +=6;
        $this->Pdf->SetLineWidth(0.2);
        $this->Pdf->SetFont('Arial','BU','12');
        $this->Pdf->Text(118,($this->i+8),"TOTAL:");
        $this->Pdf->SetXY(137,$this->i);
        $this->Pdf->Cell(70,10,$total,1,0,'C');
        $this->Pdf->SetFont('Arial','BU','11');
        $this->i=$this->i+15;
        $this->Pdf->Text('18',"$this->i",utf8_decode("Arrêtée la présente facture à la somme de:"));
        $this->Pdf->SetFont('Arial','I','12');
        $this->Pdf->Text('18',($this->i+6),utf8_decode($lettre));
        $NbCarton=$Vente->DetailVente->NbCarton();
        if ($NbCarton>0){
            $this->i=$this->i+15;
            $this->Pdf->SetFont('Arial','B','10');
            $this->Pdf->Text('18',"$this->i",utf8_decode("Nombre de Carton: "));
            $this->Pdf->Text('65',$this->i,utf8_decode($NbCarton));
        }
        
        /* Prise en charge de la Signature */
        if ($Vente->Id>0){
            if ($Vente->FactureSignee==1){
                /* Facture Signé donc on peut ajouter la signature */
                $FichierSignature='images/signature_facture.png' ;
                
                if ($Vente->DejaImprimee==0){
                    // Si la facture ná jamais étée signée alors on peut ra jouter la signature
                    $TxSignature='Signé par Id Utilisateur '.$Vente->IdSignataire ;
                    $Signataire=new \xUser ($this->Main,$Vente->IdSignataire) ;
                    if($Signataire->Id>0){
                        $TxSignature=$Signataire->Signature();
                        if ($TxSignature==''){
                            $TxSignature='Signée par '.$Signataire->Login ;
                        }
                        if (file_exists($FichierSignature)){
                            $this->Pdf->Image($FichierSignature,150,$this->i-5,30,30);
                        }
                    }
                    $this->Pdf->SetFont('Arial','U','12');
                    $this->Pdf->Text('160',"$this->i",utf8_decode("Signature"));
                    $this->Pdf->SetFont('Arial','B','12');
                    $this->Pdf->Text('160',($this->i+6),utf8_decode($TxSignature));
                }
            }
        }
        
        $NbDetail=$Vente->DetailVente->NbDetail();
        if($NbDetail>0){
            if ($NbCarton>0){
                $this->i +=6 ;
            }else{
                $this->i +=15 ;
            }
            $this->Pdf->Text('18',"$this->i",utf8_decode("Nombre de Pièce: "));
            $this->Pdf->Text('65',$this->i,utf8_decode($NbDetail));
        }

        $this->Pdf->SetFont('Arial','I','6');
        $this->Pdf->RotatedText(2,95,iconv("UTF-8", "ISO-8859-1", "©").'PAM SARL TEL: 33 936 14 77 / 77 921 46 90',90);
    }

}
?>