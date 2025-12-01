<?php
namespace NAbySy ;

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

    public function __construct(xNAbySyGS $NAbySY,$DossierPhoto= 'photos'){
        self::$Main=$NAbySY ;
        $this->maxSize=50*MB ;

        $this->DossierPhoto=$DossierPhoto ;
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


}