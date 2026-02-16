<?php
use NAbySy\GS\Facture\Impression\xFactureA4;
use NAbySy\GS\Facture\xVente;
use NAbySy\GS\Stock\xProduit;
use NAbySy\ORM\xORMHelper;
use NAbySy\xErreur;

    //include_once 'nabysy_action.php';
    //var_dump($nabysy);
    switch ($action){
        case 'FACTURE_IAV': //Inventaire après Vente
            $Reponse->OK=1;
            $DateD=date('Y-m-d');
            $DateF=$DateD;
            $Critere="" ;
            if (isset($_REQUEST['DATEDEBUT'])){
                $DateDeb=$_REQUEST['DATEDEBUT'] ;
                $DateD=new DateTime($DateDeb);
                if (isset($_REQUEST['DATEFIN'])){
                    $DateFin=$_REQUEST['DATEFIN'] ;
                    $DateF=new DateTime($DateFin);
                }
                if ($DateD !== false && $DateF !== false){
                    $Critere .=" F.DATEFACTURE >='".$DateD->format('Y-m-d')."' and F.DATEFACTURE <='".$DateF->format('Y-m-d')."' ";
                }elseif ($DateD !== false ){
                    $Critere .=" F.DATEFACTURE ='".$DateD->format('Y-m-d')."' ";
                }
            }else{
                $Critere .=" F.DATEFACTURE ='".$DateD."' ";
            }

            //On enleve les produit Non Classé
            $Critere .=" AND t1.IDPRODUIT>0 " ;

            if(isset($_REQUEST['IDCAISSIER']) && (int)$_REQUEST['IDCAISSIER'] > 0){
                $Critere .=" AND F.IDCAISSIER = ".(int)$_REQUEST['IDCAISSIER']." " ;
            }
            if(isset($_REQUEST['CAISSIER']) && trim($_REQUEST['CAISSIER']) !== ''){
                $Critere .=" AND F.NOMCAISSIER like '".$_REQUEST['CAISSIER']."%'" ;
            }
            if(isset($_REQUEST['NOMCAISSE']) && trim($_REQUEST['NOMCAISSE']) !== ''){
                $Critere .=" AND F.NOMCAISSE like '".$_REQUEST['CAISSE']."%'" ;
            }
            if(isset($_REQUEST['IDRAYON']) && (int)$_REQUEST['IDRAYON'] > 0){
                //$Critere .=" AND R.ID = ".(int)$_REQUEST['IDRAYON']." " ;
            }
            if(isset($_REQUEST['IDFAMILLE']) && (int)$_REQUEST['IDFAMILLE'] > 0){
                //$Critere .=" AND FA.ID = ".(int)$_REQUEST['IDFAMILLE']." " ;
            }
            if(isset($_REQUEST['IDFOURNISSEUR']) && (int)$_REQUEST['IDFOURNISSEUR'] > 0){
                //$Critere .=" AND FO.ID = ".(int)$_REQUEST['IDFOURNISSEUR']." " ;
            }
            $Vente = new xVente(N::getInstance());
            $DetailVente = new xORMHelper(N::getInstance(),null,false,"detail".$Vente->Table);
            $DetailVente->JoinTable($Vente ,"F","IDFACTURE");
            $DetailVente->JoinTable(new xProduit($Vente->Main),"P","IDPRODUIT");
            
            $Ordre="R.NOM, t1.DESIGNATION";
            $GroupeBy="t1.DESIGNATION";

            $SelectChamp="t1.DESIGNATION, t1.PrixVente, sum(t1.QTE) as 'QTEVENDU', sum(PrixTotal) as 'PrixTotal', P.STOCK, P.SEUILCRITIQUE, P.STOCKMAXI ";
            $SelectChamp .= ", F.NOMCAISSIER " ;
            $SelectChamp .=", IF( P.STOCK <= P.SEUILCRITIQUE, IF( P.STOCKMAXI>P.SEUILCRITIQUE , (P.STOCKMAXI - sum(t1.QTE) ),  sum(t1.QTE) ), '0') as 'QTECMD' " ;

            $Reponse->Contenue=[];
            $Lst = $DetailVente->JointureChargeListe($Critere,$Ordre,$SelectChamp,$GroupeBy);
            if($Lst){
                $Reponse->Contenue = N::EncodeReponseSQL($Lst);
            }
            echo $Reponse->ToJSON();
            exit;
            break;
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