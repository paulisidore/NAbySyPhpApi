# NAbySyGS - Framework PHP avec ORM Intégré

[![Latest Version](https://img.shields.io/packagist/v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![Total Downloads](https://img.shields.io/packagist/dt/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![License](https://img.shields.io/packagist/l/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)
[![PHP Version](https://img.shields.io/packagist/php-v/nabysyphpapi/xnabysygs.svg)](https://packagist.org/packages/nabysyphpapi/xnabysygs)

**NAbySyGS** est un framework PHP moderne conçu par **PAM & MCP** pour faciliter la création rapide d'API REST pour vos applications. Il intègre un ORM personnalisé avec création automatique de tables et champs, un système d'authentification JWT, et une architecture modulaire.

## ✨ Fonctionnalités

- 🚀 **ORM Automatique** - Création automatique des tables et champs MySQL/MariaDB
- 🔐 **Authentification JWT** - Système de tokens sécurisés intégré
- 📦 **Architecture Modulaire** - Organisation en modules avec auto-chargement
- 🎯 **Type-Safe** - Détection automatique des types de données (INT, VARCHAR, DATE...)
- 🔄 **Gestion d'Événements** - Pattern Observer pour réagir aux changements
- 🛠️ **Modules Métier** - Gestion de boutiques, stocks, factures, clients...
- 🌐 **CORS Ready** - Gestion automatique des requêtes cross-origin
- 📝 **Logs Intégrés** - Journalisation système et débogage

## 📋 Prérequis

- PHP >= 8.1.0
- MySQL ou MariaDB
- Extension PHP: `mysqli`, `mbstring`, `json`
- Composer

## 📦 Installation

### Via Composer

```bash
composer require nabysyphpapi/xnabysygs
```

### Structure Générée

```
votre-projet/
├── vendor/
│   └── nabysyphpapi/xnabysygs/
├── gs/                    # Modules personnalisés (créés automatiquement)
├── appinfos.php          # Configuration (créé automatiquement)
├── .htaccess             # Redirection API (créé automatiquement)
└── index.php             # Point d'entrée
```

## 🚀 Démarrage Rapide

### 1. Configuration Initiale

Créez un fichier `index.php` à la racine :

```php
<?php
require 'vendor/autoload.php';

use NAbySy\xNAbySyGS as N;

// Initialisation
$nabysy = N::Init(
    "MonApp",              // Nom de l'application
    "Ma Société SARL",     // Nom du client
    "123 Rue Example",     // Adresse
    "+221 33 123 45 67",   // Téléphone
    "ma_base",            // Base de données
    "nabysygs",           // Base master
    "localhost",          // Serveur MySQL
    "root",               // Utilisateur
    "",                   // Mot de passe
    3306                  // Port
);

// Mode debug (développement uniquement)
N::SetShowDebug(true);

// Traiter les requêtes HTTP
N::ReadHttpRequest();
```

### 2. Créer Votre Premier Module

```php
<?php
use NAbySy\xNAbySyGS as N;

// Créer un module "client" avec action API et classe ORM
N::$GSModManager::CreateCategorie(
    "client",        // Nom du module
    true,           // Créer fichier action
    true,           // Créer classe ORM
    "clients"       // Nom de la table
);
```

Cela génère automatiquement :
- `gs/client/client_action.php` - Endpoints API
- `gs/client/xClient/xClient.class.php` - Classe ORM

### 3. Utiliser l'ORM

```php
<?php
use NAbySy\ORM\xORMHelper;

$nabysy = N::getInstance();

// Créer un client
$client = new xORMHelper($nabysy, null, true, "clients");
$client->Nom = "Dupont";
$client->Prenom = "Jean";
$client->Email = "jean@example.com";
$client->Telephone = "771234567";
$client->Enregistrer(); // Sauvegarde

echo "Client créé avec ID: " . $client->Id;
```

## 📖 Documentation Complète

### ORM - Opérations CRUD

#### Créer

```php
<?php
$produit = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom = "Laptop Dell";
$produit->Prix = 550000;
$produit->Stock = 10;
$produit->Enregistrer();
```

#### Lire

```php
<?php
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

#### Mettre à Jour

```php
<?php
$produit = new xORMHelper($nabysy, 5, true, "produits");
$produit->Prix = 500000;
$produit->Enregistrer();
```

#### Supprimer

```php
<?php
$produit = new xORMHelper($nabysy, 5, true, "produits");
$produit->Supprimer();
```

### Conversions de Données

```php
<?php
// Vers JSON
$json = $produit->ToJSON();

// Vers Object PHP
$obj = $produit->ToObject();

// Vers Array
$array = $produit->ToArrayAssoc();
```

### API REST

#### Structure d'une Action

Fichier `gs/client/client_action.php` :

```php
<?php
use NAbySy\xNotification;
use NAbySy\ORM\xORMHelper;

$action = $_REQUEST['action'] ?? null;

switch ($action) {
    case 'CLIENT_CREATE':
        // Vérification authentification
        if (!$nabysy->ValideUser()) exit;
        
        $client = new xORMHelper($nabysy, null, true, "clients");
        $client->Nom = $_REQUEST['nom'];
        $client->Telephone = $_REQUEST['telephone'];
        
        if ($client->Enregistrer()) {
            $rep = new xNotification();
            $rep->Contenue = $client->ToObject();
            echo json_encode($rep);
        }
        break;
        
    case 'CLIENT_LIST':
        $client = new xORMHelper($nabysy, null, true, "clients");
        $liste = $client->ChargeListe();
        echo $nabysy->SQLToJSON($liste);
        break;
}
```

#### Appel API

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

### Authentification JWT

#### Connexion

```php
<?php
use NAbySy\xUser;
use NAbySy\xAuth;

// Dans votre action auth
$user = new xUser($nabysy, null, true, "utilisateur", $_REQUEST['Login']);

if ($user->CheckPassword($_REQUEST['Password'])) {
    $auth = new xAuth($nabysy, 3600); // Token valide 1h
    $token = $auth->GetToken($user);
    
    echo json_encode([
        'OK' => 1,
        'Token' => $token,
        'User' => $user->ToObject()
    ]);
}
```

#### Protection des Routes

```php
<?php
// Au début de votre action
if (!$nabysy->ValideUser()) {
    // Retourne automatiquement une erreur 401
    exit;
}

// Code protégé...
```

#### Configuration Session

```php
<?php
// Définir une session de 24h (86400 secondes)
N::SetAuthSessionTime(86400);
```

## 🛠️ Modules Intégrés

### Module Boutique

```php
<?php
use NAbySy\GS\Boutique\xBoutique;

$boutique = new xBoutique($nabysy, 1);
echo $boutique->Nom;
$liste = $boutique->getListeBoutique();
```

### Module Stock

```php
<?php
use NAbySy\GS\Stock\xProduit;

$produit = new xProduit($nabysy, 10);
$produit->Designation = "Smartphone";
$produit->PrixVente = 150000;
$produit->Enregistrer();
```

### Module Facture

```php
<?php
use NAbySy\GS\Facture\xVente;

$vente = new xVente($nabysy);
$vente->IdClient = 5;
$vente->MontantTotal = 500000;
$vente->Enregistrer();
```

## 🔧 Configuration Avancée

### Mode Debug

```php
<?php
// Activer les logs SQL détaillés
$nabysy->ActiveDebug = true;
N::SetShowDebug(true, E_ALL);
```

### Ignorer Certaines Requêtes dans les Logs

```php
<?php
N::$RequetteToIgnoreInLOG[] = 'SELECT';
N::$RequetteToIgnoreInLOG[] = 'UPDATE';
```

### Personnalisation du Dossier Racine

```php
<?php
$nabysy = N::Init(
    "MonApp",
    "Ma Société",
    "Adresse",
    "Téléphone",
    "ma_base",
    "nabysygs",
    "localhost",
    "root",
    "",
    3306,
    "mon-dossier"  // Dossier racine personnalisé
);
```

## 📚 Exemples Complets

### API Gestion de Produits

```php
<?php
// gs/produit/produit_action.php
use NAbySy\ORM\xORMHelper;
use NAbySy\xNotification;

switch ($action) {
    case 'PRODUIT_CREATE':
        if (!$nabysy->ValideUser()) exit;
        
        $produit = new xORMHelper($nabysy, null, true, "produits");
        $produit->Nom = $_REQUEST['nom'];
        $produit->Prix = $_REQUEST['prix'];
        $produit->Stock = $_REQUEST['stock'] ?? 0;
        
        if ($produit->Enregistrer()) {
            $rep = new xNotification();
            $rep->Contenue = $produit->ToObject();
            echo json_encode($rep);
        }
        break;
        
    case 'PRODUIT_SEARCH':
        $produit = new xORMHelper($nabysy, null, true, "produits");
        $recherche = $_REQUEST['q'] ?? '';
        
        $liste = $produit->ChargeListe(
            "Nom LIKE '%$recherche%'",
            "Nom ASC",
            "*",
            null,
            "20"
        );
        
        echo $nabysy->SQLToJSON($liste);
        break;
}
```

### Clonage d'Enregistrements

```php
<?php
$produitOriginal = new xORMHelper($nabysy, 5, true, "produits");

// Cloner dans la même base
$clone = $produitOriginal->Clone();

// Cloner vers une autre base
$clone = $produitOriginal->Clone("autre_base");
```

### 🎭 Système d'Événements Unique

NAbySyGS intègre un système d'événements innovant qui se démarque des autres ORM PHP par sa **simplicité et sa puissance**.

#### Comparaison des Événements avec Autres ORM

| Feature | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---------|----------|----------|----------|------------|
| **Events automatiques** | ✅ Sur toutes les tables | ⚠️ Si configuré | ⚠️ Si Model défini | ❌ Non |
| **Observer centralisé** | ✅ Multi-tables | ❌ Un par Entity | ⚠️ Un par Model | ❌ Non |
| **Tables dynamiques** | ✅ Événements auto | ❌ Non | ❌ Non | ❌ Non |
| **Référence modifiable** | ✅ Oui (`&$EventArg`) | ✅ Oui | ✅ Oui | - |
| **Événements custom** | ✅ `RaiseEvent()` | ✅ EventManager | ⚠️ Limité | ❌ Non |
| **Cross-table events** | ✅ Un observer pour tout | ⚠️ Config complexe | ⚠️ Difficile | ❌ Non |

#### Avantages Clés du Système d'Événements NAbySyGS

##### 1. **Événements Automatiques sur TOUTES les Tables**

```php
// Aucune configuration nécessaire !
$produit = new xORMHelper($nabysy, null, true, "produits");
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

##### 2. **Observer Centralisé Multi-Tables**

```php
// Un seul observateur pour plusieurs entités !
class xObservStock extends xObservGen {
    public function __construct($nabysy) {
        parent::__construct($nabysy, 'ObservStock', [
            'xProduit_EDIT',    // Observer les produits
            'xCommande_ADD',    // Observer les commandes
            'xVente_ADD',       // Observer les ventes
        ]);
    }
    
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        // Gérer tous les événements dans une seule classe
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

**Autres ORM** : Nécessitent un Observer/Subscriber par entité

##### 3. **Support des Tables Créées Dynamiquement**

```php
// Jour 1 : Créer une nouvelle table à la volée
$nouvelleEntite = new xORMHelper($nabysy, null, true, "ma_nouvelle_table");
$nouvelleEntite->Champ1 = "Valeur";
$nouvelleEntite->Enregistrer(); 
// ✨ Déclenche automatiquement "xMaNouvelleTable_ADD" !

// Vos observateurs existants l'attrapent sans modification de code
```

**Aucun autre ORM ne permet cela !** Doctrine et Eloquent nécessitent de créer Entity/Model, configurer les événements, puis redéployer.

##### 4. **Observateur Global "Catch-All"**

```php
// Observer TOUTES les créations, modifications et suppressions
class xObservAudit extends xObservGen {
    public function __construct($nabysy) {
        parent::__construct($nabysy, 'AuditGlobal', [
            '*_ADD',    // Toutes les créations
            '*_EDIT',   // Toutes les modifications
            '*_DEL',    // Toutes les suppressions
        ]);
    }
    
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        // Audit trail automatique sur TOUTE l'application
        $audit = new xORMHelper($this->Main, null, true, "audit_log");
        $audit->Table = $ClassName;
        $audit->Action = $EventType[0];
        $audit->IdRecord = $EventType[1];
        $audit->Date = date('Y-m-d H:i:s');
        $audit->Utilisateur = $this->Main->User->Login ?? 'SYSTEM';
        $audit->Enregistrer();
    }
}
```

**Impossible avec cette simplicité sur Doctrine ou Eloquent !**

##### 5. **Événements avec Contexte Complet et Modifiable**

```php
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    $action = $EventType[0];      // Type d'événement
    $id = $EventType[1];          // ID de l'enregistrement
    $objet = $EventType[2];       // ✨ Objet complet avec toutes les données
    
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

**Utilisations pratiques :**
- ✅ Validation automatique
- ✅ Enrichissement de données
- ✅ Normalisation
- ✅ Calculs automatiques
- ✅ Audit trail complet

### Créer un Observateur

#### Exemple Complet : Système de Notifications

```php
<?php
namespace NAbySy\OBSERVGEN;

use NAbySy\xNAbySyGS;
use NAbySy\ORM\xORMHelper;

class xObservCommande extends xObservGen {
    
    public function __construct(xNAbySyGS $NabySyGS) {
        parent::__construct(
            $NabySyGS,
            'ObservateurCommande',
            ['xCommande_ADD', 'xCommande_EDIT']
        );
    }
    
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $action = $EventType[0];
        $idCommande = $EventType[1];
        $commande = $EventType[2] ?? null;
        
        switch ($action) {
            case 'xCommande_ADD':
                $this->onNewOrder($idCommande);
                break;
                
            case 'xCommande_EDIT':
                if ($commande && $commande->Statut === 'Livrée') {
                    $this->onOrderDelivered($commande);
                }
                break;
        }
    }
    
    private function onNewOrder($idCommande) {
        $commande = new xORMHelper($this->Main, $idCommande, false, "commandes");
        $client = new xORMHelper($this->Main, $commande->IdClient, false, "clients");
        
        // Notifier le client
        $message = "Commande #{$commande->Id} enregistrée avec succès";
        $this->Main::$SMSEngine->EnvoieSMS($client->Telephone, $message);
        
        // Journaliser
        $this->Main->AddToJournal('COMMANDE', 0, 'NEW_ORDER', 
            "Commande #{$idCommande} créée - Montant: {$commande->Montant}");
    }
    
    private function onOrderDelivered($commande) {
        // Envoyer confirmation de livraison
        $this->Main::$Log->Write("Commande #{$commande->Id} livrée");
    }
}
```

### Événements Disponibles

Le framework déclenche automatiquement :

- **`{CLASS}_ADD`** - Après création d'un enregistrement
- **`{CLASS}_EDIT`** - Après modification d'un enregistrement  
- **`{CLASS}_DEL`** - Après suppression d'un enregistrement

Où `{CLASS}` est le nom de votre table (ex: `xProduit_ADD`, `xClient_EDIT`)

### Cas d'Usage Pratiques

#### 1. Synchronisation Automatique avec API Externe

```php
private function onProduitCreated($idProduit) {
    $produit = new xORMHelper($this->Main, $idProduit, false, "produits");
    
    // Synchroniser avec système externe
    $curl = $this->Main::$CURL;
    $response = $curl->Post('https://api-externe.com/products', [
        'nom' => $produit->Nom,
        'prix' => $produit->Prix
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
        
        // Envoyer SMS aux responsables
        $this->Main::$SMSEngine->EnvoieSMS('+221771234567', $message);
        
        // Logger
        $this->Main::$Log->Write($message);
    }
}
```

#### 3. Mise à Jour Automatique de Statistiques

```php
private function onVenteCreated($idVente) {
    $vente = new xORMHelper($this->Main, $idVente, false, "ventes");
    
    // Mettre à jour les stats du jour
    $stats = new xORMHelper($this->Main, null, true, "stats_ventes");
    $today = date('Y-m-d');
    
    $existing = $stats->ChargeListe("Date = '$today'");
    
    if ($existing->num_rows > 0) {
        $row = $existing->fetch_assoc();
        $stats = new xORMHelper($this->Main, $row['ID'], true, "stats_ventes");
        $stats->NombreVentes += 1;
        $stats->MontantTotal += $vente->Montant;
    } else {
        $stats->Date = $today;
        $stats->NombreVentes = 1;
        $stats->MontantTotal = $vente->Montant;
    }
    
    $stats->Enregistrer();
}
```

## 🎭 Gestion des Événements (Observer Pattern)

NAbySyGS intègre un système d'événements puissant qui permet de réagir automatiquement aux changements dans votre application.

### Événements Disponibles

Le framework déclenche automatiquement des événements lors de certaines opérations :

- **`{CLASS}_ADD`** - Déclenché après la création d'un enregistrement
- **`{CLASS}_EDIT`** - Déclenché après la modification d'un enregistrement
- **`{CLASS}_DEL`** - Déclenché après la suppression d'un enregistrement
- **`DIRECTION_ADD`** - Ajout d'une direction
- **`DIRECTION_EDIT`** - Modification d'une direction
- **`SERVICE_ADD`** - Ajout d'un service
- **`SERVICE_EDIT`** - Modification d'un service
- **`MVT_AFFECTATION`** - Mouvement d'affectation
- **`SIEGE_EDIT`** - Modification du siège

### Créer un Observateur

#### 1. Créer la Classe Observateur

Créez un fichier dans `gs/votre_module/xObservVotreModule/xObservVotreModule.class.php` :

```php
<?php
namespace NAbySy\OBSERVGEN;

use NAbySy\xNAbySyGS;

class xObservProduit extends xObservGen {
    
    public function __construct(xNAbySyGS $NabySyGS) {
        // Liste des événements à observer
        $listeObservable = [
            'xProduit_ADD',    // Création de produit
            'xProduit_EDIT',   // Modification de produit
            'xProduit_DEL',    // Suppression de produit
        ];
        
        // Initialiser l'observateur
        parent::__construct(
            $NabySyGS,
            'ObservateurProduit',  // Nom unique
            $listeObservable
        );
    }
    
    /**
     * Cette méthode est appelée automatiquement lors des événements
     * 
     * @param string $ClassName - Nom de la classe source
     * @param array $EventType - Type d'événement et paramètres
     * @param mixed $EventArg - Arguments supplémentaires
     */
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $action = $EventType[0] ?? null;
        
        switch ($action) {
            case 'xProduit_ADD':
                $idProduit = $EventType[1];
                $this->onProduitCreated($idProduit);
                break;
                
            case 'xProduit_EDIT':
                $idProduit = $EventType[1];
                $produitObjet = $EventType[2] ?? null;
                $this->onProduitUpdated($idProduit, $produitObjet);
                break;
                
            case 'xProduit_DEL':
                $idProduit = $EventType[1];
                $produitObjet = $EventType[2] ?? null;
                $this->onProduitDeleted($idProduit, $produitObjet);
                break;
        }
    }
    
    /**
     * Gérer la création d'un produit
     */
    private function onProduitCreated($idProduit) {
        // Exemple : Envoyer une notification
        $this->Main::$Log->Write("Nouveau produit créé : ID $idProduit");
        
        // Exemple : Mettre à jour un cache
        // $this->updateCache($idProduit);
        
        // Exemple : Déclencher une action externe
        // $this->notifyExternalSystem($idProduit);
        
        // Journaliser dans la base
        $this->Main->AddToJournal(
            'SYSTEME',
            0,
            'PRODUIT_CREATED',
            "Nouveau produit ajouté avec ID: $idProduit"
        );
    }
    
    /**
     * Gérer la modification d'un produit
     */
    private function onProduitUpdated($idProduit, $produit) {
        if (!$produit) return;
        
        $this->Main::$Log->Write("Produit modifié : {$produit->Nom} (ID: $idProduit)");
        
        // Exemple : Vérifier le stock
        if ($produit->Stock < 10) {
            $this->alertLowStock($produit);
        }
        
        // Journaliser
        $this->Main->AddToJournal(
            'SYSTEME',
            0,
            'PRODUIT_UPDATED',
            "Produit {$produit->Nom} modifié"
        );
    }
    
    /**
     * Gérer la suppression d'un produit
     */
    private function onProduitDeleted($idProduit, $produit) {
        $this->Main::$Log->Write("Produit supprimé : ID $idProduit");
        
        // Exemple : Nettoyer les données associées
        // $this->cleanupRelatedData($idProduit);
        
        // Journaliser
        $this->Main->AddToJournal(
            'SYSTEME',
            0,
            'PRODUIT_DELETED',
            "Produit ID $idProduit supprimé"
        );
    }
    
    /**
     * Alerte de stock faible
     */
    private function alertLowStock($produit) {
        // Envoyer un email ou SMS
        $message = "Alerte stock : {$produit->Nom} - Stock: {$produit->Stock}";
        $this->Main::$Log->Write($message);
        
        // Utiliser le module SMS si disponible
        // $this->Main::$SMSEngine->EnvoieSMS('+221771234567', $message);
    }
}
```

#### 2. Activer l'Observateur

L'observateur est chargé automatiquement au démarrage de NAbySyGS. Assurez-vous que votre classe :
- Hérite de `xObservGen`
- Est dans un dossier `xObserv*` sous votre module
- Implémente la méthode `RaiseEvent()`

### Exemple Complet : Système de Notifications

#### Créer un Observateur pour les Commandes

```php
<?php
namespace NAbySy\OBSERVGEN;

use NAbySy\xNAbySyGS;
use NAbySy\ORM\xORMHelper;

class xObservCommande extends xObservGen {
    
    public function __construct(xNAbySyGS $NabySyGS) {
        parent::__construct(
            $NabySyGS,
            'ObservateurCommande',
            [
                'xCommande_ADD',
                'xCommande_EDIT',
            ]
        );
    }
    
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        $action = $EventType[0];
        $idCommande = $EventType[1];
        
        switch ($action) {
            case 'xCommande_ADD':
                $this->onNewOrder($idCommande);
                break;
                
            case 'xCommande_EDIT':
                $commande = $EventType[2];
                $this->onOrderStatusChanged($commande);
                break;
        }
    }
    
    /**
     * Nouvelle commande créée
     */
    private function onNewOrder($idCommande) {
        $commande = new xORMHelper($this->Main, $idCommande, false, "commandes");
        
        // 1. Notifier le client
        $this->notifyCustomer($commande);
        
        // 2. Notifier l'équipe
        $this->notifyTeam($commande);
        
        // 3. Mettre à jour les statistiques
        $this->updateStats($commande);
        
        // 4. Déclencher le workflow
        $this->triggerWorkflow($commande);
    }
    
    /**
     * Statut de commande modifié
     */
    private function onOrderStatusChanged($commande) {
        if ($commande->Statut === 'Livrée') {
            // Envoyer email de confirmation
            $this->sendDeliveryConfirmation($commande);
            
            // Demander un avis client
            $this->requestReview($commande);
        }
        
        if ($commande->Statut === 'Annulée') {
            // Restaurer le stock
            $this->restoreStock($commande);
            
            // Notifier le client
            $this->sendCancellationNotice($commande);
        }
    }
    
    private function notifyCustomer($commande) {
        $client = new xORMHelper($this->Main, $commande->IdClient, false, "clients");
        
        $message = "Bonjour {$client->Nom}, votre commande #{$commande->Id} a été enregistrée.";
        
        // Envoyer SMS
        if ($this->Main::$SMSEngine) {
            $this->Main::$SMSEngine->EnvoieSMS($client->Telephone, $message);
        }
        
        $this->Main::$Log->Write("Notification client envoyée : Commande #{$commande->Id}");
    }
    
    private function notifyTeam($commande) {
        // Envoyer notification à l'équipe (email, Slack, etc.)
        $this->Main::$Log->Write("Nouvelle commande #{$commande->Id} - Montant: {$commande->MontantTotal}");
    }
    
    private function updateStats($commande) {
        // Mettre à jour les statistiques du jour
        $stats = new xORMHelper($this->Main, null, true, "statistiques_ventes");
        $today = date('Y-m-d');
        
        // Chercher ou créer l'entrée du jour
        $existing = $stats->ChargeListe("Date = '$today'");
        
        if ($existing->num_rows > 0) {
            $row = $existing->fetch_assoc();
            $stats = new xORMHelper($this->Main, $row['ID'], true, "statistiques_ventes");
            $stats->NombreCommandes += 1;
            $stats->MontantTotal += $commande->MontantTotal;
        } else {
            $stats->Date = $today;
            $stats->NombreCommandes = 1;
            $stats->MontantTotal = $commande->MontantTotal;
        }
        
        $stats->Enregistrer();
    }
    
    private function triggerWorkflow($commande) {
        // Créer automatiquement une tâche pour la préparation
        $tache = new xORMHelper($this->Main, null, true, "taches");
        $tache->Titre = "Préparer commande #{$commande->Id}";
        $tache->Type = "Préparation";
        $tache->IdCommande = $commande->Id;
        $tache->Statut = "En attente";
        $tache->DateCreation = date('Y-m-d H:i:s');
        $tache->Enregistrer();
    }
    
    private function restoreStock($commande) {
        // Restaurer les quantités en stock
        $lignes = new xORMHelper($this->Main, null, false, "ligne_commande");
        $liste = $lignes->ChargeListe("IdCommande = {$commande->Id}");
        
        while ($ligne = $liste->fetch_assoc()) {
            $produit = new xORMHelper($this->Main, $ligne['IdProduit'], false, "produits");
            $produit->Stock += $ligne['Quantite'];
            $produit->Enregistrer();
        }
        
        $this->Main::$Log->Write("Stock restauré pour commande annulée #{$commande->Id}");
    }
}
```

### Déclencher Manuellement un Événement

Si vous créez une classe personnalisée qui n'hérite pas de `xORMHelper`, vous pouvez déclencher manuellement des événements :

```php
<?php
use NAbySy\xNAbySyGS;

class MaClasseMetier {
    private $nabysy;
    
    public function __construct(xNAbySyGS $nabysy) {
        $this->nabysy = $nabysy;
    }
    
    public function faireQuelqueChose() {
        // ... votre logique ...
        
        // Déclencher un événement personnalisé
        $eventArgs = [
            'MonEvenement_CUSTOM',
            123,  // ID ou données
            'Information supplémentaire'
        ];
        
        $this->nabysy::RaiseEvent('MaClasseMetier', $eventArgs);
    }
}
```

### Cas d'Usage Pratiques

#### 1. Synchronisation avec API Externe

```php
<?php
private function onProduitCreated($idProduit) {
    $produit = new xORMHelper($this->Main, $idProduit, false, "produits");
    
    // Synchroniser avec système externe
    $curl = $this->Main::$CURL;
    $data = [
        'nom' => $produit->Nom,
        'prix' => $produit->Prix,
        'stock' => $produit->Stock
    ];
    
    $response = $curl->Post('https://api-externe.com/products', $data);
    
    if ($response['success']) {
        $produit->IdExterne = $response['id'];
        $produit->Enregistrer();
    }
}
```

#### 2. Audit Trail Automatique

```php
<?php
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    $action = $EventType[0];
    $id = $EventType[1];
    $objet = $EventType[2] ?? null;
    
    // Créer automatiquement un journal d'audit
    $audit = new xORMHelper($this->Main, null, true, "audit_log");
    $audit->Action = $action;
    $audit->TableCible = $ClassName;
    $audit->IdEnregistrement = $id;
    $audit->DateAction = date('Y-m-d H:i:s');
    $audit->Utilisateur = $this->Main->User->Login ?? 'SYSTEM';
    
    if ($objet) {
        $audit->DonneesAvant = json_encode($objet->ToObject());
    }
    
    $audit->Enregistrer();
}
```

#### 3. Cache Invalidation

```php
<?php
private function onProduitUpdated($idProduit, $produit) {
    // Invalider le cache
    $cacheKey = "produit_$idProduit";
    
    if (function_exists('apcu_delete')) {
        apcu_delete($cacheKey);
    }
    
    // Ou avec Redis
    // $redis->del($cacheKey);
    
    $this->Main::$Log->Write("Cache invalidé pour produit #$idProduit");
}
```

### Désactiver un Observateur

```php
<?php
// Dans votre observateur
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    // Vérifier si l'observateur est actif
    if (!$this->State()) {
        return;
    }
    
    // ... logique normale ...
}

// Pour désactiver/activer
$observateur->State(false); // Désactiver
$observateur->State(true);  // Activer
```

### Bonnes Pratiques avec les Événements

1. **Nommage cohérent** : Utilisez `{Classe}_{Action}` pour les événements
2. **Logging** : Toujours logger les actions importantes
3. **Performances** : Évitez les traitements lourds dans les observateurs
4. **Async** : Pour les traitements longs, utilisez des queues (Beanstalkd, RabbitMQ)
5. **Test** : Créez des observateurs de test pour vérifier le déclenchement
6. **Documentation** : Documentez les événements disponibles dans votre module

### Debug des Événements

```php
<?php
// Activer les logs détaillés
$nabysy->ActiveDebug = true;

// Dans votre observateur
public function RaiseEvent($ClassName, $EventType, &$EventArg) {
    if ($this->Main->ActiveDebug) {
        $this->Main::$Log->Write("Événement déclenché : " . json_encode($EventType));
    }
    
    // ... logique ...
}
```

## ⚖️ Comparaison avec d'autres ORM PHP

### NAbySyGS vs Doctrine, Eloquent, RedBeanPHP

#### ✅ Avantages de NAbySyGS

##### 1. **Simplicité et Rapidité de Développement**
```php
// NAbySyGS - 4 lignes
$produit = new xORMHelper($nabysy, null, true, "produits");
$produit->Nom = "Laptop";
$produit->Prix = 550000;
$produit->Enregistrer();

// Doctrine - Configuration complexe nécessaire
// - Entities avec annotations
// - Mapping XML/YAML
// - Configuration du EntityManager
// - Commandes doctrine:schema
```

##### 2. **Auto-création de Tables et Champs**
- 🚀 **Aucune migration nécessaire** - Les tables et champs se créent automatiquement
- 🎯 **Zero configuration** - Pas besoin de définir des schémas
- ⚡ **Prototypage ultra-rapide** - Concentrez-vous sur la logique métier

```php
// Ajoutez simplement un nouveau champ
$user->DateNaissance = '1990-01-01';  // Champ créé automatiquement en base !
$user->Enregistrer();
```

**Comparaison :**
- **Doctrine** : Créer Entity, configurer mapping, `doctrine:schema:update`
- **Eloquent** : Créer Migration, définir fillable/casts, `php artisan migrate`
- **NAbySyGS** : Ajoutez le champ, c'est tout ! ✨

##### 3. **Pas de Dépendances Complexes**
- ✅ Fonctionne avec PHP 8.1+ et MySQLi natif
- ✅ Pas besoin de Symfony, Laravel ou autre framework
- ✅ Package autonome et léger (~2MB)

##### 4. **Système d'Événements Intégré**
```php
// Observer automatique sur TOUTES les tables
class xObservProduit extends xObservGen {
    public function RaiseEvent($ClassName, $EventType, &$EventArg) {
        // Réagir automatiquement aux changements
    }
}
```

**Autres ORM :**
- Doctrine : Events Listeners (configuration complexe)
- Eloquent : Model Events (limité aux models déclarés)

##### 5. **API REST Intégré**
- 🎁 Structure d'API fournie "out of the box"
- 🔐 Authentification JWT intégrée
- 🌐 CORS géré automatiquement
- 📝 Journalisation système incluse

##### 6. **Modules Métier Prêts à l'Emploi**
- Gestion de boutiques
- Gestion de stocks
- Facturation
- Utilisateurs avec permissions
- SMS et Email

##### 7. **Courbe d'Apprentissage Faible**
```php
// Compréhensible en 5 minutes
$client = new xORMHelper($nabysy, null, true, "clients");
$client->Nom = "Dupont";
$client->Enregistrer();
```

#### ❌ Inconvénients / Limitations

##### 1. **Moins de Fonctionnalités Avancées**

| Fonctionnalité | NAbySyGS | Doctrine | Eloquent |
|----------------|----------|----------|----------|
| Relations complexes (Many-to-Many) | ⚠️ Manuel | ✅ Automatique | ✅ Automatique |
| Lazy Loading | ❌ Non | ✅ Oui | ✅ Oui |
| Query Builder avancé | ⚠️ Basique | ✅ DQL | ✅ Fluent |
| Transactions complexes | ⚠️ Manuel | ✅ UnitOfWork | ✅ Oui |
| Caching sophistiqué | ❌ Non | ✅ 2nd level cache | ✅ Query cache |

```php
// NAbySyGS - Relations manuelles
$commande = new xORMHelper($nabysy, 1, true, "commandes");
$client = new xORMHelper($nabysy, $commande->IdClient, true, "clients");

// Eloquent - Relations automatiques
$commande = Commande::find(1);
$client = $commande->client; // Automatique via relation définie
```

### 🎯 Quand Utiliser NAbySyGS ?

#### ✅ **Idéal Pour :**

1. **Prototypes et MVPs Rapides**
   - Lancer une API en quelques heures
   - Tester des idées rapidement
   - Projets avec deadline serrée

2. **Petites et Moyennes Applications**
   - < 200 tables
   - < 500 000 enregistrements par table
   - Équipe de 1-50 développeurs

3. **Applications Métier Internes**
   - ERP légers
   - Systèmes de gestion (stocks, clients, factures)
   - Outils administratifs

4. **Projets Sans Infrastructure DevOps**
   - Hébergement mutualisé
   - Pas de CLI disponible
   - Environnement simple (FTP)

5. **Développeurs Débutants en ORM**
   - Courbe d'apprentissage douce
   - Concepts simples
   - Résultats immédiats



### 🔄 Migration vers NAbySyGS

#### Depuis Eloquent (Laravel)

```php
// Avant (Laravel Eloquent)
$produits = Produit::where('prix', '>', 1000)
    ->orderBy('nom')
    ->limit(10)
    ->get();

// Après (NAbySyGS)
$produit = new xORMHelper($nabysy, null, true, "produits");
$produits = $produit->ChargeListe(
    "prix > 1000",
    "nom ASC",
    "*",
    null,
    "10"
);
```

#### Depuis Doctrine

```php
// Avant (Doctrine)
$repository = $entityManager->getRepository(Produit::class);
$produit = $repository->find(1);
$produit->setPrix(5000);
$entityManager->flush();

// Après (NAbySyGS)
$produit = new xORMHelper($nabysy, 1, true, "produits");
$produit->Prix = 5000;
$produit->Enregistrer();
```

### 📊 Tableau Récapitulatif

| Critère | NAbySyGS | Doctrine | Eloquent | RedBeanPHP |
|---------|----------|----------|----------|------------|
| **Facilité** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Performance** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Fonctionnalités** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |
| **Auto-création** | ⭐⭐⭐⭐⭐ | ❌ | ❌ | ⭐⭐⭐⭐⭐ |
| **API Intégrée** | ⭐⭐⭐⭐⭐ | ❌ | ⭐⭐⭐ | ❌ |
| **Communauté** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Documentation** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Setup** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

### 💡 Recommandation

**Utilisez NAbySyGS si :**
- Vous voulez un MVP en **moins d'une journée**
- Vous n'avez **pas besoin de relations complexes**
- Vous développez une **application de gestion simple**
- Vous êtes **seul ou en petite équipe**
- Vous préférez la **simplicité à la puissance**

## 🎯 Bonnes Pratiques

1. **Toujours utiliser `ValideUser()`** pour protéger vos endpoints sensibles
2. **Nommer vos actions** en MAJUSCULES avec préfixe module : `PRODUIT_CREATE`
3. **Utiliser xNotification** pour les réponses réussies, `xErreur` pour les erreurs
4. **Activer le debug** uniquement en développement
5. **Versionner** votre fichier `appinfos.php` pour la configuration personnalisée
6. **Créer des observateurs** pour automatiser les tâches répétitives
7. **Logger les événements importants** pour faciliter le débogage

## 📝 Structure de Réponse

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

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Proposer des suggestions aux projet
2. Créer une branche (`git checkout -b feature/amelioration`)
3. Commit vos changements (`git commit -m 'Ajout fonctionnalité'`)
4. Push sur la branche (`git push origin feature/amelioration`)
5. Ouvrir une Pull Request

## 📄 Licence

MIT License - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 👨‍💻 Auteur

**Paul Isidore A. NIAMIE**
- Email: paul.isidore@gmail.com
- Website: [https://groupe-pam.net](https://groupe-pam.net)

## 🙏 Remerciements

Développé par **Paul & Aïcha Machinerie (PAM)** et **Micro Computer Programme (MCP)**.

---

⭐ Si ce projet vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !