<?php
Class xPDO {
	
	public function ExecuteFile($DBase,$Host,$Username,$Password,$Fichier){
//Pré-requis 0: tout viens d'un formulaire, à toi d'adapter
			//Pré-requis 1: une connexion à la BDD, j'utilise PDO ;-)
			//Pré-requis 2: Connaître la base qu'on attaque
			$ConnStr="" ;
			$bdd = new PDO('mysql:dbname='.$DBase.';host='.$Host,$Username,$Password);
			$bdd -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$bdd->query("SET NAMES 'utf8', lc_time_names = 'fr_FR'");
		 
			//Pré-requis 3: le fichier SQL est dans le répertoire SQL/
			$req = "";
			$finRequete = false;
			$nb=0 ;
			$Rapport="" ;
			$tables = file($Fichier); //Là ton fichier
			foreach ($tables AS $ligne) {
				if ($ligne[0] != "-" && $ligne[0] != "") {
					$req .= $ligne;
					//Permet de repérer quand il faut envoyer l'ordre SQL...
					$test = explode(";", $ligne);
					if (sizeof($test) > 1) {
						$finRequete = true;
					}
				}
				if ($finRequete) {
					$stmt = $bdd -> prepare($req);
					$Rapport.="Execution : ".$req."</br>" ;
					if (!$stmt -> execute()) {
						throw new PDOException("Impossible d'ins&eacute;rer la ligne:<br>".$req."<hr>", 100);
						break ;
					}
					$nb++ ;
					$req = "";
					$finRequete = false;
				}
			}
			return $Rapport ;
			
		 
		
	}
}
?>