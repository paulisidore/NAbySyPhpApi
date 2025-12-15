<?php
/**
 * @file rAuth.class.php
 * Contains Authentification URL based Router Controller for NAbySyGS
 * Author: Paul Isidore A. NIAMIE
 * Mail: direction@groupe-pam.net
 * Date: 06/Oct/2025 22:34:48
 * Version: 1.0.0
 */
use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\xAuth;
use NAbySy\xNotification;
use NAbySy\xUser;

/**
 * Routed Request Manager for PRODUIT
 * @package 
 */
class rAuth extends xNAbySyUrlRouterHelper {
    
    public function __construct(string $routerName, string $FriendlyName='Route d\'Authentification', string $Description='Permet la Gestion de l\'authentification des utilisateurs') {
        $fileSource = __FILE__ ;
        parent::__construct($routerName, $fileSource, $FriendlyName, $Description);
        $this->setupRoute();
    }

    private function setupRoute() {
        /**Process to Authentification*/
        xAuth::$ColonneToIgnore = ['password', 'CanUseMod_%', 'ACCES_BOUTIQUE_%', 'DebugSelect', 'derniere_connexion', 'connexion']; 
        
        $this->get('/auth', function() {
            N::$NO_AUTH = true ;
            $Rep = new xNotification();
            $Rep->OK=0;
            $Rep->Contenue = [];
            N::$Log->AddToLog(__FILE__." L".__LINE__." Authentification en cour...") ;
            include_once 'auth.php';
        });

        $this->post('/auth', function() {
            N::$NO_AUTH = true ;
            $Rep = new xNotification();
            $Rep->OK=0;
            $Rep->Contenue = [];
            N::$Log->AddToLog(__FILE__." L".__LINE__." Authentification en cour...") ;
            include_once 'auth.php';
        });

        //On va en profiter pour ajouter les chemins pour les autres tâches administratives
        $this->get("/api/describe", function(){
            N::$NO_AUTH=false;
            $_REQUEST['Action']="USERAPI_DESCRIBE_URL_API_ROUTE" ;
            include_once 'gs/userapi/userapi_action.php';
        });

        $this->any("/api/describe/html", function(){
            N::$NO_AUTH=false;
            $_REQUEST['Action']="USERAPI_DESCRIBE_URL_API_ROUTE" ;
            $_REQUEST['HTML']=1;
            include_once 'gs/userapi/userapi_action.php';
        });

        $this->any("/api/describe/doauth", function(){
            $Login=null;
            $Password=null;
            $Token=null;

            $nabysy = N::getInstance();
            if($_REQUEST['LOGIN']){
                $Login = $_REQUEST['LOGIN'] ;
            }
            if($_REQUEST['PASSWORD']){
                $Password = $_REQUEST['PASSWORD'] ;
            }
            $User=new xUser($nabysy,null,$nabysy::GLOBAL_AUTO_CREATE_DBTABLE,null,$Login) ;
            if ($User->CheckPassword($Password)){
                $Token=$User->GetToken();
            }
            N::$NO_AUTH=false;
            $_REQUEST['Action']="USERAPI_DESCRIBE_URL_API_ROUTE" ;
            $_REQUEST['HTML']=1;
            if(isset($Token)){
                $_REQUEST['Token']=$Token;
            }
            include_once 'gs/userapi/userapi_action.php';
        });
    }
}
?>