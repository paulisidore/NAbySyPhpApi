<?php

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Panier\xArticlePanier;

/**
*  FICHIER : cart.class.php
*
*/
class xCartCommande{
  public $MaBoutique ;
  public $IdFacture ;
  public $Fermee;
  public $Existe;	//=Yes si le panier de la session existe déja
  public $Id;	//Id Panier incrementé automatiquement a la creation
  public $PanierId;
  public $Client ;
  public $NomClt ;
  public $PrenomClt ;
  public $IdClient ;
  
  public $IdProforma ;
  public $IdCaissier ;
  public $Caissier ;
  public $ModePaiement ;
  public $MontantVerse ;
  public $MontantRendu ;
  
  public $HeureFacture ;
  public string $DateFacture ;
  
  /**
  * Constructeur de la class
  */
  function __construct($Bou=null,$IdSession=1,$IsTemp=null){
    // Démarrage des sessions si pas déjà démarrées
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
	if (isset($Bou)){
		$this->MaBoutique=$Bou ;
	}	 
	if (isset($_SESSION['user'])){
		$this->IdCaissier=$_SESSION['id_user'];
		$this->Caissier=$_SESSION['user']; 
	}
	$this->ModePaiement="Espèce";
	$this->DateFacture=date("d-m-Y") ;
	if (isset($IsTemp)){
		$IdSession .='temp' ;
		unset($_SESSION['paniercommande'.$IdSession]) ;
		$this->Existe=false ;
	}
	$this->Id=$IdSession;
	
	if (!$this->Existe){		
		$this->PanierId='paniercommande'.$this->Id ;
		if (!isset($_SESSION[$this->PanierId])){
			//Si la variable session n'existe pas alors on l'initialise
			$this->initCart();
		}
	}
	
	//$this->DejaValider(false) ;
    
  }
  
  /**
  *Initialisation du panier
  */
  public function initCart(){
	$panierid='paniercommande'.$this->Id ;
	$InfoC=$this->PanierId."CLIENT" ;
    $_SESSION[$this->PanierId] = array(); 	
		
	$this->SaveInfosClient();
	$this->GetInfosClient() ;
	$this->Existe=true ;
  }
  
  /**
  * Retourne le contenu du panier
  */
  public function getList(){
	return !empty($_SESSION[$this->PanierId]) ? $_SESSION[$this->PanierId] : NULL;
	
  }
  
  /**
  * Ajout d'un produit au panier
  */
  public function addProduct($id_produit,$libelle_produit,$qte=1,$PrixU=0,$ventegros=0,$IdClient=0,$modif=false){
	$qte=(int)$qte ;
	$PrixU=(int)$PrixU ;
	if($qte > 0 ){
		$vId=$id_produit."_".$ventegros ;
		//Si le produit est déjà la on update la qte
		//var_dump($_SESSION[$this->PanierId]) ;
		if (isset($_SESSION[$this->PanierId][$vId] )){
			$PrecQte=$_SESSION[$this->PanierId][$vId]['qte'] ;
			//echo "</br>AddProduit: La qté passe de ".$PrecQte ;
			if ($modif){
				$NewQte=$qte ;
				//echo "</br>AddProduit: Je modifie a  ".$NewQte ;
				//exit;
				if ($PrixU != (int)$_SESSION[$this->PanierId][$vId]['PrixU']){
					$_SESSION[$this->PanierId][$vId]['PrixU']=$PrixU ;
				}
			}else{
				$NewQte=$PrecQte+$qte ;
			}
			
			//echo "</br>AddProduit: A ".$NewQte ;
			$this->updateQteProduct($id_produit,$NewQte,$ventegros,$vId) ;
						
			return true;
		}else{
				$_SESSION[$this->PanierId][$vId] = array('id_produit'=>$id_produit
													,'produit'=>$libelle_produit
													,'qte'=>$qte
													,'PrixU'=>$PrixU
													,'typev'=>$ventegros
													,'IdBoutique'=>$this->MaBoutique->Id 
													,'IdClient'=>$IdClient
													,'NomClt'=>$this->NomClt
													,'PrenomClt'=>$this->PrenomClt
													,'vId'=>$vId
													); 
		}
												
		$this->updateTotalPriceProduct($id_produit,$ventegros);
		if ($IdClient > 0){
			$this->Client=new xClient($this->MaBoutique->Main,$IdClient) ;
			if ($this->Client){
				$this->NomClt=$this->Client->Nom;
				$this->PrenomClt=$this->Client->Prenom;
			}
		}
		
		return true ;
	}else{
		return "ERREUR : Vous ne pouvez pas ajouter un produit sans quantité..."; 
	}
}
  
