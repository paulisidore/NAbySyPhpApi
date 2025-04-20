<?php
namespace NAbySy\Lib\BonAchat ;

use Exception;
use ModuleMCP;
use xErreur;
use NAbySy\xNAbySyGS;

class xRemoteNAbySyBonAchat extends xNAbySyGS {

    public static \mysqli $Remotedb_link ;
    public static $CONFIG ;
    public function __construct($Myserveur,$Myuser,$Mypasswd,ModuleMCP $mod,$db,$MasterDB="nabysy"){
        parent::__construct($Myserveur,$Myuser,$Mypasswd,$mod,$db,$MasterDB);

        

    }

    public function LoadApiConfigFromFile(){
        if (!isset(self::$Remotedb_link)){
            //On Récupère la configuration dans un fichier s'il existe
            $FichierConfig=self::$MODULE->Nom.'-parametre.json';
            if (!file_exists($FichierConfig)){
                //On le crée
                $Config='{
                "Connexion": {
                    "Serveur":"'.'hypermarcheexclusive.com'.'",
                    "Port":"'."3306".'",
                    "DBUser":"'."hypermar_pharmcp".'",
                    "DBPwd":"'."pharmcp2022".'",
                    "DB":"'."hypermar_nabysygs".'",
                    "MasterDB":"'."hypermar_nabysygs".'"
                    },
                "Module": {
                    "Nom":"'.self::$MODULE->Nom." pour ".$this->MODULE->Nom.'",
                    "MCP_CLIENT":"'.$this->MODULE->MCP_CLIENT.'",
                    "MCP_ADRESSECLT":"'.$this->MODULE->MCP_ADRESSECLT.'",
                    "MCP_TELCLT":"'.$this->MODULE->MCP_TELCLT.'"
                    },
                "DebugMode":"true"
                }';
                try {
                    $F= fopen($FichierConfig, 'w');			
                    $TxT=$Config ;
                    $TxT .="\r\n" ;				
                    fputs($F, $TxT);
                    fclose($F);
                }catch(Exception $e){
                    self::$Log->Write('Erreur création du fichier de configuration du module '.__CLASS__.': '.$e->getMessage());
                    //echo 'Erreur création du fichier de configuration du module '.self::MODULE_NAME.': '.$e->getMessage();
                }
            }

            //On récupere la configuration
            $string = file_get_contents($FichierConfig);
            $Parametre = json_decode($string, false);

            if (isset($Parametre)){
                if (is_object($Parametre)){
                    self::$CONFIG=$Parametre;
                    $xserveur=$Parametre->Connexion->Serveur ;
                    $xport=(int)$Parametre->Connexion->Port ;
                    $xuser=$Parametre->Connexion->DBUser ;
                    $xpasswd=$Parametre->Connexion->DBPwd ;
                    $db=$Parametre->Connexion->DB ;
                    $masterdb=$Parametre->Connexion->MasterDB ;
                    self::$Remotedb_link=new \mysqli($xserveur, $xuser, $xpasswd, $db,$xport) or die("Error ".mysqli_error(self::$Remotedb_link )); // mysql_connect($serveur,$user,$passwd);                        // connection serveur
                    if (!self::$Remotedb_link){
                        $Err=new xErreur;
                        $Err->OK=0;
                        $Err->TxErreur=$this->MODULE->Nom."Connexion impossible a la base de donnée de gestion des Bons d'Achat. ".$xserveur." :user=".$xuser;
                        $Err->TxErreur .=" (".mysqli_error(self::$Remotedb_link ).") " ;
                        $this->Erreur=mysqli_error(self::$Remotedb_link ) ;
                        $this->ISCONNECTED=false ;
                        echo json_encode($Err);
                        return;
                    }
        
                
                    if (self::$Remotedb_link == false){                    
                        $Err=new xErreur;
                        $Err->OK=0;
                        $Err->TxErreur=$this->MODULE->Nom."Connexion impossible a la base de donnée de gestion des Bons d'Achat. ".$xserveur." :user=".$xuser;
                        $Err->TxErreur .=" (".mysqli_error(self::$Remotedb_link ).") " ;
                        $this->Erreur=mysqli_error(self::$Remotedb_link ) ;
                        $this->ISCONNECTED=false ;
                        echo json_encode($Err);
                        return;
                    }
                    $this->ActiveDebug= boolval ($Parametre->DebugMode) ;
                    return true;
                }
            }
        }
        return true;            
    }


}


?>