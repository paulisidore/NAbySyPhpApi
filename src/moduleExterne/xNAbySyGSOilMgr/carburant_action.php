<?php
/**
 * API pour le Module de gestion des Station Essence et de Lubrifiant.
 * Cet API réside sur le serveur local NAbySy GS
 * 
 * API pour le traitement lié au Carburant
 */

use NAbySy\GS\Client\xClient;
use NAbySy\GS\Facture\xVente;
use NAbySy\Lib\ModuleExterne\OilStation\Structure\xInfoControlStock;
use NAbySy\Lib\ModuleExterne\OilStation\xCuveStockageCarburant;
use NAbySy\Lib\ModuleExterne\OilStation\xIndexPompe;
use NAbySy\Lib\ModuleExterne\OilStation\xJaugeB;
use NAbySy\Lib\ModuleExterne\OilStation\xPompe;

 include_once 'nabysy_start.php';
 
 $Reponse=new xNotification;
 $Reponse->OK=1;
 $Err=new xErreur;
 $Err->OK=0;
 if(isset($_REQUEST['TRACKERID'])){
    $Err->Source = $_REQUEST['TRACKERID'];
 }

 switch ($action){
    case "CUVE_SAVE_JAUGEB" ; //Enregistrement du volume jaugé d'une cuve de carburant
        $IdCuve=null;
        $Id=null; //Id Historique de JaugeB
        $DateJauge=null;
        $StockAct=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDCUVE'])){
            if ((int)$_REQUEST['IDCUVE']>0){
                $IdCuve = (int)$_REQUEST['IDCUVE'];
            }
        }
        if(isset($_REQUEST['STOCK'])){
            if ((float)$_REQUEST['STOCK']>0){
                $StockAct = (float)$_REQUEST['STOCK'];
            }
        }

        if(!$StockAct){
            $Err->TxErreur = "Impossible de valider l'Opération. Stock non définit !";
            echo json_encode($Err);
            exit;
        }

        $Cuve=new xCuveStockageCarburant($nabysy,$IdCuve);
        if ($Cuve->Id==0){
            $Err->TxErreur = "Impossible de valider l'Opération. Cuve de Stockage introuvable !";
            echo json_encode($Err);
            exit;
        }
        $JaugeB=new xJaugeB($Cuve->Main,$Id);
        $JaugeB->Cuve = $Cuve ;
        $Dte=null;
        if(isset($_REQUEST['DATE'])){
            if ($_REQUEST['DATE']==""){
                $DateJauge = $_REQUEST['DATE'];
                $Dte=new DateTime($DateJauge);
                if(!$Dte){
                    $Err->TxErreur = "Impossible de valider l'Opération. Date mal-formée !";
                    echo json_encode($Err);
                    exit;
                }
                $DateJauge=$Dte->format("Y-m-d");
            }
        }
        if($JaugeB->SaveJaugeB($StockAct,$Dte)){
            $Reponse->OK=1;
            $Reponse->Contenue=$JaugeB->ToObject();
            $Reponse->Extra=$JaugeB->Id;
            $Reponse->Autres="Jauge-B enregistré correctement.";
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "CUVE_GET_JAUGEB": //Retourne l'Historique des Jauge-B
        $IdCuve=null;
        $Id=null; //Id Historique de JaugeB
        $DateJauge=null;
        $StockAct=null;
        $DateDu=null;
        $DateAu=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDCUVE'])){
            if ((int)$_REQUEST['IDCUVE']>0){
                $IdCuve = (int)$_REQUEST['IDCUVE'];
            }
        }
        if(isset($_REQUEST['DATE_DEPART'])){
            if ($_REQUEST['DATE_DEPART'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_DEPART']);
                if($Dte){
                    $DateDu=$Dte->format("Y-m-d");
                }
            }
        }
        if(isset($_REQUEST['DATE_FIN'])){
            if ($_REQUEST['DATE_FIN'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_FIN']);
                if($Dte){
                    $DateAu=$Dte->format("Y-m-d");
                }
            }
        }

        if (!isset($DateDu)){
            $DateDu=date("Y-m-")."01";
        }
        if (!isset($DateAu)){
            $DateAu=date("Y-m-d");
        }
        $Cuve=new xCuveStockageCarburant($nabysy,$IdCuve);
        $Critere="J.ID>0 ";
        if ($Id>0){
            $Critere ="J.ID = ".$Id;
        }else{
            if ($Cuve->Id>0){
                $Critere .=" and IDCUVE = ".$Cuve->Id;
            }
            if ($DateDu && $DateAu){
                $Critere .=" and (J.DATEJAUGE>='".$DateDu."' AND J.DATEJAUGE<='".$DateAu."') ";
            }else{
                $Critere .=" and (J.DATEJAUGE='".$DateDu."') ";
            }
        }
        
        if (isset($_REQUEST['LISTE_IDOPERATEUR'])){
            if (is_array($_REQUEST['LISTE_IDOPERATEUR'])){
                $ListeID=$_REQUEST['LISTE_IDOPERATEUR'];
                if (count($ListeID)){
                    $Critere .=" and ( ";
                    $i=1;
                    foreach($ListeID as $IdU){
                        if ($i==1){
                            $Critere .=" IDOPERATEUR = ".(int)$IdU ;
                        }else{
                            $Critere .=" OR IDOPERATEUR = ".(int)$IdU ;
                        }                       
                    }
                    $Critere .=" )" ;
                }                
            }
        }

        $JaugeB=new xJaugeB($Cuve->Main);
        $TxSQL="Select C.Nom as 'CUVE', C.Stock, C.UniteMesure, C.TypeCarburant, J.* from ".$JaugeB->Table." J 
        left outer join ".$Cuve->Table." C on C.ID = J.IDCUVE 
        where ".$Critere;
        $Lst=$JaugeB->ExecSQL($TxSQL);
        $Reponse->Autres = $action;
        if($Lst->num_rows){
            $Reponse->OK=1;
            $Reponse->Extra = "Historique de Mesure";
            $Reponse->Contenue = $JaugeB->Main->EncodeReponseSQL($Lst);
        }else{
            $Reponse->OK=0;
            $Reponse->Extra = "Historique de Mesure";
            $Reponse->TxErreur="Aucune information trouvée";
            $Reponse->Autres = $TxSQL;
            $Reponse->Contenue=[];
        }
        
        echo json_encode($Reponse);
        exit;
        break;

    case "CUVE_DELETE_JAUGEB": //Supprime un enregistrement d'une prise de Jauge B
        $Id=null; //Id Historique de JaugeB
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if ($nabysy->User->NiveauAcces <3){
            $Err->TxErreur="Votre niveau d'accès ne permet pas cette opération.";
            echo json_encode($Err);
            exit;
        }
        if (!isset($Id)){
            $Err->TxErreur="Id de l'enregistrement non définit.";
            echo json_encode($Err);
            exit;
        }
        $JaugeB=new xJaugeB(N::$Main,$Id);
        if ($JaugeB->Id==0){
            $Err->TxErreur="Enregistrement n°".$JaugeB->Id." introuvable !";
            echo json_encode($Err);
            exit;
        }
        $TxJ="L'enregistrement de jauge-b n°".$JaugeB->Id." à été supprimé.";
        $Reponse->OK=0;
        if ($JaugeB->Supprimer()){
            $Reponse->OK=1;
            $Reponse->Extra=$JaugeB->Id ;
            $Reponse->Autres ="Jauge-B n°".$JaugeB->Id." supprimé correctement";
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "POMPE_SAVE_INDEX" ; //Enregistrement de la prise d'Indexe par Pompe
        $IdPompe=null;
        $Id=null; //Id Historique Indexe
        $DateJauge=null;
        $IndexAct=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDPOMPE'])){
            if ((int)$_REQUEST['IDPOMPE']>0){
                $IdPompe = (int)$_REQUEST['IDPOMPE'];
            }
        }
        if(isset($_REQUEST['INDEXACT'])){
            if ((float)$_REQUEST['INDEXACT']>0){
                $IndexAct = (float)$_REQUEST['INDEXACT'];
            }
        }

        if(!$IndexAct){
            $Err->TxErreur = "Impossible de valider l'Opération. Indexe non définit !";
            echo json_encode($Err);
            exit;
        }

        $Pompe=new xPompe($nabysy,$IdPompe);
        if ($Pompe->Id==0){
            $Err->TxErreur = "Impossible de valider l'Opération. Pompe ou Piston introuvable !";
            echo json_encode($Err);
            exit;
        }
        $IndexPompe=new xIndexPompe($Pompe->Main,$Id);
        $IndexPompe->Pompe = $Pompe;
        $Dte=null;
        
        if(isset($_REQUEST['DATE'])){
            if ($_REQUEST['DATE'] !=="" ){
                $DateJauge = $_REQUEST['DATE'];
                $Dte=new DateTime($DateJauge);
                if(!$Dte){
                    $Err->TxErreur = "Impossible de valider l'Opération. Date mal-formée !";
                    echo json_encode($Err);
                    exit;
                }
                $DateJauge=$Dte->format("Y-m-d");
                //$IndexPompe->AddToLog("Date Index Finale: ".$DateJauge);
            }
        }
        $Retour=$IndexPompe->SaveIndex($IndexAct,$Dte);
        if($Retour && !is_bool($Retour)){
            $Reponse = $Retour ;
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "POMPE_GET_INDEX": //Retourne l'Historique des Indexes
        $Id=null; //Id Historique de Indexe
        $IdPompe=null;
        $DateJauge=null;
        $DateDu=null;
        $DateAu=null;
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        if(isset($_REQUEST['IDPOMPE'])){
            if ((int)$_REQUEST['IDPOMPE']>0){
                $IdPompe = (int)$_REQUEST['IDPOMPE'];
            }
        }
        if(isset($_REQUEST['DATE_DEPART'])){
            if ($_REQUEST['DATE_DEPART'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_DEPART']);
                if($Dte){
                    $DateDu=$Dte->format("Y-m-d");
                }
            }
        }
        if(isset($_REQUEST['DATE_FIN'])){
            if ($_REQUEST['DATE_FIN'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_FIN']);
                if($Dte){
                    $DateAu=$Dte->format("Y-m-d");
                }
            }
        }

        if (!isset($DateDu)){
            $DateDu=date("Y-m-d"); //."01";
        }
        if (!isset($DateAu)){
            $DateAu = date("Y-m-d");
        }
        $Pompe=new xPompe($nabysy,$IdPompe);
        $Critere="J.ID>0 ";
        if ($Id>0){
            $Critere ="J.ID = ".$Id;
        }else{
            if ($Pompe->Id>0){
                $Critere .=" and J.IDPOMPE = ".$Pompe->Id;
            }
            if ($DateDu && $DateAu){
                $Critere .=" and (J.DATEINDEX>='".$DateDu."' AND J.DATEINDEX<='".$DateAu."') ";
            }else{
                $Critere .=" and (J.DATEINDEX ='".$DateDu."') "; 
            }
        }
        if (isset($_REQUEST['LISTE_IDOPERATEUR'])){
            if (is_array($_REQUEST['LISTE_IDOPERATEUR'])){
                $ListeID=$_REQUEST['LISTE_IDOPERATEUR'];
                if (count($ListeID)){
                    $Critere .=" and ( ";
                    $i=1;
                    foreach($ListeID as $IdU){
                        if ($i==1){
                            $Critere .=" IDOPERATEUR = ".(int)$IdU ;
                        }else{
                            $Critere .=" OR IDOPERATEUR = ".(int)$IdU ;
                        }                       
                    }
                    $Critere .=" )" ;
                }                
            }
        }

        $IndexPompe=new xIndexPompe($Pompe->Main);
        $Cuve=new xCuveStockageCarburant($Pompe->Main);
        $Fact=new xVente($Pompe->Main);

        $TxSQL="Select C.Nom as 'CUVE', C.Stock as 'CUVE_STOCKRESTANT', C.UniteMesure, C.TypeCarburant, P.Nom as 'NOMPOMPE', 
        P.INDEXACT, P.IDILOT, P.IDCARBURANT, F.IDCAISSIER,F.NOMCAISSIER as 'POMPISTE',F.TOTALFACTURE, 
        (F.TotalFacture/J.Ecart) as 'PrixUnitaire', J.* from ".$IndexPompe->Table." J 
        left outer join ".$Pompe->Table." P on P.ID = J.IDPOMPE 
        left outer join ".$Fact->Table." F on F.ID = J.IDFACTURE 
        left outer join ".$Cuve->Table." C on C.ID = P.IDCARBURANT 
        where ".$Critere;
        $Lst=$IndexPompe->ExecSQL($TxSQL);
        //echo $TxSQL."</br>";
        $Reponse->Autres = $action;
        if($Lst->num_rows){
            $Reponse->OK=1;
            $Reponse->Extra = "Historique des Indexes";
            $DetailContenue = $IndexPompe->Main->EncodeReponseSQL($Lst);
            $ListeTotaux['RESUME']=[];
            //Calcule des Tauxtoaux par Pompe
            $TxSQL="Select C.Nom as 'CUVE', C.Stock as 'CUVE_STOCKRESTANT', C.UniteMesure, C.TypeCarburant, P.Nom as 'NOMPOMPE', 
            P.INDEXACT, P.IDILOT, P.IDCARBURANT, F.IDCAISSIER,F.NOMCAISSIER as 'POMPISTE',F.TOTALFACTURE, 
            COUNT(J.IDPOMPE) as 'NB_MESURE', SUM(J.ECART) as 'TOTAL_ECART', SUM(F.TOTALFACTURE) as 'TOTAL_CA' from ".$IndexPompe->Table." J 
            left outer join ".$Pompe->Table." P on P.ID = J.IDPOMPE 
            left outer join ".$Fact->Table." F on F.ID = J.IDFACTURE 
            left outer join ".$Cuve->Table." C on C.ID = P.IDCARBURANT 
            where ".$Critere;
            $TxSQL .=" GROUP BY C.TypeCarburant ORDER BY P.NOM " ;
            $Lst=$IndexPompe->ExecSQL($TxSQL);
            
            if($Lst->num_rows){
                while($rw =$Lst->fetch_assoc()){
                    $Totaux[$rw['NOMPOMPE']]['CARBURANT']=$rw['TypeCarburant'];
                    $Totaux[$rw['NOMPOMPE']]['NB_MESURE']=$rw['NB_MESURE'];
                    $Totaux[$rw['NOMPOMPE']]['UNITE_MESURE']=$rw['UniteMesure'];
                    $Totaux[$rw['NOMPOMPE']]['TOTAL_ECART']=$rw['TOTAL_ECART'];
                    $Totaux[$rw['NOMPOMPE']]['TOTAL_VENTE']=$rw['TOTAL_CA'];
                    $Totaux[$rw['NOMPOMPE']]['STOCK_CUVE']=$rw['CUVE_STOCKRESTANT'];
                    $ListeTotaux['RESUME'][]=$Totaux[$rw['NOMPOMPE']] ;
                }
            }
            $Reponse->Contenue['DETAIL'] = $DetailContenue ;
            $Reponse->Contenue['RESUME']= $ListeTotaux['RESUME'];
        }else{
            $Reponse->OK=0;
            $Reponse->Extra = "Historique d'Index";
            $Reponse->TxErreur="Aucune information trouvée";
            $Reponse->Contenue=[];
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "POMPE_DELETE_INDEX": //Supprime un enregistrement d'une prise d'Index
        $Id=null; //Id Historique
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
       
        if ($nabysy->User->NiveauAcces <3){
            $Err->TxErreur="Votre niveau d'accès ne permet pas cette opération.";
            echo json_encode($Err);
            exit;
        }
        if (!isset($Id)){
            $Err->TxErreur="Id de l'enregistrement non définit.";
            echo json_encode($Err);
            exit;
        }

        $IndexPompe=new xIndexPompe(N::$Main,$Id);        
        if ($IndexPompe->Id==0){
            $Err->TxErreur="Enregistrement n°".$IndexPompe->Id." introuvable !";
            echo json_encode($Err);
            exit;
        }
        $LastInd=$IndexPompe->GetLastIndex();
        
        if(!isset($LastInd)){
            $Err->TxErreur="La prise d'indexe n°".$IndexPompe->Id." n'est pas la dernière. Impossible de la supprimer !";
            echo json_encode($Err);
            exit;
        }
        if($LastInd->Id !== $IndexPompe->Id){
            $Err->TxErreur="La prise d'indexe n°".$IndexPompe->Id." n'est pas la dernière. Impossible de la supprimer !";
            echo json_encode($Err);
            exit;
        }
        

        $TxJ="L'enregistrement de jauge-b n°".$IndexPompe->Id." à été supprimé.";
        $Reponse->OK=0;
        if ($IndexPompe->Supprimer()){
            $Reponse->OK=1;
            $Reponse->Extra=$IndexPompe->Id ;
            $Reponse->Autres ="Jauge-B n°".$IndexPompe->Id." supprimé correctement";
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "POMPE_GETONE_INDEX": //Retourne un enregistrement dans la table des index de pompe
        $Id=null; //Id Historique
        if(isset($_REQUEST['ID'])){
            if ((int)$_REQUEST['ID']>0){
                $Id = (int)$_REQUEST['ID'];
            }
        }
        
        if ($nabysy->User->NiveauAcces <1){
            $Err->TxErreur="Votre niveau d'accès ne permet pas cette opération.";
            echo json_encode($Err);
            exit;
        }
        if (!isset($Id)){
            $Err->TxErreur="Id de l'enregistrement non définit.";
            echo json_encode($Err);
            exit;
        }

        $IndexPompe=new xIndexPompe(N::$Main,$Id);        
        if ($IndexPompe->Id==0){
            $Err->TxErreur="Enregistrement n°".$IndexPompe->Id." introuvable !";
            echo json_encode($Err);
            exit;
        }
        $Reponse->Contenue = $IndexPompe->ToObject() ;
        echo json_encode($Reponse);
        exit;
        break;
    
    case "LISTE_CLIENT_NONPOMPISTE": //Retourne la liste des Clients non Pompiste
        $IdClt=null;
        if(isset($_REQUEST['ID'])){
            if((int)$_REQUEST['ID'] > 0){
                $IdClt = (int)$_REQUEST['ID'];
            }
        }
        $Client=new xClient(N::$Main,$IdClt);
        if (isset($IdClt) && $Client->Id>1){
            $EstPompiste=false;
            foreach(N::$Main->User as $Utilisateur){
                if ((int)$Utilisateur->IdCompte == $Client->Id ){
                    $EstPompiste=true;
                    break;
                }
            }
            if ($EstPompiste){
                $Err->TxErreur = "Ce compte client est lié à utilisateur.";
                echo json_encode($Err);
                exit;
            }else{
                $Reponse->Extra = $Client->Id;
                $Reponse->Contenue = $Client->ToObject();
                echo json_encode($Reponse) ;
                exit;
            }
        }elseif(isset($IdClt)){
            $Err->TxErreur = "Id Client introuvable !";
            echo json_encode($Err);
            exit;
        }
        $Liste=[];
        foreach ($Client as $Cpte){
            $EstPompiste=false;
            if ($Cpte->Id>1){
                foreach(N::$Main->User as $Utilisateur){                
                    if ((int)$Utilisateur->IdCompte == (int)$Cpte->Id || (int)$Cpte->Id<2 ){
                        $EstPompiste=true;
                        break;
                    }
                }
                if(!$EstPompiste){
                    $Liste[]=$Cpte->ToObject();
                }
            }
        }
        $Reponse->Autres = "LISTE DES COMPTES CLIENT";
        $Reponse->Contenue = $Liste;
        echo json_encode($Reponse) ;
        exit;

    case "LISTE_CLIENT_POMPISTE": //Retourne la liste des Clients non Pompiste
        $IdClt=null;
        if(isset($_REQUEST['ID'])){
            if((int)$_REQUEST['ID'] > 0){
                $IdClt = (int)$_REQUEST['ID'];
            }
        }
        $Client=new xClient(N::$Main,$IdClt);
        
        if (isset($IdClt) && $Client->Id>1){
            $EstPompiste=false;
            foreach(N::$Main->User as $Utilisateur){
                if ((int)$Utilisateur->IdCompte == $Client->Id ){
                    if ($nabysy->User->NiveauAcces <3){
                        if ($nabysy->User->Id == $Utilisateur->Id){                            
                            $EstPompiste=true;
                            break;
                        }
                    }else{
                        $EstPompiste=true;
                        break;
                    }                    
                }
            }
            if (!$EstPompiste){
                $Err->TxErreur = "Ce compte client n'est pas celui d' un pompiste.";
                echo json_encode($Err);
                exit;
            }else{
                $Reponse->Extra = $Client->Id;
                $Reponse->Contenue = $Client->ToObject();
                echo json_encode($Reponse) ;
                exit;
            }
        }elseif(isset($IdClt)){
            $Err->TxErreur = "Id Client introuvable !";
            echo json_encode($Err);
            exit;
        }
        $Liste=[];
        foreach ($Client as $Cpte){
            $EstPompiste=false;
            if ($Cpte->Id>1){
                foreach(N::$Main->User as $Utilisateur){                
                    if ((int)$Utilisateur->IdCompte == (int)$Cpte->Id || (int)$Cpte->Id<2 ){
                        if ($nabysy->User->NiveauAcces <3){
                            if ($nabysy->User->Id == $Utilisateur->Id){                            
                                $EstPompiste=true;
                                break;
                            }
                        }else{
                            $EstPompiste=true;
                            break;
                        }
                    }
                }
                if($EstPompiste){
                    $Liste[]=$Cpte->ToObject();
                }
            }            
        }
        $Reponse->Autres = "LISTE DES COMPTES DE POMPISTE";
        $Reponse->Contenue = $Liste;
        echo json_encode($Reponse) ;
        exit;

    case "POMPE_SAVE_BONCLIENT" ; //Enregistrement d'une Vente sur un compte Client à partir d'une prise d'Index
        $IdIndex=null;
        $IdClient=null; //Id Historique Indexe
        $QteServit=null;
        $MtServit=null;
        $DateVers=null;

        if(isset($_REQUEST['IDINDEX'])){
            if ((int)$_REQUEST['IDINDEX']>0){
                $IdIndex = (int)$_REQUEST['IDINDEX'];
            }
        }
        if(!isset($IdIndex)){
            $Err->TxErreur = "Indexe de Pompe non définit.";
            echo json_encode($Err);
            exit;
        }
        $IndexPompe = new xIndexPompe($nabysy,$IdIndex);
        if ($IndexPompe->Id==0){
            $Err->TxErreur = "Prise d'index de Pompe introuvable pour Id = ".$IdIndex;
            echo json_encode($Err);
            exit;
        }

        if(isset($_REQUEST['IDCLIENT'])){
            if ((int)$_REQUEST['IDCLIENT']>0){
                $IdClient = (int)$_REQUEST['IDCLIENT'];
            }
        }
        if(!isset($IdClient)){
            $Err->TxErreur = "Id Client non définit.";
            echo json_encode($Err);
            exit;
        }
        $Client=new xClient($nabysy,$IdClient);
        if($Client->Id==0){
            $Err->TxErreur = "Compte Client introuvable !";
            echo json_encode($Err);
            exit;
        }
        if(isset($_REQUEST['QTE'])){
            if ((float)$_REQUEST['QTE'] !== 0){
                $QteServit = (float)$_REQUEST['QTE'];
            }
        }elseif(isset($_REQUEST['MONTANT'])){
            if((float)($_REQUEST['MONTANT']) !== 0){
                $MtServit = (float)$_REQUEST['MONTANT'];
            }
        }elseif(isset($_REQUEST['Montant'])){
            if((float)($_REQUEST['Montant']) !== 0){
                $MtServit = (float)$_REQUEST['Montant'];
            }
        }
        if(!isset($QteServit) && !isset($MtServit)){
            $Err->TxErreur = "Quantité ou Montant du carburant non définit.";
            echo json_encode($Err);
            exit;
        }
        if(isset($_REQUEST['DATEVERS'])){
            if($_REQUEST['DATEVERS']!==""){
                $dte=new DateTime($_REQUEST['DATEVERS']);
                if($dte){
                    $DateVers = $dte->format("Y-m-d");
                }
            }
        }

        if (isset($QteServit)){
            $Reponse = $IndexPompe->SaveVenteToBonClient($Client,$QteServit,$IndexPompe);
        }elseif(isset($MtServit)){
            $Reponse = $IndexPompe->SaveVenteToBonClientFromMontant($Client,$MtServit,$IndexPompe);
        }
        if(isset($_REQUEST['TRACKERID'])){
            $Reponse->Source = $_REQUEST['TRACKERID'];
        }
        echo json_encode($Reponse);
        exit;
        break;

    case "GET_RAPPORT_CARBURANT":   //Retourne le rapport périodique sur la vente et le stock de carburant
        $IdCuve=null;
        $IdPompe=null; //Id Historique de JaugeB
        $DateJauge=null;
        $StockAct=null;
        $DateDu=null;
        $DateAu=null;
        
        if(isset($_REQUEST['IDCUVE'])){
            if ((int)$_REQUEST['IDCUVE']>0){
                $IdCuve = (int)$_REQUEST['IDCUVE'];
            }
        }
        if(isset($_REQUEST['DATE_DEPART'])){
            if ($_REQUEST['DATE_DEPART'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_DEPART']);
                if($Dte){
                    $DateDu=$Dte;
                }
            }
        }
        if(isset($_REQUEST['DATE_FIN'])){
            if ($_REQUEST['DATE_FIN'] !==""){
                $Dte=new DateTime($_REQUEST['DATE_FIN']);
                if($Dte){
                    $DateAu=$Dte;
                }
            }
        }

        if (!isset($DateDu)){
            $DateDu= new DateTime('now');
        }
        if (!isset($DateAu)){
            $DateAu= new DateTime('now');
        }
        $Pompe=new xPompe($nabysy);
        if(isset($_REQUEST['IDPOMPE'])){
            if ((int)$_REQUEST['IDPOMPE']>0){
                $IdPompe = (int)$_REQUEST['IDPOMPE'];
                $Pompe=new xPompe($nabysy,$IdPompe);
            }
        }
        $Reponse=new xNotification;
        $Reponse->OK=0;

        $Cuve=new xCuveStockageCarburant($nabysy,$IdCuve);
        $ListeCuve = [];
        if ($Cuve->Id>0){
            $ListeCuve[] = $Cuve ;
        }else{
            foreach($Cuve as $UneCuve){
                $ListeCuve[] = $UneCuve ;
            }
        }

        $ListeReponse=[];
        $Resume=new xInfoControlStock();
        if ($Cuve->Id>0){
            $Resume->Cuve = $Cuve->ToObject();
        }
        $PompeRech=null;
        if($Pompe->Id>0){
            $PompeRech = $Pompe ;
            $Resume->Pompe = $Pompe->ToObject();
        }

        $Ind=0;
        $Resume->DateDebut = $DateDu ;
        $Resume->DateFin = $DateAu ;
        if(isset($Pompe)){
            $Resume->Pompe = $Pompe->ToObject();
        }
        foreach($ListeCuve as $xCuve){
            $Cuve=new xCuveStockageCarburant($nabysy,$xCuve->Id);
            //echo "Recherche infos Stock de la cuve ".$Cuve->Nom." du ".$DateDu->format("d/m/Y")." au ".$DateAu->format("d/m/Y")."</br>";
            $Rep = $Cuve->GetInfosControlStock($DateDu,$DateAu,$PompeRech);            
            if ($Rep){
                if ($Rep->OK>0){
                    //var_dump($Rep->ToJSON());
                    $Resume->OK=1;
                    $Resume->Contenue[]=$Rep->ToObject() ;
                }
            }
        }
        echo json_encode($Resume);
        exit;
        break;

    default:
        break;

 }

 ?>