  private function updateTotalPriceProduct($id_produit,$ventegros=0){
	  $vId=$id_produit."_".$ventegros ;
    if(isset($_SESSION[$this->PanierId][$vId])){
      $_SESSION[$this->PanierId][$vId]['prix_Total'] = $_SESSION[$this->PanierId][$vId]['qte'] * $_SESSION[$this->PanierId][$vId]['PrixU'];
    }
  }
  
  /**
  * Modifie la quantité d'un produit dans le panier
  */
  public function updateQteProduct($id_produit,$qte=0,$ventegros=0,$vId=null){
	if (!isset($vId)){
		$vId=$id_produit."_".$ventegros ;
	}
    if(isset($_SESSION[$this->PanierId][$vId])){
	  $_SESSION[$this->PanierId][$vId]['qte'] = (int)$qte;
	  //echo "</br>updateqteProduit: La qté est passé à ".$_SESSION[$this->PanierId][$vId]['qte'] ;
      $this->updateTotalPriceProduct($id_produit,$ventegros);
    }else{
      return "ERREUR : produit non présent dans le panier"; 
    }
  }
  
  /**
  * Supprime un produit du panier
  */
  public function removeProduct($id_produit,$ventegros=0,$vId=null){
	  if (!isset($vId)){
		$vId=$id_produit."_".$ventegros ;
	  }
    if(isset($_SESSION[$this->PanierId][$vId])){
      unset($_SESSION[$this->PanierId][$vId]);
	  return true ;
    }
  }
  
  /**
  * Retourne le nombre de produits dans le panier
  */
  public function getNbProductsInCart(){
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
    $nb = 0;
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
    if(!empty($panier)){
      foreach($panier as $P){ 
        $nb +=1 ;		// $P['qte'];
      }
    }
    return $nb;
  }
  
  public function getTotalPriceCart(){
    $total = 0;
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
    if(!empty($panier)){
      foreach($panier as $P){ 
        $total += $P['prix_Total'];
      }
    }
    return $total;
  }
  
  /**
  * Retourne Les produits dans le panier sous forme de Tableau HTML
  */
  public function GetListePdtHTML($ParametreSuplementaire=null){
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
    $nb = 0;
	$Titre='<thead>
				<tr>
					<th> </th>
					<th scope="col">Quantité</th>
					<th scope="col">Article</th>
					<th scope="col">Prix unitaire</th>
					<th scope="col">Prix total</th>
					<th scope="col">M</th>
					<th scope="col">S</th>
				</tr>
			</thead>
			<tbody>';
	$Ligne ='' ;
    if(!empty($panier)){
		$ParamT='';
		if (isset($ParametreSuplementaire)){
			$ParamT='&'.$ParametreSuplementaire ;
		}
      foreach($panier as $P){ 
		$Modifier='<a href="commande.php?IdPanier='.$this->Id.'&ModifierLigne='.$P['vId'].$ParamT.'">Modifier</a>';
		$Supprimer='<a href="commande.php?IdPanier='.$this->Id.'&SupprimerLigne='.$P['vId'].$ParamT.'">Supprimer</a>';
		$Couleur='' ;
		if ($P['typev']==1){
			$Couleur='bgcolor="#00FF00"' ;
		}
		
		$L='<tr id='.$P['vId'].' name='.$P['vId'].'>
				<td><input type ="checkbox" name="checkbox[]"></td>
				<td '.$Couleur.'>'.$P['qte'].'</td>
				<td>'.$P['produit'].'</td>
				<td>'.$P['PrixU'].'</td>
				<td>'.$P['prix_Total'].'</td>
				<td>'.$Modifier.'</td>
				<td>'.$Supprimer.'</td>
			</tr> '
			;
        $Ligne .=$L ;
      }
	  $LigneTableau=$Titre.$Ligne.'</tbody>' ;
	  
    }
    return $LigneTableau;
  }  
  
