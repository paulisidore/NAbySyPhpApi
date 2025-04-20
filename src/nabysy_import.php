<?php
Class xImportHTML{	
	public $MAX_FILE_S; 	//Taille en Mo
	public $Boutique ;
	public function __construct($Boutique=null,$Id=0){
		if (!$this->MAX_FILE_S){
			$this->MAX_FILE_S=25 ;	//25 Mo Max
		}
		$this->Boutique = $Boutique ;
	}
	public function GetHTML($Href='#'){
		if (!$this->MAX_FILE_S){
			$this->MAX_FILE_S=25 ;	//25 Mo Max
		}
		$IdBoutique=0 ;
		$NomBoutique="" ;
		if ($this->Boutique){
			$IdBoutique=$this->Boutique->Id ;
			$NomBoutique=$this->Boutique->Nom ;
		}
		$TailMax=$this->MAX_FILE_S * 1000000 ;
		$ScriptImport='<form id="importfichier" method="POST" action="'.$Href.'" enctype="multipart/form-data">
							 <input type="hidden" name="MAX_FILE_SIZE" value="'.$TailMax.'">
							 <input type="hidden" name="IdBoutique" id="IdBoutique" value="'.$IdBoutique.'">
							 Fichier : <input type="file" name="FICHIER" id="FICHIER" >
							 <input type="submit" name="Importer" id="Importer" value="Importer le Fichier">
						</form>' ;
		return $ScriptImport ;
	}
	
	
	
}


?>

