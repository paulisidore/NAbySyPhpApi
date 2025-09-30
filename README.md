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

## 🎯 Bonnes Pratiques

1. **Toujours utiliser `ValideUser()`** pour protéger vos endpoints sensibles
2. **Nommer vos actions** en MAJUSCULES avec préfixe module : `PRODUIT_CREATE`
3. **Utiliser xNotification** pour les réponses réussies, `xErreur` pour les erreurs
4. **Activer le debug** uniquement en développement
5. **Versionner** votre fichier `appinfos.php` pour la configuration personnalisée

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

1. Fork le projet
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

Développé par **Paul & Aïcha Machinerie (PAM)** et **MCP**.

---

⭐ Si ce projet vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !