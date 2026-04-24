# Guide Débutant — Créer votre première API avec NAbySyGS

Ce guide vous accompagne pas à pas, de l'installation jusqu'à une API fonctionnelle avec jointures et routes RESTful. Aucune expérience préalable avec les frameworks PHP n'est requise.

**Temps estimé : 30 à 45 minutes**

---

## Prérequis

- [ ] PHP 8.1+ — vérifiez avec `php -v`
- [ ] MySQL ou MariaDB accessible
- [ ] Composer — vérifiez avec `composer -V`
- [ ] Un serveur web (Apache/Nginx ou `php -S localhost:8000`)

---

## Étape 1 — Installer la CLI (une seule fois)

```bash
composer global require nabysyphpapi/xnabysygs-cli
```

Ajoutez le dossier `bin` de Composer à votre PATH :

**Linux/macOS** (`~/.bashrc` ou `~/.zshrc`) :
```bash
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

**Windows** — variables d'environnement système :
```
%APPDATA%\Composer\vendor\bin
```

Vérifiez :
```bash
koro version
# NAbySyGS CLI version x.x.x 🦅
```

---

## Étape 2 — Créer votre projet

```bash
mkdir mon-api-produits
cd mon-api-produits
koro init mon-api-produits
```

La CLI installe le framework et ouvre `setup.html` automatiquement.

---

## Étape 3 — Configurer via setup.html

Remplissez le formulaire :

| Champ | Exemple |
|---|---|
| Nom de l'application | Mon API Produits |
| Base de données | mon_api |
| Serveur MySQL | localhost |
| Utilisateur | root |
| URL du projet | http://localhost:8000 |

Cliquez **« Générer la configuration »** — `appinfos.php` est créé automatiquement.

---

## Étape 4 — Démarrer le serveur

```bash
php -S localhost:8000
```

---

## Étape 5 — Créer vos modules

```bash
# Module produits (avec action API + ORM)
koro create categorie produit -a -o -t produits

# Module clients (pour les jointures plus tard)
koro create categorie client -a -o -t clients
```

Structure générée :
```
gs/
├── produit/
│   ├── produit_action.php
│   └── xProduit/xProduit.class.php
└── client/
    ├── client_action.php
    └── xClient/xClient.class.php
```

---

## Étape 6 — Écrire vos endpoints (actions classiques)

Ouvrez `gs/produit/produit_action.php` :

```php
<?php
use NAbySy\ORM\xORMHelper;
use NAbySy\xNotification;
use NAbySy\xErreur;

$action = $_REQUEST['action'] ?? null;

switch ($action) {

    case 'PRODUIT_CREATE':
        if (!$nabysy->ValideUser()) exit;
        $p = new xORMHelper($nabysy, null, true, "produits");
        $p->Nom   = $_REQUEST['nom']  ?? '';
        $p->Prix  = $_REQUEST['prix'] ?? 0;
        $p->Stock = $_REQUEST['stock'] ?? 0;
        if ($p->Enregistrer()) {
            $rep = new xNotification();
            $rep->Contenue = $p->ToObject();
            echo json_encode($rep);
        }
        break;

    case 'PRODUIT_LIST':
        $p     = new xORMHelper($nabysy, null, true, "produits");
        $liste = $p->ChargeListe('', 'Nom ASC');
        echo $nabysy->SQLToJSON($liste);
        break;

    case 'PRODUIT_GET':
        $id = intval($_REQUEST['id'] ?? 0);
        $p  = new xORMHelper($nabysy, $id, true, "produits");
        $rep = new xNotification();
        $rep->Contenue = $p->ToObject();
        echo json_encode($rep);
        break;
}
```

---

## Étape 7 — Ajouter une route RESTful

```bash
koro create route produit
```

Ouvrez `gs/produit/rProduit.route.php` et ajoutez vos routes :

```php
<?php
use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\ORM\xORMHelper;

class rProduit extends xNAbySyUrlRouterHelper {

    public function __construct(string $name, string $fileSrc = '') {
        parent::__construct($name, $fileSrc, "Produits", "API Catalogue");
        $this->registerRoutes();
    }

    private function registerRoutes(): void {
        $this->get('/api/produits', function() {
            global $nabysy;
            $p = new xORMHelper($nabysy, null, true, "produits");
            echo $nabysy->SQLToJSON($p->ChargeListe('', 'Nom ASC'));
        });

        $this->get('/api/produits/{id}', function($id) {
            global $nabysy;
            $p = new xORMHelper($nabysy, (int)$id, true, "produits");
            echo $p->ToJSON();
        });
    }
}
```

---

## Étape 8 — Tester

```bash
# Via action classique
curl "http://localhost:8000/gs_api.php?action=PRODUIT_LIST"

# Via route RESTful
curl "http://localhost:8000/api/produits"
curl "http://localhost:8000/api/produits/1"

# Connexion et token
curl -X POST "http://localhost:8000/gs_api.php" \
  -d "action=LOGIN&Login=admin&Password=votre_mdp"
```

---

## Étape 9 — Jointures entre tables

Ajoutez un champ `IdClient` dans vos commandes, puis :

```php
$commande = new xORMHelper($nabysy, null, true, "commandes");
$client   = new xORMHelper($nabysy, null, true, "clients");

$commande->JoinTable($client, 'cl', 'IdClient', 'ID');

$liste = $commande->JointureChargeListe(
    "t1.Statut = 'En cours'",
    "t1.Date DESC",
    "t1.*, cl.Nom, cl.Telephone"
);

while ($row = $liste->fetch_assoc()) {
    echo $row['Nom'] . " — " . $row['Telephone'];
}
```

---

## Félicitations !

Vous avez une API REST complète avec :
- CRUD automatique
- Routes RESTful
- Jointures en code
- Authentification JWT

### Prochaines étapes

- [Référence complète de l'API](reference-api.md)
- [Jointures ORM en détail](jointures-orm.md)
- [Routeur URL avancé](routeur-url.md)
- [Système d'événements](evenements.md)

---

## Problèmes fréquents

| Symptôme | Solution |
|---|---|
| Table non créée | Lancer `koro db update` |
| Token invalide | Vérifier `N::SetAuthSessionTime()` dans `appinfos.php` |
| Route non trouvée (404) | Vérifier que le fichier se nomme bien `r*.route.php` |
| Jointure échouée | Vérifier que les champs de jointure existent dans les tables |
| Erreur MySQL | Vérifier les paramètres dans `appinfos.php` |
