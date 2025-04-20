<?php
// Outil de Sauvegarde de la Base de donnée
ini_set("memory_limit","1024M");
ini_set('max_execution_time', 300) ;

Class xSauvegarde
{
	public $DBase ;
	public $Serveur_Cible ;
	public $User_Cible ;
	public $Pwd_Cible ;
	public $Main ;
	public $connexion ;
	public $FichierSQL ;
	public $MyPDO ;
	private $Zip ;
	public $Boutique ;

	public function __construct($Boutique=null){	
		if ($Boutique){
			$this->Main=$Boutique->Main ;
			$this->FichierSQL=$Boutique->DBase."-back" ;
			$this->DBase=$Boutique->DBase ;
			$this->Serveur_Cible=$this->Main->db_serveur ;
			$this->User_Cible=$this->Main->db_user ;
			$this->Pwd_Cible=$this->Main->db_pass ;
			$this->MyPDO = new xPDO ;
			$this->Boutique =$Boutique ;
		}
		$Zip = new ZipArchive();
		
	}
	
	public function ImportSQL($Fichier){
		if (!$this->MyPDO){
			return false ;
		}		
		$reponse=$this->MyPDO->ExecuteFile($this->DBase,"127.0.0.1",$this->User_Cible,$this->Pwd_Cible,$Fichier) ;
		return $reponse ;
	}
	
	public function Sauvegarder($DBsource=null,$FichierDst=null,$SrvCible=null,$UserC='pharmcp',$PwdC='microcp',$ExporterStructure=1,$ExporterDonnee=1){
		if ($DBsource)
			$this->DBase = $DBsource;
		
		if ($this->DBase == ''){
			if ($this->Main){
				$this->DBase = $this->Main->dbase;
			}
		}
		if ($SrvCible)
			$this->Serveur_Cible = $SrvCible;
		if ($UserC)
			$this->User_Cible = $UserC;
		if ($PwdC)
			$this->Pwd_Cible = $PwdC;	
		
		$this->FichierSQL="backup/".$this->DBase."-[".date("d-M-Y-His")."].sql" ;
		if ($FichierDst)
			$this->FichierSQL=$FichierDst ;
		
		$typeexport=0 ;
		if ($ExporterDonnee==1){
			$typeexport=4 ;
			
		}
		if ($ExporterDonnee==1 and $ExporterStructure==1){
			$typeexport=2 ;
		}
		if ($ExporterDonnee==0 and $ExporterStructure==1){
			$typeexport=1 ;
		}
		
		$Fichier=$this->dump_MySQL($this->Serveur_Cible, $this->User_Cible, $this->Pwd_Cible, $this->DBase, $typeexport);
		//On va compresser le fichier
		$FZip=$Fichier.".zip" ;
		if (!$this->Zip){
			$this->Zip=new ZipArchive() ;
		}
		if ($this->Zip->open($FZip, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
				die ("Erreur de création du fichier ZIP ".$FZip);
		}else{
			$this->Zip->addFile($Fichier) ;
			$this->Zip->close() ;
			unlink ($Fichier);	//Effacement du fichier non compressé
			echo "</br><h2>Sauvegarde terminée: ".$FZip."</h2>" ;
		}
		
		return $FZip ;
	}
	
	private function dump_MySQL($host, $user, $pass, $base, $mode)
	{
		if(!$this->connexion)
			$this->connexion = mysqli_connect($host, $user, $pass, $base);

		if ($this->FichierSQL ==''){
			$this->FichierSQL = "backup/sauv_".$this->DBase.date("d-M-Y-hhnnss")."sql" ;
		}
		ini_set("memory_limit",-1);
		
		$entete  = "-- ----------------------\n";
		$entete .= "-- Traitement de la base ".$base." au ".date("d-M-Y")."\n";
		$entete .= "-- ----------------------\n\n\n";
		$creations = "";
		$insertions = "\n\n";
		$fichierDump = fopen($this->FichierSQL, "ab");
		fwrite($fichierDump, $entete);
		fwrite($fichierDump, $insertions);
		fclose($fichierDump);

		$sql="USE ".$base ;
		$listeTables =$this->Main->ReadWrite($sql,null,true) ;

		$sql="show tables in ".$base ;
		$listeTables =$this->Main->ReadWrite($sql) ;// mysqli_query($connexion,"show tables");
		//echo "Sauvegarde de la base de donnée ".$base.": </br>" ;
		$Lst=$this->Main->EncodeReponseSQL($listeTables);
		//var_dump($Lst) ;
		$i=0 ;
		foreach ($Lst as $table)
		//while($table = $listeTables->fetch_assoc())  // mysqli_fetch_array($listeTables)
		{
			//$fichierDump = fopen($this->FichierSQL, "a");
			//print_r($table) ;
			$vtable = array_values($table) ;
			//print_r($vtable) ;
			//$vtable[0]=$table ;
			
			$i++;
			$prefix="Tables_in_".$base ;
			// structure ou la totalité de la BDD
			//echo "Sauvegarde de la table ".$base.".".$vtable[0].": </br>" ;
			if($mode > 0 )
			{
				$creations = "-- -----------------------------\n";
				$creations .= "-- Structure de la table ".$base.".".$vtable[0]."\n";
				$creations .= "-- -----------------------------\n";
				$sql="show create table `".$base."`.`".$vtable[0]."` " ;
				$listeCreationsTables =$this->Main->ReadWrite($sql) ;
				//$listeCreationsTables = mysqli_query($connexion,"show create table ".$table[0]);
				while($creationTable = $listeCreationsTables->fetch_assoc()) //mysqli_fetch_array($listeCreationsTables))
				{
				  $vcreationTable=array_values($creationTable) ;
				  $creations .= $vcreationTable[1].";\n\n";
				}
				$fichierDump = fopen($this->FichierSQL, "ab");
				fwrite($fichierDump, $creations);
				fclose($fichierDump);
			}

			// données ou la totalité
			if($mode > 1)
			{
				//$donnees = mysqli_query($connexion,"SELECT * FROM ".$table[0]);
				$sql="SELECT * FROM `".$base."`.`".$vtable[0]."` " ;
				$donnees = $this->Main->ReadWrite($sql);
				$insertions = "-- ------------------------------------------------------\n";
				$insertions .= "-- Contenu de la table ".$base.".".$vtable[0]."\n";
				$insertions .= "-- ------------------------------------------------------\n";

				$fichierDump = fopen($this->FichierSQL, "ab");
				fwrite($fichierDump, $insertions);
				fclose($fichierDump);

				$insertions_ligne='' ;
				$nb_ligne=0 ;

				while($ligne = $donnees->fetch_assoc()) //mysqli_fetch_array($donnees)
				{
					
					$insert_ligne= "INSERT IGNORE INTO ".$vtable[0]." VALUES(";
					$insertions_ligne .= "INSERT IGNORE INTO ".$vtable[0]." VALUES(";
					$vligne=array_values($ligne) ;
					for($i=0; $i < count($vligne); $i++)
					{
					  if($i != 0){
						 $insertions_ligne .=  ", ";
						 $insert_ligne .= ", " ;
					  }
					  //if(mysqli_fetch_field_direct($donnees, $i) == "string" || mysqli_fetch_field_direct($donnees, $i) == "blob"){
						 //$insertions .=  "'";
						 //$insert_ligne .= "'" ;
					  //}
					  $donneeChamp=$vligne[$i];
					  $encodage_orig=mb_detect_encoding($vligne[$i], mb_detect_order(), true) ;
					  //echo "<br/>".$encodage_orig ;
					  $donneeChamp = mb_convert_encoding($donneeChamp , 'UTF-8');

					  $insertions_ligne .="'". addslashes($donneeChamp)."' ";
					  $insert_ligne .= addslashes($donneeChamp);
					  //if(mysqli_fetch_field_direct($donnees, $i) == "string" || mysqli_fetch_field_direct($donnees, $i) == "blob"){
						//$insertions .=  "'";
						//$insert_ligne .= "'" ;
					  //}
					}
					$insertions_ligne .=  ");\n";
					$insert_ligne .= ");\n" ;
					$nb_ligne +=1 ;
					if ($nb_ligne>=500){
						//echo '<br/>////////////////////////////////////////////////////////////////////////////////////////////////////////<br/>' ;
						//echo 'Je sauvegarde '.$vtable[0].' -:> '.$insertions_ligne.' <br/>' ;
						//echo 'Je sauvegarde dans '.$this->FichierSQL.' <br/>' ;
						$fichierDump2 = fopen($this->FichierSQL, "ab");
						if (!$fichierDump2){
							echo 'Erreur Ouverture de '.$this->FichierSQL.' pour la table '.$vtable[0].' <br/>' ;
						}else{
							//echo '<br/>'.$insertions_ligne.' <br/>' ;
							fwrite($fichierDump2, $insertions_ligne);
							fclose($fichierDump2);
							
						}
						$nb_ligne=0 ;
						$insertions_ligne='' ;
						//echo '////////////////////////////////////////////////////////////////////////////////////////////////////////<br/>' ;

					}
					

					//On va intégrer dans la base de donnée cible !!!
					if ($this->connexion){
						if ($i==1){
							echo "Execution sur le serveur ".$this->Serveur_Cible."\n SQL: ".$insert_ligne."\n" ;
						}
						//mysqli_query($connexion,$insert_ligne);
					}
				}
				//$insertions_ligne .= "\n";

				

			}
			
			if ($nb_ligne>0){
				//echo '<br/>////////////////////////////////////////////////////////////////////////////////////////////////////////<br/>' ;
				//echo 'Je sauvegarde moins de 500 lignes dans '.$this->FichierSQL.' <br/>' ;
				$fichierDump3 = fopen($this->FichierSQL, "ab");
				if (!$fichierDump3){
					echo 'Erreur Ouverture de '.$this->FichierSQL.' (Ligne moins de 500) pour la table '.$vtable[0].' <br/>' ;
				}else{
					//echo '<br/>'.$insertions_ligne.'<br/>' ;
					fwrite($fichierDump3, $insertions_ligne);
					fclose($fichierDump3);
				}
				$nb_ligne=0 ;
				$insertions_ligne='' ;
				//echo '////////////////////////////////////////////////////////////////////////////////////////////////////////<br/>' ;
			}

			
			
		}
		
		//fclose($fichierDump);

		if ($this->connexion){
			//mysqli_close($this->connexion);
		}		

		
		return $this->FichierSQL ;
	}

	


}

?>