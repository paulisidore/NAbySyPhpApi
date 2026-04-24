# Référence API complète — NAbySyGS

---

## `xNAbySyGS` (classe principale)

### Initialisation

```php
$nabysy = N::Init(
    string $AppName,        // Nom de l'application
    string $ClientName,     // Société
    string $Address,        // Adresse
    string $Phone,          // Téléphone
    string $Database,       // Base de données
    string $MasterDatabase, // Base master (nabysygs)
    string $Host,           // Serveur MySQL
    string $User,           // Utilisateur
    string $Password,       // Mot de passe
    int    $Port,           // Port (3306)
    string $RootFolder = '' // Dossier racine (optionnel)
): xNAbySyGS
```

### Méthodes statiques

| Méthode | Description |
|---|---|
| `N::Init(...)` | Initialise le framework |
| `N::getInstance()` | Retourne l'instance courante |
| `N::ReadHttpRequest()` | Traite la requête HTTP entrante |
| `N::SetShowDebug(bool, int)` | Active le mode debug |
| `N::SetAuthSessionTime(int)` | Durée de session JWT en secondes |
| `N::RaiseEvent(string, array)` | Déclenche un événement manuellement |

### Méthodes d'instance

| Méthode | Retour | Description |
|---|---|---|
| `ValideUser()` | `bool` | Vérifie le token JWT |
| `SQLToJSON($result)` | `string` | Convertit un résultat SQL en JSON |
| `AddToJournal(...)` | `void` | Journal système |
| `ExecSQL(string)` | `mixed` | Exécute SQL brut |

---

## `xORMHelper` — ORM + Jointures

### Constructeur

```php
new xORMHelper(
    xNAbySyGS $nabysy,     // Instance du framework
    ?int      $id,          // ID à charger (null = nouvel enregistrement)
    bool      $autoCreate,  // Créer table/colonnes si inexistantes
    string    $table,       // Nom de la table
    ?string   $dbName       // Nom de la base (optionnel, défaut = base principale)
)
```

### Méthodes CRUD

| Méthode | Retour | Description |
|---|---|---|
| `Enregistrer()` | `bool` | Crée ou met à jour |
| `Supprimer()` | `bool` | Supprime l'enregistrement |
| `ChargeListe(critere, ordre, select, groupBy, limit)` | `mysqli_result` | Liste filtrée |
| `ChargeOne(int $id)` | `mysqli_result` | Charge par ID |
| `Refresh()` / `Actualise()` | `bool` | Recharge depuis la base |
| `ViderTable()` | `bool` | Vide la table (TRUNCATE) |

### Méthodes de jointure

```php
// Déclarer une jointure (retourne $this pour le chaînage)
public function JoinTable(
    xORMHelper $TargetOrm,          // Table à joindre
    ?string    $Alias,               // Alias SQL (auto si null : j1, j2...)
    string     $cleJointeSrc,        // Clé dans la table principale (ex: IdClient)
    string     $cleJointeEtrangere,  // Clé dans la table jointe (ex: ID)
    string     $type                 // Type de jointure (défaut: LEFT OUTER JOIN)
): xORMHelper

// Exécuter la requête avec les jointures déclarées
public function JointureChargeListe(
    ?string $Critere,
    mixed   $Ordre,
    string  $SelectChamp = '*',
    mixed   $GroupBy,
    ?string $Limit
): ?mysqli_result

// Obtenir le SQL généré sans l'exécuter (debug)
public function ChargeListeNoExecute(...): string
```

**Types de jointures disponibles (`xORMJoinTableSpec`) :**

```php
xORMJoinTableSpec::LEFT_OUTER_JOIN   // défaut
xORMJoinTableSpec::LEFT_JOIN
xORMJoinTableSpec::RIGHT_JOIN
xORMJoinTableSpec::RIGHT_OUTER_JOIN
xORMJoinTableSpec::INNER_JOIN
```

**Alias dans les critères :**
- Table principale → `t1`
- 1ère table jointe → `j1` (ou alias fourni)
- 2ème table jointe → `j2` (ou alias fourni)

### Méthodes de conversion

| Méthode | Retour | Description |
|---|---|---|
| `ToJSON()` | `string` | JSON de l'objet |
| `ToObject()` | `stdClass` | Objet PHP standard |
| `ToArrayAssoc()` | `array` | Tableau associatif |
| `Clone(?string $db)` | `xORMHelper` | Clone (même base ou autre) |

### Méthodes utilitaires

| Méthode | Description |
|---|---|
| `TableExisteInDataBase()` | Vérifie si la table existe |
| `ChampsExisteInTable(string)` | Vérifie si un champ existe |
| `DBExiste(?string)` | Vérifie si une base existe |
| `count()` | Nombre d'enregistrements |
| `FlushMeToDB()` | Force la création de la table |
| `ChangeTypeChamps(nom, newType)` | Modifie le type d'un champ |
| `ExecSQL(string)` | Exécute SQL SELECT |
| `ExecUpdateSQL(string, ?table)` | Exécute INSERT/UPDATE/DELETE |
| `GetInsertSQLString(...)` | Retourne la requête INSERT |
| `GetUpDateSQLString()` | Retourne la requête UPDATE |

