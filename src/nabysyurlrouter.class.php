<?php
/**
 * Classe xNAbySyUrlRouterHelper
 * 
 * Implémentation de base du système de routing URL pour NAbySyGS
 * Peut être étendue par les développeurs pour personnaliser le comportement
 * 
 * @package NAbySy\Router\Url
 * @author Paul Isidore
 * @version 1.0.0
 */

namespace NAbySy\Router\Url;

use Exception;

class xNAbySyUrlRouterHelper implements INAbySyUrlRouter {

    private string $_name ='';
    private bool $_active = true;
    private string $_fileSrc = '';

    /**
     * @var array Routes enregistrées organisées par méthode HTTP
     */
    protected array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'ANY' => []
    ];

    /**
     * @var array Routes nommées pour génération d'URL
     */
    protected array $namedRoutes = [];

    /**
     * @var array Paramètres extraits de la dernière résolution
     */
    protected array $params = [];

    /**
     * @var array Pile de préfixes pour les groupes
     */
    protected array $groupStack = [];

    /**
     * @var array Middlewares globaux et par route
     */
    protected array $middlewares = [
        'global' => [],
        'routes' => []
    ];

    public function __construct(string $RouterName, string $FileSource){
        if(trim($RouterName)==''){
            throw new Exception("Router name is mandatory", 1);
        }
        $this->_name=$RouterName;
        $this->_fileSrc=$FileSource;
    }
    public function routeName(): string {
        return $this->_name;
    }

    public function IsActive(): bool{
        return $this->_active;
    }

    /**
     * Retourne le chemin contenant le router
     * @return string 
     */
    public function SourceFile():string{
        return $this->_fileSrc;
    }

    public function getRegisteredRoute():array{
        $Liste= $this->routes;
        return $Liste;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('PUT', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('DELETE', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('PATCH', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $pattern, $handler, ?string $name = null): self
    {
        return $this->addRoute('ANY', $pattern, $handler, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $prefix, callable $callback, array $attributes = []): self
    {
        // Empiler le préfixe
        $this->groupStack[] = [
            'prefix' => trim($prefix, '/'),
            'attributes' => $attributes
        ];

        // Exécuter le callback avec le router
        $callback($this);

        // Dépiler
        array_pop($this->groupStack);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $url, string $method): ?array
    {
        $this->params = [];
        $url = '/' . trim($url, '/');
        $method = strtoupper($method);

        // Chercher dans les routes de la méthode spécifique
        $result = $this->matchRoute($url, $method);
        
        // Si pas trouvé, chercher dans les routes ANY
        if ($result === null && $method !== 'ANY') {
            $result = $this->matchRoute($url, 'ANY');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteByName(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateUrl(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $pattern = $this->namedRoutes[$name]['pattern'];
        
        // Remplacer les paramètres dans le pattern
        foreach ($params as $key => $value) {
            $pattern = preg_replace('/\{' . $key . '(\?|:[^}]+)?\}/', $value, $pattern);
        }

        // Supprimer les paramètres optionnels non fournis
        $pattern = preg_replace('/\{[^}]+\?\}/', '', $pattern);

        return $pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function middleware($middleware, ?string $routeName = null): self
    {
        if ($routeName === null) {
            $this->middlewares['global'][] = $middleware;
        } else {
            if (!isset($this->middlewares['routes'][$routeName])) {
                $this->middlewares['routes'][$routeName] = [];
            }
            $this->middlewares['routes'][$routeName][] = $middleware;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Ajoute une route au registre
     * 
     * @param string $method Méthode HTTP
     * @param string $pattern Pattern de la route
     * @param callable|array $handler Handler de la route
     * @param string|null $name Nom optionnel
     * @return self
     */
    protected function addRoute(string $method, string $pattern, $handler, ?string $name = null): self
    {
        // Appliquer le préfixe des groupes
        $fullPattern = $this->buildGroupPattern($pattern);
        
        // Normaliser le pattern
        $fullPattern = '/' . trim($fullPattern, '/');

        $route = [
            'pattern' => $fullPattern,
            'handler' => $handler,
            'name' => $name,
            'regex' => $this->buildRegex($fullPattern)
        ];

        $this->routes[$method][] = $route;

        // Enregistrer la route nommée
        if ($name !== null) {
            $this->namedRoutes[$name] = $route + ['method' => $method];
        }

        return $this;
    }

    /**
     * Construit le pattern complet avec les préfixes de groupes
     * 
     * @param string $pattern Pattern de base
     * @return string Pattern complet
     */
    protected function buildGroupPattern(string $pattern): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . $group['prefix'];
        }

        return $prefix . '/' . trim($pattern, '/');
    }

    /**
     * Construit une regex à partir d'un pattern de route
     * 
     * @param string $pattern Pattern de route (ex: /api/boutiques/{id})
     * @return string Regex compilée (ex: #^/api/boutiques/([^/]+)$#)
     */
    protected function buildRegex(string $pattern): string {
        // Échapper seulement les slashes (pas preg_quote qui échappe trop)
        // Échapper seulement les slashes, PAS preg_quote()
        $regex = str_replace('/', '\/', $pattern);
        
        // 1. Contraintes {id:[0-9]+}
        $regex = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*):([^\}]+)\}/',
            '($2)',
            $regex
        );
        
        // 2. Optionnels {id?}
        $regex = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            '(?:\/([^\/]+))?',
            $regex
        );
        
        // 3. Simples {id}
        $regex = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '([^\/]+)',
            $regex
        );
        
        return '#^' . $regex . '$#';
    }

    /**
     * Tente de matcher une URL avec les routes d'une méthode
     * 
     * @param string $url URL à matcher
     * @param string $method Méthode HTTP
     * @return array|null Résultat du match ou null
     */
    protected function matchRoute(string $url, string $method): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['regex'], $url, $matches)) {
                // Extraire les noms de paramètres
                $paramNames = $this->extractParamNames($route['pattern']);
                
                // Construire le tableau de paramètres
                array_shift($matches); // Retirer le match complet
                $this->params = $this->buildParamsArray($paramNames, $matches);

                return [
                    'handler' => $route['handler'],
                    'params' => $this->params,
                    'name' => $route['name'],
                    'middlewares' => $this->getRouteMiddlewares($route['name'])
                ];
            }
        }

        return null;
    }

    /**
     * Extrait les noms de paramètres d'un pattern
     * 
     * @param string $pattern Pattern de route
     * @return array Noms des paramètres
     */
    protected function extractParamNames(string $pattern): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?|:[^\}]+)?\}/', $pattern, $matches);
        return $matches[1];
    }

    /**
     * Construit un tableau associatif paramètre => valeur
     * 
     * @param array $names Noms des paramètres
     * @param array $values Valeurs capturées
     * @return array Tableau associatif
     */
    protected function buildParamsArray(array $names, array $values): array
    {
        $params = [];
        foreach ($names as $index => $name) {
            if (isset($values[$index]) && $values[$index] !== '') {
                $params[$name] = $values[$index];
            }
        }
        return $params;
    }

    /**
     * Récupère les middlewares pour une route
     * 
     * @param string|null $routeName Nom de la route
     * @return array Liste des middlewares
     */
    protected function getRouteMiddlewares(?string $routeName): array
    {
        $middlewares = $this->middlewares['global'];
        
        if ($routeName !== null && isset($this->middlewares['routes'][$routeName])) {
            $middlewares = array_merge($middlewares, $this->middlewares['routes'][$routeName]);
        }

        return $middlewares;
    }

    /**
     * Exécute le handler d'une route
     * 
     * @param mixed $handler Handler à exécuter
     * @param array $params Paramètres à passer
     * @return mixed Résultat de l'exécution
     */
    public function executeHandler($handler, array $params = [])
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, array_values($params));
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;
            
            if (is_string($controller)) {
                $controller = new $controller();
            }

            if (method_exists($controller, $method)) {
                return call_user_func_array([$controller, $method], array_values($params));
            }
        }

        throw new \RuntimeException("Handler invalide pour la route");
    }
}