  public function GetArticle($vId){	  
	$panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
	$Article=null ;
	if(!empty($panier)){
		  foreach($panier as $P){ 
			if ($P['vId']==$vId){
				$IdPdt=$P['id_produit'] ;
				$TypeV=$P['typev'] ;
				$Article=new xArticlePanier($this->MaBoutique->Main,$IdPdt,$P['qte'],$TypeV,$this->MaBoutique) ;
				$Article->PrixU=$P['PrixU'] ;
				break ;
			}		
		}
	}
	return $Article;
  }
  
  public function Vider(){
	  //Libere la variable session du panier et le panier lui meme
	  $InfoC=$this->PanierId."CLIENT" ;
	  $this->Existe=false ;
	  $this->DejaValider(true);
	  $nvListe=array() ;
	  foreach ($this->MaBoutique->ListePanier as $P){
		  if ($P->Id !== $this->Id){			  
			  array_push($nvListe,$P);
		  }		  
	  }
	  $this->MaBoutique->ListePanier=$nvListe ;
	  $_SESSION[$InfoC]['IdCmd']=null ;
	  $_SESSION[$InfoC]['IdBoutiqueCmd']=null ;
	  unset ($InfoC) ;
	  unset ($_SESSION[$this->PanierId]) ;
	  
	  return true ;
  }
  
  public function SaveInfosClient($NomC=null,$PrenomC=null,$IdClient=null,$IdFacture=null,$DateFacture=null){
	  $InfoC=$this->PanierId."CLIENT" ;
	  $_SESSION[$InfoC]['NomClt']=$this->NomClt ;
	  $_SESSION[$InfoC]['PrenomClt']=$this->PrenomClt ;
	  $_SESSION[$InfoC]['IdClient']=$this->IdClient ;
	  $_SESSION[$InfoC]['IdFacture']=$this->IdFacture ;
	  $_SESSION[$InfoC]['Existe']=$this->Existe ;
	  
	  if (isset($NomC)){
		$_SESSION[$InfoC]['NomClt']=$NomC ;
		if ($this->IdFacture>0){
			$TxSQL="update ".$this->MaBoutique->DBase.".commande SET nom='".$NomC."' where id='".$this->IdFacture."' limit 1" ;
			$this->MaBoutique->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
		}
	  }
	  if (isset($PrenomC)){
		$_SESSION[$InfoC]['PrenomClt']=$PrenomC ;
		if ($this->IdFacture>0){
			$TxSQL="update ".$this->MaBoutique->DBase.".commande SET prenom='".$PrenomC."' where id='".$this->IdFacture."' limit 1" ;
			$this->MaBoutique->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
		}
	  }
	  if (isset($IdClient)){
		$_SESSION[$InfoC]['IdClient']=$IdClient ;
	  }
	  if (isset($IdFacture)){
		$_SESSION[$InfoC]['IdFacture']=$IdFacture ;
	  } 
	  if (isset($DateFacture)){
		  $_SESSION[$InfoC]['DateFacture']=$DateFacture ;
		  //$this->Dump() ;
		  //exit ;
	  }
	  
	  $this->GetInfosClient() ;
	  return true ;
  }
  public function GetInfosClient(){
	  $InfoC=$this->PanierId."CLIENT" ;
	  $this->NomClt=$_SESSION[$InfoC]['NomClt'] ;
	  $this->PrenomClt=$_SESSION[$InfoC]['PrenomClt'] ;
	  $this->IdClient=$_SESSION[$InfoC]['IdClient'] ;
	  $this->IdFacture=$_SESSION[$InfoC]['IdFacture'] ;
	  //$this->DateFacture=$_SESSION[$InfoC]['DateFacture'] ;
	  $this->Existe=$_SESSION[$InfoC]['Existe'] ;
	  return true ;
  }
  
