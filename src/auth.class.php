<?php
namespace NAbySy ;

use Exception;
use Firebase\JWT\JWT;
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;
use NAbySy\xUser;

Class xAuth
{
    public xNAbySyGS $Main ;
    public xBoutique $Boutique ;
    public $Key ;
    public $Payload ;
    public int $DureeVieSecode ;

    public function __construct(xNAbySyGS $nabysy,$duree_exp_seconde=3600){
        $this->Main=$nabysy ;
        $this->Key = $nabysy->MasterDataBase;
        $dateexp=time();
        $this->DureeVieSecode=$duree_exp_seconde ;
        $this->Payload = array(
            "pam_application" => $nabysy->MODULE->Nom,
            "pam_client" => $nabysy->MODULE->MCP_CLIENT,
            "client_adr" => $nabysy->MODULE->MCP_ADRESSECLT,
            "client_tel" => $nabysy->MODULE->MCP_TELCLT,
            "iss" => "https://groupe-pam.net",
            "aud" => "https://groupe-pam.net",
            "iat" => $dateexp,
            "nbf" => 1648173206,
            "exp" => $dateexp+$duree_exp_seconde,
            "Author" => "Paul Isidore A. NIAMIE"
        );
        var_dump(JWT::encode($this->Payload,$this->Key)) ;
    }

    public function GetToken(xUser &$User,$Algo='HS256'){
        if (!isset($User)){
        }else{
            echo "<br>Utilisateur Null ici: ".__FILE__." Ligne ".__LINE__."</br>";
        }
        if ($User->BLOQUE=='OUI' || strtoupper($User->Etat ) !== 'ACTIF' ){
            return '';
        }
        $dateexp=time();
        $IdPoste=0;
        $NomPoste="SERVEUR";
        if (isset($this->Main->IdPosteClient)){
            $IdPoste=(int)$this->Main->IdPosteClient;
            $NomPoste=$this->Main->NomPosteClient;
        }
        $this->Payload = array(
            "pam_application" => $this->Main->MODULE->Nom,
            "pam_client" => $this->Main->MODULE->MCP_CLIENT,
            "client_adr" => $this->Main->MODULE->MCP_ADRESSECLT,
            "client_tel" => $this->Main->MODULE->MCP_TELCLT,
            "boutique_id" => $this->Main->MaBoutique->Id,
            "IdBoutique" => $this->Main->MaBoutique->Id,
            "IdPoste" => $IdPoste,
            "NomPoste" => $NomPoste,
            "user_id" => $User->Id,
            "user_login" => $User->Login,
            "user_data" => json_encode($User->RS),
            "iss" => "https://groupe-pam.net",
            "aud" => "https://groupe-pam.net",
            "iat" => $dateexp,
            "nbf" => $dateexp,
            "exp" => $dateexp+$this->DureeVieSecode ,
            "Author" => $this->Main->MODULE->MCP_CLIENT
        );
        
        $jwt=JWT::encode($this->Payload,$this->Key,$Algo) ;
        return $jwt ;
    }

    public function DecodeToken($JWT_TOKEN,$Algo='HS256',$NoRetournError=true){
        $decoded=null;
        if (isset($JWT_TOKEN)){
            try{
                $decoded = JWT::decode($JWT_TOKEN, $this->Key, array($Algo));
                //var_dump($decoded);
                if (!isset($decoded->user_data)){
                    $decoded->user_data=json_decode($decoded->user_data);
                }else{
                    //var_dump($decoded->user_data);
                }
            }
            catch (Exception $e){

                if($e->getMessage() == "Expired token"){
                    list($header, $payload, $signature) = explode(".", $JWT_TOKEN);
                    $payload = json_decode(base64_decode($payload));
                    //$refresh_token = $payload->data->refresh_token;
                    //print_r($payload->user_data) ;
                    $Err=new xErreur ;
                    $Err->TxErreur="(ERR:SESSION_EXP) Votre session a expirée" ;
                    $Err->OK=0;
                    $Err->Source="auth.class.php" ;
                    $Err->Extra="Reconnectez-vous svp." ;
                    $Reponse=json_encode($Err) ;
                    //echo $Reponse ;
                    $decoded=$Err ; //"EXPIRE" ;
                    if (!$NoRetournError){
                        http_response_code(419); 
                        exit ;
                    } 
                
                } else {
                
                    // set response code
                    http_response_code(401);
                
                    // show error message
                    echo json_encode(array(
                        "message" => "Access denied.",
                        "error" => $e->getMessage()
                    ));
                    die();
                }
            }
        }

        return $decoded ;
    }

    public function EnteteAPI(){
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Max-Age: 3600");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        return true ;
    }

}



?>