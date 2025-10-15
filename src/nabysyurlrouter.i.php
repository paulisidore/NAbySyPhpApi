<?php
/**
 * Interface INAbySyUrlRouter
 * 
 * Interface de base pour le système de routing URL de NAbySyGS
 * Permet de définir des routes API dynamiques de type Laravel
 * 
 * @package NAbySy\Router\Url
 * @author Paul Isidore
 * @version 1.0.0
 */

namespace NAbySy\Router\Url;

use NAbySy\xErreur;
use NAbySy\xNotification;

interface INAbySyUrlRouter {
    /**
     * Nom unique de la route
     * @return string 
     */
    public function routeName(): string ;

    /**
     * Indique i OUI/NON la route peut être utilisé par NAbySyGS
     * @return bool 
     */
    public function IsActive():bool ;

    /**
     * Retourne la liste de toutes les routes et leurs descriptions inscrites dans le Router
     * @return string[] 
     */
    public function getRegisteredRoute():array ;

    /**
     * Enregistre une route GET
     * 
     * @param string $pattern Pattern de la route (ex: '/users/{id}')
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function get(string $pattern, $handler, ?string $name = null): self;

    /**
     * Enregistre une route POST
     * 
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function post(string $pattern, $handler, ?string $name = null): self;

    /**
     * Enregistre une route PUT
     * 
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function put(string $pattern, $handler, ?string $name = null): self;

    /**
     * Enregistre une route DELETE
     * 
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function delete(string $pattern, $handler, ?string $name = null): self;

    /**
     * Enregistre une route PATCH
     * 
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function patch(string $pattern, $handler, ?string $name = null): self;

    /**
     * Enregistre une route pour toutes les méthodes HTTP
     * 
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Fonction de callback ou [Controller, method]
     * @param string|null $name Nom optionnel de la route
     * @return self Pour chaînage
     */
    public function any(string $pattern, $handler, ?string $name = null): self;

    /**
     * Groupe de routes avec un préfixe commun
     * 
     * @param string $prefix Préfixe pour toutes les routes du groupe
     * @param callable $callback Fonction contenant les définitions de routes
     * @param array $attributes Attributs supplémentaires (middleware, namespace, etc.)
     * @return self Pour chaînage
     */
    public function group(string $prefix, callable $callback, array $attributes = []): self;

    /**
     * Résout une route en fonction de l'URL et de la méthode HTTP
     * 
     * @param string $url URL à résoudre
     * @param string $method Méthode HTTP (GET, POST, etc.)
     * @return array|null Retourne ['handler' => callable, 'params' => array] ou null
     */
    public function resolve(string $url, string $method): ?array;

    /**
     * Récupère toutes les routes enregistrées
     * 
     * @return array Liste de toutes les routes
     */
    public function getRoutes(): array;

    /**
     * Récupère une route par son nom
     * 
     * @param string $name Nom de la route
     * @return array|null Informations de la route ou null
     */
    public function getRouteByName(string $name): ?array;

    /**
     * Génère une URL à partir d'un nom de route et de paramètres
     * 
     * @param string $name Nom de la route
     * @param array $params Paramètres à injecter dans l'URL
     * @return string|null URL générée ou null si la route n'existe pas
     */
    public function generateUrl(string $name, array $params = []): ?string;

    /**
     * Ajoute un middleware global ou à une route
     * 
     * @param string|callable $middleware Nom du middleware ou callable
     * @param string|null $routeName Nom de la route (null pour global)
     * @return self Pour chaînage
     */
    public function middleware($middleware, ?string $routeName = null): self;

    /**
     * Récupère les paramètres de la dernière route résolue
     * 
     * @return array Paramètres extraits de l'URL
     */
    public function getParams(): array;
}

/**
 * Reponse retournée par le Gestionnaire des Routes d'URL pour NAbySyGS
 * @package NAbySy\Router\Url
 */
class xGSUrlRouterResponse extends xNotification{
    public ?INAbySyUrlRouter $Router ;
    public ?array $route ;
    public ?string $Path ;
    public ?string $Methode ;

    public function __construct(){
        $this->OK=0;
        $this->route=[];
        $this->Path="";
        $this->Methode="";
        $this->Contenue="";
    }
}