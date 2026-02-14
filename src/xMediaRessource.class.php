<?php
namespace NAbySy\Media ;
use NAbySy\xNAbySyGS;
use NAbySy\xPhoto;

/**
 * Gestionnaire des Medias pour NAbySyGS
 */

class xMediaRessource {
    public xNAbySyGS $Main ;
    public string $DossierMedia ='media' ;

    public function __construct(xNAbySyGS $nabysy, string $LocalDir="media"){
        $this->Main = $nabysy;
        $this->DossierMedia = $LocalDir;
        if(!is_dir($this->DossierMedia)){
            try {
                mkdir($this->DossierMedia,0777,true);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
    }

    /**
     * Enregistre un media localement
     * @param string $FullFilePath 
     * @param string $dstFileName 
     * @param bool $deleteIfExiste 
     * @return bool 
     */
    public function SaveMedia(string $FullFilePath, string $dstFileName, bool $deleteIfExiste=false):bool{
        if (file_exists($FullFilePath)){
            $File = $this->DossierMedia.DIRECTORY_SEPARATOR. $dstFileName ;
            if(file_exists($File)){
                if(!$deleteIfExiste){
                    //On ne supprime pas s'il existe
                    //$this->Main::$Log->AddToLog("Le fichier média ".$File." existe pas besoins de le supprimer",5);
                    return true;
                }else{
                    //On supprime le fichier
                    try {
                        unlink($File);
                    } catch (\Throwable $th) {
                        $this->Main::$Log->AddToLog("ERREUR de suppression du fichier existant ".$File.": ".$th->getMessage(),5);
                    }
                }
            }
			$this->Main::$Log->AddToLog("Copie du média ".$FullFilePath." vers ".$File, 4);
            try {
                if (copy($FullFilePath,$File)){
                    $this->Main::$Log->AddToLog($File." Copié correctement.",4);
                }
            } catch (\Throwable $th) {
                $this->Main::$Log->AddToLog("ERREUR de copie de ".$FullFilePath.": ".$th->getMessage(),4);
                return false;
            }
			return true;
		}else{
			$this->Main::$Log->AddToLog("Fichier ".$FullFilePath." est inexistant.",4);
		}
        return false;
    }

    /**
     * Indique si Oui/Non un media existe
     * @param string $MediaName | Nom du fichier representant le media
     * @return bool 
     */
    public function MediaExiste(string $MediaName):bool{
        $File = $this->DossierMedia.DIRECTORY_SEPARATOR. $MediaName ;
        return file_exists($File) ;
    }

    /**
     * Retourne le chemin d'accès local d'un média
     * @param string $MediaName | Nom du Media (Nom de fichier)
     * @param bool $checkPresent | Si VRAI, une vérification est faite sur l'existance du fichier. Retourne une chaine vide s'il n'existe pas.
     * @return string 
     */
    public function GetMediaRealPath(string $MediaName, bool $checkPresent=false):string{
        $File = $this->DossierMedia.DIRECTORY_SEPARATOR. $MediaName ;
        if($checkPresent){
            if(!file_exists($File)){
                return '';
            }
        }
        return $File ;
    }

    /**
	 * Sauvegarder un Média envoyé depuis une requette HTTP
	 */
	public function SaveMediaFromRequest($ChampFichier="fichier",$NomFichier="monfichierMedia.png"){
		$DossierPhoto=$this->Main->CurrentFolder(true).$this->DossierMedia;
		$Photo=new xPhoto($this->Main,$DossierPhoto);
		$Repo=$Photo->SaveToFile($ChampFichier,$NomFichier);
		return $Repo ;
	}
	
	/**
	 * Retourne l'url d'un media accessible à l'extérieur via HTTP/HTTPS
	 * @param bool $NoSendToClient | Si VRAI, le média sera envoyé directement au client via HTTP/HTTPS
	 * @param string|null $baseUrl 
	 * @return true|string 
	 */
	public function GetMediaURL(string $FileName, $NoSendToClient=false,string $baseUrl=null){
        $DossierPhoto=$this->Main->CurrentFolder(true).$this->DossierMedia;
		$Photo=new xPhoto($this->Main, $DossierPhoto);
		$DossierPhotos=$Photo->GetDossierPhoto() ;
        $vFileName=$FileName ;
		$FileName=$DossierPhotos.$FileName ;
		
		//if (file_exists($FileName)){
			//$this->Main::$Log->AddToLog("Fichier ".$FileName." existe.",4);
		//}else{
			//$this->Main::$Log->AddToLog("Fichier ".$FileName." absent.",4);
		//}
		//On copie dans un dossier temporaire pour la sécurité
		$httpX='http://' ;
		if (isset($_SERVER['HTTPS'])){
			$httpX='https://';
		}
		//print_r($_SERVER);
		$DosTmp=$_SERVER['DOCUMENT_ROOT'].'/tmp' ;		

		if (!is_dir($DosTmp)){
			mkdir($DosTmp) ;
		}

		if (!$NoSendToClient){
			$Photo->SendFile($FileName) ;
			return true ;
		}

		$File=$DosTmp.'/'.$vFileName ;
		$Site=$httpX.$_SERVER['HTTP_HOST'].'/tmp/'.$vFileName ;

		if(isset($baseUrl)){
			$Site = $baseUrl . '/' . $vFileName;
		}

		//echo $DosTmp ;
		$FichierComplet=''.$FileName;
		if (file_exists($FichierComplet)){
            if (!file_exists($File)){
                $this->Main::$Log->AddToLog("Copie du média dans le dossier tmp: ".$File,4);
                if (copy($FichierComplet,$File)){
                    $this->Main::$Log->AddToLog($File." Copié correctement.",4);
                }
            }
		}else{
			$this->Main::$Log->AddToLog("Fichier source ".$FichierComplet." est inexistant.",4);
		}
		if (file_exists($File)){
			//$this->Main::$Log->AddToLog("Lien fichier Envoyé: ".$Site,4);
			return $Site ;
		}
		return $httpX.$_SERVER['HTTP_HOST'].'/tmp/aucune.png';		
	}
}
?>