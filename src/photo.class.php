<?php
namespace NAbySy ;

use Exception;
use NAbySy\ORM\xORMHelper;

/* define('KB', 1024);
define('MB', 1048576);
define('GB', 1073741824);
define('TB', 1099511627776); */

if (!defined('KB')){
    define('KB', 1024);
}
if (!defined('MB')){
    define('MB', 1048576);
}
if (!defined('GB')){
    define('GB', 1073741824);
}
if (!defined('TB')){
    define('TB', 1099511627776);
}

Class xPhoto{
    /**
     * Cette Class permet de gérer l'envoie et le téléchargement de fichier Image
     */
    
    Public static xNAbySyGS $Main;
    Public $DossierPhoto ;
    public $FICHIERS ;
    public $ExtentionAccepte ;
    public $maxSize ;    //Voir dans le Constructor
    public string $DossierMiniature ;

    public function __construct(xNAbySyGS $NAbySY,$DossierPhoto= 'photos', ?bool $IgnoreCltTechnoWEBFolder=false){
        self::$Main=$NAbySY ;
        $this->maxSize=50*MB ;

        $this->DossierPhoto=$DossierPhoto ;
        if(xNAbySyGS::$TECHNOWEB_ACTIVE && isset(xNAbySyGS::$TechnoWEBClient) && !$IgnoreCltTechnoWEBFolder){
            $this->DossierPhoto .="-".xNAbySyGS::$TechnoWEBClient->IDCLIENT;
        }
        if (!file_exists($this->DossierPhoto)) {
            xNAbySyGS::$Log->AddToLog("Création du dossier des média: ".$this->DossierPhoto. " en cour ...");
            try {
                mkdir($this->DossierPhoto, 0777, true);
                xNAbySyGS::$Log->AddToLog("Dossier média: ".$this->DossierPhoto. " crée correctement.");
            } catch (\Throwable $th) {
                xNAbySyGS::$Log->AddToLog("Erreur de création du dossier ".$this->DossierPhoto. ": ".$th->getMessage());
                //throw $th;
            }
            
        }

        $this->DossierMiniature = $this->DossierPhoto."/miniatures";
        if (!file_exists($this->DossierMiniature)) {
            mkdir($this->DossierMiniature, 0777, true);
        }
        
        if (isset($_FILES)){
            $this->FICHIERS=$_FILES ;
        }
        $this->ExtentionAccepte = ['jpg', 'png', 'jpeg', 'gif', 'bmp'];

    }

    /**
     * Fonction pour stocker un fichier uploader sur le serveur
     * @param String $ChampPhoto Le nom donné à la variable qui stock temporairement le fichier
     * @param String $FichierDest le nom du fichier de destination ou sera stocker le fichier dans le dossier $this->DossierPhoto
     * @param bool $NoSendReponse : SI Vrai, n'envoie pas de notification au client en cas de succès
     * @return Object xErreur | xNotification
     */
    public function SaveToFile($ChampPhoto='file',$FichierDest='photo.png',$NoSendReponse=false){
        $Err=new xErreur;
        $Err->OK=0 ;
        $Err->TxErreur='Impossible de traiter la demande pour le champ '.$ChampPhoto.' Fichier '.$FichierDest ;

        if (!isset($this->FICHIERS)){
            return $Err ;
        }
        //print_r($_FILES) ;
        self::$Main::$Log->Write(__FILE__." L".__LINE__." Début SaveToFile : ".json_encode($_FILES) );
        $tmpName='';
        $SendInBody=false;

        if (!isset($_FILES[$ChampPhoto])){
            $Blob = file_get_contents('php://input');
            //var_dump($Blob) ;
            if ($Blob !== ''){
                //le fichier est le contenue du Blog
                $tmpfname = tempnam($this->DossierPhoto, 'tmp'); // good
                if ($tmpfname !== false){
                    $f = fopen($tmpfname, "w+b");
                    if ($f !== false){
                        fwrite($f,$Blob);
                        fclose($f);
                        $size=filesize($tmpfname);
                        $name= $tmpfname;
                        $tmpName=$tmpfname;
                        $SendInBody=true;
                    } 
                }
            }else{
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='Aucun fichier envoyé par le client.' ;            
                return $Err ;
            }
            
        }else{
            
            if (!isset($_FILES[$ChampPhoto])){
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='Aucun fichier téléchargé dans le formulaire ' ;
                //var_dump($_FILES) ;
                return $Err ;
            }
            $tmpName = $_FILES[$ChampPhoto]['tmp_name'];
            $name = $_FILES[$ChampPhoto]['name'];
            $size = $_FILES[$ChampPhoto]['size'];
            $error = $_FILES[$ChampPhoto]['error'];
    
            if ($error>0){
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='Erreur lors du traitement du fichier: '.$error ;
                return $Err ;
            }
    
            if ($size>$this->maxSize){
                $Surplus=$this->maxSize-$size ;
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='Le fichier envoyé est au dessus de la limite autorisée. Ecart:'.$Surplus ;
                return $Err ;
            }
    
            //$vDest=$this->DossierPhoto.'/'.$FichierDest ;
    
            $tabExtension = explode('.', $name);
            $extension = strtolower(end($tabExtension));
            if(!in_array($extension, $this->ExtentionAccepte)){           
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='Mauvaise extension du fichier '.$name ;
                return $Err ;
            }
        }

        $vDest=$this->DossierPhoto.'/'.$FichierDest ;
        if (file_exists($vDest)){
            //On supprime le fichier existant
            if (!unlink($vDest)) { 
                $Err=new xErreur;
                $Err->OK=0 ;
                $Err->TxErreur='La photo existe sur le serveur mais il est impossible de la mettre à jour.' ;
                return $Err ;
            } 
        }

        $Notif=new xNotification() ;
        $Notif->OK=1 ;
        $Notif->Extra=$vDest ;
        $Notif->Source=__CLASS__ ;
        if ($SendInBody){
            $Rep=rename($tmpName,$vDest);
        }else{
            //self::$Main::$Log->Write(__FILE__." L".__LINE__." Copie du fichier ".$tmpName." vers ".$vDest );
            $Rep=move_uploaded_file($tmpName, $vDest);
            //self::$Main::$Log->Write(__FILE__." L".__LINE__." Résultat de la copie : ".$Rep );
            if ($Rep === 1 || $Rep === true || $Rep === "1"){
                $Notif->OK=1 ;
                $Notif->TxErreur="";
            }else{
                $Notif=new xErreur() ;
                $Notif->OK=0 ;
                $Notif->TxErreur='ERREUR SYSTEME: Impossible copie dans le dossier '.$vDest ;
            }
        }        
       
        if (!$NoSendReponse){
            return $Notif ;
        }
        return true ;
        
        
    }

    public function SendFile($Fichier){
        $filename=$Fichier;
        if (!file_exists($filename)){
            return false ;
        }
        header("Content-Description: File Transfer");
        header("Content-type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");
        header("Content-disposition: attachment;filename=$filename");
        
        readfile($filename);

        return true ;
    }

    public function GetDossierPhoto(){
        $vDest=$this->DossierPhoto.'/' ;
        return $vDest ;
    }

    public function GetDossierMinitaurePhoto(){
        $vDest=$this->DossierMiniature.'/' ;
        return $vDest ;
    }

    /**
     * Ajoute une extension de fichier à prendre en charge
     * @param string $Extention 
     * @return bool 
     */
    public function AddFileExtension(string $Extention):bool{
        $canAdd=true;
        foreach ($this->ExtentionAccepte as $ext) {
            if(strtolower($ext) == strtolower($Extention)){
                $canAdd=false;
                break;
            }
        }
        if($canAdd){
            $this->ExtentionAccepte[]=$Extention ;
        }
        return true;
    }

    /**
     * Ajoute l'extension du fichier s'il n'est pas déjà ^pris en charge
     * @param string $FileName 
     * @return bool 
     */
    public function AddExtentionFromFileName(string $FileName):bool{
        if($FileName && is_string($FileName)){
            if($FileName == ''){
                return false;
            }
            $part=explode(".",$FileName);
            if(count($part) == 0 ){
                return false;
            }
            $lastIndex=count($part) -1;
            if($lastIndex < 1){
                return false;
            }
            $ext = $part[$lastIndex];
            return $this->AddFileExtension($ext);

        }
        return false;
    }

    /**
     * Redimensionne une image
     */
    private function resizeImage(string $fichierImageSrc, $resourceType, $image_width, $image_height, $resizeWidth=576, $resizeHeight=718){
        // $resizeWidth = 100;
        // $resizeHeight = 100;
        $fichierSortie=$fichierImageSrc ;

        $fileExt = pathinfo($fichierImageSrc, PATHINFO_EXTENSION);
        $sourceProperties = getimagesize($fichierImageSrc);
        $ImageType = $sourceProperties[2];
        $sourceImageWidth = $sourceProperties[0];
        $sourceImageHeight = $sourceProperties[1];

        $old_x = imageSX($resourceType);
        $old_y = imageSY($resourceType);

        if($old_x > $old_y) 
        {
            $thumb_w    =   $resizeWidth;
            $thumb_h    =   $old_y*($resizeHeight/$old_x);
        }

        if($old_x < $old_y) 
        {
            $thumb_w    =   $old_x*($resizeWidth/$old_y);
            $thumb_h    =   $resizeHeight;
        }

        if($old_x == $old_y) 
        {
            $thumb_w    =   $resizeWidth;
            $thumb_h    =   $resizeHeight;
        }

        $imageLayer = imagecreatetruecolor((int)$thumb_w, (int)$thumb_h);
        imagecopyresampled($imageLayer, $resourceType, 0, 0, 0, 0, (int)$thumb_w, (int)$thumb_h, (int)$old_x, (int)$old_y);
        return $imageLayer;
    }
    
    /**
     * Créer une miniature de la photo
     */
    public function createImageThump(string $fichierImageSrc, string $destFileNameSansExtention){
        $sourceProperties=false;
        $Debugger=new xORMHelper( $this->Boutique->Main,null,false,'journal');

        try{
            $fileExt = pathinfo($fichierImageSrc, PATHINFO_EXTENSION);
            $sourceProperties = getimagesize($fichierImageSrc);
        }catch (Exception $ex){
            $Debugger->AddToLog("Err dans photo.createImageThump: Impossible de créer la miniature pour ".$fichierImageSrc);
            $Debugger->AddToLog("Exception: ".$ex->getMessage());
            return ;
        }
        
        $ImageType = $sourceProperties[2];
        $sourceImageWidth = $sourceProperties[0];
        $sourceImageHeight = $sourceProperties[1];
        

        $new_width = 576;
        $new_height = 718 ;

        $fichierMiniature=$destFileNameSansExtention . '.' . $fileExt ;

        switch ($ImageType)
        {
            case IMAGETYPE_JPEG:
                $resourceType = imagecreatefromjpeg($fichierImageSrc);                

                $imageLayer = $this->resizeImage($fichierImageSrc, $resourceType, $sourceImageWidth, $sourceImageHeight, $new_width, $new_height);
                imagejpeg($imageLayer, $destFileNameSansExtention . '.' . $fileExt);
                return  $fichierMiniature;
            break;

            case IMAGETYPE_GIF:
                $resourceType = imagecreatefromgif($fichierImageSrc);
                $imageLayer = $this->resizeImage($fichierImageSrc, $resourceType, $sourceImageWidth, $sourceImageHeight, $new_width, $new_height);
                imagegif($imageLayer,  $destFileNameSansExtention . '.' . $fileExt);
                return  $fichierMiniature;
            break;

            case IMAGETYPE_PNG:
                $resourceType = imagecreatefrompng($fichierImageSrc);
                $imageLayer = $this->resizeImage($fichierImageSrc, $resourceType, $sourceImageWidth, $sourceImageHeight, $new_width, $new_height);
                imagepng($imageLayer, $destFileNameSansExtention . '.' . $fileExt);
                return  $fichierMiniature;
            break;

            case IMAGETYPE_JPEG2000:
                $resourceType = imagecreatefrompng($fichierImageSrc);
                $imageLayer = $this->resizeImage($fichierImageSrc, $resourceType, $sourceImageWidth, $sourceImageHeight, $new_width, $new_height);
                imagepng($imageLayer,  $destFileNameSansExtention . '.' . $fileExt);
                return  $fichierMiniature;
            break;

            default:

            break;
        }
    }
}