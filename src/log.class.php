<?php
namespace NAbySy ;

use Exception;
use NAbySy\ORM\xORMHelper;

/**
 * Module de gestion de la journalisation
 */
class xLog{

    public xNAbySyGS $Main ;
    public string $File ;
    public string $Dossier ;

    public function __construct(xNAbySyGS $NabySy,?string $LogFile="Log.csv"){
        $this->Main=$NabySy ;
        $this->File=$LogFile ;
        $this->Dossier= xNAbySyGS::CurrentFolder(true).'log' ;
    }

    /**
     * Ecriture dans le fichier Log
     * @param string $LogInfos Information à enregistrer dans le fichier journal
     * @param int $DebugTraceLevel Niveau de débuggage de la pile d'appel)
     * @param bool $LogToDB Si vrai, enregistre le log dans la base de donnée
     * @return bool 
     */
    public function Write($LogInfos, int $DebugTraceLevel=2, bool $LogToDB=false){
        if (!isset($LogInfos)){
            return false;
        }
        if(xNAbySyGS::isRunFromCLI()){
            echo "[LOG]: ".$LogInfos.PHP_EOL;
        }
        if($LogToDB){
            $this->AddToLog($LogInfos);
        }

        // Gestion sécurisée de l'umask pour la création de dossier et fichier
        $oldumask = umask(0002);

        try {
            if (!is_dir($this->Dossier)){
                // Crée le dossier avec les droits 775 réels grâce à l'umask(0002)
                mkdir($this->Dossier, 0777, true);
                // Force le bit SGID (2775) pour que les futurs fichiers héritent du groupe parent
                chmod($this->Dossier, 02775);
            }
        } catch(Exception $ex) {
            umask($oldumask); // Toujours restaurer avant de throw
            if(xNAbySyGS::isRunFromCLI()){
                echo "[LOG]: Erreur: Impossible de créer le dossier ".$this->Dossier.". ".$ex->getMessage().PHP_EOL;
            }
            throw new Exception("Erreur: Impossible de créer le dossier ".$this->Dossier.". ".$ex->getMessage(), ERR_FILE_SYSTEM);
        }
                                
        try {
            $Dte = date("d/m/Y H:i:s");
            $Fichier = $this->Dossier.DIRECTORY_SEPARATOR.$this->File;    
            
            // On vérifie si le fichier existe AVANT l'ouverture pour savoir si on doit lui appliquer le chmod
            $isNewFile = !file_exists($Fichier);

            $monfichier = fopen($Fichier, 'a');
            if (!$monfichier) {
                if(xNAbySyGS::isRunFromCLI()){
                    echo "[LOG]: Erreur: Impossible d'ouvrir le fichier en écriture: ".$Fichier.PHP_EOL;
                }
                throw new Exception("Impossible d'ouvrir le fichier en écriture.");
            }
            
            // 1. Verrouille le fichier de manière exclusive (attend son tour si déjà utilisé)
            flock($monfichier, LOCK_EX);

            $Traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $DebugTraceLevel);
            $Trace = $Dte." ".__FILE__." Ligne: ".__LINE__."-> ";
            
            if (!empty($Traces)) {
                $lastTrace = end($Traces);
                $Trace = $Dte." ".($lastTrace['file'] ?? 'Unknown')." Ligne: ".($lastTrace['line'] ?? '0').": ";
            }

            fputs($monfichier, $Trace); 
            fputs($monfichier, $LogInfos."\r\n");

            // 2. Force l'écriture physique sur le disque avant de libérer le verrou
            fflush($monfichier);

            // 3. Libère le verrou pour le processus suivant
            flock($monfichier, LOCK_UN);
            
            fclose($monfichier);

            // N'applique le chmod QUE si c'est ce script qui vient de créer le fichier
            if ($isNewFile) {
                chmod($Fichier, 0664);
            }

            // Restauration de l'umask initial en fin de succès
            umask($oldumask);
            return true;
        }
        catch(Exception $ex){
            // Restauration de l'umask initial en cas d'erreur
            umask($oldumask);
            if(xNAbySyGS::isRunFromCLI()){
                echo "[LOG]: Erreur systeme de fichier sur ".$this->File.". ".$ex->getMessage().PHP_EOL;
            }
            throw new Exception("Erreur systeme de fichier sur ".$this->File.". ".$ex->getMessage(), ERR_FILE_SYSTEM);
        }                
    }



    public function AddToJournalODBC(string $LogInfos, ?int $DebugTraceLevel=2):bool{
        $Journal=new xORMHelper($this->Main,null,false,"journal");
        return $Journal->AddToLog($LogInfos, $DebugTraceLevel);
    }

    /**
     * Ajoute une entrée dans le journal CSV des évènements systèmes
     * @param string $Note : Note à inscrire
     * @return bool
     */
    public function AddToLog(string $Note, ?int $DebugTraceLevel=2):bool{
        if ($Note==''){
            return false;
        }
        $this->Main::$Log->Write($Note, $DebugTraceLevel) ;
        return true;
    }

}

?>