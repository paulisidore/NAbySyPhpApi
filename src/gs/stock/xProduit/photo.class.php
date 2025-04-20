<?php

Class xPhoto{
    /**
     * Cette Class permet de gérer l'envoie et le téléchargement de fichier Image
     */
    Public $Boutique;
    Public $DossierPhoto ;
    public $FICHIERS ;
    public $ExtentionAccepte ;
    public $maxSize = 400000;

    public function __construct(xBoutique $Boutiq,$DossierPhoto= 'photos'){
        $this->Boutique=$Boutiq ;
        if (isset($Boutiq)){
            $this->Boutique=$Boutiq ;
        }
        $this->DossierPhoto=$DossierPhoto ;
        if (!file_exists('path/to/directory')) {
            mkdir('path/to/directory', 0777, true);
        }
        if (!file_exists($DossierPhoto)) {
            mkdir($DossierPhoto, 0777, true);
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
     * @return Object xErreur | xNotification
     */
    public function SaveToFile($ChampPhoto='photo',$FichierDest='photo.png'){
        $Err=new xErreur;
        $Err->OK=0 ;
        $Err->TxErreur='Impossible de traiter la demande pour le champ '.$ChampPhoto.' Fichier '.$FichierDest ;

        if (!isset($this->FICHIERS)){
            return $Err ;
        }
        //print_r($_FILES) ;

        if (!isset($_FILES)){
            $Err=new xErreur;
            $Err->OK=0 ;
            $Err->TxErreur='Aucun fichier telecharge avec le nom de champs '.$ChampPhoto ;
            //var_dump($_FILES) ;
            return $Err ;
        }
        $tmpName = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'];
        $size = $_FILES['file']['size'];
        $error = $_FILES['file']['error'];

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

        $vDest=$this->DossierPhoto.'/'.$FichierDest ;

        $tabExtension = explode('.', $name);
        $extension = strtolower(end($tabExtension));
        if(in_array($extension, $this->ExtentionAccepte)){
            if (file_exists($vDest)){
                //On supprime le fichier existant
                if (!unlink($vDest)) { 
                    $Err=new xErreur;
                    $Err->OK=0 ;
                    $Err->TxErreur='La photo existe sur le serveur mais il est impossible de la mettre à jour.' ;
                    return $Err ;
                } 
            }else{

            }

            move_uploaded_file($tmpName, $vDest);
            $Notif=new xErreur ;
            $Notif->OK=1 ;
            $Notif->Extra=$vDest ;
            $Notif->Source='xPhoto.class.php' ;
            return $Notif ;
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

    public function GetDossierPhoto(){
        $vDest=$this->DossierPhoto.'/' ;
        return $vDest ;
    }


}