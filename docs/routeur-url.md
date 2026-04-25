# Routeur URL RESTful — NAbySyGS

Le routeur URL intégré au framework NAbySyGS permet de définir des routes RESTful propres, de les organiser par groupes, d'appliquer des middlewares et de générer automatiquement une page de documentation interactive.

## Table des matières

1. [Concepts fondamentaux](#concepts-fondamentaux)
2. [Créer un fichier de routes](#créer-un-fichier-de-routes)
3. [Définir des routes](#définir-des-routes)
4. [Paramètres d'URL](#paramètres-durl)
5. [Groupes de routes](#groupes-de-routes)
6. [Routes nommées](#routes-nommées)
7. [Middlewares](#middlewares)
8. [Gestionnaire global (xGSUrlRouterManager)](#gestionnaire-global)
9. [Documentation auto-générée](#documentation-auto-générée)
10. [Exemples complets](#exemples-complets)

---

## Concepts fondamentaux

Le système de routage repose sur deux classes :

| Classe | Rôle |
|--------|------|
| `xNAbySyUrlRouterHelper` | Classe de base à étendre pour chaque fichier `.route.php` |
| `xGSUrlRouterManager` | Gestionnaire global — auto-découverte, résolution, documentation |

**Flux d'une requête :**
```
Requête HTTP → xGSUrlRouterManager::resolveUrlRoute()
    → Découverte des fichiers *.route.php dans gs/
    → Résolution du pattern correspondant
    → Appel du handler PHP
    → Réponse JSON
```

---

## Créer un fichier de routes

Chaque module de votre application peut avoir son propre fichier `.route.php`. La convention de nommage est :

```
gs/
├── articles/
│   ├── articles.route.php   ← routes du module articles
│   └── articles.action.php
├── users/
│   ├── users.route.php      ← routes du module users
│   └── users.action.php
```

Structure minimale d'un fichier de routes :

```php
<?php
// gs/articles/articles.route.php

use xNAbySyUrlRouterHelper;

$router = new class extends xNAbySyUrlRouterHelper {
    public function __construct() {
        parent::__construct();
        $this->registerRoutes();
    }

    protected function registerRoutes(): void {
        $this->get('/articles', 'ArticlesAction@getAll', 'articles.index');
        $this->get('/articles/{id}', 'ArticlesAction@getOne', 'articles.show');
        $this->post('/articles', 'ArticlesAction@create', 'articles.create');
        $this->put('/articles/{id}', 'ArticlesAction@update', 'articles.update');
        $this->delete('/articles/{id}', 'ArticlesAction@delete', 'articles.delete');
    }
};
```

> **CLI** : Générez ce fichier automatiquement avec `nsy create route articles`

---

## Définir des routes

Toutes les méthodes HTTP standard sont disponibles :

```php
// GET
$this->get(string $pattern, $handler, ?string $name = null): self

// POST
$this->post(string $pattern, $handler, ?string $name = null): self

// PUT
$this->put(string $pattern, $handler, ?string $name = null): self

// DELETE
$this->delete(string $pattern, $handler, ?string $name = null): self

// PATCH
$this->patch(string $pattern, $handler, ?string $name = null): self

// Toutes méthodes
$this->any(string $pattern, $handler, ?string $name = null): self
```

### Handler : string ou callable

```php
// Format string : Classe@méthode (recommandé)
$this->get('/users', 'UserAction@index', 'users.index');

// Format closure (pour les réponses rapides)
$this->get('/ping', function() {
    return (new xNotification('pong', 200))->getJson();
}, 'ping');
```

---

## Paramètres d'URL

### Paramètre simple `{param}`

```php
$this->get('/articles/{id}', 'ArticlesAction@getOne');
// Correspond à : /articles/42, /articles/abc
// $params['id'] = '42'
```

### Paramètre optionnel `{param?}`

```php
$this->get('/articles/{page?}', 'ArticlesAction@getAll');
// Correspond à : /articles  ET  /articles/2
// $params['page'] = null ou '2'
```

### Paramètre avec contrainte `{param:regex}`

```php
$this->get('/articles/{id:[0-9]+}', 'ArticlesAction@getOne');
// Correspond à : /articles/42
// Ne correspond PAS à : /articles/abc

$this->get('/users/{slug:[a-z\-]+}', 'UsersAction@getBySlug');
// Correspond à : /users/jean-dupont
```

### Récupérer les paramètres dans l'action

```php
// ArticlesAction.php
public function getOne(array $params): void {
    $id = (int) $params['id'];
    $orm = new xORMHelper('articles');
    $orm->Chargement($id);
    echo (new xNotification($orm->toArray(), 200))->getJson();
}
```

---

## Groupes de routes

Regroupez les routes sous un préfixe commun :

```php
// Toutes ces routes auront le préfixe /api/v1
$this->group('/api/v1', function() {
    $this->get('/users', 'UserAction@index', 'users.index');
    $this->post('/users', 'UserAction@store', 'users.create');
    $this->get('/users/{id}', 'UserAction@show', 'users.show');
});
// Routes : /api/v1/users, /api/v1/users, /api/v1/users/{id}
```

### Groupes imbriqués

```php
$this->group('/api', function() {
    $this->group('/v1', function() {
        $this->get('/products', 'ProductAction@index');
    });
    $this->group('/v2', function() {
        $this->get('/products', 'ProductV2Action@index');
    });
});
// Routes : /api/v1/products, /api/v2/products
```

### Attributs de groupe

```php
$this->group('/admin', function() {
    $this->get('/dashboard', 'AdminAction@dashboard');
    $this->get('/users', 'AdminAction@users');
}, [
    'middleware' => ['auth', 'admin-role']
]);
```

---

## Routes nommées

Nommez vos routes pour générer des URLs dynamiquement :

```php
$this->get('/articles/{id}', 'ArticlesAction@show', 'articles.show');

// Générer l'URL depuis n'importe où :
$url = xGSUrlRouterManager::getRouteByName('articles.show')
    ->generateUrl(['id' => 42]);
// Résultat : '/articles/42'
```

---

## Middlewares

### Ajouter un middleware à une route

```php
// Sur la dernière route définie
$this->get('/profile', 'UserAction@profile', 'user.profile')
    ->middleware('auth');

// Ou par nom de route
$this->middleware('auth', 'user.profile');
```

### Middleware sur un groupe

```php
$this->group('/api/secure', function() {
    $this->get('/data', 'DataAction@index');
}, ['middleware' => ['auth', 'rate-limit']]);
```

### Implémenter un middleware

```php
// Exemple de middleware d'authentification JWT
class AuthMiddleware {
    public function handle(): bool {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        $user = new xUser();
        return $user->ValideUser($token);
    }
}
```

---

## Gestionnaire global

`xGSUrlRouterManager` est le point d'entrée du système de routage.

### Résoudre une requête

```php
// Dans votre index.php ou point d'entrée
$response = xGSUrlRouterManager::resolveUrlRoute(true);
// true = envoyer la réponse directement
```

### Auto-découverte des fichiers de routes

Le gestionnaire parcourt automatiquement tous les sous-dossiers de `gs/` à la recherche de fichiers `*.route.php` :

```
gs/
├── articles/articles.route.php   ← découvert automatiquement
├── users/users.route.php         ← découvert automatiquement
└── orders/orders.route.php       ← découvert automatiquement
```

Aucune configuration manuelle n'est nécessaire.

### API du gestionnaire

```php
// Résoudre la requête courante
xGSUrlRouterManager::resolveUrlRoute(bool $CanSendReponse = false): xGSUrlRouterResponse

// Lister toutes les routes enregistrées
xGSUrlRouterManager::getRegistredRoute(): array

// Récupérer une route par nom
xGSUrlRouterManager::getRouteByName(string $routerName): ?xNAbySyUrlRouterHelper

// Générer la page de documentation HTML
xGSUrlRouterManager::generateRoutesDocumentationPage(string $jsonRoutes): string
```

---

## Documentation auto-générée

Le framework peut générer une **page HTML interactive** listant toutes vos routes :

```php
// Générer la page de documentation
$routes = json_encode(xGSUrlRouterManager::getRegistredRoute());
$html = xGSUrlRouterManager::generateRoutesDocumentationPage($routes);
echo $html;
```

La page affiche pour chaque route :
- Méthode HTTP (colorée : GET=vert, POST=bleu, PUT=orange, DELETE=rouge)
- Pattern d'URL
- Nom de la route
- Middlewares appliqués
- Un bouton « Tester » pour lancer la requête directement depuis le navigateur

Exemple de route vers la documentation :

```php
$this->get('/api/docs', function() {
    $routes = json_encode(xGSUrlRouterManager::getRegistredRoute());
    echo xGSUrlRouterManager::generateRoutesDocumentationPage($routes);
}, 'api.docs');
```

---

## Exemples complets

### API CRUD complète — Articles

```php
<?php
// gs/articles/articles.route.php

$router = new class extends xNAbySyUrlRouterHelper {
    public function __construct() {
        parent::__construct();

        $this->group('/api/v1/articles', function() {

            // Liste tous les articles (avec pagination optionnelle)
            $this->get('/{page?}', 'ArticlesAction@index', 'articles.index');

            // Détail d'un article (ID numérique obligatoire)
            $this->get('/show/{id:[0-9]+}', 'ArticlesAction@show', 'articles.show');

            // Créer un article (authentification requise)
            $this->post('/create', 'ArticlesAction@store', 'articles.create')
                ->middleware('auth');

            // Modifier un article
            $this->put('/update/{id:[0-9]+}', 'ArticlesAction@update', 'articles.update')
                ->middleware('auth');

            // Supprimer un article
            $this->delete('/delete/{id:[0-9]+}', 'ArticlesAction@destroy', 'articles.delete')
                ->middleware('auth');

        });
    }
};
```

### Action correspondante

```php
<?php
// gs/articles/ArticlesAction.php

class ArticlesAction {

    public function index(array $params): void {
        $page = (int)($params['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $orm = new xORMHelper('articles');
        $result = $orm->ChargeListe(null, 'DATE_CREATION DESC', '*', null, "$offset,$limit");

        $articles = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $articles[] = $row;
        }

        echo (new xNotification(['articles' => $articles, 'page' => $page], 200))->getJson();
    }

    public function show(array $params): void {
        $orm = new xORMHelper('articles');
        $orm->Chargement((int)$params['id']);

        if (!$orm->ID) {
            echo (new xErreur('Article introuvable', 404))->getJson();
            return;
        }

        echo (new xNotification($orm->toArray(), 200))->getJson();
    }

    public function store(array $params): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $orm = new xORMHelper('articles');
        $orm->TITRE = $data['titre'] ?? '';
        $orm->CONTENU = $data['contenu'] ?? '';
        $orm->DATE_CREATION = date('Y-m-d H:i:s');
        $orm->Enregistrement();

        echo (new xNotification(['id' => $orm->ID], 201))->getJson();
    }

    public function update(array $params): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $orm = new xORMHelper('articles');
        $orm->Chargement((int)$params['id']);

        if (!$orm->ID) {
            echo (new xErreur('Article introuvable', 404))->getJson();
            return;
        }

        $orm->TITRE = $data['titre'] ?? $orm->TITRE;
        $orm->CONTENU = $data['contenu'] ?? $orm->CONTENU;
        $orm->Enregistrement();

        echo (new xNotification('Article mis à jour', 200))->getJson();
    }

    public function destroy(array $params): void {
        $orm = new xORMHelper('articles');
        $orm->Chargement((int)$params['id']);

        if (!$orm->ID) {
            echo (new xErreur('Article introuvable', 404))->getJson();
            return;
        }

        $orm->Suppression();
        echo (new xNotification('Article supprimé', 200))->getJson();
    }
}
```

### Intégration dans index.php

```php
<?php
// index.php
require_once 'vendor/autoload.php';

// Initialiser la connexion BD
xBD::Connexion('localhost', 'ma_bdd', 'root', '');

// Résoudre et répondre à la requête
xGSUrlRouterManager::resolveUrlRoute(true);
```

---

## Référence rapide

| Méthode | Pattern | Exemple |
|---------|---------|----------|
| `get()` | `/users` | Liste tous les utilisateurs |
| `post()` | `/users` | Créer un utilisateur |
| `put()` | `/users/{id}` | Remplacer un utilisateur |
| `patch()` | `/users/{id}` | Modifier partiellement |
| `delete()` | `/users/{id}` | Supprimer |
| `any()` | `/users/{id?}` | Toutes méthodes |
| `group()` | `/api/v1` | Préfixe commun |

| Pattern | Signification |
|---------|---------------|
| `{id}` | Paramètre obligatoire |
| `{id?}` | Paramètre optionnel |
| `{id:[0-9]+}` | Paramètre avec regex |
| `{slug:[a-z\-]+}` | Paramètre lettres + tirets |
