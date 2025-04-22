<?php
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\xErreur;
use NAbySy\xUser;

	$PARAM=$_REQUEST;
	

	$action=null ;
	if (isset($PARAM['Action'])){
		$action=$PARAM['Action'] ;
	}
	if (isset($PARAM['action'])){
		$action=$PARAM['action'] ;
	}

    if (!isset($action)){		
        //Il n'y a pas d'action, on retourne a la page précedente
        $Err=new xErreur ;
        $Err->OK=0;
        $Err->TxErreur='Action non définit !' ;
        $Err->Source= __FUNCTION__ ;
        $reponse=json_encode($Err) ;
        echo $reponse ;
        exit;	
	}

	if (isset($PARAM['IDBOUTIQUE'])){
		$IdBoutique=(int)$PARAM['IDBOUTIQUE'];
		$Bout=new xBoutique($nabysy,$IdBoutique);
		if ($Bout->Id>0){
			if ($nabysy->MaBoutique->Id !== $IdBoutique){
				$nabysy->MaBoutique=$Bout;
			}
		}
	}

	switch ($action){	
		case 'OPEN_SESSION' :
			$UserN=null;
			$Pwd=null;
			$Module=null;

			if (isset($PARAM['User'])){
				$UserN=$PARAM['User'] ;
			}
			if (isset($PARAM['Password'])){
				$Pwd=$PARAM['Password'] ;
			}
			if (isset($PARAM['ModuleName'])){
				//$Module=$PARAM['ModuleName'] ;
			}
			if (!isset($Module)){
				//$Module="xCodeBarEAN13" ;
			}

			$Table=$nabysy->MaBoutique->DataBase.".utilisateur" ;
			$TxSQL="select * from ".$Table." where login like '".$UserN."' and (password like MD5('".$Pwd."') or password like '".$Pwd."' ) limit 1" ;
			if(isset($_REQUEST['IsModuleConnexion'])){
				if ((int)$_REQUEST['IsModuleConnexion']>0){
					$TxSQL="select * from ".$Table." where login like '".$UserN."' limit 1" ;
					$nabysy->NomPosteClient="";
					$nabysy->IdPosteClient=0;
				}
			}
			
			$Rep=$nabysy->ReadWrite($TxSQL) ;
			
			if ($Rep->num_rows>0){
                //$Direc=new xDirection($nabysy) ;
                //var_dump($Direc) ;
                
				$RW=$Rep->fetch_assoc() ;
				$RW['PASSWORD']='***********';
				$User=new xUser($nabysy,$RW['ID']) ;
				if (isset($Module)){
					$ChampMod='CanUseMod_'.$Module ;
					if ($User->CanUseModule($Module)==false){
					//if (isset($RW[$ChampMod])){
						//if ($RW[$ChampMod]==0){
							$Err=new xErreur;
							$Err->TxErreur="Vous n'avez pas accès à ".$Module.". Contactez votre Administrateur svp.";
							$Err->Source="nabysy_action.php" ;
							$Err->OK=0 ;
							$json=json_encode($Err) ;
							echo $json ;
							//echo json_encode(null) ; //Pour rendre compatible ancienne version de NAbySy xCodeBarEAN13
							return ;
						//}
					}
				}
				
				$json=json_encode($RW) ;
				echo $json ;

				//Essaie Module SMS
				/* $Sms=new \NAbySy\Lib\Sms\xSMSOrange($nabysy) ;
				$Balance=$Sms->GetSMSBalance();
				if (isset($Balance)){
					if (is_array($Balance)){
						$Sms->EnvoieSms('+221775618816','Credit Restant: '.$Balance['CreditRestant'].'. '.$Balance['Note']);
					}
				}		 */		
				//echo json_encode($Balance);
				//--------------------------------------------------------------------------------------------------------------------

				//Essaie de mail
				$MailEngine=new \NAbySy\Lib\Mail\xMailEngine($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,"mailrpt", "paul.isidore@gmail.com");
				$Dest[]="paul_isidore@hotmail.com";
				$Dest[]="paul.isidore@gmail.com";
				//$Resultat=$MailEngine->EnvoieMail($Dest,"NAbySy Mail Engine Tester","Bienvenu dans le monde de NAbySy");
				//var_dump($Resultat);
				// -------------------------------------------------------------------------------------------------------------
				return ;
			}

			$Err=new xErreur;
			$Err->TxErreur="Nom d'utilisateur ou mot de passe incorrect.";
			$Err->Source="nabysy_action.php" ;
			$Err->OK=0 ;
			$json=json_encode($Err) ;
			echo $json ;
			
			break;

		case 'GET_INFOS_USER' :
			$UserN=null;
			$Pwd=null;
			$Module=null;

			if (isset($PARAM['User'])){
				$UserN=$PARAM['User'] ;
			}
			if (isset($PARAM['Password'])){
				$Pwd=$PARAM['Password'] ;
			}
			
			$Table=$nabysy->MaBoutique->DataBase.".utilisateur" ;
			$TxSQL="select * from ".$Table." where login like '".$UserN."' and password like MD5('".$Pwd."') limit 1" ;
			
			$Rep=$nabysy->ReadWrite($TxSQL) ;
			
			if ($Rep->num_rows>0){				
				$RW=$Rep->fetch_assoc() ;
				$json=json_encode($RW) ;
				$Reponse='';
				$User=new xUser($nabysy,$RW['ID']) ;
				//var_dump($User->IdEmploye);
				if ($User->Id>0){
					while ($row = $Rep->fetch_assoc()){
						$User=new xUser($nabysy,$row['ID']) ;
						$User->RS['PASSWORD']='***********';
						$Reponse=$User->ToJSON() ;
					}
					$json= $Reponse;
					//$json=json_encode($Reponse);					
				}				
				echo $json ;
				exit ;
			}

			$Err=new xErreur;
			$Err->TxErreur="Nom d'utilisateur ou mot de passe incorrect.";
			$Err->Source="nabysy_action.php" ;
			$Err->OK=0 ;
			$json=json_encode($Err) ;
			echo $json ;
			
			break;
		
		default:
			Retourne();	
			break;
	}
	 
	
	
	function Retourne($lien=null){
		
		 exit ;
	}