  public function Dump(){
	   $InfoC=$this->PanierId."CLIENT" ;
	   echo 'Objet Panier '.$this->Id.': </pre>' ;
	   var_dump($_SESSION[$this->PanierId]) ;
	   echo 'Information de facturation liée au panier: </pre>' ;
	   var_dump($_SESSION[$InfoC]) ;
	   return true ;
  }
  
  public function DateFacture($NvDate=null){
	  $InfoC=$this->PanierId."CLIENT" ;
	  
	  if (isset($NvDate)){
		  $_SESSION[$InfoC]['DateFacture']=$NvDate ;
		  return true ;
	  }
	  $DateF=date('Y-m-d') ;
	  if (isset($_SESSION[$InfoC]['DateFacture'])){
		$DateF=$_SESSION[$InfoC]['DateFacture'] ;
	  }
	  return $DateF ;
  }
  
  public function DejaValider($SetTrue=null){
	  $InfoC=$this->PanierId."CLIENT" ;
	  if (isset($SetTrue)){
		$_SESSION[$InfoC]['DejaValider']=$SetTrue ;		
	  }
	  if (isset($_SESSION[$InfoC]['DejaValider'])){
		return $_SESSION[$InfoC]['DejaValider'] ;
	  }
	  return false ;
	  
  }
  public function IdCmd($IdCmd=null,$IdBoutiqueCmd=null){
	  $InfoC=$this->PanierId."CLIENT" ;
	  if (isset($IdCmd)){
		$_SESSION[$InfoC]['IdCmd']=$IdCmd ;	
		if (isset($IdBoutiqueCmd)){
			$_SESSION[$InfoC]['IdBoutiqueCmd']=$IdBoutiqueCmd ;		
		}
	  }
	  if (isset($_SESSION[$InfoC]['IdCmd'])){
		return $_SESSION[$InfoC]['IdCmd'] ;
	  }
	  return false ;	  
  }	
  public function IdBoutiqueCmd($IdBoutiqueCmd=null,$IdCmd=null){
	  $InfoC=$this->PanierId."CLIENT" ;
	  if (isset($IdBoutiqueCmd)){
		$_SESSION[$InfoC]['IdBoutiqueCmd']=$IdBoutiqueCmd ;	
		if (isset($IdCmd)){
			$_SESSION[$InfoC]['IdCmd']=$IdCmd ;		
		}
	  }
	  if (isset($_SESSION[$InfoC]['IdBoutiqueCmd'])){
		return $_SESSION[$InfoC]['IdBoutiqueCmd'] ;
	  }
	  return false ;	  
  } 

  public function PdtExiste($IdPdt,$TypeV=0){
		$vId=$IdPdt."_".$TypeV ;
		if(isset($_SESSION[$this->PanierId][$vId])){
			return true ;
		}else{
			return false; 
		}
	}

  public function ToPanierVente(){
		$Bout=new xBoutique($this->MaBoutique->Main,$this->MaBoutique->Id);
		$PanierVente=$Bout->GetNewPanier();
		$panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
		if(!empty($panier)){
			foreach($panier as $P){ 
				$PanierVente->addProduct($P['id_produit'],
				$P['produit'],
				$P['qte'],
				$P['PrixU'],
				$P['typev'],
				$this->IdClient	);
			}
			$PanierVente->SaveInfosClient($this->NomClt,$this->PrenomClt,
											$this->IdClient) ;
		}
		return $PanierVente ;
	}
  
}