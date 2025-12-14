<?php
/**
 * Classe xGSUrlRouterManager
 * 
 * Gestionnaire des route basées sur l'URL.
 * 
 * @package NAbySy\Router\Url
 * @author Paul Isidore
 * @version 1.0.0
 */

namespace NAbySy\Router\Url;
include_once "nabysyurlrouter.i.php";
include_once "nabysyurlrouter.class.php";
include_once "rAuth.class.php";

use Exception;
use NAbySy\xGSModuleCategory;
use NAbySy\xNAbySyGS;
use NAbySy\xNotification;
use rAuth;

class xGSUrlRouterManager{
    /**
    * Active le debbuguage dans le fichier log de l'application hôte
    * @var bool
    */
   public static bool $DebugToLog = false ;
   public static xNAbySyGS $Main ;

   /**
    * Tous les fichiers contenant les définitions de route doivent se trouver dans
    * un sous dossier gs et avoir cet extention à sa fin
    */
   public const ROUTER_FILE_EXT = '.route.php' ;
    
    /**
     * Retient la liste des différentes routes configurées
     * @var xNAbySyUrlRouterHelper[]
     */
    private static array $ListeRouter = [];

    private string $HostFolder = '' ;

    public function __construct(xNAbySyGS $NAbySy){
        //Chargement de la liste des dossier catégories
        self::$Main = $NAbySy;
        self::$ListeRouter = [];

        //On va ajouter la route vers le service d'authetification de NAbySyGS
        $RouteAuth = new rAuth("NAbySyAuthRouter");
        self::$ListeRouter[] = $RouteAuth ;


        $dossierGs= self::$Main::ModuleGSHostFolder().DIRECTORY_SEPARATOR ;
        //echo "Fichier ".__FILE__." L ".__LINE__.": Repertoir GS => ".$dossierGs."</br>" ;
        $this->HostFolder=$dossierGs;
        //On va parcourir tous les sous dossiers du dossier Gs de l'application Hote pour récuprer les routes définits
        $rep=scandir($dossierGs) ;
        if(count($rep)>0){
            foreach ($rep as $key => $value) {
                //On ne prend pas en compte les fichiers spéciaux . et ..
                //echo "<br>Dossier : ".$dossierGs.$value." ? ".is_dir($dossierGs.$value)."</br>" ;
                if ($value != '.' && $value != '..' && is_dir($dossierGs.$value)){
                    //echo "<br>Dossier : ".$dossierGs.$value." ? ".is_dir($dossierGs.$value)."</br>" ;
                    $cat=new xGSModuleCategory( $value,  $dossierGs.$value.DIRECTORY_SEPARATOR) ;
                    //Pour chaque catégorie on y ajoute la liste de ses modules
                    $repModule=scandir($cat->Dossier) ;
                    if(count($repModule)>0){
                        foreach ($repModule as $key => $value) {
                            $dos_cat = $cat->Dossier.$value ;
                            //echo "<br>Fichier : ".$value."</br>";
                            if ($value != '.' && $value != '..' && !is_dir($dos_cat)){
                                $value = $dos_cat ;
                                //echo "<br>Fichier php: ".$value."</br>";
                                //echo "<br>Liste sous Dossier cat: ".var_dump($lstMod)."</br>";
                                    //C'est un fichier, on vérifie s'il s'agit d'un module NAbySyUrlRouter
                                if ($value != '.' && $value != '..'){
                                    if(str_contains($value,self::ROUTER_FILE_EXT)){
                                        $vpath=explode(DIRECTORY_SEPARATOR,$value);
                                        $cheminFichier=$value;
                                        if(count($vpath)>0){
                                            $value=$vpath[count($vpath)-1];
                                            //echo __FILE__." L ".__LINE__.": Fichier Router => ".$value."</br>" ;
                                            $exp = explode(".",$value);
                                            if(count($exp)>0 && str_contains($value,self::ROUTER_FILE_EXT)){
                                                $routerName = str_replace(self::ROUTER_FILE_EXT,"",$value) ;
                                                 $NewClassModule= $routerName ;
                                                if(substr(strtolower($routerName),0,1) !== 'r'){
                                                    $NewClassModule= 'r'.self::$Main::toCamelCase($routerName);
                                                }else{
                                                    if(substr($routerName,0,1)=='R'){
                                                        $routerName=substr($routerName,1);
                                                        $NewClassModule= 'r'.self::$Main::toCamelCase($routerName);
                                                    }
                                                }

                                                include_once $cheminFichier ;
                                                $finalName=$routerName."_".$cat->Nom;
                                                $PrecRoute=self::getRouteByName($routerName);
                                                if(!isset($PrecRoute)){
                                                    $ModClass=new $NewClassModule($finalName, $cheminFichier) ;
                                                    if($ModClass instanceof xNAbySyUrlRouterHelper){
                                                        self::$ListeRouter[] = $ModClass ;
                                                    }
                                                }else{
                                                    throw new Exception("La route ".$routerName." existe déjà dans ".$PrecRoute->SourceFile(), 1);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Nombre de route disponible
     * @return int 
     */
    public static function count():int{
        return count(self::$ListeRouter);
    }

    /**
     * Retourne un Router de part son nom
     * @param string $routerName 
     * @return xNAbySyUrlRouterHelper|null 
     */
    public static function getRouteByName(string $routerName):xNAbySyUrlRouterHelper | null{
        if(self::count()==0){return null;}
        foreach (self::$ListeRouter as $Router) {
            if($Router->routeName()== $routerName){
                return $Router ;
                break;
            }
        }
        return null;
    }

    /**
     * Traite éventuellement les routes URL et leurs paramètres. Si aucune route définit, la fonction retourne une reponse
     * xGSUrlRouterResponse ou envoie directement la reponse si CanSendReponse est VRAI.
     * @param bool $CanSendReponse | ATTENTION: Si CanSendReponse est Vrai, Assurez vous de traiter les routes NAbySyGS avant cette routine.
     * @return xGSUrlRouterResponse 
     */
    public static function resolveUrlRoute(bool $CanSendReponse=false):xGSUrlRouterResponse{
        $Rep=new xGSUrlRouterResponse();
        $Rep->OK=0;
        // Récupérer l'URL demandée
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);

        // Retirer le chemin du script de l'URI
        if ($scriptName !== '/') {
            $requestUri = substr($requestUri, strlen($scriptName));
        }

        // Retirer la query string
        $requestUri = strtok($requestUri, '?');

        // Récupérer la méthode HTTP
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        // Permettre l'override de la méthode via _method dans POST
        if ($requestMethod === 'POST' && isset($_POST['_method'])) {
            $requestMethod = strtoupper($_POST['_method']);
        }

        $route=null;
        $GoodRouter=null;
        foreach (self::$ListeRouter as $router){
            // Résoudre la route
            $route = $router->resolve($requestUri, $requestMethod);
            if (isset($route)) {
                $GoodRouter = $router ;
                //Route définit
                break;
            }
            //var_dump($router->getRegisteredRoute());
        }
        if ($route === null && !self::$Main::$ByPasseNoUrlRoute) {
            $Rep->TxErreur='Aucune route trouvée';
            $Rep->Path = $requestUri ;
            $Rep->Methode = $requestMethod ;
            if($CanSendReponse){
                // Définir les headers de réponse
                header('Content-Type: application/json; charset=utf-8');
                self::$Main::AllowCORS();
                // Route non trouvée
                http_response_code(404);
                echo json_encode($Rep);
                exit;
            }
            return $Rep;
        }

        if($GoodRouter){
            $Rep->OK=1;
            $Rep->Source=$GoodRouter;
            $Rep->Extra=$route ;
            $Rep->Path = $requestUri ;
            $Rep->Methode = $requestMethod ;
            $Rep->Router=$GoodRouter ;
            $Rep->route=$route ;
            if($CanSendReponse){
                $Rep->Contenue = self::runRouteHandler($Rep->Router, $Rep->route);
                //echo json_encode($Rep);
                //exit;
            }
        }
        return $Rep;
    }

    /**
     * Execute le module de routage URL
     * @param xNAbySyUrlRouterHelper $router 
     * @param array $route 
     * @return xNotification 
     * @throws Exception 
     */
    public static function runRouteHandler(xNAbySyUrlRouterHelper $router, array $route): xNotification{
        $Rep=new xNotification();
        $Rep->OK=0;
        $Rep->TxErreur="Router ".$router->routeName()." en cour d'execution..." ;
        try {
            // Exécuter les middlewares si nécessaire
            if (!empty($route['middlewares'])) {
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        $Rep->TxErreur="Router Middlwere ".$router->routeName()." en cour d'execution..." ;
                        $middleware();
                    }
                }
            }

            // Exécuter le handler de la route
            $response = $router->executeHandler($route['handler'], $route['params']);
            
            // Afficher la réponse
            self::$Main::AllowCORS();
            http_response_code(200);
            $Rep->TxErreur="" ;

            if (is_string($response)) {
                echo $response;
                $Rep->Contenue = $response ;
            } else {
                echo json_encode($response);
                $Rep->Contenue = json_encode($response) ;
            }
            $Rep->OK=1;
            $Rep->TxErreur=null;
            

        } catch (\Throwable $e) {
            // Gestion des erreurs
            http_response_code(500);
            $Rep->TxErreur="Erreur d'execution du Router ".$router->routeName().": ".$e->getMessage() ;
            echo json_encode($Rep);
            self::$Main::$Log->Write($Rep->TxErreur." Fichier ".$e->getFile()." Ligne ".$e->getLine());
            return $Rep;
        }
        return $Rep ;
    }

    
}


?>