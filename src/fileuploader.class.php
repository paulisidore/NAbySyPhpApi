<?php
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



Class xFileUpLoader{
    /**
     * Cette Class permet de gérer l'envoie et le téléchargement de fichier Image
     */
    Public static xNAbySyGS $Main;
    Public $Dossier ;
    public $FICHIERS ;
    public $ExtentionAccepte ;
    public $maxSize ;    //Voir dans le Constructor

    public function __construct(xNAbySyGS $NAbySY,$Dossier= 'userdocs'){
        self::$Main=$NAbySY ;
        $this->maxSize=100*MB ;

        $this->Dossier=$Dossier ;
        if (!file_exists($Dossier)) {
            mkdir($Dossier, 0777, true);
        }

        if (isset($_FILES)){
            $this->FICHIERS=$_FILES ;
        }
        $this->ExtentionAccepte = ['doc', 'docx', 'xls', 'xlsx', 'pdf', 'tiff', 'txt','jpg', 'png', 'jpeg', 'gif', 'bmp', 'ppt',];

    }

    /**
     * Fonction pour stocker un fichier uploader sur le serveur
     * @param String $ChampFichier Le nom donné à la variable qui stock temporairement le fichier
     * @param String $Prefixe Un préfixe ajouté au debut du nom du fichier
     * @return Object xErreur | xNotification
     */
    public function SaveToFile($ChampFichier='fichier',$Prefixe='',$NoSendReponse=false){
        $Err=new xErreur;
        $Err->OK=0 ;
        $Err->TxErreur='Impossible de traiter la demande pour le champ '.$ChampFichier ;
        //echo __FILE__.'.53: Enregistrement du fichier en cour ...';
        if (!isset($this->FICHIERS)){
            $Err->TxErreur='Impossible de traiter la demande pour le champ '.$ChampFichier ;
            return $Err ;
        }
        //var_dump($_FILES[$ChampFichier]) ;

        if (!isset($_FILES)){
            $Err=new xErreur;
            $Err->OK=0 ;
            $Err->TxErreur='Aucun fichier telechargé avec le nom de champs '.$ChampFichier ;
            //var_dump($_FILES) ;
            return $Err ;
        }
        if (!isset($_FILES[$ChampFichier])){
            $Err=new xErreur;
            $Err->OK=0 ;
            $Err->TxErreur='Aucun fichier envoyé par le client.' ;
            //var_dump($_FILES) ;
            return $Err ;
        }
        $tmpName = $_FILES[$ChampFichier]['tmp_name'];
        $name = $_FILES[$ChampFichier]['name'];
        $size = $_FILES[$ChampFichier]['size'];
        $error = $_FILES[$ChampFichier]['error'];
        
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

        $tabExtension = explode('.', $name);
        $extension = strtolower(end($tabExtension));

        $vDest=$this->Dossier.'/'.$name ;
        if ($Prefixe !==''){
            $vDest=$this->Dossier.'/'.$Prefixe.$name ;
        }

        if(in_array($extension, $this->ExtentionAccepte)){
            if (file_exists($vDest)){
                //On supprime le fichier existant
                if (!unlink($vDest)) { 
                    $Err=new xErreur;
                    $Err->OK=0 ;
                    $Err->TxErreur='Le fichier existe sur le serveur et il est impossible de le mettre à jour.' ;
                    return $Err ;
                } 
            }
            //echo "Déplacement du fichier ".$tmpName." vers ".$vDest."</br>";
            $Retour=move_uploaded_file($tmpName, $vDest);
            //echo "fileuploader.class : 177: Reponse: ".$Retour."</br>";
            $Notif=new xErreur ;
            $Notif->OK=1 ;
            $Notif->Extra="Fichier enregistré correctement." ;
            $Notif->Source=get_class($this) ;
            $Notif->Autres="Fichier ".$name." enregistré correctement." ;
            if (!$NoSendReponse){
                return $Notif ;
            }
            return true;
        }
        else{
            $Err=new xErreur;
            $Err->OK=0 ;
            $Err->TxErreur='Mauvaise extension du fichier '.$name ;
            return $Err ;
        }
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

    public function GetDossier(){
        $vDest=$this->Dossier.'/' ;
        return $vDest ;
    }


}