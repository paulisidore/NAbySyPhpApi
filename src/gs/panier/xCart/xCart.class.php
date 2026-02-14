<?php
 namespace NAbySy\GS\Panier ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;
use NAbySy\GS\Stock\xProduitNC;
use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;

/**
*  FICHIER : cart.class.php
*
*/
include_once 'cartproforma.class.php';
include_once 'cartproforma.class.php';
include_once 'cartGeneric.class.php';
include_once 'xArticlePanier.class.php';
include_once 'xCaissier.class.php';
include_once 'xDevise.class.php';
include_once 'xPanier.class.php';

class xCart{
  public xNAbySyGS $Main;
  public xBoutique $MaBoutique ;
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

  public $TotalRemise ;

  public $TotalReduction ;

  public int $IdPoste ;
  public string $NomPoste ;

  public string $NomBeneficiaireRemise = '' ;

  public string $TextNote ;

  /**
   * Référence du Bon de Commande lié au panier
   * @var string
   */
  public string $RefCMD = '';

  public ?xORMHelper $MetaDonnee ;

  public const META_DONNEE_ARTICLE_SUPPRIME = "ARTICLE_SUPPRIME";
  public const META_DONNEE_ARTICLE_QTE_EN_MOINS = "QTE_EN_MOINS";
  public const META_DONNEE_SAISIE_ANNULEE = "ANNULATION_DE_LA_SAISIE";
  
  
  
  /**
  * Constructeur de la class
  */
  function __construct(xBoutique $Bou=null,$IdSession=1,$IsTemp=null){
    // Démarrage des sessions si pas déjà démarrées
    if (session_status() == PHP_SESSION_NONE) {
        //session_start();
    }
	if (isset($Bou)){
		$this->Main = $Bou->Main;
		$this->MaBoutique=$Bou ;
	}	 
	if (isset($_SESSION['user'])){
		$this->IdCaissier=$_SESSION['id_user'];
		$this->Caissier=$_SESSION['user']; 
	}
	$this->ModePaiement="Espèce";
	$this->DateFacture(date("d-m-Y")) ;

	if (isset($Bou)){
		if (isset($Bou->Main->User)){
			if ($Bou->Main->User->Id>0){
				$this->IdCaissier=$Bou->Main->User->Id;
				$this->Caissier=$Bou->Main->User->LOGIN; 
			}
		}
	}

	if (isset($IsTemp)){
		$IdSession .='temp' ;
		unset($_SESSION['panier'.$IdSession]) ;
		$this->Existe=false ;
	}
	if ($IdSession <= 0){
		$Trouv=0;
		$i=1;
		//On recherche un nouveau IdSession
		while ($Trouv==0){
			$RPanierId='panier'.$i ;
			if (!isset($_SESSION[$RPanierId])){
				$Trouv=$i ;
				break;
			}
			$i++ ;
		}
		$IdSession=$Trouv;
	}

	$this->Id=$IdSession;
	
	if (!$this->Existe){		
		$this->PanierId='panier'.$this->Id ;
		if (!isset($_SESSION[$this->PanierId])){
			//Si la variable session n'existe pas alors on l'initialise
			$this->initCart();
		}
	}    
  }
  
