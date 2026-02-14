<?php

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\GS\Stock\xProduit;
use NAbySy\GS\Stock\xProduitNC;
use NAbySy\xErreur;
use NAbySy\xNotification;

    //include_once '../../nabysy_start.php' ;
    
    $PARAM=$_REQUEST;
    $Boutique=$nabysy->MaBoutique ;
    
    if (!isset($action)){
        //Il n'y a pas d'action, on retourne a la page précedente
        echo "Aucune Action!" ;
        exit;
    }

    $Err=new xErreur;
    $Err->TxErreur="Produit introuvable." ;
    $Err->OK=0;
    $Err->Source="stock_action" ;
    if ($nabysy->ActiveDebug){
        $Err->Source=__FILE__ ;
    }

    $Retour = new xNotification();
    $Retour->OK=1;

	switch ($action){
		case "CANCMD":
            //Modifie une autorisation sur une page
            $Produit =null ;
            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique) ;
			}
			if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
                $Produit=new xProduit($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,'produits', $Bout) ;
                $Produit->GetProduit($IdPdt) ;
            }
            
			$TxM=false ;
            $CallBack=null ;

            $Lien=null ;
            $Reponse="[ERREUR]" ;

			$Valeur=null ;			
			if (isset($PARAM['VALEUR'])){
				$Valeur=$PARAM['VALEUR'] ;
			}
           
            if (isset($Produit)){
                if ($Produit->Id>0){
                    if (isset($Valeur)){
                        $Reponse=$Produit->CanBeCmd($Valeur) ;
                    }
                }
            }
			echo $Reponse ;
			break ;

        case "EDIT_CODEBAR":
            //Modifie un CodeBarre
            $Produit =null ;
            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique)  ;
            }
            if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
                $Produit=new xProduit($nabysy) ;
                $Produit->GetProduit($IdPdt) ;
            }
            
            $TxM=false ;
            $CallBack=null ;

            $Lien=null ;
            $Reponse="[ERREUR]" ;

            $CodeBar=null ;			
            if (isset($PARAM['CODEBAR'])){
                $CodeBar=$PARAM['CODEBAR'] ;
            }
            
            if (isset($Produit)){
                if ($Produit->Id>0){
                    if (isset($CodeBar)){
                        $Reponse=$Produit->ChargeCodeBar($CodeBar) ;
                    }
                }
            }
            echo $Reponse ;
            break ; 
        
        case "GET_PRODUIT_BYID":
            $TypeReponse='JSON' ;
            $IdPdt=null;
            $Produit =null ;
            $Bout=$nabysy->MaBoutique ;
            if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
            }
            if (isset($PARAM['IdBoutique'])){
                $IdBoutique=$PARAM['IdBoutique'] ;
                $Bout=new xBoutique($nabysy,$IdBoutique)  ;
            }
            $Produit=new xProduit($nabysy,$IdPdt) ;           
            $Reponse="[ERREUR]" ;
            $retour=json_encode($Reponse) ;
            if ($Produit->Id>0){
                $Ligne=$Produit->RS;
                $Ligne['StockCarton']=$Produit->GetStockGros();
                $Ligne['StockDetail']=$Produit->GetStockRestantDetail();
                $retour=json_encode($Ligne) ; 
            }
            RetourneReponseAPI($retour) ; 

        case "SAVE_PRODUIT":
            $PdtJSON=null ;
            $vPdt=null;
            $NbTrouv=0 ;
            $IdPdt=0 ;
            $IsNew=true;

            $Err->TxErreur="Produit introuvable." ;
            $Reponse=json_encode($Err) ;
            if (isset($PARAM['ID'])){
				$IdPdt=(int)$PARAM['ID'] ;
            }elseif(isset($PARAM['Id'])){
				$IdPdt=(int)$PARAM['Id'] ;
            }elseif (isset($PARAM['IdProduit'])){
				$IdPdt=(int)$PARAM['IdProduit'] ;
            }elseif (isset($PARAM['IdPdt'])){
				$IdPdt=(int)$PARAM['IdPdt'] ;
            }            
            $Pdt=new xProduit($nabysy,$IdPdt) ;
            //var_dump($Pdt->Id);
            if ($Pdt->Id>0){
                $IsNew=false;
                $IdPdt=$Pdt->Id ;
            }else{
                if($IdPdt>0 && $Pdt->Id==0){
                    $Err->TxErreur="IdProduit ".$IdPdt." est introuvable !";
                    echo json_encode($Err);
                    exit;
                }
            }

            if (isset($PARAM['JSON_PDT'])){
				$PdtJSON=$PARAM['JSON_PDT'] ;
                if ($PdtJSON !==''){
                    $vPdt=json_decode($PdtJSON,true) ;
                }
            }else{
                $vPdt=[];
                foreach ($_REQUEST as $key => $value) {
                    if(strtoupper($key) !=="ACTION" && strtoupper($key) !=="TOKEN" && strtoupper($key) !=="STOCK" 
                        && strtoupper($key) !=="STOCKDETAIL" && strtoupper($key) !=="ID"  ){
                        $vPdt[$key] = $value;
                    } 
                }
            }

            $UpDatePresent=false ;
            if (isset($vPdt)){
                //print_r($Pdt->RS);
                foreach ($vPdt as $Champ => $Valeur){
                    //print_r($Valeur) ;
                    if($Pdt->ChampsExisteInTable($Champ)){
                        if ($Pdt->$Champ !== $Valeur || $IsNew){
                            $Pdt->$Champ=$Valeur ;
                        }                     
                        $UpDatePresent=true;
                    }
                }
            }

            if ($UpDatePresent){
                //print_r($Pdt->RS) ;
                $Pdt->Enregistrer() ;
                $IdPdt = $Pdt->Id ;
            }
            $Pdt=new xProduit($nabysy,$IdPdt) ;
            if (($Pdt->Id>0)){
                $PdtJSON=$Pdt->ToJSON() ;
                $Reponse=$PdtJSON ;
            }
            $Retour->Extra=$Pdt->Id ;
            if($IsNew && $Pdt->Id>0){
                $Retour->Autres="Nouveau Produit ajouté correctement.";
            }
            $Retour->Contenue = $Pdt->ToObject();
            echo json_encode($Retour) ;            
            exit;
            
        case "GET_PRODUIT":
            //var_dump($PARAM);
            $Pdt=new xProduit($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,'produits',$Boutique) ;
            $NbTrouv=0 ;
            $IdPdt=0 ;
            
            $Reponse=json_encode($Err) ;
            $Trouv=null;
            $CodeBar=null;
            $Designation=null;
            $IdPdt=null;
            $AvecCritere=false ;

            if (isset($PARAM['CODEBAR'])){
				$CodeBar=$PARAM['CODEBAR'] ;

                //On va d'abord vérifier s'il ne s'agit pas de pdt non classé
                $PdtNC=new xProduitNC($nabysy);
                if ($PdtNC->IsPdtNC($CodeBar)){
                    //Produit non classé
                    if ($PdtNC->ChargePdtNC($CodeBar)){
                        $PdtNC->PdtVirtuel=1;
                        $Reponse=$PdtNC->ToJSON();
                        //$Reponse=json_encode($Reponse) ;
                        $NbTrouv=1 ;
                        $Trouv=null ;
                        echo $Reponse ;
                        exit;
                    }                    
                }
                
				if ($CodeBar !==''){
					$Trouv=$Pdt->GetProduit(null,null,null,null,$CodeBar);					
					if ($Trouv){
                        //var_dump($Trouv);
						if ($Trouv->num_rows==1){
                            $rw=$Trouv->fetch_assoc();
							$IdPdt=$rw['ID'] ;
                            //var_dump($IdPdt);
                            $NbTrouv=1;
                            $AvecCritere=true ;
                            $Trouv=null ;
						}else{
                            $Reponse=$nabysy->EncodeReponseSQL($Trouv);
                            //$Reponse=$nabysy->SQLToJSON($Trouv) ;
                            $Reponse=json_encode($Reponse) ;
                            echo $Reponse ;
                            exit;
                        }
					}
				}
                							
			}			
			if (isset($PARAM['nomrech'])){
                $Designation=$PARAM['nomrech'] ;
                $AvecCritere=true ;
			}
            if (isset($PARAM['DESIGNATION'])){
                $Designation=$PARAM['DESIGNATION'] ;
                $AvecCritere=true ;		
			}
            if (isset($PARAM['Designation'])){
                $Designation=$PARAM['Designation'] ;
                $AvecCritere=true ;		
			}

			if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
                $AvecCritere=true ;
			}
            if (isset($PARAM['IdProduit'])){
                $IdPdt=$PARAM['IdProduit'] ;
                $AvecCritere=true ;
			}
            if (isset($IdPdt)){     
                //var_dump($IdPdt); 
                if ($IdPdt>0){
                    $Pdt=new xProduit($nabysy,$IdPdt,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,'produits',$Boutique) ; 
                    $Reponse=$Pdt->ToJSON();
                    //$Reponse=json_encode($Reponse) ;
                    $NbTrouv=1 ;
                    $Trouv=null ; 
                }				               
			}elseif($AvecCritere) {               
                $Lst=$Pdt->GetProduit($IdPdt,$Designation,null,null,$CodeBar);
                $Err->TxErreur="Produit introuvable." ;
                $Err->OK=0;
                $Err->Source=$action;
                $Reponse=json_encode($Err) ;
                $Trouv=null;                
                if ($Lst){
                    $NbTrouv=$Lst->num_rows;                 
                    if ($NbTrouv==0){
                        $Err->TxErreur="Produit introuvable." ;
                        $Err->OK=0;
                        $Reponse=json_encode($Err) ;
                        if (!$AvecCritere){
                            $Trouv=$Pdt->GetProduit();
                            if ($Trouv){
                                $NbTrouv=$Trouv->num_rows ;	
                                $Err->TxErreur="Liste de tous les articles: ".$NbTrouv ;
                                $Reponse=json_encode($Err) ;					
                            }
                        }                        
                        
                    }else{
                        $Trouv=$Lst;
                    }
                }
            }else{
                //Pour afficher tous les articles
                $Trouv=$Pdt->GetProduit();
                if ($Trouv){
                    $NbTrouv=$Trouv->num_rows ;	
                    $Err->TxErreur="Liste de tous les articles: ".$NbTrouv ;
                    $Reponse=json_encode($Err) ;					
                }

            }

			if (isset($Trouv)){
                //var_dump($Trouv)			;
				if ($Trouv->num_rows>0){
                    $Reponse=$nabysy->EncodeReponseSQL($Trouv);
                    //$Reponse=$nabysy->SQLToJSON($Trouv) ;
                    $Reponse=json_encode($Reponse) ;                    
				}                
			}

            echo $Reponse ;
            exit;
            

        case 'SAVE_PHOTO':
            $ChampFichier='photo' ;
            $IdPdt=null;
            if (isset($PARAM['IdProduit'])){
                $IdPdt=$PARAM['IdProduit'] ;
            }
            if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
            }
            if (isset($PARAM['CHAMPFICHIER'])){
                $ChampFichier=$PARAM['CHAMPFICHIER'] ;
            }

            if (isset($IdPdt)){
				//$Pdt=new xProduit($nabysy,$Boutique,$IdPdt) ;
                $Pdt=new xProduit($nabysy,$IdPdt);
                $Repo=$Pdt->SavePhoto($ChampFichier);
                $TypeReponse=get_class($Repo) ;
                if ( $TypeReponse=='xErreur' || $TypeReponse=='NAbySy\xErreur' || $TypeReponse=='NAbySy\xNotification'){
                    $Reponse=json_encode($Repo) ;
                    //$Pdt->AddToLog("Reponse de SavePhoto: ".$Reponse);
                }else{
                    $Reponse=new xErreur ;
                    $Reponse->OK=1;
                    $Reponse->Extra="Photo Enregistré correctement." ;
                    $Reponse=$nabysy->SQLToJSON($Repo) ;
                }
                echo $Reponse;
                
			}else{
                $Reponse=new xErreur ;
                $Reponse->OK=0;
                $Reponse->Extra="Impossible de valider l'opération !!!" ;
                $Reponse=$nabysy->SQLToJSON($Repo) ;
                echo $Reponse;
                
            }
            exit;
            
            
        case 'GET_PHOTO':
            $IdPdt=null;
            $GetChemin=true;

            if (isset($PARAM['IdProduit'])){
                $IdPdt=$PARAM['IdProduit'] ;
            }
            if (isset($PARAM['IdPdt'])){
                $IdPdt=$PARAM['IdPdt'] ;
            }

            if (isset($IdPdt)){
				$Pdt=new xProduit($nabysy,$IdPdt) ;
                
                $Chemin=$Pdt->GetPhoto($GetChemin) ;
                if ($GetChemin){
                    echo $Chemin;
                }

            }else{
                $Reponse=new xErreur ;
                $Reponse->OK=0;
                $Reponse->Extra="Impossible de valider l'opération. photo introuvable pour IdPdt inconnue !" ;
                $Reponse=$nabysy->SQLToJSON($Repo) ;
                echo $Reponse;
                return ;
            }
            exit;
            
        case 'PRODUITS_STATS': //Retourne les statistiques des produits du stock
            $Reponse = new xNotification();
            $Reponse->OK=1;
            $Pdt=new xProduit(N::getInstance());
            $Reponse->Contenue['TOTAL']=xProduit::TotalLines($Pdt->Table, $Pdt->DataBase);
            $Reponse->Contenue['RUPTURE']=xProduit::getIdRuptures()?->num_rows;
            $Reponse->Contenue['CRITIQUE']=xProduit::getIdCritiques()?->num_rows;
            $Reponse->Contenue['NORMAL']=xProduit::getIdStockNormal()?->num_rows;
            $Reponse->Contenue['PERIMES']=xProduit::getIdPerimes()?->num_rows;

            echo $Reponse->ToJSON();
            exit;
            break;

		default:
			
	}
	
    //Action non pris en compte ici.
    $FichierActionProduitsNonClasse="codebarnc_action.php";
    include_once 'codebarnc_action.php';
    if (file_exists($FichierActionProduitsNonClasse)){
        include_once $FichierActionProduitsNonClasse;
    }

    function RetourneReponseAPI($JSONdata=null){
		if (isset($JSONdata)){
			echo $JSONdata ;
			exit ;
		}
        $Notif=new xErreur;
        $Notif->OK=1;
		echo json_encode($Notif) ;
		exit ;
	}

	

?>