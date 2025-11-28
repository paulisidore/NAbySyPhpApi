<?php
use NAbySy\GS\Facture\Impression\xFactureA4;
use NAbySy\xErreur;

    //include_once 'nabysy_action.php';
    //var_dump($nabysy);
    switch ($action){
		case "PRINT_FACTA4":
            //if ($nabysy->ValideUser()){
                //exit;
                //var_dump($_REQUEST);
            //}        
            $IdFacture=null;
            if (isset($_REQUEST['Id'])){
                $IdFacture=(int)$_REQUEST['Id'];
            }
            if (isset($_REQUEST['IdFacture'])){
                $IdFacture=(int)$_REQUEST['IdFacture'];
            }
            if (isset($_REQUEST['id'])){
                $IdFacture=(int)$_REQUEST['id'];
            }
            if (!isset($IdFacture)){
                $Err=new xErreur;
                $Err->TxErreur="Id Facture non définit";
                $Err->OK=0;
                echo json_encode($Err);
                exit;
            }
        
            $FactureA4=new xFactureA4($nabysy,$IdFacture);
            if ($FactureA4->IdFacture>0){
                $FactureA4->ImprimeFacture();
            }else{
                $Err=new xErreur;
                $Err->TxErreur="Facture introuvable !!!";
                $Err->OK=0;
                echo json_encode($Err);
            }
            exit ;
        default:

    }
    
?>