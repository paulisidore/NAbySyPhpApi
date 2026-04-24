# Système d'événements (Observer) — NAbySyGS

NAbySyGS intègre un système d'événements basé sur le pattern Observer. Chaque opération ORM (ajout, modification, suppression) déclenche automatiquement des événements, permettant de réagir sans modifier le code métier.

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Événements disponibles](#événements-disponibles)
3. [Créer un Observer](#créer-un-observer)
4. [Enregistrer un Observer](#enregistrer-un-observer)
5. [Wildcards — écouter plusieurs tables](#wildcards)
6. [Cas d'usage pratiques](#cas-dusage-pratiques)
7. [Référence API](#référence-api)

---

## Vue d'ensemble

Le système d'événements repose sur la classe `xObservGen`. Il est déclenché automatiquement par les méthodes `Enregistrement()` et `Suppression()` de `xORMHelper` :

```
xORMHelper::Enregistrement()
    ↓
    Si nouvel enregistrement → déclenche TABLE_ADD
    Si mise à jour          → déclenche TABLE_UPDATE

xORMHelper::Suppression()
    ↓
    Déclenche TABLE_DELETE
```

Vous n'avez pas à appeler les événements manuellement — ils se déclenchent de façon transparente.

---

## Événements disponibles

Pour une table nommée `ARTICLES`, les événements sont :

| Événement | Déclenché quand |
|-----------|------------------|
| `ARTICLES_ADD` | Un nouvel enregistrement est créé |
| `ARTICLES_UPDATE` | Un enregistrement existant est modifié |
| `ARTICLES_DELETE` | Un enregistrement est supprimé |

La convention est `NOM_TABLE_ACTION` avec le nom de table en majuscules.

---

## Créer un Observer

Un Observer est une classe qui implémente (ou étend) `xObservGen` et définit la méthode `handle()` :

```php
<?php
// gs/articles/ArticlesObserver.php

use xObservGen;

class ArticlesObserver extends xObservGen {

    /**
     * Réagit à l'événement ARTICLES_ADD
     */
    public function onArticleAdded(array $data): void {
        // $data contient les données de l'ORM
        $titre = $data['TITRE'] ?? 'Sans titre';
        
        // Exemple : envoyer une notification
        $notif = new xORMHelper('notifications');
        $notif->MESSAGE = "Nouvel article : $titre";
        $notif->DATE = date('Y-m-d H:i:s');
        $notif->Enregistrement();
    }

    /**
     * Réagit à l'événement ARTICLES_UPDATE
     */
    public function onArticleUpdated(array $data): void {
        // Logger la modification
        error_log("Article mis à jour : ID={$data['ID']}");
    }

    /**
     * Réagit à l'événement ARTICLES_DELETE
     */
    public function onArticleDeleted(array $data): void {
        // Nettoyer les données liées
        $id = $data['ID'];
        // ... supprimer les commentaires liés, etc.
    }
}
```

---

## Enregistrer un Observer

### Observer pour une table spécifique

```php
// Enregistrer l'observer au démarrage de l'application
$observer = new ArticlesObserver();

// Lier les méthodes aux événements
$observer->on('ARTICLES_ADD',    [$observer, 'onArticleAdded']);
$observer->on('ARTICLES_UPDATE', [$observer, 'onArticleUpdated']);
$observer->on('ARTICLES_DELETE', [$observer, 'onArticleDeleted']);
```

### Observer dans le constructeur

```php
class ArticlesObserver extends xObservGen {
    public function __construct() {
        $this->on('ARTICLES_ADD',    [$this, 'onArticleAdded']);
        $this->on('ARTICLES_UPDATE', [$this, 'onArticleUpdated']);
        $this->on('ARTICLES_DELETE', [$this, 'onArticleDeleted']);
    }
    // ... méthodes ...
}

// Instanciation unique au démarrage
new ArticlesObserver();
```

---

## Wildcards

Les wildcards permettent d'écouter plusieurs tables avec un seul Observer :

### `*_ADD` — Tous les ajouts

```php
$observer->on('*_ADD', function(array $data, string $event) {
    // Déclenché pour ARTICLES_ADD, USERS_ADD, ORDERS_ADD...
    $tableName = str_replace('_ADD', '', $event);
    error_log("Nouvel enregistrement dans : $tableName");
});
```

### `*_UPDATE` — Toutes les modifications

```php
$observer->on('*_UPDATE', function(array $data, string $event) {
    $tableName = str_replace('_UPDATE', '', $event);
    // Audit log universel
    $audit = new xORMHelper('audit_log');
    $audit->TABLE_NAME = $tableName;
    $audit->RECORD_ID  = $data['ID'];
    $audit->ACTION     = 'UPDATE';
    $audit->DATE       = date('Y-m-d H:i:s');
    $audit->Enregistrement();
});
```

### `*` — Tout intercepter

```php
$observer->on('*', function(array $data, string $event) {
    // Intercepte ABSOLUMENT tous les événements ORM
    // Utile pour le débogage ou l'audit complet
    error_log("[ORM Event] $event : " . json_encode($data));
});
```

---

## Cas d'usage pratiques

### 1. Audit log automatique

```php
class AuditObserver extends xObservGen {
    public function __construct() {
        $this->on('*', [$this, 'logAll']);
    }

    public function logAll(array $data, string $event): void {
        [$table, $action] = explode('_', $event, 2) + [null, null];

        $audit = new xORMHelper('audit_log');
        $audit->TABLE_NAME  = $table;
        $audit->RECORD_ID   = $data['ID'] ?? null;
        $audit->ACTION      = $action;
        $audit->DONNEES     = json_encode($data);
        $audit->DATE        = date('Y-m-d H:i:s');
        $audit->IP          = $_SERVER['REMOTE_ADDR'] ?? null;
        $audit->Enregistrement();
    }
}

new AuditObserver();
```

### 2. Invalidation de cache sur modification

```php
class CacheInvalidationObserver extends xObservGen {
    public function __construct() {
        $this->on('*_UPDATE', [$this, 'invalidate']);
        $this->on('*_DELETE', [$this, 'invalidate']);
    }

    public function invalidate(array $data, string $event): void {
        $table = strtolower(str_replace(['_UPDATE', '_DELETE'], '', $event));
        $cacheFile = "cache/{$table}_{$data['ID']}.cache";

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}

new CacheInvalidationObserver();
```

### 3. Envoi de notification push sur création

```php
class PushNotificationObserver extends xObservGen {
    public function __construct() {
        $this->on('ORDERS_ADD', [$this, 'notifyNewOrder']);
    }

    public function notifyNewOrder(array $data): void {
        // Envoyer une notification aux admins
        $adminTokens = $this->getAdminTokens();
        foreach ($adminTokens as $token) {
            $this->sendPush($token, "Nouvelle commande #{$data['ID']}");
        }
    }

    private function getAdminTokens(): array {
        $orm = new xORMHelper('admin_tokens');
        $result = $orm->ChargeListe('ACTIF = 1');
        $tokens = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row['TOKEN'];
        }
        return $tokens;
    }

    private function sendPush(string $token, string $message): void {
        // Intégration FCM, OneSignal, etc.
    }
}

new PushNotificationObserver();
```

### 4. Synchronisation temps réel (WebSockets)

```php
class RealtimeSyncObserver extends xObservGen {
    public function __construct() {
        $this->on('*_ADD',    [$this, 'broadcast']);
        $this->on('*_UPDATE', [$this, 'broadcast']);
        $this->on('*_DELETE', [$this, 'broadcast']);
    }

    public function broadcast(array $data, string $event): void {
        $payload = json_encode([
            'event' => $event,
            'data'  => $data,
            'ts'    => time()
        ]);

        // Publier dans Redis pour les WebSockets
        // $redis->publish('nabysygs:events', $payload);
    }
}
```

---

## Référence API

### `xObservGen`

```php
// Enregistrer un listener
public function on(string $event, callable $handler): self

// Déclencher manuellement un événement
public function emit(string $event, array $data = []): void

// Supprimer tous les listeners d'un événement
public function off(string $event): self

// Vérifier si un événement a des listeners
public function hasListeners(string $event): bool
```

### Événements standards

| Format | Exemple | Déclenché par |
|--------|---------|----------------|
| `TABLE_ADD` | `USERS_ADD` | `Enregistrement()` (nouvel ID) |
| `TABLE_UPDATE` | `USERS_UPDATE` | `Enregistrement()` (ID existant) |
| `TABLE_DELETE` | `USERS_DELETE` | `Suppression()` |
| `*_ADD` | Wildcard | Tout `_ADD` |
| `*_UPDATE` | Wildcard | Tout `_UPDATE` |
| `*_DELETE` | Wildcard | Tout `_DELETE` |
| `*` | Wildcard | Tout |

---

## Bonnes pratiques

1. **Instancier les observers au démarrage** — dans `index.php` ou un bootstrap, avant tout traitement de requête.

2. **Éviter les boucles infinies** — un observer qui appelle `Enregistrement()` déclenche lui-même des événements. Utilisez un flag ou évitez d'écouter la même table que celle que vous modifiez.

3. **Opérations légères dans les observers** — pour les traitements lourds (emails, notifications push), utilisez une file de tâches asynchrone.

4. **Un observer par domaine** — un `AuditObserver`, un `CacheObserver`, etc. Évitez les classes fourre-tout.
