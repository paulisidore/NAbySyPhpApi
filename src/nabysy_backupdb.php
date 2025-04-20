<?php
// Outil de Sauvegarde de la Base de donnée
Class xSauvegarde
{
	public $DBase ;
	public $Serveur_Cible ;
	public $User_Cible ;
	public $Pwd_Cible ;
	public $Main ;
	public $connexion ;
	public $FichierSQL ;
	
	public function __construct($NAbySy=null){	
		if ($NAbySy){
			$this->Main=$NAbySy ;
			$this->FichierSQL=$this->Main->dbase."-back" ;
		}
	}
	
	public function Sauvegarder($DBsource=null,$FichierDst=null,$SrvCible=null,$UserC='',$PwdC='',$ExporterStructure=0,$ExporterDonnee=1){
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
		return $Fichier ;
	}
	
	private function dump_MySQL($host, $user, $pass, $db, $mode)
	{
		if(!$this->connexion)
			$this->connexion = mysqli_connect($host, $user, $pass, $db);
		
		$entete  = "-- ----------------------\n";
		$entete .= "-- Traitement de la base ".$db." au ".date("d-M-Y")."\n";
		$entete .= "-- ----------------------\n\n\n";
		$creations = "";
		$insertions = "\n\n";
		$sql="show tables" ;
		$listeTables =$this->Main->ReadWrite($sql) ;// mysqli_query($connexion,"show tables");
		while($table = $listeTables->fetch_assoc())  // mysqli_fetch_array($listeTables)
		{
			// structure ou la totalité de la BDD
			if($mode == 1 || $mode == 2)
			{
				$creations .= "-- -----------------------------\n";
				$creations .= "-- Structure de la table ".$db.".".$table[0]."\n";
				$creations .= "-- -----------------------------\n";
				$sql="show create table ".$table[0] ;
				$listeCreationsTables =$this->Main->ReadWrite($sql) ;
				//$listeCreationsTables = mysqli_query($connexion,"show create table ".$table[0]);
				while($creationTable = $listeCreationsTables->fetch_assoc()) //mysqli_fetch_array($listeCreationsTables))
				{
				  $creations .= $creationTable[1].";\n\n";
				}
			}
			// données ou la totalité
			if($mode > 1)
			{
				//$donnees = mysqli_query($connexion,"SELECT * FROM ".$table[0]);
				$sql="SELECT * FROM ".$table[0] ;
				$donnees = $this->Main->ReadWrite($sql);
				$insertions .= "-- -----------------------------\n";
				$insertions .= "-- Contenu de la table ".$db.".".$table[0]."\n";
				$insertions .= "-- -----------------------------\n";
				while($ligne = $donnees->fetch_assoc()) //mysqli_fetch_array($donnees)
				{
					$insert_ligne= "INSERT INTO ".$table[0]." VALUES(";
					$insertions .= "INSERT INTO ".$table[0]." VALUES(";
					for($i=0; $i < mysqli_num_rows($donnees); $i++)
					{
					  if($i != 0){
						 $insertions .=  ", ";
						 $insert_ligne .= ", " ;
					  }
					  if(mysqli_fetch_field_direct($donnees, $i) == "string" || mysqli_fetch_field_direct($donnees, $i) == "blob"){
						 $insertions .=  "'";
						 $insert_ligne .= "'" ;
					  }
					  $insertions .= addslashes($ligne[$i]);
					  $insert_ligne .= addslashes($ligne[$i]);
					  if(mysqli_fetch_field_direct($donnees, $i) == "string" || mysqli_fetch_field_direct($donnees, $i) == "blob"){
						$insertions .=  "'";
						$insert_ligne .= "'" ;
					  }
					}
					$insertions .=  ");\n";
					$insert_ligne .= ");\n" ;
					//On va intégrer dans la base de donnée cible !!!
					if ($this->connexion){
						if ($i==1){
							echo "Execution sur le serveur ".$this->Serveur_Cible."\n SQL: ".$insert_ligne."\n" ;
						}
						//mysqli_query($connexion,$insert_ligne);
					}
				}
				$insertions .= "\n";
			}
		}
		
		if ($this->connexion){
			mysqli_close($this->connexion);
		}
		if ($this->FichierSQL ==''){
			$this->FichierSQL = "sauv_".$this-DBase."sql" ;
		}
		$fichierDump = fopen($this->FichierSQL, "wb");
		fwrite($fichierDump, $entete);
		fwrite($fichierDump, $creations);
		fwrite($fichierDump, $insertions);
		fclose($fichierDump);

		echo "Sauvegarde terminée: ".$this->FichierSQL ;
		
		return $this->FichierSQL ;
	}

	


}

?>