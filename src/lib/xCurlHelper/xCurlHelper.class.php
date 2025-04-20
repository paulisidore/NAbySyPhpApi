<?php
namespace NAbySy\Lib\Http ;

use xErreur;
use NAbySy\xNAbySyGS;

/**
 * Ce module permet de faire des appel API vers des ressources distantes.
 */
class xCurlHelper implements ICurlHelper {

    public static xNAbySyGS $Main ;
    
    public function __construct(xNAbySyGS $NAbySyGS){
        self::$Main=$NAbySyGS ;
    }

    /**
         * Fonction permettant l'envoie de requette à un serveur web distant
         * @param string $url : le site internet ciblé
         * @param array $ListeParametre : liste des paramètres a envoiyer sous la forme de tableau de pair de donnée
         * @param array $Headers : Liste d'éventuel paramètre header
         * @param CURLOPT_POST |null Envoie les données via le paramètre POST au lieu de GET
         * exemple: array('name' => 'Robert', 'id' => '1')
         * 
         * @return string : reponse obtenue du serveur.
         */
        public  function EnvoieRequette($url, $ListeParametre=[],array $Headers=null,$Method=CURLOPT_POST,$BodyData=''):string{
            
            $precTimeLimit=ini_get('max_execution_time');;
            //set_time_limit(0);
            $postdata=null;
            $ch = curl_init() ;
            if (isset($ListeParametre)){
                if (count($ListeParametre)){
                    $postdata = http_build_query($ListeParametre);
                }
            }

            //var_dump($url);
            //exit;
            curl_setopt($ch,CURLOPT_URL, $url);
            if ($Method==CURLOPT_POST){
                curl_setopt($ch,CURLOPT_POST, true);
                if ($BodyData==''){
                    if (isset($postdata)){
                        $BodyData=$postdata;
                        //var_dump($url);
                        //var_dump($postdata);
                    }
                }
                curl_setopt($ch,CURLOPT_POSTFIELDS, $BodyData);
            }else{
                if (isset($ListeParametre)){
                    if (count($ListeParametre)){
                        $postdata = http_build_query($ListeParametre);
                        $url .= "?" . $postdata;
                    }
                }
                curl_setopt($ch,CURLOPT_HTTPGET, true);
            }
            //exit;
            
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

            if (isset($Headers)){
                curl_setopt($ch, CURLOPT_HTTPHEADER,$Headers);
            }

            //Delais pour l'établissement de la connexion (20sec)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); 

            //Delais maximum du script CURL dans sa globalité (2mn)
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $NAbySyVersion='NAbySy/'.self::$Main->MODULE->Version ;
            curl_setopt($ch, CURLOPT_USERAGENT, $NAbySyVersion);

            curl_setopt($ch,CURLINFO_HEADER_OUT,true);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            //var_dump($ch);
            //exit;
            $result = curl_exec($ch);
            //set_time_limit($precTimeLimit);
            //var_dump($result);
            $EnteteEnvoie=curl_getinfo($ch,CURLINFO_HEADER_OUT ) ;
            //var_dump($EnteteEnvoie);
            //var_dump($BodyData);
            if (curl_errno($ch)) {
                $Err=new xErreur;
                $Err->OK=0;
                $Err->TxErreur=curl_error($ch);
                $Err->Source=__FILE__ ;
                $result = json_encode($Err);
            }else{
                $info = curl_getinfo($ch);
                //var_dump($info);
            }
            //var_dump($result);
            return $result ;
        }
}

?>