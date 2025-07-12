<?php
namespace NAbySy\UI ;

use DOMDocument;
use DOMXPath;

Class xFormat
{
	public $taux_usd;
	public $devise;
	public $Main;
	public $MaBoutique;
	
	public function __construct($NAbySyGS,$Boutique=null){
		$this->Main=$NAbySyGS ;
		$this->MaBoutique=$NAbySyGS->MaBoutique ;
		$this->devise='F CFA' ;

		if (isset($Boutique)){
			$this->MaBoutique=$Boutique ;
		}
		$this->devise=' FCFA' ;
		$this->taux_usd=0;
		
		
	}

	public function money_usd($taux,$number)
	{
	  $usd=$number/$taux;
	  
	  return $this->my_money_format($usd) ;
	}

	public function my_money_format($number) 
	{ 
		$number=(double)$number;
		$resultat=number_format ($number,  0,  ',' , ' ' );
		return $resultat; 
	} 



	public function format($format, $number)
	{
		 $resultat=$format;
		$number=(int)$number;
		$resultat=number_format ($number,  0,  ',' , ' ' );
		return $resultat;
	}



	public function money_format2($format, $number)
	{
		 $resultat=$format;
		$number=(double)$number;
		$resultat=number_format ($number,  0,  ',' , ' ' );
		return $resultat.$this->devise;
	}



	public function chifre_en_lettre($montant, $devise1='', $devise2='')
	{
		$EnLettre= NumberToLetter($montant) ;
		$_rturn="";
		if(empty($devise1)) {
			$dev1='CFA';
		}else{
			$dev1=$devise1;
		}
		if(empty($devise2)) {
			$dev2='Centimes' ;
		}else{
			$dev2=$devise2;
		}
		$EnLettre .=' '.$dev1 ;
		return ucwords($EnLettre) ;
		/*
			//echo '<script>console.log("Montant Recus:'.$montant.'")</script>' ;
			$valeur_entiere=intval($montant);
			//echo '<script>console.log("Valeur Entière:'.$valeur_entiere.'")</script>' ;
			$valeur_decimal=intval(round($montant-intval($montant), 2)*100);
			

			$dix_c=intval($valeur_decimal%100/10);
			$cent_c=intval($valeur_decimal%1000/100);
			$unite[1]=$valeur_entiere%10;
			$dix[1]=intval($valeur_entiere%100/10);
			$cent[1]=intval($valeur_entiere%1000/100);
			$unite[2]=intval($valeur_entiere%10000/1000);
			$dix[2]=intval($valeur_entiere%100000/10000);
			$cent[2]=intval($valeur_entiere%1000000/100000);
			$unite[3]=intval($valeur_entiere%10000000/1000000);
			$dix[3]=intval($valeur_entiere%100000000/10000000);
			$cent[3]=intval($valeur_entiere%1000000000/100000000);
			$chif=array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix sept', 'dix huit', 'dix neuf');
				$secon_c='';
				$trio_c='';
			for($i=1; $i<=3; $i++){
				$prim[$i]='';
				$secon[$i]='';
				$trio[$i]='';
				if($dix[$i]==0){
					$secon[$i]='';
					$prim[$i]=$chif[$unite[$i]];
				}
				else if($dix[$i]==1){
					$secon[$i]='';
					$prim[$i]=$chif[($unite[$i]+10)];
				}
				else if($dix[$i]==2){
					if($unite[$i]==1){
					$secon[$i]='vingt et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='vingt';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==3){
					if($unite[$i]==1){
					$secon[$i]='trente et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='trente';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==4){
					if($unite[$i]==1){
					$secon[$i]='quarante et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='quarante';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==5){
					if($unite[$i]==1){
					$secon[$i]='cinquante et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='cinquante';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==6){
					if($unite[$i]==1){
					$secon[$i]='soixante et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='soixante';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==7){
					if($unite[$i]==1){
					$secon[$i]='soixante et';
					$prim[$i]=$chif[$unite[$i]+10];
					}
					else {
					$secon[$i]='soixante';
					$prim[$i]=$chif[$unite[$i]+10];
					}
				}
				else if($dix[$i]==8){
					if($unite[$i]==1){
					$secon[$i]='quatre-vingts et';
					$prim[$i]=$chif[$unite[$i]];
					}
					else {
					$secon[$i]='quatre-vingt';
					$prim[$i]=$chif[$unite[$i]];
					}
				}
				else if($dix[$i]==9){
					if($unite[$i]==1){
					$secon[$i]='quatre-vingts et';
					$prim[$i]=$chif[$unite[$i]+10];
					}
					else {
					$secon[$i]='quatre-vingts';
					$prim[$i]=$chif[$unite[$i]+10];
					}
				}

				

				if($cent[$i]==1){
					$trio[$i]='cent';
				}				
				else if($cent[$i]!=0 || $cent[$i]!='') $trio[$i]=$chif[$cent[$i]] .' cents';
			}
			
			
			$chif2=array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingts', 'quatre-vingts dix');
			$secon_c=$chif2[$dix_c];
			if($cent_c==1) $trio_c='cent';
			else if($cent_c!=0 || $cent_c!='') $trio_c=$chif[$cent_c] .' cents';
			
			if(($cent[3]==0 || $cent[3]=='') && ($dix[3]==0 || $dix[3]=='') && ($unite[3]==1))
				$_rturn=$_rturn.$trio[3]. '  ' .$secon[3]. ' ' . $prim[3]. ' million ';
			else if(($cent[3]!=0 && $cent[3]!='') || ($dix[3]!=0 && $dix[3]!='') || ($unite[3]!=0 && $unite[3]!=''))
				$_rturn=$_rturn. $trio[3]. ' ' .$secon[3]. ' ' . $prim[3]. ' millions ';
			else
				$_rturn=$_rturn. $trio[3]. ' ' .$secon[3]. ' ' . $prim[3];
			
			echo '<script>console.log("_rturn:'.$_rturn.'")</script>' ;

			if(($cent[2]==0 || $cent[2]=='') && ($dix[2]==0 || $dix[2]=='') && ($unite[2]==1))
				$_rturn=$_rturn. ' mille ';
			else if(($cent[2]!=0 && $cent[2]!='') || ($dix[2]!=0 && $dix[2]!='') || ($unite[2]!=0 && $unite[2]!=''))
				$_rturn=$_rturn. $trio[2]. ' ' .$secon[2]. ' ' . $prim[2]. ' mille ';
			else
				$_rturn=$_rturn. $trio[2]. ' ' .$secon[2]. ' ' . $prim[2];
			
			$_rturn=$_rturn. $trio[1]. ' ' .$secon[1]. ' ' . $prim[1];
			
			$_rturn=$_rturn. ' '. $dev1 .' ' ;
			
			if(($cent_c=='0' || $cent_c=='') && ($dix_c=='0' || $dix_c=='')){
				// $_rturn=$_rturn. ' et z&eacute;ro '. $dev2;
				$_rturn=$_rturn. '';
			}else{
				$_rturn=$_rturn. $trio_c. ' ' .$secon_c. ' ' . $dev2;
			}			
				
				
				return ucwords($_rturn);
		*/
	}

	public function GetListeMenu($pageweb,$baliserechercher='a'){

		// Start output buffering
        ob_start();

        // Include the template file
        include $pageweb;

        // End buffering and return its contents
		$contenue = ob_get_clean();
		
		//$contenue=file_get_contents($pageweb) ;
		
		$DOCUMENT_ROOT=$_SERVER['DOCUMENT_ROOT'] ;
		$file = $pageweb;
		
		$doc = new DOMDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$doc->encoding = 'utf-8';
		$doc->loadHTMLFile($file);
		$xpath = new DOMXpath($doc);
		$nav = $doc->getElementsByTagName('nav')->item(0);

		// notre requête est relative au noeud nav
		$query = '//'.$baliserechercher;
		$elements = $xpath->query($query);
		$reponse=[] ;
		if (!is_null($elements)) {
			foreach ($elements as $element) {
				//echo "<br/>[". $element->nodeName. "]";
				$href=$element->getAttribute('href') ;
				//var_dump($element) ;
				//echo " -Lien: ".$href." Titre: " ;
				$nodes = $element->childNodes;
				foreach ($nodes as $node) {
					$Titre=$node->nodeValue ;

					$ligne['Titre'] = $Titre ;
					$ligne['Lien'] = $href ;
					//echo $Titre. "\n";	
					$ligne['IsMenu'] = false ;
					if ($href=='#'){
						//var_dump($node) ;
						//echo "<br/>".$Titre." est un sous menu -\n" ;
						$ligne['IsMenu'] = true ;
						// **************************************************************
					}	

					$Saute=false;
					if (strpos($Titre,'echo ') !==false ){
						$Saute=true;
					}
					if (strpos($Titre,'$') !==false ){
						$Saute=true;
					}

					if (!$Saute){
						$reponse[]=$ligne ;
					}
					
				}
				
			}
		}
		
		return $reponse ;

	}
}
?>