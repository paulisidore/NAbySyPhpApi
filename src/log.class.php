<?php
namespace NAbySy ;
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
        $this->Dossier='log' ;
    }

    /**
     * Ecriture dans le fichier Log
     * @param string $LogInfos Information à enregistrer dans le fichier journal
     * @return bool 
     */
    public function Write($LogInfos){
        if (!isset($LogInfos)){
            return false ;
        }
        try{
            if (!is_dir($this->Dossier)){
                mkdir($this->Dossier) ;
            }
        }catch(Exception $ex){
            echo "Erreur: Impossible de créer le dossier ".$this->Dossier.". ".$ex->getMessage() ;
            return false ;
        }
        						
        // 1 : on ouvre le fichier
        try {
            $Dat=date("Y-m-d");
            $Tim=date("H:i:s");
            $Dte=$Dat." ".$Tim ;
            $Fichier=$this->Dossier.DIRECTORY_SEPARATOR.$this->File ;		
            $monfichier = fopen($Fichier, 'a');
            $nbTraceArr=3;
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
            echo "Erreur systeme de fichier sur ".$this->File.". ".$ex->getMessage() ;
        }				
    
        return false ;
    }

    public function AddToLog($LogInfos):bool{
        $Journal=new xORMHelper($this->Main,null,false,"journal");
        return $Journal->AddToLog($LogInfos);
    }

}

?>