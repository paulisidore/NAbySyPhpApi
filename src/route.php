<?php
	$Titre='' ;
	$Lien=null ;
    $cssfile=null;
    $PARAM=null ;
    if (isset($_POST['Lien'])){
        $PARAM=$_POST ;
    }
    if (isset($_GET['Lien'])){
        $PARAM=$_GET ;
    }
	if (isset($PARAM['Lien'])){
		$Lien=$PARAM['Lien'] ;
		if (isset($PARAM['Titre'])){
			$Titre=$PARAM['Titre'] ;
                }
                if (isset($PARAM['CSS'])){
			$cssfile=$PARAM['CSS'] ;
                }
	}

	if ($Lien){
		include_once '../nabysy/nabysy_start.php';
                //echo '</br>Lien='.$Lien ;
                //echo '</br>Titre='.$Titre ;
                //echo '</br>CSSFile='.$cssfile ;
                exit;
        $vue=new xVue($Titre,$nabysy->MaBoutique,$Lien,$Titre,$cssfile) ;
        var_dump($vue->Contenue ) ;
                
	}else{
		//Aucune route transmise revenir en arrière
		echo "<script>console.log('Aucun lien transmit...');
		window.history.back();
		</script>" ;
	}

?>