<?php
namespace NAbySy ;

use Exception;
use NAbySy\ORM\xORMHelper;

/**
 * Module de gestion de la journalisation
 */
class xLog{

    public xNAbySyGS $Main ;
    public $File ;
    public $Dossier ;

    public function __construct(xNAbySyGS $NabySy,$LogFile="Log.csv"){
        $this->Main=$NabySy ;
        $this->File=$LogFile ;
        $this->Dossier= $NabySy::CurrentFolder(true).'log' ;
    }

    /**
     * Ecriture dans le fichier Log
     * @param string $LogInfos Information à enregistrer dans le fichier journal
     * @param int $DebugTraceLevel Niveau de débuggage de la pile d'appel)
     * @param bool $LogToDB Si vrai, enregistre le log dans la base de donnée
     * @return bool 
     */
    public function Write($LogInfos, int $DebugTraceLevel=3, bool $LogToDB=false){
        if (!isset($LogInfos)){
            return false ;
        }
        if($LogToDB){
            $this->AddToLog($LogInfos) ;
        }
        try{
            if (!is_dir($this->Dossier)){
                mkdir($this->Dossier, 0777, true) ;
            }
        }catch(Exception $ex){
            throw new Exception("Erreur: Impossible de créer le dossier ".$this->Dossier.". ".$ex->getMessage(), ERR_FILE_SYSTEM);
            return false ;
        }
        						
        // 1 : on ouvre le fichier
        try {
            $Dat=date("Y-m-d");
            $Tim=date("H:i:s");
            $Dte=$Dat." ".$Tim ;
            $Fichier=$this->Dossier.DIRECTORY_SEPARATOR.$this->File ;		
            $monfichier = fopen($Fichier, 'a');
            $nbTraceArr= $DebugTraceLevel;
            $Traces=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,$nbTraceArr);
            $niv=0;
            $Trace=$Dte." ". __FILE__." Ligne: ".__LINE__."-> ";
            foreach ($Traces as $dbg) {
                $niv++ ;
                if($niv==$nbTraceArr){
                    $Trace=$Dte." ".$dbg['file']." Ligne: ".$dbg['line']." ".$dbg['class'].":".$dbg['function']."-> " ;
                }
            }
            fputs($monfichier, $Trace);            
            $TxLog=str_replace("\n","",$LogInfos) ;
            $TxLog=str_replace("\r\n","",$TxLog) ;
            $TxLog=str_replace("\r","",$TxLog) ;
            $TxT=$TxLog."\r\n" ;	
            fputs($monfichier, $TxT);
            fclose($monfichier);
            return true ;
        }
        catch(Exception $ex){
            throw new Exception("Erreur systeme de fichier sur ".$this->File.". ".$ex->getMessage(), ERR_FILE_SYSTEM);
        }				
    
        return false ;
    }

    public function AddToLog($LogInfos):bool{
        $Journal=new xORMHelper($this->Main,null,false,"journal");
        return $Journal->AddToLog($LogInfos);
    }

}

?>