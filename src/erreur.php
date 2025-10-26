<?php

/* 
 * Application developpé par Paul Isidore A. NIAMIE
 * Each line should be prefixed with  * 
 */
namespace NAbySy ;

use N;
use Throwable;

Class xErreur
{
	public int $OK = 0;
	public string|null $TxErreur;
	public $Source;
	public $Extra ;
    public $Autres ;
	
    /**
     * Serialise l'objet en JSON
     * @return string|false 
     * @throws Throwable 
     */
    public function ToJSON(){
        try {
            return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public static function LASTJSON_ERROR(){
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return ' - No errors';
            break;
            case JSON_ERROR_DEPTH:
                return  ' - Maximum stack depth exceeded';
            break;
            case JSON_ERROR_STATE_MISMATCH:
                return  ' - Underflow or the modes mismatch';
            break;
            case JSON_ERROR_CTRL_CHAR:
                return  ' - Unexpected control character found';
            break;
            case JSON_ERROR_SYNTAX:
                return  ' - Syntax error, malformed JSON';
            break;
            case JSON_ERROR_UTF8:
                return  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
            default:
                return  ' - Unknown error';
            break;
        }

        return  PHP_EOL;
    }

    /**
     * Renvoie la reponse directement au client connecté.
     * @param bool $SendAndExit : Si Oui, l'opération arrête le traitement du script complet après l'envoie de la réponse
     * @return bool 
     * @throws Throwable 
     */
    public function SendAsJSON(bool $SendAndExit = true):bool{
        try {
            echo json_encode($this);
            if($SendAndExit){
                exit;
            }
           return true;
        } catch (\Throwable $th) {
            N::$Log->Write(__FILE__." Ligne ".__LINE__.": Impossible d'envoyer la réponse json. Erreur: ".$th->getMessage());
            if(N::$ActiveDebug){
                throw $th;
            }else{
                $Erreur=new xErreur();
                $Erreur->TxErreur="ERREUR SYSTEME. Contactez le support technique svp.";
                echo json_encode($Erreur);
            }
            if($SendAndExit){
                exit;
            }
            return false;
        }
    }
        
}
