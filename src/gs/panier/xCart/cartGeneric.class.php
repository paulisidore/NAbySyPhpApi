<?php
namespace NAbySy\GS\Panier ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Client\xClient;

/**
*  FICHIER : cart.class.php
*
*/
class xCartGeneric{
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
  public $Prefix ;

  public $ListePanier ;
  public $PanierMAX ;

  public $IdDevise ;
  public $Devise ;
  
  /**
  * Constructeur de la class
  */
  function __construct(xBoutique $Bou=null,$IdSession=1,$IsTemp=null,$IsNewCart=false,$pref=null ){
	// Démarrage des sessions si pas déjà démarrées
	$nomClass=get_class($Bou) ;
	if ($nomClass =="xNAbySyGS"){
		echo 'Erreur :'.$nomClass." donnée au lieu de xBoutique Attendue." ;
		exit ;
	}
	

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
	}
	if (!isset($pref)){
		$pref='paniergeneric';
	}
	$this->Prefix=$pref ;
	$this->PanierMAX=200 ;
	$this->ListePanier=[] ;
	if (isset($Bou)){
		$this->MaBoutique=$Bou ;
	}	

	if (isset($_SESSION['user'])){
		$this->IdCaissier=$_SESSION['id_user'];
		$this->Caissier=$_SESSION['user']; 
	}
	$this->ModePaiement="Espèce";
	$this->DateFacture(date("d-m-Y")) ;
	if (isset($IsTemp)){
		$IdSession .='temp' ;
		unset($_SESSION[$this->Prefix.$IdSession]) ;
		$this->Existe=false ;
	}
	if (isset($IsNewCart)){
		if ($IsNewCart){
			//Recherche AutoMatique du nouveau IdPanier
			$IdR=1 ;
			$RechId=$this->Prefix.$IdR ;
			if (isset($_SESSION[$RechId])){
				//On boucle
				while (isset($_SESSION[$RechId])){
					$IdR ++ ;
					$RechId=$this->Prefix.$IdR ;
				}
			}
			$IdSession=$IdR ;
		}
	}
	$this->Id=$IdSession;
	$this->PanierId=$this->Prefix.$this->Id ;
	$this->Client=new xClient($this->MaBoutique->Main) ;

