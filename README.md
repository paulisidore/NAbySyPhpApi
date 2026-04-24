# NAbySyGS — Framework PHP · API REST · ORM Automatique

[![Latest Version](https://img.shields.io/packagist/v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![Total Downloads](https://img.shields.io/packagist/dt/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![PHP Version](https://img.shields.io/packagist/php-v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**NAbySyGS** est un framework PHP moderne qui vous permet de créer une **API REST complète en quelques minutes**, avec un ORM qui crée les tables automatiquement, des jointures déclarées directement en code (sans clé étrangère en base), un routeur URL RESTful intégré, un système de cache fichier, une authentification JWT, et des modules métier prêts à l'emploi.

> Développé par **Paul & Aïcha Machinerie (PAM)** et **Micro Computer Programme (MCP)**  
> Auteur : [Paul Isidore A. NIAMIE](mailto:paul.isidore@gmail.com)

---

## Pourquoi NAbySyGS ?

| Problème courant | Solution NAbySyGS |
|---|---|
| "Je dois écrire des migrations" | Tables et colonnes créées **automatiquement** |
| "Les JOIN SQL sont pénibles à écrire" | **Jointures déclarées en code** avec `JoinTable()` |
| "Configurer un routeur REST est complexe" | Routeur URL **intégré et auto-découvert** |
| "Le JWT est difficile à mettre en place" | Authentification JWT **prête à l'emploi** |
| "Je veux du cache sans Redis" | Cache fichier **natif** avec `xCacheFileMGR` |
| "Les CORS me posent problème" | CORS géré **automatiquement** |

---

## Sommaire

- [Prérequis](#-prérequis)
- [Installation en 5 minutes](#-installation-en-5-minutes)
- [Votre première API](#-votre-première-api)
- [L'ORM — CRUD automatique](#-lorm)
- [Jointures en code](#-jointures-en-code)
- [Routeur URL RESTful](#-routeur-url-restful)
- [Cache fichier](#-cache-fichier)
- [Authentification JWT](#-authentification-jwt)
- [Système d'événements](#-système-dévénements)
- [Modules intégrés](#-modules-intégrés)
- [CLI — nsy / koro](#-cli)
- [Comparatif](#-comparatif)
- [Documentation complète](#-documentation-complète)
- [Contribuer](#-contribuer)

---

## Prérequis

- PHP **>= 8.1**
- MySQL ou MariaDB
- Extensions PHP : `mysqli`, `mbstring`, `json`
- Composer

---

## Installation en 5 minutes

### Option A — Nouveau projet (avec la CLI)

```bash
# 1. Installer la CLI globale
composer global require nabysyphpapi/xnabysygs-cli

# 2. Créer et initialiser votre projet
mkdir mon-api && cd mon-api
koro init mon-api

# 3. setup.html s'ouvre automatiquement → configurer la base de données
```

### Option B — Projet existant

```bash
composer require nabysyphpapi/xnabysygs
```

Ouvrez `vendor/nabysyphpapi/xnabysygs/setup.html` dans votre navigateur.

### Structure générée

```
mon-api/
├── vendor/
├── gs/                   # Vos modules (créés automatiquement)
├── appinfos.php          # Configuration
├── db_structure.php      # Définition des modules
├── .htaccess             # Routage Apache
└── index.php
```

---

## Votre première API

### 1. Créer un module complet

```bash
koro create categorie produit -a -o -t produits
```

Génère : `gs/produit/produit_action.php` + `gs/produit/xProduit/xProduit.class.php`

### 2. Appeler votre API

```bash
curl "https://votre-api.com/gs_api.php?action=PRODUIT_LIST"
```

### 3. Réponse JSON standard

```json
{ "OK": 1, "TxErreur": "", "Contenue": { "Id": 1, "Nom": "Laptop" } }
```

---

## L'ORM

Pas de migration, pas de schéma. Assignez une propriété — le champ est créé automatiquement.

```php
use NAbySy\ORM\xORMHelper;

// Créer
$produit = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom   = "Laptop Dell";   // Colonne créée automatiquement
$produit->Prix  = 550000;
$produit->Stock = 10;
$produit->Enregistrer();
echo $produit->Id; // ID auto-incrémenté

// Lire par ID
$produit = new xORMHelper($nabysy, 5, true, "produits");
echo $produit->Nom;

// Lister avec filtres
$liste = $produit->ChargeListe(
    "Prix > 100000 AND Stock > 0",  // WHERE
    "Nom ASC",                       // ORDER BY
    "*",                             // SELECT
    null,                            // GROUP BY
    "10"                             // LIMIT
);
while ($row = $liste->fetch_assoc()) {
    echo $row['Nom'];
}

// Modifier
$produit = new xORMHelper($nabysy, 5, true, "produits");
$produit->Prix = 490000;
$produit->Enregistrer();

// Supprimer
$produit->Supprimer();

// Conversions
$json  = $produit->ToJSON();
$obj   = $produit->ToObject();
$array = $produit->ToArrayAssoc();

// Cloner
$clone = $produit->Clone();
$clone = $produit->Clone("autre_base");
```

---

## Jointures en code

NAbySyGS permet de déclarer des **jointures SQL directement dans le code PHP**, sans définir de clés étrangères en base de données. C'est une approche unique qui donne une flexibilité totale.

```php
use NAbySy\ORM\xORMHelper;

// Tables impliquées
$commande = new xORMHelper($nabysy, null, true, "commandes");
$client   = new xORMHelper($nabysy, null, true, "clients");
$produit  = new xORMHelper($nabysy, null, true, "produits");

// Déclarer les jointures (fluent API)
$commande
    ->JoinTable($client,  'cl', 'IdClient',  'ID')  // commandes.IdClient = clients.ID
    ->JoinTable($produit, 'pr', 'IdProduit', 'ID'); // commandes.IdProduit = produits.ID

// Exécuter la requête avec jointures
$liste = $commande->JointureChargeListe(
    "t1.Statut = 'En cours'",          // WHERE (t1 = alias de la table principale)
    "t1.DateCommande DESC",             // ORDER BY
    "t1.*, cl.Nom, cl.Telephone, pr.Designation, pr.Prix",  // SELECT
    null,
    "20"
);

while ($row = $liste->fetch_assoc()) {
    echo $row['Nom'] . ' — ' . $row['Designation'];
}
```

### Types de jointures disponibles

```php
use NAbySy\ORM\xORMJoinTableSpec;

$orm->JoinTable($autreOrm, 'alias', 'cleLocale', 'cleEtrangere',
    xORMJoinTableSpec::LEFT_OUTER_JOIN   // défaut
    // xORMJoinTableSpec::LEFT_JOIN
    // xORMJoinTableSpec::RIGHT_JOIN
    // xORMJoinTableSpec::RIGHT_OUTER_JOIN
    // xORMJoinTableSpec::INNER_JOIN
);
```

### Alias automatiques

Si vous ne fournissez pas d'alias, le framework génère automatiquement `j1`, `j2`, `j3`... pour chaque table jointe. La table principale utilise toujours l'alias `t1`.

### Obtenir le SQL sans l'exécuter

```php
$sql = $commande->ChargeListeNoExecute(
    "t1.Statut = 'Livrée'",
    "t1.Date DESC"
);
// Utile pour le debug ou les requêtes complexes
```

---

## Routeur URL RESTful

NAbySyGS intègre un **routeur URL complet** inspiré des frameworks modernes. Les fichiers de routes sont **auto-découverts** dans les sous-dossiers `gs/`.

### Créer un fichier de routes

```bash
koro create route produit
```

Génère `gs/produit/rProduit.route.php` :

```php
<?php
use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\ORM\xORMHelper;

class rProduit extends xNAbySyUrlRouterHelper {

    public function __construct(string $name, string $fileSrc = '') {
        parent::__construct($name, $fileSrc,
            "Produits",              // Nom convivial (affiché dans la doc auto)
            "Gestion du catalogue"   // Description
        );
        $this->registerRoutes();
    }

    private function registerRoutes(): void {
        // GET /api/produits
        $this->get('/api/produits', function() {
            global $nabysy;
            $p = new xORMHelper($nabysy, null, true, "produits");
            echo $nabysy->SQLToJSON($p->ChargeListe('', 'Nom ASC'));
        }, 'produits.list');

        // GET /api/produits/{id}
        $this->get('/api/produits/{id}', function($id) {
            global $nabysy;
            $p = new xORMHelper($nabysy, (int)$id, true, "produits");
            echo $p->ToJSON();
        }, 'produits.get');

        // POST /api/produits
        $this->post('/api/produits', function() {
            global $nabysy;
            if (!$nabysy->ValideUser()) exit;
            $p = new xORMHelper($nabysy, null, true, "produits");
            $p->Nom  = $_POST['nom']  ?? '';
            $p->Prix = $_POST['prix'] ?? 0;
            $p->Enregistrer();
            echo $p->ToJSON();
        }, 'produits.create');

        // PUT /api/produits/{id}
        $this->put('/api/produits/{id}', function($id) {
            global $nabysy;
            if (!$nabysy->ValideUser()) exit;
            $p = new xORMHelper($nabysy, (int)$id, true, "produits");
            if (isset($_POST['prix'])) $p->Prix = $_POST['prix'];
            $p->Enregistrer();
            echo $p->ToJSON();
        }, 'produits.update');

        // DELETE /api/produits/{id}
        $this->delete('/api/produits/{id}', function($id) {
            global $nabysy;
            if (!$nabysy->ValideUser()) exit;
            $p = new xORMHelper($nabysy, (int)$id, true, "produits");
            $p->Supprimer();
            echo json_encode(['OK' => 1]);
        }, 'produits.delete');
    }
}
```

### Fonctionnalités du routeur

```php
// Paramètres simples
$this->get('/api/produits/{id}', $handler);

// Paramètre optionnel
$this->get('/api/produits/{id?}', $handler);

// Paramètre avec contrainte de type
$this->get('/api/produits/{id:[0-9]+}', $handler);

// Groupes de routes avec préfixe commun
$this->group('/api/v2', function($router) {
    $router->get('/produits', $handler1);
    $router->post('/produits', $handler2);
});

// Toutes les méthodes HTTP
$this->any('/api/ping', fn() => echo 'pong');

// Middleware par route
$this->middleware(fn() => checkAuth(), 'produits.create');

// Middleware global (appliqué à toutes les routes)
$this->middleware(fn() => logRequest());

// Générer une URL depuis un nom de route
$url = $this->generateUrl('produits.get', ['id' => 5]);
// → /api/produits/5
```

### Documentation interactive auto-générée

NAbySyGS génère automatiquement une **page HTML de documentation** de toutes vos routes, accessible après authentification :

```php
// Dans une action dédiée
$routes = xGSUrlRouterManager::getRegistredRoute();
$html   = xGSUrlRouterManager::generateRoutesDocumentationPage(json_encode(['Contenue' => $routes]));
echo $html;
```

La page affiche : méthodes HTTP, patterns, noms, statistiques — et permet l'export JSON.

---

## Cache fichier

`xCacheFileMGR` est un système de cache fichier simple et natif, sans dépendance externe.

```php
use xCacheFileMGR;

$cache = new xCacheFileMGR($nabysy);

// Rafraîchir le cache si le fichier source est plus récent
$cache->refreshCacheFile(
    '/chemin/cache/rapport.pdf',   // Fichier mis en cache
    '/chemin/source/rapport.pdf'   // Fichier source
);

// Logique :
// - Si le cache n'existe pas → copie depuis la source
// - Si la source est plus récente → met à jour le cache
// - Si le cache est à jour → rien ne se passe
```

### Cas d'usage typiques

```php
// Mise en cache de rapports PDF générés
$cacheDir = '/var/cache/rapports/';
$srcDir   = '/var/data/rapports/';

$rapport = 'rapport_' . date('Y-m') . '.pdf';
xCacheFileMGR::refreshCacheFile(
    $cacheDir . $rapport,
    $srcDir   . $rapport
);

// Servir le fichier depuis le cache
header('Content-Type: application/pdf');
readfile($cacheDir . $rapport);
```

---

## Authentification JWT

```php
use NAbySy\xUser;
use NAbySy\xAuth;

// Connexion
$user = new xUser($nabysy, null, true, "utilisateur", $_REQUEST['Login']);
if ($user->CheckPassword($_REQUEST['Password'])) {
    $auth  = new xAuth($nabysy, 3600); // Token valide 1h
    $token = $auth->GetToken($user);
    echo json_encode(['OK' => 1, 'Token' => $token, 'User' => $user->ToObject()]);
}

// Protéger un endpoint
if (!$nabysy->ValideUser()) exit; // Retourne 401 automatiquement

// Durée de session
N::SetAuthSessionTime(86400); // 24h
```

---

## Système d'événements

Pattern Observer automatique sur **toutes les tables**, sans configuration.

```php
<?php
namespace NAbySy\OBSERVGEN;
use NAbySy\xNAbySyGS;

class xObservProduit extends xObservGen {

    public function __construct(xNAbySyGS $nabysy) {
        parent::__construct($nabysy, 'ObservProduit', [
            'xProduit_ADD', 'xProduit_EDIT', 'xProduit_DEL',
        ]);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg): void {
        [$action, $id, $objet] = array_pad($EventType, 3, null);
        match ($action) {
            'xProduit_ADD'  => $this->Main::$Log->Write("Produit #{$id} créé"),
            'xProduit_EDIT' => $objet?->Stock < 10
                ? $this->Main::$Log->Write("Stock faible : {$objet->Nom}")
                : null,
            default => null,
        };
    }
}
```

Placez votre observateur dans `gs/produit/xObservProduit/xObservProduit.class.php` — il est **chargé automatiquement**.

| Événement | Déclencheur |
|---|---|
| `{TABLE}_ADD` | Après `Enregistrer()` (INSERT) |
| `{TABLE}_EDIT` | Après `Enregistrer()` (UPDATE) |
| `{TABLE}_DEL` | Après `Supprimer()` |
| `*_ADD` / `*_EDIT` / `*_DEL` | Wildcard — toutes les tables |

---

## Modules intégrés

| Module | Namespace | Description |
|---|---|---|
| Boutique | `NAbySy\GS\Boutique` | Points de vente |
| Stock | `NAbySy\GS\Stock` | Inventaire, produits |
| Facture | `NAbySy\GS\Facture` | Ventes, facturation |
| Client | `NAbySy\GS\Client` | Gestion clients |
| Panier | `NAbySy\GS\Panier` | E-commerce |
| Comptabilité | `NAbySy\GS\Comptabilite` | Opérations comptables |
| Utilisateur | `NAbySy\xUser` | Auth, permissions |

---

## CLI

Installable séparément : [`nabysyphpapi/xnabysygs-cli`](https://github.com/paulisidore/xnabysygs-cli)

```bash
composer global require nabysyphpapi/xnabysygs-cli
```

| Commande | Description |
|---|---|
| `koro init <projet>` | Nouveau projet |
| `koro create categorie <nom> -a -o -t <table>` | Module complet |
| `koro create action <nom>` | Fichier action API |
| `koro create orm <nom> <table>` | Classe ORM |
| `koro create route <nom>` | Contrôleur de route RESTful |
| `koro db update` | Synchronise la structure en base |

---

## Comparatif

| Critère | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---|---|---|---|---|
| Setup initial | < 5 min | 30–60 min | 15–30 min | 10 min |
| Auto-création tables/colonnes | ✅ | ❌ | ❌ | ✅ |
| **Jointures déclarées en code** | ✅ | ❌ | ❌ | ❌ |
| **Cache fichier natif** | ✅ | ❌ | ❌ | ❌ |
| **Routeur URL RESTful intégré** | ✅ | ❌ | ✅ (Laravel) | ❌ |
| **Doc API auto-générée** | ✅ | ❌ | ❌ | ❌ |
| API REST out-of-the-box | ✅ | ❌ | Partiel | ❌ |
| JWT intégré | ✅ | ❌ | ❌ | ❌ |
| CORS automatique | ✅ | ❌ | ❌ | ❌ |
| Événements sur tables dynamiques | ✅ | ❌ | ❌ | ❌ |
| Migrations requises | ❌ | ✅ | ✅ | ❌ |
| Relations Many-to-Many auto | ❌ | ✅ | ✅ | ✅ |
| Courbe d'apprentissage | Faible | Élevée | Moyenne | Faible |

---

## Documentation complète

| Guide | Lien |
|---|---|
| Guide débutant (pas à pas) | [docs/guide-debutant.md](docs/guide-debutant.md) |
| Référence complète de l'API | [docs/reference-api.md](docs/reference-api.md) |
| Jointures ORM en code | [docs/jointures-orm.md](docs/jointures-orm.md) |
| Routeur URL RESTful | [docs/routeur-url.md](docs/routeur-url.md) |
| Système d'événements | [docs/evenements.md](docs/evenements.md) |
| Modules métier intégrés | [docs/modules-integres.md](docs/modules-integres.md) |
| Architecture du framework | [docs/architecture.md](docs/architecture.md) |
| Guide contributeur | [CONTRIBUTING.md](CONTRIBUTING.md) |

---

## Contribuer

Consultez [CONTRIBUTING.md](CONTRIBUTING.md) pour démarrer.

```bash
git clone https://github.com/paulisidore/NAbySyPhpApi.git
git checkout -b feature/ma-fonctionnalite
git push origin feature/ma-fonctionnalite
# → Ouvrir une Pull Request
```

---

## Licence

MIT — voir [LICENSE](LICENSE)

---

<div align="center">
  <sub>Développé avec ❤️ par <strong>PAM & MCP</strong> — Si ce projet vous aide, ⭐ une étoile est appréciée !</sub>
</div>