  /**
  *Initialisation du panier
  */
  public function initCart(){
	$panierid='panier'.$this->Id ;
	$InfoC=$this->PanierId."CLIENT" ;
    $_SESSION[$this->PanierId] = array();
	$this->NomBeneficiaireRemise=""; 	
		
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
  public function addProduct($id_produit,$libelle_produit,float $qte=1, float $PrixU=0,$ventegros=0,$IdClient=0,$modif=false,
  	$Grossiste=false,$CodeB=null){
	  $qte=(float)$qte ;
	  $PrixU=(float)$PrixU ;
	  $IsPdtClown=false;

	  if ($id_produit<0 && isset($CodeB)){
		if($CodeB !==''){
			$IsPdtClown=true;
		}		
	  }
	  $PrefixPdtClown="";
	  if ($IsPdtClown){
		$NbLigneAct=$this->getNbProductsInCart()+1;
		$PrefixPdtClown="clown_".$NbLigneAct;
	  }
    if($qte !== 0 ){
		$vId=$id_produit."_".$ventegros.$PrefixPdtClown ;
		//Si le produit est déjà la on update la qte
		if (isset($_SESSION[$this->PanierId][$vId] ) ){
			$PrecQte=$_SESSION[$this->PanierId][$vId]['qte'] ;
			//echo "</br>AddProduit: La qté passe de ".$PrecQte ;
			if ($modif){
				$NewQte=$qte ;
				//echo "</br>AddProduit: Je modifie a  ".$NewQte ;
				//exit;
				if ($PrixU != (float)$_SESSION[$this->PanierId][$vId]['PrixU']){
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
													,'IsPdtClown'=>$IsPdtClown
													,'CodeBar'=>$CodeB
													); 
		}
												
      $this->updateTotalPriceProduct($id_produit,$ventegros,$vId);
	  if ($IdClient > 0){
		  $this->Client=new xClient($this->Main,$IdClient) ;
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
  
  private function updateTotalPriceProduct($id_produit,$ventegros=0,$vraiIdSession=null){
	  $vId=$id_produit."_".$ventegros ;
	  if (isset($vraiIdSession)){
		$vId=$vraiIdSession;
	  }
    if(isset($_SESSION[$this->PanierId][$vId])){
      $_SESSION[$this->PanierId][$vId]['prix_Total'] = (float)$_SESSION[$this->PanierId][$vId]['qte'] * (float)$_SESSION[$this->PanierId][$vId]['PrixU'];
    }
  }
  
  /**
  * Modifie la quantité d'un produit dans le panier
  */
  public function updateQteProduct($id_produit,float $qte=0,$ventegros=0,$vId=null){
	if (!isset($vId)){
		$vId=$id_produit."_".$ventegros ;
	}
    if(isset($_SESSION[$this->PanierId][$vId])){
      $_SESSION[$this->PanierId][$vId]['qte'] = $qte;
      $this->updateTotalPriceProduct($id_produit,$ventegros,$vId);
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
  public function getNbProductsInCart():int{
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
  
  public function getTotalPriceCart():float{
    $total = 0;
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : NULL;
    if(!empty($panier)){
      foreach($panier as $P){ 
		//$this->MaBoutique->Main::$Log->AddToLog(json_encode($P));
		$pt=(float)$P['prix_Total'];
		if($this->MaBoutique->Parametre && $this->MaBoutique->Parametre->ChampsExisteInTable('NbArrondie')){
			$pt=round($pt, (int)$this->MaBoutique->Parametre->NbArrondie, PHP_ROUND_HALF_DOWN);
			if((int)$this->MaBoutique->Parametre->NbArrondie == 0){
				$pt = (int)$pt ;
			}
		}
        $total += $pt;
		//$TxLog= "total=".$total."+".(float)$P['prix_total'];
		//$this->MaBoutique->Main::$Log->AddToLog($TxLog);
      }
    }
    return $total;
  }

  /**
   * Retourne le montant Total Net à payer aprés toutes les réductions.
   */
  public function getTotalNetAPayer():float{
	$TotalFacture=$this->getTotalPriceCart();
	//$TxLog= "getTotalNetAPayer=".$TotalFacture ."-". (float)$this->TotalRemise ."-". (float)$this->TotalReduction ;
	$Total=$TotalFacture - (float)$this->TotalRemise - (float)$this->TotalReduction ;
	$pt=$Total;
	if($this->MaBoutique->Parametre && $this->MaBoutique->Parametre->ChampsExisteInTable('NbArrondie')){
		$pt=round($pt, (int)$this->MaBoutique->Parametre->NbArrondie, PHP_ROUND_HALF_DOWN);
		$Total = $pt;
		if((int)$this->MaBoutique->Parametre->NbArrondie == 0){
			$Total = (int)$Total ;
		}
	}
	//$TxLog  ." = ".$Total."</br>";
	//$this->MaBoutique->Main::$Log->AddToLog($TxLog);
	return (float)$Total ;
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
		$Modifier='<a href="vente.php?IdPanier='.$this->Id.'&ModifierLigne='.$P['vId'].$ParamT.'">Modifier</a>';
		$Supprimer='<a href="vente.php?IdPanier='.$this->Id.'&SupprimerLigne='.$P['vId'].$ParamT.'">Supprimer</a>';
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
				//var_dump($P);
				if ($P['IsPdtClown']){
					$PdtNC=new xProduitNC($this->MaBoutique->Main,null,false,null,null,$P['CodeBar']);
					if ($P['CodeBar'] == $PdtNC->CodeBar){
						$IdPdt=$P['id_produit'] ;
						$TypeV=$P['typev'] ;
						$CodeB=$P['CodeBar'];
						$Article=new xArticlePanier($this->MaBoutique->Main,$IdPdt,$P['qte'],$TypeV,$this->MaBoutique,$CodeB) ;
						$Article->PrixU=$P['PrixU'] ;
						break ;
					}
				}else{
					$IdPdt=$P['id_produit'] ;
					$TypeV=$P['typev'] ;
					$CodeB=$P['CodeBar'];
					$Article=new xArticlePanier($this->MaBoutique->Main,$IdPdt,$P['qte'],$TypeV,$this->MaBoutique,$CodeB) ;
					$Article->PrixU=$P['PrixU'] ;
					break ;
				}
				
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
	//   foreach ($this->MaBoutique->ListePanier as $P){
	// 	  if ($P->Id !== $this->Id){			  
	// 		  array_push($nvListe,$P);
	// 	  }		  
	//   }
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
			$TxSQL="update ".$this->MaBoutique->DBase.".vente SET nom='".$NomC."' where id='".$this->IdFacture."' limit 1" ;
			//$this->MaBoutique->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
		}
	  }
	  if (isset($PrenomC)){
		$_SESSION[$InfoC]['PrenomClt']=$PrenomC ;
		if ($this->IdFacture>0){
			$TxSQL="update ".$this->MaBoutique->DBase.".vente SET prenom='".$PrenomC."' where id='".$this->IdFacture."' limit 1" ;
			//$this->MaBoutique->Main->ReadWrite($TxSQL,null,true,null,null,null,true) ;
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

  /**
  * Retourne Les produits dans le panier sous forme JSON
  */
  public function GetListeJSON($ParametreSuplementaire=null,$ForDeskTop=false){
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : null;
	$Ligne ='' ;
	//$JSON[] ;
	if (!isset($panier)){
		if (!$ForDeskTop){
			return '{"data":[]}' ;
		}else{
			return '{"ListeArticle":[]}' ;
		}		
	}
	$Reponse='{"ListeArticle":' ;
	if (!$ForDeskTop){
		$Reponse='{"data":' ;
	}
    if(!empty($panier)){
		$ParamT='';
		if (isset($ParametreSuplementaire)){
			$ParamT='&'.$ParametreSuplementaire ;
		}
      foreach($panier as $P){ 
		$SelectBox='<input type="checkbox" id="check"'.$P['vId'].'" />' ;
		$Modifier='<a href="vente.php?IdPanier='.$this->Id.'&ModifierLigne='.$P['vId'].$ParamT.'">Modifier</a>';
		$Supprimer='<a href="vente.php?IdPanier='.$this->Id.'&SupprimerLigne='.$P['vId'].$ParamT.'">Supprimer</a>';
		$Couleur='' ;
		if ($P['typev']==1){
			$Couleur='bgcolor="#00FF00"' ;
		}
		if (!$ForDeskTop){
			$lignejson[]=$SelectBox ;
			$lignejson[]=$P['qte'] ;
			$lignejson[]=$P['produit'] ;
			$lignejson[]=$P['PrixU'] ;
			$lignejson[]=$P['prix_Total'] ;
			$lignejson[]=$Modifier ;
			$lignejson[]=$Supprimer ;		
			$JSON[]=$lignejson ;
		}else{
			$lignejson['ID']=$P['id_produit'] ;
			$lignejson['DESIGNATION']=$P['produit'] ;
			$lignejson['Qte']=$P['qte'] ;
			$lignejson['PRIXVENTE']=$P['PrixU'] ;
			$lignejson['PRIXTOTAL']=$P['prix_Total'] ;	
			$lignejson['VENTEDETAILLEE']=$P['typev'] ;
			$JSON[]=$lignejson ;
		}
		
		$lignejson=array();
      }	  
	}
	if (isset($JSON)){
		$Reponse .=json_encode($JSON) ;
	}
	$Reponse .="}" ;
    return $Reponse;
  }

  public function ToJSON(){
	/* Convertion du Panier au Format JSON */
	$Pan=array();
	$Pan['IdFacture']=$this->IdFacture ;
	$Pan['Fermee']=$this->Fermee;
	$Pan['Existe']=$this->Existe;	//=Yes si le panier de la session existe déja
	$Pan['Id']=$this->Id;	//Id Panier incrementé automatiquement a la creation
	//$Pan['PanierId']=$this->PanierId;
	//$Pan[]=$this->Client ;
	$Pan['NomClt']=$this->NomClt ;
	$Pan['PrenomClt']=$this->PrenomClt ;
	$Pan['IdClient']=$this->IdClient ;

	$Pan['IdProforma']=$this->IdProforma ;
	$Pan['IdCaissier']=$this->IdCaissier ;
	//$Pan[]=$this->Caissier ;
	$Pan['ModePaiement']=$this->ModePaiement ;
	$Pan['MontantVerse']=$this->MontantVerse ;
	$Pan['MontantRendu']=$this->MontantRendu ;	  
	$Pan['DateFacture']=$this->DateFacture() ;
	$Pan['HeureFacture']=$this->HeureFacture ;

	$Pan['TotalRemise']=$this->TotalRemise ;
	$Pan['TotalReduction']=$this->TotalReduction ;
	$Pan['NomBeneficiaireRemise']=$this->NomBeneficiaireRemise ;

	//print_r($Pan) ;
	$nPan = (object) array_filter((array) $Pan);

	$Reponse=json_encode($this->MaBoutique->Main->utf8ize($nPan), JSON_FORCE_OBJECT) ;
	$Reponse ="[".$Reponse."]" ;
	return $Reponse ;

  }
  
  /**
	 * Permet d'ajouter ou de retourner une reference de tout type au panier
	 * @param mixed $Ref
	 * @return mixed
	 */
	public function Reference($Ref=null){
		$InfoC=$this->PanierId."CLIENT" ;
		if (isset($Ref)){
			$_SESSION[$InfoC]['REFERENCE']=$Ref ;		
		}
		if (isset($_SESSION[$InfoC]['REFERENCE'])){
			return $_SESSION[$InfoC]['REFERENCE'] ;
		}
		return false ;
	}
  
	/**
	 * Permet de modifier le lieu de déstockage pour un produit du panier
	 * @param mixed $id_produit
	 * @param int $ventegros
	 * @param int $IdDepot
	 * @return bool
	 */
	public function UpdateProductIdDepot(string $vId,int $IdDepot=0): bool{
		if($IdDepot==0){
			$IdDepot=$this->MaBoutique->Id;
		}
	  if(isset($_SESSION[$this->PanierId][$vId])){
		$_SESSION[$this->PanierId][$vId]['IdDepot'] = $IdDepot;
	  }
	  return true;
	}

	/**
	 * Modifie le Lieu de déstockage pour tous les articles du panier
	 * @param int $IdDepot
	 * @return bool
	 */
	public function UpDatePanierIdDepot(int $IdDepot =0):bool{
		if($IdDepot==0){
			$IdDepot=$this->MaBoutique->Id;
		}
		$panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : null;
		if(!isset($panier)){return false;}
		foreach($panier as $P){
			$this->UpdateProductIdDepot($P['vId'],$IdDepot);
		}
		//echo __FILE__.' Ligne '.__LINE__.' => ' ;
		//echo $this->Dump() ;
		return true;
	}

}