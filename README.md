# NAbySyGS - Framework PHP avec ORM Intégré

[![Latest Version](https://img.shields.io/packagist/v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![Total Downloads](https://img.shields.io/packagist/dt/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![License](https://img.shields.io/packagist/l/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![PHP Version](https://img.shields.io/packagist/php-v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)

**NAbySyGS** est un framework PHP moderne conçu par **PAM & MCP** pour faciliter la création rapide d'API REST pour vos applications. Il intègre un ORM personnalisé avec création automatique de tables et champs, un système d'authentification JWT, une architecture modulaire, et un double système de routage (Action classique + URL Laravel-style).

---

## ✨ Fonctionnalités

* 🚀 **ORM Automatique** — Création automatique des tables et champs MySQL/MariaDB
* 🔗 **Jointures ORM Fluentes** — API de jointure chainable entre entités, sans écrire de SQL
* 🔐 **Authentification JWT** — Système de tokens sécurisés intégré
* 📦 **Architecture Modulaire** — Organisation en modules avec auto-chargement
* 🎯 **Type-Safe** — Détection automatique des types de données (INT, VARCHAR, DATE…)
* 🔄 **Gestion d'Événements** — Pattern Observer pour réagir aux changements
* 🌐 **Double Routage** — Routage par Action classique **et** routage URL Laravel-style, simultanément
* 🛡️ **Middlewares** — Appliqués automatiquement, personnalisables par le développeur
* 📋 **Documentation des Routes Intégrée** — Endpoint `/api/describe` avec rendu web interactif, export JSON/PDF et import
* 🛠️ **Modules Métier** — Gestion de boutiques, stocks, factures, clients…
* 🌐 **CORS Ready** — Gestion automatique des requêtes cross-origin
* 📝 **Logs Intégrés** — Journalisation système et débogage
* 🎛️ **Setup HTML Interactif** — Configuration initiale via interface web, ouverte automatiquement au premier lancement

---

## 📋 Prérequis

* PHP >= 8.1.0
* MySQL ou MariaDB
* Extensions PHP : `mysqli`, `mbstring`, `json`
* Composer

---

## 📦 Installation

### Via Composer

```bash
composer require nabysyphpapi/xnabysygs
```

### Via la CLI NAbySyGS (`koro` / `nsy`)

```bash
# Installer la CLI globalement
composer global require nabysyphpapi/xnabysygs-cli

# Initialiser un nouveau projet dans le dossier courant
koro init mon-projet-api
```

> Voir la documentation de la CLI pour le détail des commandes disponibles.

### 🎛️ Setup Initial Automatique

Lors de la **première installation** (via `composer require` ou via n'importe quelle commande `koro`), NAbySyGS ouvre automatiquement **`setup.html`** dans votre navigateur par défaut.

Cette interface web vous permet de configurer :

* La connexion à la base de données
* L'URL du serveur
* Le nom de l'application et les informations client

Une fois le formulaire validé, **`appinfos.php`** est généré automatiquement à la racine de votre projet. Le setup ne s'ouvrira plus automatiquement par la suite.

> ⚠️ Toute commande `koro` (sauf `koro version`) déclenche cette vérification et ouvre le setup si le projet n'est pas encore configuré.

### Structure Générée

```
votre-projet/
├── vendor/
│   └── nabysyphpapi/xnabysygs/
├── gs/                         # Modules personnalisés (créés automatiquement)
│   └── client/
│       ├── client_action.php   # Endpoints Action API
│       └── xClient/
│           └── xClient.class.php  # Classe ORM
├── appinfos.php                # Configuration (généré par setup.html)
├── db_structure.php            # Déclaration des modules et routes
├── .htaccess                   # Redirection API (créé automatiquement)
└── index.php                   # Point d'entrée
```

---

## 🚀 Démarrage Rapide

### 1. Configuration Initiale

Créez un fichier `index.php` à la racine :

```php
<?php
require 'vendor/autoload.php';

use NAbySy\xNAbySyGS as N;

$nabysy = N::Init(
    "MonApp",              // Nom de l'application
    "Ma Société SARL",     // Nom du client
    "123 Rue Example",     // Adresse
    "+221 33 123 45 67",   // Téléphone
    "ma_base",             // Base de données
    "nabysygs",            // Base master
    "localhost",           // Serveur MySQL
    "root",                // Utilisateur
    "",                    // Mot de passe
    3306                   // Port
);

// Mode debug (développement uniquement)
N::SetShowDebug(true);

// Traiter les requêtes HTTP
N::ReadHttpRequest();
```

> En pratique, `appinfos.php` généré par le setup contient déjà cet appel `N::Init()` configuré pour votre environnement.

### 2. Déclarer vos Modules — `db_structure.php`

Tous les modules (catégories, ORM, routes URL) sont déclarés dans `db_structure.php`, inclus automatiquement dans `appinfos.php`. Ce fichier peut être généré et enrichi via la CLI ou manuellement.

```php
<?php
// ── categorie: client ──────────────────────────────── 2026-04-25 00:44 ──
N::$GSModManager::CreateCategorie("client", true, true, "clients");
// ── end: client ────────────────────────────────────────────────────────

// ── categorie: client_url ──────────────────────────── 2026-04-25 00:44 ──
N::$GSModManager::GenerateUrlRouteController("client", "client");
// ── end: client_url ────────────────────────────────────────────────────
```

Vous pouvez également avoir **plusieurs fichiers de structure** en les déclarant via la CLI avec `--struct` :

```bash
koro create categorie commande -a -o -t commandes --struct structure/commerce.php
```

### 3. Créer Votre Premier Module

```php
<?php
use NAbySy\xNAbySyGS as N;

// Créer un module "client" avec action API et classe ORM
N::$GSModManager::CreateCategorie(
    "client",   // Nom du module
    true,       // Créer fichier action
    true,       // Créer classe ORM
    "clients"   // Nom de la table
);
```

Cela génère automatiquement :

* `gs/client/client_action.php` — Endpoints API
* `gs/client/xClient/xClient.class.php` — Classe ORM

### 4. Utiliser l'ORM

```php
<?php
use NAbySy\ORM\xORMHelper;

$nabysy = N::getInstance();

// Créer un client
$client            = new xORMHelper($nabysy, null, true, "clients");
$client->Nom       = "Dupont";
$client->Prenom    = "Jean";
$client->Email     = "jean@example.com";
$client->Telephone = "771234567";
$client->Enregistrer();

echo "Client créé avec ID : " . $client->Id;
```

---

## 🌐 Double Système de Routage

NAbySyGS supporte **deux modes de routage simultanément**. Vous pouvez utiliser l'un, l'autre, ou les deux dans le même projet.

### Mode 1 — Routage par Action (classique)

Le routage historique de NAbySyGS. Chaque requête transmet un paramètre `action` ou `Action`.

```php
// Appel
GET /gs_api.php?action=CLIENT_LIST
POST /gs_api.php  { "action": "CLIENT_CREATE", "nom": "Dupont" }
```

```php
// gs/client/client_action.php
switch ($action) {
    case 'CLIENT_LIST':
        $client = new xORMHelper($nabysy, null, true, "clients");
        echo $nabysy->SQLToJSON($client->ChargeListe());
        break;

    case 'CLIENT_CREATE':
        if (!$nabysy->ValideUser()) exit;
        $client      = new xORMHelper($nabysy, null, true, "clients");
        $client->Nom = $_REQUEST['nom'];
        $client->Enregistrer();
        break;
}
```

### Mode 2 — Routage URL Laravel-style

Routage expressif basé sur l'URL, avec paramètres dynamiques, contraintes et middlewares. Déclaré via `GenerateUrlRouteController()`.

#### Déclaration d'un contrôleur de route

```bash
# Via CLI
koro create route client client
# ou avec le module complet
koro create categorie client -a -o -t clients
```

```php
// db_structure.php
N::$GSModManager::GenerateUrlRouteController("client", "client");
```

#### Définir des routes dans le contrôleur

```php
// gs/client/ClientRouteController.php

$router->get('/clients', 'CLIENT_LIST');
$router->get('/clients/{id}', 'CLIENT_SHOW');
$router->post('/clients', 'CLIENT_CREATE');
$router->put('/clients/{id}', 'CLIENT_UPDATE');
$router->delete('/clients/{id}', 'CLIENT_DELETE');

// Avec contrainte sur le paramètre
$router->get('/clients/{id}', 'CLIENT_SHOW')->where('id', '[0-9]+');

// Route optionnelle
$router->get('/clients/{id?}', 'CLIENT_LIST_OR_SHOW');

// Groupe de routes avec préfixe
$router->prefix('/api/v1')->group(function($router) {
    $router->get('/clients', 'CLIENT_LIST');
    $router->get('/produits', 'PRODUIT_LIST');
});
```

#### Récupérer les paramètres d'URL dans l'action

```php
// gs/client/client_action.php
case 'CLIENT_SHOW':
    $id     = $nabysy->RouteParam('id');
    $client = new xORMHelper($nabysy, $id, true, "clients");
    echo json_encode($client->ToObject());
    break;
```

### Middlewares

Les middlewares sont appliqués **automatiquement** par défaut (authentification JWT, CORS, etc.). Le développeur peut les personnaliser :

```php
// Appliquer un middleware sur une route spécifique
$router->get('/clients', 'CLIENT_LIST')->middleware('auth');

// Appliquer sur un groupe
$router->middleware('auth')->group(function($router) {
    $router->post('/clients', 'CLIENT_CREATE');
    $router->put('/clients/{id}', 'CLIENT_UPDATE');
    $router->delete('/clients/{id}', 'CLIENT_DELETE');
});

// Route publique (sans middleware)
$router->get('/clients/public', 'CLIENT_PUBLIC_LIST')->withoutMiddleware('auth');
```

### Coexistence des deux modes

Les deux modes fonctionnent **simultanément** dans le même projet. Le framework détecte automatiquement le type de requête et dispatch vers le bon handler.

```
GET  /clients/5           → Routage URL    → CLIENT_SHOW
GET  /?action=CLIENT_LIST → Routage Action → CLIENT_LIST
```

---

## 📋 Documentation des Routes — `/api/describe`

NAbySyGS génère automatiquement une **documentation interactive de vos routes URL** accessible via un endpoint dédié, sans aucune configuration supplémentaire.

### Accès

```
# Version JSON brute (authentifiée)
GET  ENDPOINT/api/describe

# Version web interactive (authentifiée)
GET  ENDPOINT/api/describe?HTML=1
```

L'endpoint est disponible **dès le premier lancement**, même si `db_structure.php` est vide. Les routes internes du framework y figurent toujours à titre de référence.

> La documentation est pour l'instant limitée aux routes déclarées via le routage URL (`GenerateUrlRouteController`).

### Authentification

L'accès à `/api/describe` (JSON ou HTML) est protégé par les **credentials de la table utilisateur** créée automatiquement lors du setup initial. Des accès supplémentaires peuvent être ajoutés directement dans cette table.

### Version Web Interactive (`?HTML=1`)

La page web propose :

* **Vue structurée** de toutes les routes enregistrées (méthode HTTP, chemin, action, middlewares)
* **Ajout de titres et commentaires** par route, pour documenter le rôle de chaque endpoint
* **Export JSON** — pour sauvegarder la description annotée sur votre support de stockage
* **Export PDF** — pour produire une documentation imprimable, générée côté navigateur
* **Import JSON** — pour recharger une description précédemment exportée et retrouver vos annotations

> Les annotations (titres, commentaires) ne sont pas stockées côté serveur. C'est l'utilisateur qui gère ses exports/imports, garantissant une totale portabilité sans aucune dépendance serveur.

### Exemple — Réponse JSON

```json
{
    "routes": [
        {
            "method": "GET",
            "path": "/clients",
            "action": "CLIENT_LIST",
            "middlewares": ["auth"]
        },
        {
            "method": "GET",
            "path": "/clients/{id}",
            "action": "CLIENT_SHOW",
            "middlewares": ["auth"],
            "constraints": { "id": "[0-9]+" }
        },
        {
            "method": "POST",
            "path": "/clients",
            "action": "CLIENT_CREATE",
            "middlewares": ["auth"]
        }
    ]
}
```

### Avantage par rapport aux autres solutions

Doctrine et Eloquent ne fournissent aucune documentation d'API intégrée — il faut intégrer des outils tiers comme Swagger/OpenAPI, configurer des annotations et maintenir un fichier YAML séparé. Avec NAbySyGS, la documentation est **vivante, toujours à jour et disponible immédiatement**, avec une interface web prête à l'emploi et un système d'export/import sans infrastructure supplémentaire.

---

## 📖 ORM — Opérations CRUD

### Créer

```php
$produit        = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom   = "Laptop Dell";
$produit->Prix  = 550000;
$produit->Stock = 10;
$produit->Enregistrer();
```

### Lire

```php
// Par ID
$produit = new xORMHelper($nabysy, 5, true, "produits");
echo $produit->Nom;

// Liste avec critères
$liste = $produit->ChargeListe(
    "Prix > 100000 AND Stock > 0",  // Critère WHERE
    "Nom ASC",                       // ORDER BY
    "*",                             // SELECT
    null,                            // GROUP BY
    "10"                             // LIMIT
);

while ($row = $liste->fetch_assoc()) {
    echo $row['Nom'];
}
```

### Mettre à Jour

```php
$produit       = new xORMHelper($nabysy, 5, true, "produits");
$produit->Prix = 500000;
$produit->Enregistrer();
```

### Supprimer

```php
$produit = new xORMHelper($nabysy, 5, true, "produits");
$produit->Supprimer();
```

### Conversions de Données

```php
$json  = $produit->ToJSON();
$obj   = $produit->ToObject();
$array = $produit->ToArrayAssoc();
```

### Clonage d'Enregistrements

```php
$produitOriginal = new xORMHelper($nabysy, 5, true, "produits");

// Cloner dans la même base
$clone = $produitOriginal->Clone();

// Cloner vers une autre base
$clone = $produitOriginal->Clone("autre_base");
```

### Jointures entre Entités

NAbySyGS propose une **API de jointure fluente et chainable** directement sur les classes ORM, sans écrire une seule ligne de SQL. Là où Doctrine exige des annotations de relation et Eloquent impose de déclarer `belongsTo` / `hasMany` dans chaque Model, NAbySyGS permet de joindre n'importe quelles deux entités à la volée, même créées dynamiquement.

```php
$nabysy = xNAbySyGS::getInstance();
$Pays   = new xPays($nabysy);
$Maison = new xMaison($nabysy);

$Rep     = new xNotification();
$Rep->OK = 1;

$Lst = $Maison
    ->JoinTable($Pays, null, "IdPays", "ID")  // Maison.IdPays = Pays.ID
    ->JointureChargeListe();                   // exécution de la requête jointe

$Rep->Contenue = $nabysy->SQLToJSON($Lst);
echo json_encode($Rep);
```

**Signature de `JoinTable()` :**

```
JoinTable(
    object  $EntiteJointe,  // Instance de l'entité à joindre
    ?string $critere,       // Critère WHERE supplémentaire (null = aucun)
    string  $champLocal,    // Champ de la table principale (clé étrangère)
    string  $champJoint     // Champ de la table jointe (clé primaire cible)
) : $this
```

La méthode retourne `$this`, permettant de **chaîner plusieurs jointures** successives :

```php
$liste = $Commande
    ->JoinTable($Client,   null, "IdClient",  "ID")
    ->JoinTable($Boutique, null, "IdBoutique", "ID")
    ->JointureChargeListe();
```

#### Comparaison jointures avec autres ORM

| Feature | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---|---|---|---|---|
| **Jointure sans config préalable** | ✅ À la volée | ❌ Annotations requises | ❌ Relations à déclarer | ⚠️ Partiel |
| **Chainable** | ✅ Oui | ✅ DQL | ✅ Oui | ❌ Non |
| **Tables dynamiques joignables** | ✅ Oui | ❌ Non | ❌ Non | ❌ Non |
| **Sans écrire de SQL** | ✅ Oui | ⚠️ DQL nécessaire | ✅ Oui | ⚠️ Partiel |

> Aucune relation ne doit être déclarée à l'avance : la jointure s'exprime directement là où vous en avez besoin, ce qui est particulièrement puissant sur des tables créées dynamiquement.

---

## 🔐 Authentification JWT

### Connexion

```php
use NAbySy\xUser;
use NAbySy\xAuth;

$user = new xUser($nabysy, null, true, "utilisateur", $_REQUEST['Login']);

if ($user->CheckPassword($_REQUEST['Password'])) {
    $auth  = new xAuth($nabysy, 3600); // Token valide 1h
    $token = $auth->GetToken($user);

    echo json_encode([
        'OK'    => 1,
        'Token' => $token,
        'User'  => $user->ToObject()
    ]);
}
```

### Protection des Routes

```php
// Au début de votre action
if (!$nabysy->ValideUser()) {
    // Retourne automatiquement une erreur 401
    exit;
}
```

### Configuration Session

```php
// Définir une session de 24h (86400 secondes)
N::SetAuthSessionTime(86400);
```

---

## 🎭 Système d'Événements Unique

NAbySyGS intègre un système d'événements innovant qui se démarque des autres ORM PHP par sa **simplicité et sa puissance**.

### Comparaison des Événements avec Autres ORM

| Feature | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---|---|---|---|---|
| **Events automatiques** | ✅ Sur toutes les tables | ⚠️ Si configuré | ⚠️ Si Model défini | ❌ Non |
| **Observer centralisé** | ✅ Multi-tables | ❌ Un par Entity | ⚠️ Un par Model | ❌ Non |
| **Tables dynamiques** | ✅ Événements auto | ❌ Non | ❌ Non | ❌ Non |
| **Référence modifiable** | ✅ Oui (`&$EventArg`) | ✅ Oui | ✅ Oui | — |
| **Événements custom** | ✅ `RaiseEvent()` | ✅ EventManager | ⚠️ Limité | ❌ Non |
| **Cross-table events** | ✅ Un observer pour tout | ⚠️ Config complexe | ⚠️ Difficile | ❌ Non |

### Avantages Clés du Système d'Événements NAbySyGS

#### 1. Événements Automatiques sur TOUTES les Tables

```php
// Aucune configuration nécessaire !
$produit      = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom = "Laptop";
$produit->Enregistrer(); // ✨ Déclenche automatiquement "xProduit_ADD"

// Eloquent nécessite de déclarer chaque Model
class Produit extends Model {
    protected static function boot() {
        parent::boot();
        static::created(function($produit) {
            // Configuration manuelle requise
        });
    }
}
```

#### 2. Observer Centralisé Multi-Tables

```php
// Un seul observateur pour plusieurs entités !
class xObservStock extends xObservGen {
    public function __construct($nabysy) {
        parent::__construct($nabysy, 'ObservStock', [
            'xProduit_EDIT',   // Observer les produits
            'xCommande_ADD',   // Observer les commandes
            'xVente_ADD',      // Observer les ventes
        ]);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        switch ($EventType[0]) {
            case 'xProduit_EDIT':
                $this->checkStock($EventType[1]);
                break;
            case 'xCommande_ADD':
                $this->updateInventory($EventType[1]);
                break;
        }
    }
}
```

**Autres ORM** : Nécessitent un Observer/Subscriber par entité.

#### 3. Support des Tables Créées Dynamiquement

```php
// Créer une nouvelle table à la volée
$nouvelleEntite         = new xORMHelper($nabysy, null, true, "ma_nouvelle_table");
$nouvelleEntite->Champ1 = "Valeur";
$nouvelleEntite->Enregistrer();
// ✨ Déclenche automatiquement "xMaNouvelleTable_ADD" !

// Vos observateurs existants l'attrapent sans modification de code
```

**Aucun autre ORM ne permet cela !** Doctrine et Eloquent nécessitent de créer Entity/Model, configurer les événements, puis redéployer.

#### 4. Observateur Global "Catch-All"

```php
class xObservAudit extends xObservGen {
    public function __construct($nabysy) {
        parent::__construct($nabysy, 'AuditGlobal', [
            '*_ADD',   // Toutes les créations
            '*_EDIT',  // Toutes les modifications
            '*_DEL',   // Toutes les suppressions
        ]);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $audit              = new xORMHelper($this->Main, null, true, "audit_log");
        $audit->Table       = $ClassName;
        $audit->Action      = $EventType[0];
        $audit->IdRecord    = $EventType[1];
        $audit->Date        = date('Y-m-d H:i:s');
        $audit->Utilisateur = $this->Main->User->Login ?? 'SYSTEM';
        $audit->Enregistrer();
    }
}
```

**Impossible avec cette simplicité sur Doctrine ou Eloquent !**

#### 5. Événements avec Contexte Complet et Modifiable

```php
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    $action = $EventType[0];  // Type d'événement
    $id     = $EventType[1];  // ID de l'enregistrement
    $objet  = $EventType[2];  // ✨ Objet complet avec toutes les données

    // Vous pouvez MODIFIER l'objet dans l'observateur !
    if ($objet->Prix < 0) {
        $objet->Prix = 0; // Correction automatique
    }

    // Enrichir automatiquement les données
    if (empty($objet->DateCreation)) {
        $objet->DateCreation = date('Y-m-d H:i:s');
    }
}
```

**Utilisations pratiques :** validation automatique, enrichissement de données, normalisation, calculs automatiques, audit trail complet.

### Créer un Observateur

#### 1. Créer la Classe Observateur

Créez un fichier dans `gs/votre_module/xObservVotreModule/xObservVotreModule.class.php` :

```php
<?php
namespace NAbySy\OBSERVGEN;

use NAbySy\xNAbySyGS;

class xObservProduit extends xObservGen {

    public function __construct(xNAbySyGS $NabySyGS) {
        $listeObservable = [
            'xProduit_ADD',
            'xProduit_EDIT',
            'xProduit_DEL',
        ];

        parent::__construct($NabySyGS, 'ObservateurProduit', $listeObservable);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $action = $EventType[0] ?? null;

        switch ($action) {
            case 'xProduit_ADD':
                $this->onProduitCreated($EventType[1]);
                break;
            case 'xProduit_EDIT':
                $this->onProduitUpdated($EventType[1], $EventType[2] ?? null);
                break;
            case 'xProduit_DEL':
                $this->onProduitDeleted($EventType[1], $EventType[2] ?? null);
                break;
        }
    }

    private function onProduitCreated($idProduit) {
        $this->Main::$Log->Write("Nouveau produit créé : ID $idProduit");
        $this->Main->AddToJournal('SYSTEME', 0, 'PRODUIT_CREATED',
            "Nouveau produit ajouté avec ID: $idProduit");
    }

    private function onProduitUpdated($idProduit, $produit) {
        if (!$produit) return;
        $this->Main::$Log->Write("Produit modifié : {$produit->Nom} (ID: $idProduit)");

        if ($produit->Stock < 10) {
            $this->alertLowStock($produit);
        }

        $this->Main->AddToJournal('SYSTEME', 0, 'PRODUIT_UPDATED',
            "Produit {$produit->Nom} modifié");
    }

    private function onProduitDeleted($idProduit, $produit) {
        $this->Main::$Log->Write("Produit supprimé : ID $idProduit");
        $this->Main->AddToJournal('SYSTEME', 0, 'PRODUIT_DELETED',
            "Produit ID $idProduit supprimé");
    }

    private function alertLowStock($produit) {
        $message = "Alerte stock : {$produit->Nom} - Stock: {$produit->Stock}";
        $this->Main::$Log->Write($message);
        // $this->Main::$SMSEngine->EnvoieSMS('+221771234567', $message);
    }
}
```

#### 2. Activer l'Observateur

L'observateur est chargé automatiquement au démarrage de NAbySyGS. Assurez-vous que votre classe :

* Hérite de `xObservGen`
* Est dans un dossier `xObserv*` sous votre module
* Implémente la méthode `RaiseEvent()`

### Exemple Complet : Système de Notifications pour les Commandes

```php
<?php
namespace NAbySy\OBSERVGEN;

use NAbySy\xNAbySyGS;
use NAbySy\ORM\xORMHelper;

class xObservCommande extends xObservGen {

    public function __construct(xNAbySyGS $NabySyGS) {
        parent::__construct($NabySyGS, 'ObservateurCommande', [
            'xCommande_ADD',
            'xCommande_EDIT',
        ]);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $action     = $EventType[0];
        $idCommande = $EventType[1];
        $commande   = $EventType[2] ?? null;

        switch ($action) {
            case 'xCommande_ADD':
                $this->onNewOrder($idCommande);
                break;
            case 'xCommande_EDIT':
                $this->onOrderStatusChanged($commande);
                break;
        }
    }

    private function onNewOrder($idCommande) {
        $commande = new xORMHelper($this->Main, $idCommande, false, "commandes");
        $client   = new xORMHelper($this->Main, $commande->IdClient, false, "clients");

        // Notifier le client
        $message = "Bonjour {$client->Nom}, votre commande #{$commande->Id} a été enregistrée.";
        if ($this->Main::$SMSEngine) {
            $this->Main::$SMSEngine->EnvoieSMS($client->Telephone, $message);
        }

        $this->Main::$Log->Write("Notification client envoyée : Commande #{$commande->Id}");

        // Mettre à jour les statistiques
        $this->updateStats($commande);

        // Déclencher le workflow
        $this->triggerWorkflow($commande);
    }

    private function onOrderStatusChanged($commande) {
        if ($commande->Statut === 'Livrée') {
            $this->sendDeliveryConfirmation($commande);
            $this->requestReview($commande);
        }

        if ($commande->Statut === 'Annulée') {
            $this->restoreStock($commande);
        }
    }

    private function updateStats($commande) {
        $stats = new xORMHelper($this->Main, null, true, "statistiques_ventes");
        $today = date('Y-m-d');

        $existing = $stats->ChargeListe("Date = '$today'");
        if ($existing->num_rows > 0) {
            $row   = $existing->fetch_assoc();
            $stats = new xORMHelper($this->Main, $row['ID'], true, "statistiques_ventes");
            $stats->NombreCommandes += 1;
            $stats->MontantTotal    += $commande->MontantTotal;
        } else {
            $stats->Date            = $today;
            $stats->NombreCommandes = 1;
            $stats->MontantTotal    = $commande->MontantTotal;
        }
        $stats->Enregistrer();
    }

    private function triggerWorkflow($commande) {
        $tache               = new xORMHelper($this->Main, null, true, "taches");
        $tache->Titre        = "Préparer commande #{$commande->Id}";
        $tache->Type         = "Préparation";
        $tache->IdCommande   = $commande->Id;
        $tache->Statut       = "En attente";
        $tache->DateCreation = date('Y-m-d H:i:s');
        $tache->Enregistrer();
    }

    private function restoreStock($commande) {
        $lignes = new xORMHelper($this->Main, null, false, "ligne_commande");
        $liste  = $lignes->ChargeListe("IdCommande = {$commande->Id}");

        while ($ligne = $liste->fetch_assoc()) {
            $produit        = new xORMHelper($this->Main, $ligne['IdProduit'], false, "produits");
            $produit->Stock += $ligne['Quantite'];
            $produit->Enregistrer();
        }
        $this->Main::$Log->Write("Stock restauré pour commande annulée #{$commande->Id}");
    }
}
```

### Événements Disponibles

Le framework déclenche automatiquement :

* **`{CLASS}_ADD`** — Après création d'un enregistrement
* **`{CLASS}_EDIT`** — Après modification d'un enregistrement
* **`{CLASS}_DEL`** — Après suppression d'un enregistrement

Où `{CLASS}` est le nom de votre table (ex : `xProduit_ADD`, `xClient_EDIT`).

### Cas d'Usage Pratiques

#### 1. Synchronisation Automatique avec API Externe

```php
private function onProduitCreated($idProduit) {
    $produit  = new xORMHelper($this->Main, $idProduit, false, "produits");
    $curl     = $this->Main::$CURL;
    $response = $curl->Post('https://api-externe.com/products', [
        'nom'   => $produit->Nom,
        'prix'  => $produit->Prix,
        'stock' => $produit->Stock,
    ]);

    if ($response['success']) {
        $produit->IdExterne = $response['id'];
        $produit->Enregistrer();
    }
}
```

#### 2. Alertes Automatiques de Stock Faible

```php
private function onProduitUpdated($produit) {
    if ($produit->Stock < 10) {
        $message = "ALERTE: Stock faible pour {$produit->Nom} (Stock: {$produit->Stock})";
        $this->Main::$SMSEngine->EnvoieSMS('+221771234567', $message);
        $this->Main::$Log->Write($message);
    }
}
```

#### 3. Mise à Jour Automatique de Statistiques

```php
private function onVenteCreated($idVente) {
    $vente    = new xORMHelper($this->Main, $idVente, false, "ventes");
    $stats    = new xORMHelper($this->Main, null, true, "stats_ventes");
    $today    = date('Y-m-d');
    $existing = $stats->ChargeListe("Date = '$today'");

    if ($existing->num_rows > 0) {
        $row   = $existing->fetch_assoc();
        $stats = new xORMHelper($this->Main, $row['ID'], true, "stats_ventes");
        $stats->NombreVentes += 1;
        $stats->MontantTotal += $vente->Montant;
    } else {
        $stats->Date         = $today;
        $stats->NombreVentes = 1;
        $stats->MontantTotal = $vente->Montant;
    }
    $stats->Enregistrer();
}
```

### Déclencher Manuellement un Événement

```php
use NAbySy\xNAbySyGS;

class MaClasseMetier {
    private $nabysy;

    public function __construct(xNAbySyGS $nabysy) {
        $this->nabysy = $nabysy;
    }

    public function faireQuelqueChose() {
        // ... votre logique ...

        $this->nabysy::RaiseEvent('MaClasseMetier', [
            'MonEvenement_CUSTOM',
            123,
            'Information supplémentaire'
        ]);
    }
}
```

### Désactiver un Observateur

```php
// Dans votre observateur
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    if (!$this->State()) return;
    // ... logique normale ...
}

$observateur->State(false); // Désactiver
$observateur->State(true);  // Activer
```

### Debug des Événements

```php
$nabysy->ActiveDebug = true;

public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    if ($this->Main->ActiveDebug) {
        $this->Main::$Log->Write("Événement déclenché : " . json_encode($EventType));
    }
    // ... logique ...
}
```

### Bonnes Pratiques avec les Événements

1. **Nommage cohérent** : Utilisez `{Classe}_{Action}` pour les événements
2. **Logging** : Toujours logger les actions importantes
3. **Performances** : Évitez les traitements lourds dans les observateurs
4. **Async** : Pour les traitements longs, utilisez des queues (Beanstalkd, RabbitMQ)
5. **Test** : Créez des observateurs de test pour vérifier le déclenchement
6. **Documentation** : Documentez les événements disponibles dans votre module

---

## 🛠️ Modules Intégrés

### Module Boutique

```php
use NAbySy\GS\Boutique\xBoutique;

$boutique = new xBoutique($nabysy, 1);
echo $boutique->Nom;
$liste = $boutique->getListeBoutique();
```

### Module Stock

```php
use NAbySy\GS\Stock\xProduit;

$produit              = new xProduit($nabysy, 10);
$produit->Designation = "Smartphone";
$produit->PrixVente   = 150000;
$produit->Enregistrer();
```

### Module Facture

```php
use NAbySy\GS\Facture\xVente;

$vente               = new xVente($nabysy);
$vente->IdClient     = 5;
$vente->MontantTotal = 500000;
$vente->Enregistrer();
```

---

## 📚 Exemples Complets

### API Gestion de Produits

```php
// gs/produit/produit_action.php
use NAbySy\ORM\xORMHelper;
use NAbySy\xNotification;

switch ($action) {
    case 'PRODUIT_CREATE':
        if (!$nabysy->ValideUser()) exit;

        $produit        = new xORMHelper($nabysy, null, true, "produits");
        $produit->Nom   = $_REQUEST['nom'];
        $produit->Prix  = $_REQUEST['prix'];
        $produit->Stock = $_REQUEST['stock'] ?? 0;

        if ($produit->Enregistrer()) {
            $rep           = new xNotification();
            $rep->Contenue = $produit->ToObject();
            echo json_encode($rep);
        }
        break;

    case 'PRODUIT_SEARCH':
        $produit   = new xORMHelper($nabysy, null, true, "produits");
        $recherche = $_REQUEST['q'] ?? '';

        $liste = $produit->ChargeListe(
            "Nom LIKE '%$recherche%'",
            "Nom ASC", "*", null, "20"
        );
        echo $nabysy->SQLToJSON($liste);
        break;
}
```

### Appel API (curl)

```bash
# Créer un client
curl -X POST https://votre-domaine.com/gs_api.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "CLIENT_CREATE",
    "nom": "Dupont",
    "telephone": "771234567",
    "Token": "votre_token_jwt"
  }'
```

---

## 🔧 Configuration Avancée

### Mode Debug

```php
$nabysy->ActiveDebug = true;
N::SetShowDebug(true, E_ALL);
```

### Ignorer Certaines Requêtes dans les Logs

```php
N::$RequetteToIgnoreInLOG[] = 'SELECT';
N::$RequetteToIgnoreInLOG[] = 'UPDATE';
```

### Personnalisation du Dossier Racine

```php
$nabysy = N::Init(
    "MonApp", "Ma Société", "Adresse", "Téléphone",
    "ma_base", "nabysygs", "localhost", "root", "", 3306,
    "mon-dossier"
);
```

---

## 🔄 Mise à Jour de la Structure via `db update`

La CLI NAbySyGS fournit une commande `db update` (alias `koro db u`) qui appelle l'API du projet pour synchroniser la structure de base de données avec `db_structure.php`.

```bash
koro db update
# ou avec URL explicite
koro db update --url http://monapi.local
```

Cette commande est appelée **automatiquement** après chaque `koro create`. Elle peut aussi être invoquée manuellement à tout moment pour forcer la synchronisation.

> L'URL de l'API est lue automatiquement depuis `__SERVER_URL__` dans `appinfos.php`. Vous pouvez la surcharger avec `--url`.

---

## ⚖️ Comparaison avec d'autres ORM PHP

### ✅ Avantages de NAbySyGS

#### 1. Simplicité et Rapidité de Développement

```php
// NAbySyGS — 4 lignes
$produit       = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom  = "Laptop";
$produit->Prix = 550000;
$produit->Enregistrer();

// Doctrine — Configuration complexe nécessaire
// - Entities avec annotations / mapping XML/YAML
// - Configuration du EntityManager
// - Commandes doctrine:schema
```

#### 2. Auto-création de Tables et Champs

* 🚀 **Aucune migration nécessaire** — Les tables et champs se créent automatiquement
* 🎯 **Zero configuration** — Pas besoin de définir des schémas
* ⚡ **Prototypage ultra-rapide** — Concentrez-vous sur la logique métier

```php
$user->DateNaissance = '1990-01-01'; // Champ créé automatiquement en base !
$user->Enregistrer();
```

#### 3. Jointures à la Volée

```php
// NAbySyGS — aucune configuration préalable
$liste = $Maison->JoinTable($Pays, null, "IdPays", "ID")->JointureChargeListe();

// Eloquent — déclaration obligatoire dans le Model
class Maison extends Model {
    public function pays() { return $this->belongsTo(Pays::class, 'IdPays'); }
}
```

#### 4. API REST + Documentation Intégrées

* 🎁 Structure d'API fournie "out of the box"
* 🔐 Authentification JWT intégrée
* 🌐 CORS géré automatiquement
* 📋 Documentation des routes via `/api/describe`, avec export JSON/PDF
* 📝 Journalisation système incluse

#### 5. Pas de Dépendances Complexes

* ✅ Fonctionne avec PHP 8.1+ et MySQLi natif
* ✅ Pas besoin de Symfony, Laravel ou autre framework
* ✅ Package autonome et léger (~2MB)

### ❌ Limitations

| Fonctionnalité | NAbySyGS | Doctrine | Eloquent |
|---|---|---|---|
| Relations complexes (Many-to-Many) | ⚠️ Manuel | ✅ Automatique | ✅ Automatique |
| Lazy Loading | ❌ Non | ✅ Oui | ✅ Oui |
| Query Builder avancé | ⚠️ Basique | ✅ DQL | ✅ Fluent |
| Transactions complexes | ⚠️ Manuel | ✅ UnitOfWork | ✅ Oui |
| Caching sophistiqué | ❌ Non | ✅ 2nd level cache | ✅ Query cache |

### 📊 Tableau Récapitulatif

| Critère | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---|---|---|---|---|
| **Facilité** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Performance** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Fonctionnalités** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |
| **Auto-création tables** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ⭐⭐⭐⭐⭐ |
| **Jointures sans config** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ⚠️ |
| **Routage URL intégré** | ⭐⭐⭐⭐⭐ | ❌ | ⭐⭐⭐ | ❌ |
| **Double routage** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ❌ |
| **API Intégrée** | ⭐⭐⭐⭐⭐ | ❌ | ⭐⭐⭐ | ❌ |
| **Doc routes intégrée** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ❌ |
| **Setup HTML auto** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ❌ |
| **Communauté** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Setup** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

### 🎯 Quand Utiliser NAbySyGS ?

1. **Prototypes et MVPs Rapides** — Lancer une API en quelques heures, tester des idées rapidement
2. **Petites et Moyennes Applications** — < 200 tables, < 500 000 enregistrements/table, équipe de 1-50 développeurs
3. **Applications Métier Internes** — ERP légers, stocks, factures, clients, outils administratifs
4. **Projets Sans Infrastructure DevOps** — Hébergement mutualisé, pas de CLI disponible, environnement simple
5. **Développeurs Débutants en ORM** — Courbe d'apprentissage douce, résultats immédiats

### 🔄 Migration vers NAbySyGS

#### Depuis Eloquent (Laravel)

```php
// Avant
$produits = Produit::where('prix', '>', 1000)->orderBy('nom')->limit(10)->get();

// Après
$produit  = new xORMHelper($nabysy, null, true, "produits");
$produits = $produit->ChargeListe("prix > 1000", "nom ASC", "*", null, "10");
```

#### Depuis Doctrine

```php
// Avant
$produit = $entityManager->getRepository(Produit::class)->find(1);
$produit->setPrix(5000);
$entityManager->flush();

// Après
$produit       = new xORMHelper($nabysy, 1, true, "produits");
$produit->Prix = 5000;
$produit->Enregistrer();
```

---

## 📝 Structure de Réponse API

### Succès

```json
{
    "OK": 1,
    "TxErreur": "",
    "Source": "produit_action.php",
    "Contenue": {
        "Id": 15,
        "Nom": "Laptop Dell",
        "Prix": 550000
    }
}
```

### Erreur

```json
{
    "OK": 0,
    "TxErreur": "Produit introuvable",
    "Source": "produit_action.php",
    "Extra": "Vérifiez l'ID fourni"
}
```

---

## 🎯 Bonnes Pratiques

1. **Toujours utiliser `ValideUser()`** pour protéger vos endpoints sensibles
2. **Nommer vos actions** en MAJUSCULES avec préfixe module : `PRODUIT_CREATE`
3. **Utiliser `xNotification`** pour les réponses réussies, `xErreur` pour les erreurs
4. **Activer le debug** uniquement en développement
5. **Versionner** votre fichier `appinfos.php` pour la configuration personnalisée
6. **Créer des observateurs** pour automatiser les tâches répétitives
7. **Logger les événements importants** pour faciliter le débogage
8. **Utiliser `koro db update`** après toute modification manuelle de `db_structure.php`
9. **Consulter `/api/describe`** pour vérifier que toutes vos routes URL sont bien enregistrées

---

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Proposer des suggestions via les Issues
2. Créer une branche (`git checkout -b feature/amelioration`)
3. Committer vos changements (`git commit -m 'Ajout fonctionnalité'`)
4. Pusher sur la branche (`git push origin feature/amelioration`)
5. Ouvrir une Pull Request

---

## 📄 Licence

MIT License — voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 👨‍💻 Auteur

**Paul Isidore A. NIAMIE**

* Email : [paul.isidore@gmail.com](mailto:paul.isidore@gmail.com)
* Website : [https://groupe-pam.net](https://groupe-pam.net)

---

## 🙏 Remerciements

Développé par **Paul & Aïcha Machinerie (PAM)** et **Micro Computer Programme (MCP)**.

---

⭐ Si ce projet vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !