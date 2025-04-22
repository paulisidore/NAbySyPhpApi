<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\xErreur;

$ChampAction='Action';
$action=null ;
$PARAM=$_REQUEST;
if (isset($PARAM[$ChampAction])){
    $action=$PARAM[$ChampAction] ;
}
if (isset($PARAM[strtolower($ChampAction)])){
    $action=$PARAM[strtolower($ChampAction)] ;
}

$Err=new xErreur ;
$Err->TxErreur='Erreur';
$Err->OK=0 ;
if (!isset($action)){		
    //Il n'y a pas d'action, on retourne a la page précedente
    $Err->OK=0;
    $Err->TxErreur='Action non définit !' ;
    $Err->Source= __FUNCTION__ ;
    $reponse=json_encode($Err) ;
    echo $reponse ;
    exit;	
}

switch ($action){
		case "LISTE_BOUTIQUE":
			//Retourne la Liste des Boutiques
			$TxM=false ;
			$CallBack=null ;
			
			if (isset($PARAM['CallBack'])){
				$CallBack=$PARAM['CallBack'] ;
			}		
            $TxSQL="select * from ".$nabysy->MainDataBase.".".$nabysy->MaBoutique->Table." order by ID" ;
            //$Reponse=R::getAll($TxSQL) ;

            $Rep=$nabysy->ReadWrite($TxSQL) ;
                       
            $Liste=$nabysy->EncodeReponseSQL($Rep) ;
            $vListe=array() ;
            foreach ($Liste as $Ligne){
                $vListe[]=$Ligne ;
            }
            ;
            $json=json_encode($nabysy->utf8ize($vListe)) ;
            if (!$json){
                RetourneJsonError($nabysy->GetJsonError());
                exit ;
            }
            echo $json ;            

            break ;
        
        case "LISTE_PRODUIT":
            //Retourne la Liste des Articles de la Boutique
            $Produit=new xProduit($nabysy);
            $IdBoutique=$nabysy->MaBoutique->Id ;
            $Table=$nabysy->MaBoutique->DataBase.".".$Produit->Table ;

            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DataBase.".".$Produit->Table ;
            }
            if (isset($PARAM['IDBOUTIQUE'])){
                $IdBoutique=$PARAM['IDBOUTIQUE'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
                $Table=$Bout->DataBase.".".$Produit->Table ;
            }
            $NbCrit=0 ;
            $TxCritere="" ;
            $TxOr="";

            $TxSQL="select * from ".$Table." where id>0 " ;
            if (isset($PARAM['DESIGNATION'])){
                $NbCrit ++;
                if ($NbCrit>1){
                    $TxOr=" OR ";
                }
                $TxCritere .=$TxOr ." nom like '%".$PARAM['DESIGNATION']."%' " ;
            }
            if (isset($PARAM['CODEBAR'])){
                $NbCrit ++;
                if ($NbCrit>1){
                    $TxOr=" OR ";
                }
                $TxCritere .=$TxOr ." code like '".$PARAM['CODEBAR']."' or id like '".$PARAM['CODEBAR']."' " ;
            }

            if ($NbCrit>0){
                $TxSQL .=" and ( ".$TxCritere.") " ;
            }            

            $Rep=$nabysy->ReadWrite($TxSQL) ;
            $Liste=array();
            if ($Rep)			{
                while ($RW=$Rep->fetch_assoc()){
                    $Liste[]=$nabysy->utf8ize($RW) ;
                }
            }
            $json=json_encode($nabysy->utf8ize($Liste)) ;
            if (!$json){
                RetourneJsonError($nabysy->GetJsonError());
                exit ;
            }
            echo $json ;
            
            break ;

        case "LISTE_USER":
            $IdBoutique=null;
            $Bout=$nabysy->MaBoutique ;

            $TableUser="utilisateur";
            if (isset($nabysy->User)){
                $TableUser=$nabysy->User->Table;
            }
            
            if (isset($_REQUEST["IDBOUTIQUE"])){
                $IdBoutique=$_REQUEST["IDBOUTIQUE"] ;
                if ($nabysy->MaBoutique->Id != $IdBoutique){
                    $Bout= new xBoutique($nabysy, $IdBoutique) ; //$nabysy->GetBoutiqueFromCache($IdBoutique);
                    if (isset($Bout)){
                        $nabysy->MaBoutique=$Bout ;
                    }
                }
            }
            if (!isset($IdBoutique)){
                $Bout=$nabysy->MaBoutique ;
            }

            $Table=$Bout->DataBase.".".$TableUser ;
            $TxSQL="select * from ".$Table." order by login " ;
            $Rep=$nabysy->ReadWrite($TxSQL) ;
            if ($Rep->num_rows>0){
                //$RW=$Rep->fetch_assoc() ;
                while ($RW=$Rep->fetch_assoc()){
                    $Liste[]=$nabysy->utf8ize($RW) ;
                }
                $json=json_encode($nabysy->utf8ize($Liste)) ;
            }else{
                $Err=new xErreur;
                $Err->TxErreur="Aucun utilisateur trouvé.";
                $Err->Source="boutique_action.php" ;
                $Err->OK=0 ;
                $json=json_encode($Err) ;
            }			
            echo $json ;

            break;
		default:
			Retourne();	
			break;
    }

function Retourne($lien=null){
    $Err=new xErreur;
    $Err->TxErreur="Go back.";
    $Err->Source="boutique_action" ;
    $Err->OK=0 ;
    $json=json_encode($Err) ;
    echo $json ;
}

function RetourneJsonError($TxErr=''){
    if ($TxErr==''){
        $TxErr='Erreur non précisée';
    }
    $Err=new xErreur;
    $Err->OK=0;
    $Err->TxErreur = $TxErr;
    $json=json_encode($Err) ;
    echo $json ;
}


?>