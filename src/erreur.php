<?php

/* 
 * Application developpé par Paul Isidore A. NIAMIE
 * Each line should be prefixed with  * 
 */
namespace NAbySy ;

use Throwable;

Class xErreur
{
	public $OK;
	public $TxErreur;
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
            return json_encode($this);
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
        
}
