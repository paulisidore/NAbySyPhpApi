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

    /**
     * Liste des colonnes à ignorer avant l'envoie des données de l'utilisateur authentifié
     * @var array
     */
    public static array $ColonneToIgnore = []; // ['password', 'CanUseMod_%', 'ACCES_BOUTIQUE_%', 'DebugSelect', 'derniere_connexion', 'connexion']; 

    public function __construct(xNAbySyGS $nabysy,$duree_exp_seconde=3600, xUser $User=null, string $key="nabysygscloud", string $Algo='HS256'){
        $token = self::extractFromHeader();
        if (!isset($_REQUEST['Token']) && is_string($token) && $token !=="" ){
            $_REQUEST['Token'] = $token ;
        }
        if(!isset($User) && isset($_REQUEST['Token']) && $_REQUEST['Token'] !==''){
            //Il ya un token, on va changer la boutique en fonction
            try {
                $this->Key = $key;
                $decoded = $this->DecodeToken($_REQUEST['Token'], $Algo);
                if(isset($decoded) && is_object($decoded)){
                    $nabysy->UserConnectedByToken = true;
                    //var_dump($decoded);exit;
                    if(isset($decoded->IdBoutique) && (int)$decoded->IdBoutique>0){
                        $BoutToken=$nabysy->GetBoutiqueByID((int)$decoded->IdBoutique);
                        $BoutEnCour = $BoutToken;
                        if($BoutEnCour && $BoutEnCour->Id>0){
                            xNAbySyGS::$Main->MaBoutique = $BoutEnCour ;
                            xNAbySyGS::getInstance()->MaBoutique = $BoutEnCour ;
                        }
                        if($BoutEnCour && $BoutEnCour->Id>0 && $BoutEnCour->Id !== $nabysy->MaBoutique->Id){
                            $nabysy->SelectBoutique($BoutEnCour->Id);
                            if($nabysy->MaBoutique->Id !== $decoded->IdBoutique){
                                $nabysy->MaBoutique = $BoutEnCour ;
                            }
                        }
                    }
                }
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        $this->Main=$nabysy ;
        $this->Key = $key;
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
        $idTechnoWEB=null;
        if(isset($_REQUEST['IDTECHNOWEB']) && trim($_REQUEST['IDTECHNOWEB'])!==''){
            $idTechnoWEB = trim($_REQUEST['IDTECHNOWEB']);
        }

        $this->Payload = array(
            "pam_application" => $this->Main->MODULE->Nom,
            "pam_client" => $this->Main->MODULE->MCP_CLIENT,
            "client_adr" => $this->Main->MODULE->MCP_ADRESSECLT,
            "client_tel" => $this->Main->MODULE->MCP_TELCLT,
            "boutique_id" => $IdBout,
            "IdBoutique" => $IdBout,
            "IDTECHNOWEB" => $idTechnoWEB ?? '',
            "IdPoste" => $IdPoste,
            "NomPoste" => $NomPoste,
            "user_id" => $User->Id,
            "user_login" => $User->Login,
            "role" => $User->Profile ?? 'Utilisateur',
            "user_data" => json_encode($User->ToObject()),
            "iss" => "https://groupe-pam.net",
            "aud" => "https://groupe-pam.net",
            "iat" => $dateexp,
            "nbf" => $dateexp,
            "exp" => $dateexp+$this->DureeVieSecode ,
            "Author" => $this->Main->MODULE->MCP_CLIENT,
            "Special" => $this->Key
        );
        
        $jwt=JWT::encode($this->Payload,$this->Key,$Algo) ;
        return $jwt ;
    }

    public function DecodeToken(string $JWT_TOKEN,$Algo='HS256', bool $NoRetournError=true, ?xErreur $LastTokenErr = null){
        $decoded=null;
        
        if (isset($JWT_TOKEN)){
            try{
                xNAbySyGS::$Log->AddToLog('Token non décodé: '. $JWT_TOKEN);
                //echo $JWT_TOKEN ;exit;
                //echo __FILE__." Key =". $this->Key ;exit;
                $decoded = JWT::decode($JWT_TOKEN, $this->Key, array($Algo));
                //var_dump($decoded);//exit;
                xNAbySyGS::$Log->AddToLog("Reponse décodage Token reçu: ".json_encode($decoded));
                if (isset($decoded->user_data) && is_string($decoded->user_data)){
                    xNAbySyGS::$Log->AddToLog('User_Data était un string, on va le décoder ici ... ');
                    $decoded->user_data=json_decode($decoded->user_data);
                }else{
                    //var_dump($decoded);
                    //var_dump($decoded->user_data);
                    $Err = new xErreur;
                    $Err->TxErreur = "Token invalid ou erreur de signature.";
                    $LastTokenErr = $Err ;
                    $Err->SendAsJSON();
                    if(!xNAbySyGS::isRunFromCLI()){
                        die();
                    }
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
                    $LastTokenErr = $Err;
                    $decoded=$Err ; //"EXPIRE" ;
                    if (!$NoRetournError){
                        if(!xNAbySyGS::isRunFromCLI()){
                            http_response_code(401); 
                        }
                        $this->Main->AllowCORS();
                        $Err->SendAsJSON();
                        if(!xNAbySyGS::isRunFromCLI()){
                            exit ;
                        }
                    } 
                
                } else {
                
                    // set response code
                    if(!xNAbySyGS::isRunFromCLI()){
                        http_response_code(401);
                    }
                
                    // show error message
                    xNAbySyGS::AllowCORS();
                    $Err=new xErreur ;
                    $Err->TxErreur="Accès refusé" ;
                    $Err->Autres = $e->getMessage();
                    $Err->OK=0;
                    $Err->Source="auth.class" ;
                    if(xNAbySyGS::$LogLevel>3){
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS , 3);
                        $Err->Source=$trace ;
                    }
                    $LastTokenErr=$Err;
                    $Err->SendAsJSON();
                    if(!xNAbySyGS::isRunFromCLI()){
                        die();
                    }
                }
            }
        }
        return $decoded ;
    }

    /**
     * Générère un token à partir de la charge personnalisée
     * @param string $Key 
     * @param array $PayLoad 
     * @param string $Algo 
     * @return string 
     */
    public static function GetTokenFromPayLoad(array $PayLoad, ?string $Key = "nabysygscloud",?string $Algo='HS256'):string{
        if (!is_array($PayLoad)){
            return '';
        }
        if (count($PayLoad) == 0){
            return '';
        }
        if(!isset($Key)){
            $Key = "nabysygscloud";
        }
        if(!isset($Algo)){
            $Algo='HS256';
        }
        $dateexp=time();
        
        $jwt=JWT::encode($PayLoad,$Key,$Algo) ;
        return $jwt ;
    }

    /**
     * Extrait le token du header Authorization
     * Dans le Headers placez:
     *  Authorization = "Bearer TOKEN"
     * @return string|null Token ou null si absent
     */
    public static function extractFromHeader(): ?string {
        if (xNAbySyGS::isRunFromCLI()){
			return null ;
		}
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $auth = $headers['Authorization'];
        
        // Format attendu : "Bearer TOKEN"
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            if($matches[1] && trim($matches[1]) !=='' && !isset($_REQUEST['Token'])){
                $_REQUEST['Token'] = trim($matches[1]) ;
            }
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