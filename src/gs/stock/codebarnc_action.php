<?php

use NAbySy\GS\Stock\xCodeBarShema;
use NAbySy\GS\Stock\xProduitNC;
use NAbySy\xErreur;

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
    $Err->Source="codebarnc_action" ;
    if ($nabysy->ActiveDebug){
        $Err->Source=__FILE__ ;
    }

	switch ($action){
        case "PDTNC_GET_SHEMA":
            //Retourna liste des shema des code bar non classés
            $CodeBar=null ;			
            if (isset($PARAM['CODEBAR'])){
                $CodeBar=$PARAM['CODEBAR'] ;
            }
            $Id=null;
            if (isset($PARAM['ID'])){
                $Id=$PARAM['ID'] ;
            }
            $PdtNC=new xProduitNC($nabysy,$Id,false,null,null,$CodeBar);
            $Critere=null ;
            if ($PdtNC->Id>0){
                $Critere=" ID=".$PdtNC->IdClown ;
            }
            $Reponse="[]";
            $LstCB =new xCodeBarShema($nabysy);
            $Lst=$LstCB->ChargeListe($Critere);
            if ($Lst->num_rows){
                $Reponse=$LstCB->EncodeReponseSQLToJSON($Lst);
            }
            echo $Reponse ;
            exit ; 
        
        case "PDTNC_GET_VENTE":
                //Retourne uniquement les ligne de produit non classé dans les ventes 

            exit;

    }

?>