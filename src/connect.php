<?php
	//Gestion des connexions
	//----------------------------------
	$NoCSS=true ;
	include_once 'nabysy_start.php';

function MY_URL(){
	return sprintf(
		"%s://%s%s",
		isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		$_SERVER['SERVER_NAME'],
		$_SERVER['REQUEST_URI']
	);
}
	
   if ($nabysy->ISCONNECTED == false){
	   echo "</br>NAbySy Erreur: ".$nabysy->Erreur."</br>" ;
	   exit ;
   }

if (isset($_POST['Login'])){
	$User=$_POST['Login'] ;
}
if (isset($_POST['Password'])){
	$Password=$_POST['Password'] ;
}

$table=$nabysy->MaBoutique->DBase.'.utilisateur' ;

$sql="select login,login as 'user',acces, acces as 'Profile','1' as 'OK',id,prenom,nom from ".$table." where Login like '".$User."' and Password=md5('".$Password."') limit 1 " ;
$reponse=$nabysy->ReadWrite($sql,null,false,null,null,null,false) ;
//$ligne = "{OK: '0', TxErreur: 'Login ou Mot de passe incorrect !', Extra: 'connect.php}" ;
$Err=new xErreur ;
$Err->OK='0' ;
$Err->TxErreur="Login ou Mot de passe incorrect" ;
$Err->Source="connect.php" ;
$Err->Extra="" ;
//$TxE=array("TxErreur"=>"Login ou Mot de passe incorrect", "OK"=>0, "Source"=>"connect.php", "Extra"=>"NoLogin");
$ligne=json_encode($Err,JSON_UNESCAPED_SLASHES ) ;

if (!isset($_SESSION['key'])){
	//Si la session a expiré alors on retourne à la page d'acceuil pour choisir la boutique
	//header('Location:'.$nabysy->BaseSite,true) ;
	//exit;
	$Err->TxErreur="La session a expirée" ;
	$Err->Source="connect.php" ;
	$Err->Extra="SESSION_OUT" ;
	$ligne=json_encode($Err,JSON_UNESCAPED_SLASHES ) ;
	echo $ligne ;
	exit ;
}

$retour=$ligne ;
if ($reponse->num_rows>0)
	{
		
		$ligne = $reponse->fetch_assoc() ;
		$IdUser=$ligne['id'];
		$profile=$ligne['Profile'];
		unset($_SESSION['user']) ;
		unset($_SESSION['acces']) ;
		unset($_SESSION['IdUser']) ;
		$_SESSION['user']=$User ;
		$_SESSION['acces']=$profile ;
		$_SESSION['IdUser']=$IdUser ;
		$nabysy->User=new xUser($nabysy->MaBoutique,$IdUser) ;

		if($nabysy->User->CanAccesBoutique((int)$nabysy->MaBoutique->Id) ==false ){
			$Err->TxErreur="Accès non autorisé à la boutique ".$nabysy->MaBoutique->Nom." [Id:".$nabysy->MaBoutique->Id."]" ;
			$Err->Source="connect.php" ;
			$Err->Extra="BOUTIQUE_ACCES_REFUSE" ;
			$ligne=json_encode($Err,JSON_UNESCAPED_SLASHES ) ;
			echo $ligne ;
			exit ;
		}

		$ligne['OK'] =1 ;
		$ligne['home'] ='../vues/ouverture_caisse.php';
		$ligne['IdBoutique'] =$nabysy->MaBoutique->Id;
		$ligne['NomBoutique'] =$nabysy->MaBoutique->Nom;

		if ($ligne['acces']=='Administrateur'){
			$ligne['home'] ='../vues/accueil.php' ;
		}
		if ($ligne['acces']=='Assistant'){
			$ligne['home'] ='../vues/inventaire_vente.php';
		}

		$_SESSION['user']=$ligne['acces'].": ".$ligne['prenom']." ".$ligne['nom'];
		$_SESSION['id_user']=$ligne['id'];
		$_SESSION['acces']=$ligne['acces'];
	//print_r($_SESSION) ;
	//exit ;	
	/* Journalisation dans NAbySyGS de la Connexion */
		$Dat=date("Y-m-d");
		$Tim=date("H:i:s");
		$OPERATION='CONNEXION' ;
		$DBCONN=$_SESSION['key'] ;
		$SiteWeb=MY_URL() ;
		$UserN=$_POST['Login'] ;
		$NOTE="Utilisateur ".$UserN." connecté sur la base de donnée ".$DBCONN." via le site ".$SiteWeb ;
		$req="insert into nabysygs.journal (`DATEENREG`,`HEUREENREG`,`OPERATION`,`NOTE`,`IDUTILISATEUR`) 
		VALUES ('".$Dat."','".$Tim."','".$OPERATION."','".$NOTE."','".$UserN."')";
		
		$reponsenabysygs=$nabysy->ReadWrite($req,null,true,null,null,null,true) ;
		//echo $req ;
		//exit ;
	/* ----------------------------------------------- */
				
		$retour=json_encode($ligne) ;
	}

echo $retour ;


?>