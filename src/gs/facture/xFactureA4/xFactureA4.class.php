<?php
namespace NAbySy\GS\Facture\Impression ;

use DateTime;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Facture\xVente;
use NAbySy\xNAbySyGS;
use NAbySy\Lib\Pdf\xPDF;
use NAbySy\ORM\xORMHelper;
use NAbySy\UI\xFormat;
use NAbySy\xUser;

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
    public function ImprimeFacture(int $IdFact=null, ?string $vTableClient = null){
        if (!isset($IdFact)){
            $IdFact=$this->IdFacture;
        }
        $DBase=$this->Main->DataBase;
        if(xNAbySyGS::$TECHNOWEB_ACTIVE && xNAbySyGS::$TechnoWEBClient){
            $DBase=xNAbySyGS::$TechnoWEBClient->DataBase;
        }
        $Vente=new xVente($this->Main,$IdFact,true,"facture",$DBase);
        
        if ($Vente->Id==0){
            return "Facture introuvable";
        }
        if ($Vente->IdClient>0 && !isset($vTableClient)){
           if (!isset($Vente->Client)){
            $Vente->Client=new xClient($this->Main,$Vente->IdClient, false,"client",$DBase);
           }
        }elseif($Vente->IdClient>0){
            $Vente->Client = new xClient($this->Main,$Vente->IdClient, true,$vTableClient,$DBase); ;
            if(trim($Vente->Client->Nom) == '' && trim($Vente->Client->Prenom) =='' && $Vente->Client->ChampsExisteInTable("RaisonSocial")){
                if($Vente->Client->ChampsExisteInTable("TypeSociete")){
                    $Vente->Client->Prenom = $Vente->Client->RaisonSocial ;
                    $Vente->Client->Nom = $Vente->Client->TypeSociete;
                }else{
                    $Vente->Client->Nom = $Vente->Client->TypeSociete;
                }
            }
        }
        if(!$Vente->ChampsExisteInTable("TotalFacture") && $Vente->ChampsExisteInTable("Montant")){
            $Vente->AutoCreate=true;
            $Vente->TotalFacture=$Vente->Montant;
            $Vente->Enregistrer();
        }elseif($Vente->ChampsExisteInTable("TotalFacture") && $Vente->ChampsExisteInTable("Montant")){
            if($Vente->TotalFacture != $Vente->Montant){
                $Vente->TotalFacture = $Vente->Montant ;
                $Vente->Enregistrer();
            }
        }

        echo "Je suis Ici ".__FILE__." à la ligne ".__LINE__. " ".$Vente->FullTableName()."<br>" ;
        //echo "Je suis Ici ".__FILE__." à la ligne ".__LINE__. " ".$Vente->DetailVente->FullTableName()."<br>";exit;
        
        //Ecriture de l'entete
        $this->SendEntete($Vente);
        $this->SendBody($Vente);
        $this->SendPiedPqge($Vente);
        
        $this->Pdf->AutoPrint(false,1);
        // CORRECTION SÉCURITÉ VERSION
        //$this->Pdf->Close(); // On force la fermeture complète du document

        if (ob_get_length()) {
            ob_end_clean(); 
        }

        $this->Pdf->Output();
        //ob_end_flush();
        exit;
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
        $this->Pdf->SetAlpha(0.1);
        $this->Pdf->SetAlpha(1);
        
        $date=date('d/m/Y');
        $xDate=new DateTime($Vente->DateFacture);
        if ($xDate !==false){
            $date=$xDate->format('d/m/Y');
        }
        
        if ($Vente->Client){
            $telcli=$Vente->Client->Tel ;
        }

        // --- GESTION DE L'IMAGE D'ENTÊTE DYNAMIQUE ---
        $PosY = 12; // Position de base par défaut
        $hasImageHeader = $this->CheckAndSendImageHeader($PosY);

        if (!$hasImageHeader) {
            // S'il n'y a pas d'image d'en-tête globale, on utilise les coordonnées texte par défaut de la boutique
            $PosY = $this->Main->MaBoutique->GetEntetePDF($this->Pdf);
        }
        // ---------------------------------------------

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
        
        // On descend un peu après le cadre "FACTURE" pour lister les infos clients
        $InfoY = $PosY + 15; 
        
        $this->Pdf->SetFont('Arial','I','11');
        $this->Pdf->Text(22, $InfoY, iconv('UTF-8', 'windows-1252//TRANSLIT', 'Numéro Client: '.$Vente->Client->Id));
        if ($Vente->IdClient>2){
            $this->Pdf->Text(22, ($InfoY + 8), iconv('UTF-8', 'windows-1252//TRANSLIT', "Nom Client: ".$Vente->Client->Prenom."   ".$Vente->Client->Nom));
            $this->Pdf->Text(22, ($InfoY + 14), iconv('UTF-8', 'windows-1252//TRANSLIT', "Adresse Client: ".$Vente->Client->Adresse."  Téléphone: ".$Vente->Client->Tel));
        }else{
            $this->Pdf->Text(22, ($InfoY + 8), iconv('UTF-8', 'windows-1252//TRANSLIT', "Nom Client: ".$Vente->NomBeneficiaire));
        }
       
        $this->Pdf->Text(140, $InfoY, iconv('UTF-8', 'windows-1252//TRANSLIT', "Numéro: ".$Vente->Id));
        $this->Pdf->Text(140, ($InfoY + 6), iconv('UTF-8', 'windows-1252//TRANSLIT', 'Facturé le '.$date." à ".$Vente->HeureFacture));
        
        // On calcule la position Y idéale pour tracer la ligne d'en-tête du tableau d'articles
        $TableY = $InfoY + 20;
        
        $this->Pdf->SetXY(5, $TableY);
        $str = iconv('UTF-8', 'windows-1252//TRANSLIT', 'Désignation');
        $this->Pdf->SetFont('Arial','B','11');
        $this->Pdf->Cell(30,6, iconv('UTF-8', 'windows-1252//TRANSLIT', "Quantité"),1,0,'C');
        $this->Pdf->Cell(102,6,$str,1,0,'C');
        $this->Pdf->Cell(30,6,"Prix Unitaire",1,0,'C');
        $this->Pdf->Cell(40,6," ".iconv('UTF-8', 'windows-1252//TRANSLIT', "Total"),1,0,'L');
        $this->Pdf->SetFont('Arial','','10');
        $this->Pdf->SetLineWidth(0.2);
        
        // Position de départ du premier article pour la boucle (SendBody prend le relais)
        $this->Pdf->SetXY(5, ($TableY + 10));   
        
        $this->i = $PosY+5;
    }



    private function SendBody(xVente $Vente){
        $this->i +=31;
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
        
        // MODIFICATION ENCODAGE PAGE
        $this->Pdf->Text(90,285, iconv('UTF-8', 'windows-1252//TRANSLIT', "Page ".$c_page)." / ".$nb_page);

        foreach ($Vente->DetailVente->ListeProduits as $art1)
        {
            if ($this->i > 254 ){
                $this->Pdf->AddPage();
                $this->SendEntete($Vente);
                $this->i=80;
                $c_page++;
                $this->Pdf->Text(90,285, iconv('UTF-8', 'windows-1252//TRANSLIT', "Page ".$c_page)." / ".$nb_page);
            }

            $this->i +=6;
            $nomart=$art1['Designation'] ?? $art1['DESIGNATION'] ?? '';
            $Qte = $art1['Qte'] ?? $art1['QTE'] ?? 1 ;
            $PrixVente = $art1['PrixVente'] ?? $art1['PRIXVENTE'] ?? $art1['PRIXVENTETTC'] ?? 0;
            $tot=$Qte*$PrixVente;
            $prix=" ".$this->Format->money_format2("%.2n",$PrixVente);
            $qte=$this->Format->format("%.2n",$Qte);

            $tot=" ".$this->Format->money_format2("%.2n",$tot);
            if($qte<9)
            $qte="00".$qte;
            elseif($qte<99)
            $qte="0".$qte;

            $VenteDetaillee = $art1['VenteDetaillee'] ?? $art1['VENTEDETAILLEE'] ?? 'NON' ;
            if($VenteDetaillee=="NON"){
                //$qte=$this->Main->utf8ize($qte." ".$art1['unitec']);
            }                
            else{
                //$qte=$this->Main->utf8ize($qte." ".$art1['united']);
            }            
            //$pin=$art1['pin'];
            $pin="";
            if ($pin!="")
                $pin="ID: ".$pin;

            $this->Pdf->SetXY(5,$this->i);
            
            // MODIFICATION ENCODAGE DESIGNATION PRODUIT
            $str = " ".iconv('UTF-8', 'windows-1252//TRANSLIT', $nomart);
            
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
        if((float)$Vente->TotalTVA == 0){
            $this->Pdf->Text(118,($this->i+8),"TOTAL:");
            $this->Pdf->SetXY(137,$this->i);
            $this->Pdf->Cell(70,10,$total,1,0,'C');
            $this->Pdf->SetFont('Arial','BU','11');
            $this->i=$this->i+15;
        }else{
            $TotalTTC=$Vente->TotalFacture ;
            $TotalHT=$Vente->TotalFacture - $Vente->TotalTVA ;
            $TotalHT=$this->Format->money_format2("%.2n", $TotalHT);
            $TotalTTC=$this->Format->money_format2("%.2n", $TotalTTC);
            
            $this->Pdf->SetFont('Arial','B','12');

            //Ligne Total HT
            $this->Pdf->Text(115,($this->i+6),"TOTAL HT:");
            $this->Pdf->SetXY(137,$this->i);
            $this->Pdf->Cell(70,10,$TotalHT,1,0,'C');
            $this->Pdf->SetFont('Arial','B','11');
            $this->i=$this->i+10;

            //Ligne Taux TVA et TVA
            //Déduction du TauxTVA a partir du TotalTVA et du TotalHT
            $TauxTVA= (float)$Vente->TauxTVA;
            if($TauxTVA ==0 && (float)$TotalHT>0){
                $TauxTVA=((float)$Vente->TotalTVA/(float)$TotalHT)*100;
            }
            $TauxTVA=$this->Format->format("%.2n",$TauxTVA);
            $TotalTVA=$this->Format->money_format2("%.2n", $Vente->TotalTVA);
            $this->Pdf->Text(118,($this->i+6),"TVA $TauxTVA%:");
            $this->Pdf->SetXY(137,$this->i);
            $this->Pdf->Cell(70,10,$TotalTVA,1,0,'C');
            $this->Pdf->SetFont('Arial','B','11');
            $this->i=$this->i+10;

            //Ligne Total TTC
            $this->Pdf->Text(115,($this->i+6),"TOTAL TTC:");
            $this->Pdf->SetXY(137,$this->i);
            $this->Pdf->Cell(70,10,$TotalTTC,1,0,'C');
            $this->Pdf->SetFont('Arial','B','11');
            $this->i=$this->i+15;
        }
        
        // MODIFICATION DES ENCODAGES ICI
        $this->Pdf->Text('18',"$this->i", iconv('UTF-8', 'windows-1252//TRANSLIT', "Arrêtée la présente facture à la somme de:"));
        $this->Pdf->SetFont('Arial','I','12');
        $this->Pdf->Text('18',($this->i+6), iconv('UTF-8', 'windows-1252//TRANSLIT', $lettre));
        $NbCarton=$Vente->DetailVente->NbCarton();
        if ($NbCarton>0){
            $this->i=$this->i+15;
            $this->Pdf->SetFont('Arial','B','10');
            $this->Pdf->Text('18',"$this->i", iconv('UTF-8', 'windows-1252//TRANSLIT', "Nombre de Carton: "));
            $this->Pdf->Text('65',$this->i, iconv('UTF-8', 'windows-1252//TRANSLIT', $NbCarton));
        }
        
        /* Prise en charge de la Signature */
        if ($Vente->Id>0){
            if ($Vente->FactureSignee==1){
                /* Facture Signé donc on peut ajouter la signature */
                $FichierSignature='media/signature_facture.png' ;
                
                if ($Vente->DejaImprimee==0){
                    // Si la facture ná jamais étée signée alors on peut ra jouter la signature
                    $TxSignature='Signé par Id Utilisateur '.$Vente->IdSignataire ;
                    $Signataire=new xUser ($this->Main,$Vente->IdSignataire) ;
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
                    $this->Pdf->Text('160',"$this->i", iconv('UTF-8', 'windows-1252//TRANSLIT', "Signature"));
                    $this->Pdf->SetFont('Arial','B','12');
                    $this->Pdf->Text('160',($this->i+6), iconv('UTF-8', 'windows-1252//TRANSLIT', $TxSignature));
                }
            }
        }
        
        //Impression du nombre de carton / Piece
        $NbDetail=$Vente->DetailVente->NbDetail();
        if($NbDetail>0){
            if ($NbCarton>0){
                $this->i +=6 ;
            }else{
                $this->i +=15 ;
            }
            $this->Pdf->Text('18',"$this->i", iconv('UTF-8', 'windows-1252//TRANSLIT', "Nombre de Pièce: "));
            $this->Pdf->Text('65',$this->i, iconv('UTF-8', 'windows-1252//TRANSLIT', $NbDetail));
        }

        $this->Pdf->SetFont('Arial','I','6');
        $this->Pdf->RotatedText(2,95,iconv("UTF-8", "windows-1252//TRANSLIT", "©").'PAM SARL TEL: 33 936 14 77 / 77 921 46 90',90);
    }

        /** Génère l'image d'en-tête si elle existe */
    private function CheckAndSendImageHeader(&$PosY) {
        // Définissez ici le chemin vers votre fichier d'image d'en-tête globale
        $cheminImageHeader = $_SERVER['DOCUMENT_ROOT'] . '/media/entete_facture.png'; 
        
        if (file_exists($cheminImageHeader)) {
            // Insère l'image : Image(chemin, X, Y, Largeur, Hauteur)
            // On la cale à 5mm de la marge gauche avec une largeur de 200mm (A4 fait 210mm)
            $this->Pdf->Image($cheminImageHeader, 5, 10, 200);
            
            // On calcule dynamiquement la hauteur pour décaler le reste du texte ($PosY)
            $tailleImage = getimagesize($cheminImageHeader);
            if ($tailleImage !== false) {
                $largeurPx = $tailleImage[0];
                $hauteurPx = $tailleImage[1];
                // Conversion de la hauteur proportionnelle en millimètres (Largeur PDF = 200mm)
                $hauteurMm = ($hauteurPx * 200) / $largeurPx;
                
                // On met à jour la position Y de départ pour la suite (10mm de marge top + hauteur + 5mm d'espace)
                $PosY = 10 + $hauteurMm + 5; 
            } else {
                $PosY = 45; // Valeur de repli si la taille ne peut pas être lue
            }
            return true;
        }
        return false;
    }


}
?>