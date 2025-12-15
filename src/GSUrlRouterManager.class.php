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
include_once "rAuth.route.php";

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
     * Retourne la liste des routes inscrites sous forme de tableau
     * @return array 
     */
    public static function getRegistredRoute():array{
        $Liste=[];
        foreach (self::$ListeRouter as $route) {
            $detail = $route->getRegisteredRoute();
            $nom = $route->routeName();
            $item['routeName']=$nom;
            foreach ($detail as $methode) {
                if(is_array($methode)){
                    foreach ($methode as $vroute) {
                        unset($vroute['regex']);
                        unset($vroute['handler']);
                    }
                }
            }
            $item['registeredRoute'] = $detail;
            $Liste[]=$item ;
        }
        return $Liste;
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

    /**
     * Génère une page HTML interactive pour visualiser et documenter les routes de l'API
     * Version 2 - Adaptée au nouveau format JSON avec friendlyName et description
     * 
     * @param string $jsonRoutes Le JSON contenant les routes
     * @return string Le HTML complet de la page
     */
    public static function generateRoutesDocumentationPage($jsonRoutes) {
        // Vérifier l'authentification
        $isAuthenticated = false;
        
        if (isset(self::$Main->User) && isset(self::$Main->User->Id) && self::$Main->User->Id > 0) {
            $isAuthenticated = true;
        }
        
        // Si pas authentifié, afficher le formulaire de login
        if (!$isAuthenticated) {
            return self::generateLoginPage();
        }
        
        // Décoder le JSON
        $data = json_decode($jsonRoutes, true);
        if($data == false){
            return "<html><body><h1>Erreur: JSON invalide: ".json_last_error_msg()."</h1></body></html>";
        }
        if (!$data || !isset($data['Contenue'])) {
            return "<html><body><h1>Erreur: JSON invalide</h1></body></html>";
        }
        
            // Décoder le JSON
        $data = json_decode($jsonRoutes, true);
        
        if (!$data || !isset($data['Contenue'])) {
            return "<html><body><h1>Erreur: JSON invalide</h1></body></html>";
        }
        
        $routes = $data['Contenue'];
        
        // Définir les méthodes HTTP dans l'ordre du tableau registeredRoute
        $httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'ANY'];
        
        // Calculer les statistiques
        $totalRoutes = 0;
        $methodCounts = ['GET' => 0, 'POST' => 0, 'PUT' => 0, 'DELETE' => 0, 'PATCH' => 0, 'ANY' => 0];
        
        foreach ($routes as $router) {
            foreach ($router['registeredRoute'] as $methodIndex => $routesList) {
                $count = count($routesList);
                $totalRoutes += $count;
                $method = $httpMethods[$methodIndex];
                $methodCounts[$method] += $count;
            }
        }
        
        $currentDate = date('d/m/Y à H:i');
        
        // Début du HTML
        $html = '<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Documentation des Routes API</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        padding: 20px;
                        min-height: 100vh;
                    }
                    
                    .container {
                        max-width: 1400px;
                        margin: 0 auto;
                        background: white;
                        border-radius: 15px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        overflow: hidden;
                    }
                    
                    .header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 30px;
                        text-align: center;
                    }
                    
                    .header h1 {
                        font-size: 2.5em;
                        margin-bottom: 10px;
                    }
                    
                    .header p {
                        font-size: 1.1em;
                        opacity: 0.9;
                    }
                    
                    .toolbar {
                        background: #f8f9fa;
                        padding: 15px 30px;
                        border-bottom: 2px solid #e9ecef;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: 10px;
                    }
                    
                    .btn {
                        padding: 10px 20px;
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 600;
                        transition: all 0.3s;
                    }
                    
                    .btn-primary {
                        background: #667eea;
                        color: white;
                    }
                    
                    .btn-primary:hover {
                        background: #5568d3;
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                    }
                    
                    .btn-success {
                        background: #51cf66;
                        color: white;
                    }
                    
                    .btn-success:hover {
                        background: #40c057;
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(81, 207, 102, 0.4);
                    }
                    
                    .btn-info {
                        background: #339af0;
                        color: white;
                    }
                    
                    .btn-info:hover {
                        background: #228be6;
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(51, 154, 240, 0.4);
                    }
                    
                    .file-input-wrapper {
                        position: relative;
                        overflow: hidden;
                        display: inline-block;
                    }
                    
                    .file-input-wrapper input[type=file] {
                        position: absolute;
                        left: -9999px;
                    }
                    
                    .content {
                        padding: 30px;
                    }
                    
                    .router-group {
                        margin-bottom: 40px;
                        border: 2px solid #e9ecef;
                        border-radius: 10px;
                        overflow: hidden;
                        background: white;
                    }
                    
                    .router-header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 20px;
                        cursor: pointer;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    
                    .router-header:hover {
                        opacity: 0.9;
                    }
                    
                    .router-title-section {
                        flex: 1;
                    }
                    
                    .router-friendly-name {
                        font-size: 1.5em;
                        font-weight: bold;
                        margin-bottom: 8px;
                    }
                    
                    .router-technical-name {
                        font-size: 0.85em;
                        opacity: 0.8;
                        font-family: \'Courier New\', monospace;
                        margin-bottom: 8px;
                    }
                    
                    .router-description {
                        font-size: 0.95em;
                        opacity: 0.9;
                        line-height: 1.4;
                    }
                    
                    .toggle-icon {
                        font-size: 1.5em;
                        transition: transform 0.3s;
                        margin-left: 20px;
                    }
                    
                    .router-group.collapsed .toggle-icon {
                        transform: rotate(-90deg);
                    }
                    
                    .router-body {
                        padding: 20px;
                        display: block;
                    }
                    
                    .router-group.collapsed .router-body {
                        display: none;
                    }
                    
                    .method-section {
                        margin-bottom: 25px;
                    }
                    
                    .method-title {
                        font-size: 1.2em;
                        font-weight: bold;
                        margin-bottom: 15px;
                        padding: 10px;
                        border-radius: 5px;
                        display: inline-block;
                    }
                    
                    .method-GET { background: #51cf66; color: white; }
                    .method-POST { background: #339af0; color: white; }
                    .method-PUT { background: #ff922b; color: white; }
                    .method-DELETE { background: #ff6b6b; color: white; }
                    .method-PATCH { background: #9775fa; color: white; }
                    .method-ANY { background: #868e96; color: white; }
                    
                    .route-item {
                        background: #f8f9fa;
                        border-left: 4px solid #667eea;
                        padding: 15px;
                        margin-bottom: 15px;
                        border-radius: 5px;
                        transition: all 0.3s;
                    }
                    
                    .route-item:hover {
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                        transform: translateX(5px);
                    }
                    
                    .route-pattern {
                        font-family: \'Courier New\', monospace;
                        font-size: 1.1em;
                        color: #495057;
                        margin-bottom: 10px;
                        font-weight: bold;
                    }
                    
                    .editable {
                        border: 2px dashed transparent;
                        padding: 8px;
                        border-radius: 4px;
                        transition: all 0.3s;
                        cursor: text;
                        display: inline-block;
                        min-width: 200px;
                    }
                    
                    .editable:hover {
                        background: #fff3cd;
                        border-color: #ffc107;
                    }
                    
                    .editable:focus {
                        outline: none;
                        background: white;
                        border-color: #667eea;
                        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                        color: #333;
                    }
                    
                    /* Style spécifique pour les editables dans le header */
                    .router-header .editable:focus {
                        background: rgba(255, 255, 255, 0.95);
                        color: #333;
                        border-color: #ffc107;
                        box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.3);
                    }
                    
                    .router-header .editable:hover {
                        background: rgba(255, 243, 205, 0.95);
                        border-color: #ffc107;
                        color: #333;
                    }
                    
                    .editable[contenteditable="true"]:empty:before {
                        content: attr(data-placeholder);
                        color: #adb5bd;
                        font-style: italic;
                    }
                    
                    .editable-block {
                        display: block;
                        width: 100%;
                    }
                    
                    .route-meta {
                        display: grid;
                        grid-template-columns: auto 1fr;
                        gap: 10px;
                        font-size: 0.95em;
                    }
                    
                    .meta-label {
                        font-weight: 600;
                        color: #495057;
                    }
                    
                    .meta-value {
                        color: #6c757d;
                    }
                    
                    .stats {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 30px;
                    }
                    
                    .stat-card {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 20px;
                        border-radius: 10px;
                        text-align: center;
                    }
                    
                    .stat-number {
                        font-size: 2.5em;
                        font-weight: bold;
                        margin-bottom: 5px;
                    }
                    
                    .stat-label {
                        font-size: 1em;
                        opacity: 0.9;
                    }
                    
                    .no-routes {
                        color: #868e96;
                        font-style: italic;
                        padding: 10px;
                    }
                    
                    /* Modal pour l\'importation */
                    .modal {
                        display: none;
                        position: fixed;
                        z-index: 1000;
                        left: 0;
                        top: 0;
                        width: 100%;
                        height: 100%;
                        overflow: auto;
                        background-color: rgba(0,0,0,0.5);
                    }
                    
                    .modal-content {
                        background-color: #fefefe;
                        margin: 10% auto;
                        padding: 30px;
                        border: 1px solid #888;
                        border-radius: 10px;
                        width: 80%;
                        max-width: 600px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    }
                    
                    .modal-header {
                        font-size: 1.5em;
                        font-weight: bold;
                        margin-bottom: 20px;
                        color: #667eea;
                    }
                    
                    .close {
                        color: #aaa;
                        float: right;
                        font-size: 28px;
                        font-weight: bold;
                        cursor: pointer;
                    }
                    
                    .close:hover,
                    .close:focus {
                        color: #000;
                    }
                    
                    .drop-zone {
                        border: 3px dashed #667eea;
                        border-radius: 10px;
                        padding: 40px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s;
                        background: #f8f9fa;
                        margin-bottom: 20px;
                    }
                    
                    .drop-zone:hover,
                    .drop-zone.dragover {
                        background: #e7f5ff;
                        border-color: #339af0;
                    }
                    
                    .drop-zone-icon {
                        font-size: 3em;
                        margin-bottom: 10px;
                    }
                    
                    .drop-zone-text {
                        font-size: 1.1em;
                        color: #495057;
                    }
                    
                    @media print {
                        body {
                            background: white;
                            padding: 0;
                        }
                        
                        .toolbar {
                            display: none !important;
                        }
                        
                        .toggle-icon {
                            display: none;
                        }
                        
                        .router-group {
                            page-break-inside: avoid;
                        }
                        
                        .router-body {
                            display: block !important;
                        }
                        
                        .editable {
                            border: none !important;
                            background: transparent !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>📚 Documentation des Routes API</h1>
                        <p>Cliquez sur les textes en jaune pour les modifier - Généré le ' . $currentDate . '</p>
                    </div>
                    
                    <div class="toolbar">
                        <div>
                            <button class="btn btn-primary" onclick="expandAll()">📖 Tout Développer</button>
                            <button class="btn btn-primary" onclick="collapseAll()">📕 Tout Réduire</button>
                        </div>
                        <div>
                            <button class="btn btn-info" onclick="openImportModal()">📥 Importer JSON</button>
                            <button class="btn btn-success" onclick="exportJSON()">💾 Exporter JSON</button>
                            <button class="btn btn-success" onclick="window.print()">🖨️ Imprimer / PDF</button>
                        </div>
                    </div>
                    
                    <div class="content">';
                
                // Afficher les statistiques
                $html .= '<div class="stats">';
                $html .= '<div class="stat-card"><div class="stat-number">' . count($routes) . '</div><div class="stat-label">Routeurs</div></div>';
                $html .= '<div class="stat-card"><div class="stat-number">' . $totalRoutes . '</div><div class="stat-label">Routes Totales</div></div>';
                $html .= '<div class="stat-card"><div class="stat-number">' . $methodCounts['GET'] . '</div><div class="stat-label">GET</div></div>';
                $html .= '<div class="stat-card"><div class="stat-number">' . $methodCounts['POST'] . '</div><div class="stat-label">POST</div></div>';
                $html .= '<div class="stat-card"><div class="stat-number">' . ($methodCounts['PUT'] + $methodCounts['DELETE']) . '</div><div class="stat-label">PUT/DELETE</div></div>';
                $html .= '</div>';
                
                // Générer chaque groupe de routeur
                foreach ($routes as $routerIndex => $router) {
                    $routerName = $router['routeName'];
                    $friendlyName = isset($router['friendlyName']) ? $router['friendlyName'] : $routerName;
                    $description = isset($router['description']) ? $router['description'] : '';
                    
                    $html .= '<div class="router-group" id="router-' . $routerIndex . '">';
                    $html .= '<div class="router-header" onclick="toggleRouter(' . $routerIndex . ')">';
                    $html .= '<div class="router-title-section">';
                    
                    // Nom convivial (modifiable)
                    $html .= '<div class="router-friendly-name editable editable-block" contenteditable="true" data-placeholder="Nom convivial du routeur" onclick="event.stopPropagation()">' 
                            . htmlspecialchars($friendlyName) . '</div>';
                    
                    // Nom technique (non modifiable)
                    $html .= '<div class="router-technical-name">Routeur technique: ' . htmlspecialchars($routerName) . '</div>';
                    
                    // Description (modifiable)
                    $html .= '<div class="router-description editable editable-block" contenteditable="true" data-placeholder="Ajouter une description du routeur..." onclick="event.stopPropagation()">' 
                            . htmlspecialchars($description) . '</div>';
                    
                    $html .= '</div>';
                    $html .= '<span class="toggle-icon">▼</span>';
                    $html .= '</div>';
                    $html .= '<div class="router-body">';
                    
                    // Parcourir chaque méthode HTTP selon l'index du tableau
                    foreach ($httpMethods as $methodIndex => $method) {
                        if (!empty($router['registeredRoute'][$methodIndex])) {
                            $html .= '<div class="method-section">';
                            $html .= '<div class="method-title method-' . $method . '">' . $method . '</div>';
                            
                            foreach ($router['registeredRoute'][$methodIndex] as $routeIndex => $route) {
                                $html .= '<div class="route-item">';
                                $html .= '<div class="route-pattern">' . htmlspecialchars($route['pattern']) . '</div>';
                                $html .= '<div class="route-meta">';
                                
                                // Nom personnalisé de la route
                                $html .= '<span class="meta-label">Nom personnalisé:</span>';
                                $routeName = isset($route['name']) && $route['name'] ? htmlspecialchars($route['name']) : '';
                                $html .= '<span class="editable" contenteditable="true" data-placeholder="Donner un nom à cette route...">' . $routeName . '</span>';
                                
                                // Description de la route
                                $html .= '<span class="meta-label">Description:</span>';
                                $html .= '<span class="editable" contenteditable="true" data-placeholder="Décrire l\'utilité de cette route..."></span>';
                                
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                            
                            $html .= '</div>';
                        }
                    }
                    
                    // Message si aucune route
                    $hasRoutes = false;
                    foreach ($router['registeredRoute'] as $routesList) {
                        if (!empty($routesList)) {
                            $hasRoutes = true;
                            break;
                        }
                    }
                    
                    if (!$hasRoutes) {
                        $html .= '<div class="no-routes">Aucune route enregistrée pour ce routeur</div>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                $html .= '</div>
                </div>
                
                <!-- Modal pour l\'importation -->
                <div id="importModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeImportModal()">&times;</span>
                        <div class="modal-header">📥 Importer une documentation JSON</div>
                        
                        <div class="drop-zone" id="dropZone" onclick="document.getElementById(\'fileInput\').click()">
                            <div class="drop-zone-icon">📄</div>
                            <div class="drop-zone-text">
                                Glissez-déposez votre fichier JSON ici<br>
                                ou cliquez pour sélectionner un fichier
                            </div>
                        </div>
                        
                        <input type="file" id="fileInput" accept=".json" style="display: none;" onchange="handleFileSelect(event)">
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <button class="btn btn-primary" onclick="closeImportModal()">Annuler</button>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Fonction pour développer tous les routeurs
                    function expandAll() {
                        document.querySelectorAll(\'.router-group\').forEach(group => {
                            group.classList.remove(\'collapsed\');
                        });
                    }
                    
                    // Fonction pour réduire tous les routeurs
                    function collapseAll() {
                        document.querySelectorAll(\'.router-group\').forEach(group => {
                            group.classList.add(\'collapsed\');
                        });
                    }
                    
                    // Fonction pour toggle un routeur spécifique
                    function toggleRouter(index) {
                        const router = document.getElementById(\'router-\' + index);
                        router.classList.toggle(\'collapsed\');
                    }
                    
                    // Fonction pour ouvrir le modal d\'importation
                    function openImportModal() {
                        document.getElementById(\'importModal\').style.display = \'block\';
                    }
                    
                    // Fonction pour fermer le modal d\'importation
                    function closeImportModal() {
                        document.getElementById(\'importModal\').style.display = \'none\';
                    }
                    
                    // Fermer le modal si on clique en dehors
                    window.onclick = function(event) {
                        const modal = document.getElementById(\'importModal\');
                        if (event.target == modal) {
                            closeImportModal();
                        }
                    }
                    
                    // Gérer le drag & drop
                    const dropZone = document.getElementById(\'dropZone\');
                    
                    dropZone.addEventListener(\'dragover\', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.add(\'dragover\');
                    });
                    
                    dropZone.addEventListener(\'dragleave\', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.remove(\'dragover\');
                    });
                    
                    dropZone.addEventListener(\'drop\', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.remove(\'dragover\');
                        
                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            handleFile(files[0]);
                        }
                    });
                    
                    // Gérer la sélection de fichier
                    function handleFileSelect(event) {
                        const file = event.target.files[0];
                        if (file) {
                            handleFile(file);
                        }
                    }
                    
                    // Traiter le fichier JSON
                    function handleFile(file) {
                        if (!file.name.endsWith(\'.json\')) {
                            alert(\'❌ Erreur: Veuillez sélectionner un fichier JSON valide.\');
                            return;
                        }
                        
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            try {
                                const jsonData = JSON.parse(e.target.result);
                                
                                // Vérifier que c\'est bien un export de notre documentation
                                if (!jsonData.routers || !Array.isArray(jsonData.routers)) {
                                    alert(\'❌ Erreur: Format JSON non reconnu. Assurez-vous d\\\'importer un fichier exporté depuis cette page.\');
                                    return;
                                }
                                
                                // Appliquer les données importées
                                applyImportedData(jsonData);
                                
                                closeImportModal();
                                alert(\'✅ Importation réussie ! Les modifications ont été appliquées.\');
                                
                            } catch (error) {
                                alert(\'❌ Erreur lors de la lecture du fichier JSON: \' + error.message);
                            }
                        };
                        
                        reader.onerror = function() {
                            alert(\'❌ Erreur lors de la lecture du fichier.\');
                        };
                        
                        reader.readAsText(file);
                    }
                    
                    // Appliquer les données importées
                    function applyImportedData(jsonData) {
                        document.querySelectorAll(\'.router-group\').forEach((routerGroup, routerIndex) => {
                            if (jsonData.routers[routerIndex]) {
                                const importedRouter = jsonData.routers[routerIndex];
                                
                                // Appliquer le friendlyName
                                const friendlyNameEl = routerGroup.querySelector(\'.router-friendly-name\');
                                if (friendlyNameEl && importedRouter.friendlyName) {
                                    friendlyNameEl.textContent = importedRouter.friendlyName;
                                }
                                
                                // Appliquer la description du routeur
                                const descriptionEl = routerGroup.querySelector(\'.router-description\');
                                if (descriptionEl && importedRouter.description) {
                                    descriptionEl.textContent = importedRouter.description;
                                }
                                
                                // Appliquer les données des routes
                                if (importedRouter.routes && importedRouter.routes.length > 0) {
                                    const routeItems = routerGroup.querySelectorAll(\'.route-item\');
                                    
                                    importedRouter.routes.forEach((importedRoute, routeIndex) => {
                                        if (routeItems[routeIndex]) {
                                            const routeItem = routeItems[routeIndex];
                                            const editables = routeItem.querySelectorAll(\'.editable\');
                                            
                                            // Appliquer le nom personnalisé
                                            if (editables[0] && importedRoute.customName) {
                                                editables[0].textContent = importedRoute.customName;
                                            }
                                            
                                            // Appliquer la description de la route
                                            if (editables[1] && importedRoute.description) {
                                                editables[1].textContent = importedRoute.description;
                                            }
                                        }
                                    });
                                }
                            }
                        });
                        
                        // Sauvegarder dans localStorage
                        const data = {};
                        document.querySelectorAll(\'.editable\').forEach((el, index) => {
                            data[\'editable_\' + index] = el.textContent;
                        });
                        localStorage.setItem(\'routesDocumentation\', JSON.stringify(data));
                        console.log(\'💾 Données importées et sauvegardées\');
                    }
                    
                    // Fonction pour exporter en JSON avec les modifications
                    function exportJSON() {
                        const data = {
                            exportDate: new Date().toISOString(),
                            routers: []
                        };
                        
                        document.querySelectorAll(\'.router-group\').forEach((routerGroup, routerIndex) => {
                            const friendlyName = routerGroup.querySelector(\'.router-friendly-name\').textContent.trim();
                            const technicalName = routerGroup.querySelector(\'.router-technical-name\').textContent.replace(\'Routeur technique: \', \'\').trim();
                            const description = routerGroup.querySelector(\'.router-description\').textContent.trim();
                            
                            const routerData = {
                                routeName: technicalName,
                                friendlyName: friendlyName,
                                description: description,
                                routes: []
                            };
                            
                            routerGroup.querySelectorAll(\'.route-item\').forEach(routeItem => {
                                const pattern = routeItem.querySelector(\'.route-pattern\').textContent.trim();
                                const method = routeItem.closest(\'.method-section\').querySelector(\'.method-title\').textContent.trim();
                                const editables = routeItem.querySelectorAll(\'.editable\');
                                const customName = editables[0].textContent.trim();
                                const routeDescription = editables[1].textContent.trim();
                                
                                routerData.routes.push({
                                    method: method,
                                    pattern: pattern,
                                    customName: customName,
                                    description: routeDescription
                                });
                            });
                            
                            data.routers.push(routerData);
                        });
                        
                        // Créer un blob et télécharger
                        const blob = new Blob([JSON.stringify(data, null, 2)], { type: \'application/json\' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement(\'a\');
                        a.href = url;
                        a.download = \'routes-documentation-\' + new Date().getTime() + \'.json\';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        alert(\'✅ Export JSON réussi ! Le fichier a été téléchargé.\');
                    }
                    
                    // Sauvegarder automatiquement dans localStorage à chaque modification
                    let saveTimeout;
                    document.addEventListener(\'input\', function(e) {
                        if (e.target.classList.contains(\'editable\')) {
                            clearTimeout(saveTimeout);
                            saveTimeout = setTimeout(() => {
                                const data = {};
                                document.querySelectorAll(\'.editable\').forEach((el, index) => {
                                    data[\'editable_\' + index] = el.textContent;
                                });
                                localStorage.setItem(\'routesDocumentation\', JSON.stringify(data));
                                console.log(\'💾 Modifications sauvegardées localement\');
                            }, 1000);
                        }
                    });
                    
                    // Restaurer depuis localStorage au chargement
                    window.addEventListener(\'load\', function() {
                        const saved = localStorage.getItem(\'routesDocumentation\');
                        if (saved) {
                            try {
                                const data = JSON.parse(saved);
                                document.querySelectorAll(\'.editable\').forEach((el, index) => {
                                    if (data[\'editable_\' + index]) {
                                        el.textContent = data[\'editable_\' + index];
                                    }
                                });
                                console.log(\'✅ Modifications précédentes restaurées\');
                            } catch (e) {
                                console.error(\'Erreur lors de la restauration:\', e);
                            }
                        }
                    });
                </script>
            </body>
            </html>';
    
        return $html;
    }

    /**
     * Génère la page de login
     * @return string Le HTML de la page de login
     */
    public static function generateLoginPage() {
        // Récupérer le chemin du fichier actuel pour construire le chemin vers auth.php
        $currentFile = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        
        // Vérifier s'il y a une erreur de connexion
        $errorMessage = '';
        if (isset($_REQUEST['LOGIN']) && isset($_REQUEST['PASSWORD'])) {
            $errorMessage = '<div class="alert alert-danger">❌ Identifiants incorrects. Veuillez réessayer.</div>';
        }
    
        $html = '<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Connexion - '.self::$Main->MODULE->Nom.' API by NAbySyGS v'.self::$Main::VERSION().'</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        padding: 20px;
                    }
                    
                    .login-container {
                        background: white;
                        border-radius: 15px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        overflow: hidden;
                        max-width: 450px;
                        width: 100%;
                    }
                    
                    .login-header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 40px 30px;
                        text-align: center;
                    }
                    
                    .login-header h1 {
                        font-size: 2em;
                        margin-bottom: 10px;
                    }
                    
                    .login-header p {
                        font-size: 1em;
                        opacity: 0.9;
                    }
                    
                    .login-body {
                        padding: 40px 30px;
                    }
                    
                    .form-group {
                        margin-bottom: 25px;
                    }
                    
                    .form-label {
                        display: block;
                        font-weight: 600;
                        margin-bottom: 8px;
                        color: #495057;
                        font-size: 0.95em;
                    }
                    
                    .form-control {
                        width: 100%;
                        padding: 12px 15px;
                        border: 2px solid #e9ecef;
                        border-radius: 8px;
                        font-size: 1em;
                        transition: all 0.3s;
                        font-family: inherit;
                    }
                    
                    .form-control:focus {
                        outline: none;
                        border-color: #667eea;
                        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                    }
                    
                    .btn {
                        width: 100%;
                        padding: 14px 20px;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 1em;
                        font-weight: 600;
                        transition: all 0.3s;
                        font-family: inherit;
                    }
                    
                    .btn-primary {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                    }
                    
                    .btn-primary:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                    }
                    
                    .btn-primary:active {
                        transform: translateY(0);
                    }
                    
                    .alert {
                        padding: 12px 15px;
                        border-radius: 8px;
                        margin-bottom: 20px;
                        font-size: 0.95em;
                    }
                    
                    .alert-danger {
                        background: #ffe0e0;
                        color: #dc3545;
                        border: 1px solid #ffcdd2;
                    }
                    
                    .login-icon {
                        font-size: 4em;
                        margin-bottom: 15px;
                    }
                    
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                        20%, 40%, 60%, 80% { transform: translateX(5px); }
                    }
                    
                    .shake {
                        animation: shake 0.5s;
                    }
                </style>
            </head>
            <body>
                <div class="login-container" id="loginContainer">
                    <div class="login-header">
                        <div class="login-icon">🔐</div>
                        <h1>Connexion requise</h1>
                        <p>'.self::$Main->MODULE->Nom.' API Powered by NAbySyGS v'.self::$Main::VERSION().'</p>
                    </div>
                    
                    <div class="login-body">
                        ' . $errorMessage . '
                        
                        <form method="POST" action="./doauth" id="loginForm">
                            <div class="form-group">
                                <label class="form-label" for="login">Identifiant</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="login" 
                                    name="LOGIN" 
                                    placeholder="Votre identifiant"
                                    required
                                    autofocus
                                >
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="password">Mot de passe</label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="password" 
                                    name="PASSWORD" 
                                    placeholder="Votre mot de passe"
                                    required
                                >
                            </div>

                            <input 
                                type="hidden" 
                                id="fordocumentation" 
                                name="fordocumentation" 
                                value="1"
                            >
                            
                            <button type="submit" class="btn btn-primary">
                                🚀 Se connecter
                            </button>
                        </form>
                    </div>
                </div>
                
                <script>
                    // Ajouter une animation shake si erreur de connexion
                    ' . ($errorMessage ? 'document.getElementById("loginContainer").classList.add("shake");' : '') . '
                    
                    // Validation du formulaire
                    document.getElementById("loginForm").addEventListener("submit", function(e) {
                        const login = document.getElementById("login").value.trim();
                        const password = document.getElementById("password").value.trim();
                        
                        if (!login || !password) {
                            e.preventDefault();
                            alert("⚠️ Veuillez remplir tous les champs.");
                            return false;
                        }
                    });
                </script>
            </body>
            </html>';
                
            return $html;
    }
}


?>