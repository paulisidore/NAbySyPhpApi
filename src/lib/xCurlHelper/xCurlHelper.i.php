<?php
namespace NAbySy\Lib\Http ;

include_once 'xCurlHelper.class.php';
interface ICurlHelper {
/**
 * Effectue un appel vers une ressource HTTP distante
 */
    public function EnvoieRequette($url, $ListeParametre=[],array $Headers=null,$Method=CURLOPT_POST,$BodyData=''):string ;
}
?>