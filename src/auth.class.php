<?php
namespace NAbySy ;
require __DIR__.'/vendor/autoload.php';

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

    public static string $lastToken = '';

    public function __construct(xNAbySyGS $nabysy,$duree_exp_seconde=3600, xUser $User=null){
        $token = self::extractFromHeader();
        if (!isset($_REQUEST['Token']) && is_string($token) && $token !=="" ){
            $_REQUEST['Token'] = $token ;
        }

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

        if(isset($User)){
            self::$lastToken = $this->GetToken($User) ;
        }
    }

    public function GetToken(xUser &$User,$Algo='HS256'):string{
        if (isset($User)){
        }else{
            //echo "<br>Utilisateur Null ici: ".__FILE__." Ligne ".__LINE__."</br>";
            return '';
        }
        if (trim($User->Etat) ==''){
            $User->Etat='Actif' ;
        }
        if ($User->BLOQUE=='OUI' && strtoupper($User->Etat ) !== 'ACTIF' && strtoupper($User->Etat ) !== 'A' ){
            return '';
        }
        $dateexp=time();
        $IdPoste=0;
        $NomPoste=$_SERVER['SERVER_NAME'];
        if(isset($_SERVER['REMOTE_HOST'])){
            $NomPoste=$_SERVER['REMOTE_HOST'] ;
        }
        
        if ((int)$this->Main->IdPosteClient != 0){
            $IdPoste=(int)$this->Main->IdPosteClient;
        }
        if (trim($this->Main->NomPosteClient) !=='' ){
            $NomPoste=$this->Main->NomPosteClient;
        }

        $IdBout=0;
        if (isset($this->Main->MaBoutique)){
            $IdBout=(int)$this->Main->MaBoutique->Id;
        }

        $this->Payload = array(
            "pam_application" => $this->Main->MODULE->Nom,
            "pam_client" => $this->Main->MODULE->MCP_CLIENT,
            "client_adr" => $this->Main->MODULE->MCP_ADRESSECLT,
            "client_tel" => $this->Main->MODULE->MCP_TELCLT,
            "boutique_id" => $IdBout,
            "IdBoutique" => $IdBout,
            "IdPoste" => $IdPoste,
            "NomPoste" => $NomPoste,
            "user_id" => $User->Id,
            "user_login" => $User->Login,
            "user_data" => json_encode($User->ToObject()),
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

    /**
     * Extrait le token du header Authorization
     * 
     * @return string|null Token ou null si absent
     */
    public static function extractFromHeader(): ?string {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $auth = $headers['Authorization'];
        
        // Format attendu : "Bearer TOKEN"
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
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