---

## `xCacheFileMGR` — Cache fichier

```php
$cache = new xCacheFileMGR(xNAbySyGS $nabysy);

// Rafraîchit le cache si la source est plus récente
xCacheFileMGR::refreshCacheFile(
    string $cacheFile,   // Chemin du fichier de cache
    string $sourceFile   // Chemin du fichier source
): void
```

Logique interne :
- Cache inexistant → copie la source
- Source plus récente (timestamp) → met à jour le cache
- Cache à jour → rien

---

## `xNAbySyUrlRouterHelper` — Routeur URL

### Constructeur

```php
new xNAbySyUrlRouterHelper(
    string $RouterName,      // Nom technique unique
    string $FileSource,      // Chemin du fichier de route
    string $FriendlyName,    // Nom convivial (affiché dans la doc)
    string $Description      // Description (affichée dans la doc)
)
```

### Déclaration de routes

```php
$this->get(string $pattern, callable $handler, ?string $name = null): self
$this->post(string $pattern, callable $handler, ?string $name = null): self
$this->put(string $pattern, callable $handler, ?string $name = null): self
$this->delete(string $pattern, callable $handler, ?string $name = null): self
$this->patch(string $pattern, callable $handler, ?string $name = null): self
$this->any(string $pattern, callable $handler, ?string $name = null): self
```

### Groupes et middlewares

```php
// Groupe avec préfixe
$this->group(string $prefix, callable $callback, array $attributes = []): self

// Middleware global ou par route
$this->middleware(callable $middleware, ?string $routeName = null): self
```

### Patterns de routes

| Pattern | Description | Exemple |
|---|---|---|
| `/api/produits` | Route statique | `/api/produits` |
| `/api/produits/{id}` | Paramètre requis | `/api/produits/5` |
| `/api/produits/{id?}` | Paramètre optionnel | `/api/produits` ou `/api/produits/5` |
| `/api/produits/{id:[0-9]+}` | Paramètre avec contrainte regex | `/api/produits/42` |

### Génération d'URL

```php
$url = $this->generateUrl('produits.get', ['id' => 5]); // /api/produits/5
```

---

## `xGSUrlRouterManager` — Gestionnaire de routes

```php
// Résoudre la route de la requête courante
xGSUrlRouterManager::resolveUrlRoute(bool $CanSendReponse = false): xGSUrlRouterResponse

// Obtenir toutes les routes (pour la doc)
xGSUrlRouterManager::getRegistredRoute(): array

// Générer la page de documentation HTML
xGSUrlRouterManager::generateRoutesDocumentationPage(string $jsonRoutes): string

// Obtenir un routeur par son nom
xGSUrlRouterManager::getRouteByName(string $name): ?xNAbySyUrlRouterHelper
```

**Auto-découverte :** Tous les fichiers `*.route.php` dans les sous-dossiers de `gs/` sont chargés automatiquement au démarrage.

---

## `xAuth` — JWT

```php
$auth = new xAuth(xNAbySyGS $nabysy, int $sessionTime = 3600);
$auth->GetToken(xUser $user): string
$auth->ValidateToken(string $token): bool
$auth->GetUserFromToken(string $token): ?xUser
```

---

## `xUser` — Utilisateurs

```php
$user = new xUser(
    xNAbySyGS $nabysy,
    ?int      $id,
    bool      $autoCreate,
    string    $table,
    string    $login = ''   // Charger par login
);
$user->CheckPassword(string $pwd): bool
$user->SetPassword(string $pwd): void
$user->HasPermission(string $perm): bool
```

---

## `xNotification` et `xErreur`

```php
// Succès
$rep = new xNotification();
$rep->Contenue = $objet->ToObject();
echo json_encode($rep);
// {"OK":1, "TxErreur":"", "Contenue":{...}}

// Erreur
$err = new xErreur();
$err->TxErreur = "Description de l'erreur";
$err->Extra    = "Informations supplémentaires";
echo json_encode($err);
// {"OK":0, "TxErreur":"...", "Extra":"..."}
```

---

## `xObservGen` — Événements

```php
class MonObservateur extends xObservGen {
    public function __construct(xNAbySyGS $nabysy) {
        parent::__construct($nabysy, 'NomUnique', [
            'xTable_ADD', 'xTable_EDIT', 'xTable_DEL',
            '*_ADD',  // Wildcard : toutes les créations
        ]);
    }

    public function RaiseEvent($ClassName, $EventType, &$EventArg): void {
        $action = $EventType[0]; // Ex: 'xProduit_ADD'
        $id     = $EventType[1]; // ID de l'enregistrement
        $objet  = $EventType[2]; // Instance xORMHelper (modifiable !)
    }
}
```

---

## Variables disponibles dans les actions

Dans tous les fichiers `*_action.php` et routes :

| Variable | Type | Description |
|---|---|---|
| `$nabysy` | `xNAbySyGS` | Instance principale |
| `$action` | `string` | Valeur de `$_REQUEST['action']` |
| `N::$Log` | `xLog` | Système de logs |
| `N::$GSModManager` | `GsModuleManager` | Générateur de modules |
| `N::$UrlRouter` | `xGSUrlRouterManager` | Gestionnaire de routes |
