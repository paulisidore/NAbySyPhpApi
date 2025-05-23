<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;

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

            $Table=$Bout->DataBase.".".$nabysy->User->Table ;
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
        case 'BOUTIQUE_CONFIG_GET': //Retourne une configuration
            $IdConfig=null;
            if(isset($_REQUEST['IDCONFIG'])){
                if ((int)($_REQUEST['IDCONFIG'])){
                    $IdConfig = (int)$_REQUEST['IDCONFIG'];
                }
            }
            $Param=new xORMHelper($nabysy,$IdConfig,N::GLOBAL_AUTO_CREATE_DBTABLE,"parametre");
            $Reponse=new xNotification;
            if ($Param->Id==0){
                $Lst=$Param->ChargeListe(null,null,"ID","ID Limit 1");
                if ($Lst->num_rows){
                    $rw=$Lst->fetch_assoc();
                    $IdConfig = $rw['ID'];
                    $Param=new xORMHelper($nabysy,$IdConfig,N::GLOBAL_AUTO_CREATE_DBTABLE,"parametre");
                }
            }
            //var_dump($Param->ToJSON());
            $Reponse->OK=1;
            $Reponse->Contenue=$Param->ToObject();
            echo json_encode($Reponse);
            exit;
            break;
        
        case 'BOUTIQUE_CONFIG_SET': //Retourne une configuration
            $IdConfig=null;
            $NewConfig=false;
            if(isset($_REQUEST['ID'])){
                if ((int)($_REQUEST['ID'])){
                    $IdConfig = (int)$_REQUEST['ID'];
                }
            }
            $Param=new xORMHelper($nabysy,$IdConfig,N::GLOBAL_AUTO_CREATE_DBTABLE,"parametre");
            $Reponse=new xNotification;
            if ($Param->Id==0){
               $NewConfig=true;
            }

            $YouCanSave=false ;
            $MySQL=new xDB($nabysy);
            $MySQL->DebugMode=false;
            $ListeChampIntrouvable=[];
            //$Param->AddToLog(__FILE__.":".__LINE__.": Param.".json_encode($_REQUEST));
            
            foreach($_REQUEST as $Champ => $Valeur){
                
                if (strtolower($Champ) !== 'id' and strtolower($Champ) !== 'token' 
                    and strtolower($Champ) !== 'action' and strtolower($Champ) !== 'niveauacces' ){
                    //echo 'Champ '.$Champ." = ".$Valeur." /br" ;
                    if ($MySQL->ChampsExiste($Param->Table,$Champ,$Param->DataBase)){
                        if ($Valeur !=='undefined'){
                            if ($Param->IsTypeChampNumeric($Champ)){
                                if ($Param->GetTypeChampInDB($Champ)==$Param::$Ctype::FLOAT ||
                                    $Param->GetTypeChampInDB($Champ)==$Param::$Ctype::DOUBLE ||
                                    $Param->GetTypeChampInDB($Champ)==$Param::$Ctype::DECIMAL ){

                                    $Valeur=(float)$Valeur;
                                }else{
                                    $Valeur=(int)$Valeur;
                                }
                            }

                            $Param->$Champ=$Valeur;
                            //$Param->AddToLog(__FILE__.":".__LINE__.": Champ Param.".$Champ." = ".$Valeur);
                            $YouCanSave=true;
                        }
                    }else{
                        $ListeChampIntrouvable[]=$Champ;
                        if ($nabysy->ActiveDebug){
                            $Param->AddToLog(__FILE__.":".__LINE__.": Champ Param.".$Champ." introuvable.");
                            $Param->AddToJournal("CHAMP DYNAMIQUE",__FILE__.":".__LINE__.": Champ Param.".$Champ." introuvable.");                            
                        }
                    }
                }           
            }

            if ($YouCanSave){
                //var_dump($Param->ToJSON());
                //exit;                
                if ($Param->Enregistrer()){
                    if ($NewConfig){
                        $Param->AddToJournal("PARAMETRE","Enregistrement d'un nouveau paramètre. IdParam = ".$Param->Id) ;
                    }
                }
            }

            $Reponse->OK=1;
            $Reponse->Extra=json_encode($_REQUEST);
            $Reponse->Contenue=$Param->ToObject();
            echo json_encode($Reponse);
            exit;
            break;
            
		default:
			//Retourne();	
			break;
    }

// function Retourne($lien=null){
//     $Err=new xErreur;
//     $Err->TxErreur="Go back.";
//     $Err->Source="boutique_action" ;
//     $Err->OK=0 ;
//     $json=json_encode($Err) ;
//     echo $json ;
// }

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