	if (!$this->Existe){		
		$this->PanierId=$this->Prefix.$this->Id ;
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
	$panierid=$this->Prefix.$this->Id ;
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
  public function addProduct($id_produit,$libelle_produit,$qte=1,$PrixU=0,$ventegros=0,$IdClient=0,$modif=false,
  	$Grossiste=false){
	  $qte=(int)$qte ;
	  $PrixU=(int)$PrixU ;
    if($qte > 0 ){
		$vId=$id_produit."_".$ventegros ;
		//Si le produit est déjà la on update la qte
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
      $_SESSION[$this->PanierId][$vId]['qte'] = $qte;
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
		$Modifier='<a href="'.$this->Prefix.'.php?IdPanier='.$this->Id.'&ModifierLigne='.$P['vId'].$ParamT.'">Modifier</a>';
		$Supprimer='<a href="'.$this->Prefix.'.php?IdPanier='.$this->Id.'&SupprimerLigne='.$P['vId'].$ParamT.'">Supprimer</a>';
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
  
  public function GetArticle($vId):?xArticlePanier{	  
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
	  $_SESSION[$InfoC]['IdCmd']=null ;
	  $_SESSION[$InfoC]['IdBoutiqueCmd']=null ;
	  unset ($InfoC) ;
	  unset ($_SESSION[$this->PanierId]) ;
	  
	  return true ;
  }
  
  public function SaveInfosClient($NomC=null,$PrenomC=null,$IdClient=null,$IdFacture=null,$DateFacture=null,$Table='commande',$ChNom="MOTIF",$ChPrenom='RESPONSABLE'){
	
	//$this->GetInfosClient() ; 
	

	$InfoC=$this->PanierId."CLIENT" ;
	//echo 'Mon IdFacture='.$_SESSION[$InfoC]['IdFacture']." |" ;
	  $_SESSION[$InfoC]['NomClt']=$this->NomClt ;
	  $_SESSION[$InfoC]['PrenomClt']=$this->PrenomClt ;
	  $_SESSION[$InfoC]['IdClient']=$this->IdClient ;
	  $_SESSION[$InfoC]['IdFacture']=$this->IdFacture ;
	  $_SESSION[$InfoC]['IdDevise']=0 ;
	  
	  if (isset($IdFacture)){
		$_SESSION[$InfoC]['IdFacture']=$IdFacture ; 
	  }
	  
	  $_SESSION[$InfoC]['Existe']=$this->Existe ;
	  
	  if (isset($NomC)){
		$_SESSION[$InfoC]['NomClt']=$NomC ;
		if ($this->IdFacture>0){
			$TxSQL="update ".$this->MaBoutique->DBase.".".$Table." SET ".$ChNom."='".$NomC."' where id='".$this->IdFacture."' limit 1" ;
			$this->MaBoutique->Main->ReadWrite($TxSQL,true,true) ;
		}
	  }
	  if (isset($PrenomC)){
		$_SESSION[$InfoC]['PrenomClt']=$PrenomC ;
		if ($this->IdFacture>0){
			$TxSQL="update ".$this->MaBoutique->DBase.".".$Table." SET ".$ChPrenom."='".$PrenomC."' where id='".$this->IdFacture."' limit 1" ;
			$this->MaBoutique->Main->ReadWrite($TxSQL,true,null,true) ;
		}
	  }
	  if (isset($IdClient)){
		$_SESSION[$InfoC]['IdClient']=$IdClient ;
	  }

	  if (isset($DateFacture)){
		  $_SESSION[$InfoC]['DateFacture']=$DateFacture ;
		  //$this->Dump() ;
		  //exit ;
	  }

	  $_SESSION[$InfoC]['IdDevise']=$this->IdDevise ;
	 
	  $this->GetInfosClient() ;
	  return true ;
  }

  public function GetInfosClient(){
	  $InfoC=$this->PanierId."CLIENT" ;
	  $this->NomClt=$_SESSION[$InfoC]['NomClt'] ;
	  $this->PrenomClt=$_SESSION[$InfoC]['PrenomClt'] ;
	  $this->IdClient=$_SESSION[$InfoC]['IdClient'] ;
	  $this->IdFacture=$_SESSION[$InfoC]['IdFacture'] ;

	  $this->Client=new xClient($this->MaBoutique->Main,0);
	  $this->Client->Id=$this->IdClient ;
	  $this->Client->Nom=$this->NomClt;
	  $this->Client->Prenom=$this->PrenomClt ;
	  	
	  //$this->DateFacture=$_SESSION[$InfoC]['DateFacture'] ;
	  $this->Existe=$_SESSION[$InfoC]['Existe'] ;
	  if (isset($_SESSION[$InfoC]['IdDevise']) ){
		$this->IdDevise=$_SESSION[$InfoC]['IdDevise'];
	  }
	  
	  return true ;
  }
  
  public function Dump($NumLigne=null){
	   $InfoC=$this->PanierId."CLIENT" ;
	   if ($NumLigne){
		   echo "Numero de Ligne: ".$NumLigne."</br>" ;
	   }
	   echo 'Prefixe du Panier: '.$this->Prefix.': </pre></br>' ;
	   echo 'Objet Panier '.$this->Id.': </pre>' ;
	   var_dump($_SESSION[$this->PanierId]) ;
	   echo 'Information Client liée au panier: </pre>' ;
	   var_dump($_SESSION[$InfoC]) ;
	   //echo 'Information Client liée avec la base de donnée '.$this->MaBoutique->DBase.' : </pre>' ;
	   //var_dump($this->Client) ;

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
  public function GetListeJSON($ParametreSuplementaire=null){
    $panier = !empty( $_SESSION[$this->PanierId] ) ? $_SESSION[$this->PanierId] : null;
	$Ligne ='' ;
	//$JSON[] ;
	if (!isset($panier)){
		return '{"data":[]}' ;
	}
	$Reponse='{"data":' ;
    if(!empty($panier)){
		$ParamT='';
		if (isset($ParametreSuplementaire)){
			$ParamT='&'.$ParametreSuplementaire ;
		}
      foreach($panier as $P){ 
		$SelectBox='<input type="checkbox" id="check"'.$P['vId'].'" />' ;
		$Modifier='<a href="reductionstock.php?IdPanier='.$this->Id.'&ModifierLigne='.$P['vId'].$ParamT.'">Modifier</a>';
		$Supprimer='<a href="reductionstock.php?IdPanier='.$this->Id.'&SupprimerLigne='.$P['vId'].$ParamT.'">Supprimer</a>';
		$Couleur='' ;
		if ($P['typev']==1){
			$Couleur='bgcolor="#00FF00"' ;
		}
		$lignejson[]=$SelectBox ;
		$lignejson[]=$P['qte'] ;
		$lignejson[]=$P['produit'] ;
		$lignejson[]=$P['PrixU'] ;
		$lignejson[]=$P['prix_Total'] ;
		$lignejson[]=$Modifier ;
		$lignejson[]=$Supprimer ;		
		$JSON[]=$lignejson ;
		$lignejson=array();
      }	  
	}
	if (isset($JSON)){
		$Reponse .=json_encode($JSON) ;
	}
	$Reponse .="}" ;
    return $Reponse;
  }
  
  /*
  								Fonctions de gestion du Panier Généric
  	---------------------------------------------------------------------------------------------------------
  */

	public function GetNewPanier($IdSession=null,$IsTemp=null){
		$NbPanier=0 ;
		$PanierIdR=$this->Prefix ;		
		$NbPanier=count($this->ListePanier) ;		
		$MAX=$this->PanierMAX ;
		$Tmp='' ;
		if (isset($IsTemp)){
			$Tmp='temp' ;
		}
		for ($i=1 ; $i <= $MAX ;$i++){
			$PanierIdR .=$i.$Tmp ;
			//echo "</br>Je recherche un nouveau Id Panier...".$i ;
			if (!isset($_SESSION[$PanierIdR])){
				$NewIdPanier=$i.$Tmp ;
				break ;
			}
		}
		if (isset($IdSession)){
			$NewIdPanier=$IdSession ;
		}
		
		$NewPanier=new xCartGeneric($this->MaBoutique,$NewIdPanier,$IsTemp,null,$this->Prefix);
		
		//$NewPanier->Id=$NewId ;
		if ($NewPanier){
			$NewPanier->Existe=true;
			$NewPanier->DejaValider(false) ;
			array_push($this->ListePanier,$NewPanier);
		}
		return $NewPanier ;
	}

	public function GetPanier($IdPanier){
		$Panier=null ;
		$PanierIdR=$this->Prefix.$IdPanier ;
		$InfoC=$PanierIdR."CLIENT" ;
		$IdClient=0 ;
		$NomClient='' ;
		$PrenomClient='' ;
		$IdFacture=-1 ;
		if (isset($_SESSION[$PanierIdR])){
			$PanierS=$_SESSION[$PanierIdR] ;
			if (isset($_SESSION[$InfoC])){
				$ClientS=$_SESSION[$InfoC] ;
				$Lig=$_SESSION[$InfoC] ;
				//var_dump($Lig) ;
			}
			if (isset($ClientS['NomClt'])){
				$NomClient=$ClientS['NomClt'] ;
				$PrenomClient=$ClientS['PrenomClt'] ;
				$IdClient=$ClientS['IdClient'] ;
				$IdFacture=$ClientS['IdFacture'] ;
			}else{
				foreach($PanierS as $P){
					if (isset($P[$IdClient])){
						$IdClient=$P[$IdClient] ;
						$NomClient=$P['NomClt'] ;
						$PrenomClient=$P['PrenomClt'] ;
					}
				}				
			}
						
			$Panier=new xCartGeneric($this->MaBoutique, $IdPanier,null,null,$this->Prefix) ;
						
			$Panier->NomClt=$NomClient;
			$Panier->PrenomClt=$PrenomClient ;
			$Panier->SaveInfosClient($NomClient,$PrenomClient,$IdClient,$IdFacture) ;
			
			if ($IdClient>0){
				$Panier->Client=new xClient($this->MaBoutique->Main,$IdClient) ;
				$Panier->NomClt=$Panier->Client->Nom;
				$Panier->PrenomClt=$Panier->Client->Prenom;
				$Panier->SaveInfosClient($NomClient,$PrenomClient,$IdClient,$IdFacture) ;
			}
			
		}
		return $Panier ;
	}

	public function GetNbPanier(){
		$this->ChargeListePanier() ;
		$NbPanier=count($this->ListePanier) ;		
		return $NbPanier ;
	}

	public function ChargeListePanier(){		
			$this->ListePanier=array() ;
			for ($x=0;$x<=$this->PanierMAX;$x++){
				$PanierID=$this->Prefix.$x ;
				if (isset($_SESSION[$PanierID])){				
					$Panier=new xCartGeneric($this->MaBoutique,$x);
					if ($Panier){
						if (isset($_SESSION[$Panier->PanierId])){
							if (!$Panier->DejaValider()){
								array_push($this->ListePanier,$Panier);
							}
						}
					}
				}else{
					if ($x>0){
						//break ;
					}				
				}
			}
	}

	public function FermerToutPanier(){
		for ($x=0;$x<=100;$x++){
			$PanierID=$this->Prefix.$x ;
			if (isset($_SESSION[$PanierID])){					
				$Panier=new xCartGeneric($this->MaBoutique,$x);
				if ($Panier){
					if (isset($_SESSION[$Panier->PanierId])){
						unset ($_SESSION[$Panier->PanierId]);
					}
				}
			}
		}
		$this->ListePanier=array() ;
	}

	public function GetLastPanier(){
		$c=0 ;
		$NbP=$this->GetNbPanier(); //Permet de recharger la liste des paniers
		$Liste=array() ;
		
		foreach ($this->ListePanier as $P){
			if (!$P->DejaValider()){
				if ($P->Prefix==$this->Prefix){
					array_push($Liste,$P);
					$c++ ;
				}				
			}			
		}
		$i=0 ;
		if ($c>0){
			foreach ($Liste as $P){
				$i++ ;
				if ($i==$c){
					$Panier=$P;
					return $Panier ;
				}
			}			
		}		
		$Panier=$this->GetNewPanier(1,null) ;
		return $Panier ;		
	}

	public function SetDevise(xDevise $nDevise){

		if (isset($nDevise)){
			$this->IdDevise=$nDevise->Id ;
			$PanierIdR=$this->Prefix.$nDevise->Id ;
			$InfoC=$PanierIdR."CLIENT" ;
			$this->Devise=$nDevise ;
			$_SESSION[$InfoC]['IdDevise']=$this->IdDevise ;
		}

		
	}
}