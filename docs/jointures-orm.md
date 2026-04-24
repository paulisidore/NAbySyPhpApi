# Jointures ORM en code — NAbySyGS

NAbySyGS permet de déclarer des **jointures SQL directement dans le code PHP**, sans définir de clés étrangères (`FOREIGN KEY`) en base de données. Cette approche est unique : vous restez totalement libre de votre schéma et pouvez joindre n'importe quelles tables à la volée.

---

## Concept

```
$commande->JoinTable($client, 'cl', 'IdClient', 'ID')
              │
              ▼
SELECT t1.*, cl.Nom
FROM commandes AS t1
LEFT OUTER JOIN clients AS cl ON t1.IdClient = cl.ID
WHERE t1.Id > 0
```

Aucune `FOREIGN KEY` n'est nécessaire en base. La relation est entièrement gérée côté PHP.

---

## Utilisation de base

```php
use NAbySy\ORM\xORMHelper;

$commande = new xORMHelper($nabysy, null, true, "commandes");
$client   = new xORMHelper($nabysy, null, true, "clients");

// Déclarer la jointure
$commande->JoinTable(
    $client,      // Table à joindre
    'cl',         // Alias SQL
    'IdClient',   // Champ dans commandes
    'ID'          // Champ dans clients
);

// Exécuter
$liste = $commande->JointureChargeListe(
    "t1.Statut = 'En cours'",
    "t1.Date DESC",
    "t1.*, cl.Nom, cl.Telephone"
);

while ($row = $liste->fetch_assoc()) {
    echo $row['Nom'] . ' — ' . $row['Telephone'];
}
```

---

## Jointures multiples (chaînage)

Vous pouvez chaîner plusieurs `JoinTable()` pour joindre autant de tables que nécessaire :

```php
$commande = new xORMHelper($nabysy, null, true, "commandes");
$client   = new xORMHelper($nabysy, null, true, "clients");
$produit  = new xORMHelper($nabysy, null, true, "produits");
$boutique = new xORMHelper($nabysy, null, true, "boutiques");

$commande
    ->JoinTable($client,   'cl', 'IdClient',   'ID')  // t1.IdClient = cl.ID
    ->JoinTable($produit,  'pr', 'IdProduit',  'ID')  // t1.IdProduit = pr.ID
    ->JoinTable($boutique, 'bo', 'IdBoutique', 'ID'); // t1.IdBoutique = bo.ID

$liste = $commande->JointureChargeListe(
    "t1.Statut = 'Validée' AND pr.Stock > 0",
    "t1.Date DESC",
    "t1.Id, t1.Date, t1.Montant, cl.Nom, pr.Designation, bo.Nom AS NomBoutique",
    null,
    "50"
);
```

---

## Jointure à partir d'une table jointe

Vous pouvez utiliser un champ d'une table déjà jointe comme clé :

```php
$facture  = new xORMHelper($nabysy, null, true, "factures");
$commande = new xORMHelper($nabysy, null, true, "commandes");
$client   = new xORMHelper($nabysy, null, true, "clients");

$facture
    ->JoinTable($commande, 'cmd', 'IdCommande', 'ID')
    ->JoinTable($client,   'cl',  'cmd.IdClient', 'ID'); // clé dans la table jointe 'cmd'

$liste = $facture->JointureChargeListe(
    "t1.Paye = 0",
    "t1.DateEcheance ASC",
    "t1.*, cl.Nom, cl.Email"
);
```

---

## Types de jointures

```php
use NAbySy\ORM\xORMJoinTableSpec;

$orm->JoinTable($target, 'alias', 'cleLocale', 'cleEtrangere',
    xORMJoinTableSpec::LEFT_OUTER_JOIN   // Défaut
);

// Toutes les constantes disponibles :
// LEFT_OUTER_JOIN  → Tous les enregistrements de gauche + correspondances droite
// LEFT_JOIN        → Identique au précédent (alias SQL)
// RIGHT_JOIN       → Tous les enregistrements de droite + correspondances gauche
// RIGHT_OUTER_JOIN → Identique au précédent (alias SQL)
// INNER_JOIN       → Seulement les enregistrements avec correspondance des deux côtés
```

---

## Alias automatiques

Si vous ne fournissez pas d'alias (`null`), le framework en génère automatiquement :

| Table | Alias auto |
|---|---|
| Table principale | `t1` |
| 1ère jointe | `j1` |
| 2ème jointe | `j2` |
| 3ème jointe | `j3` |

```php
$commande->JoinTable($client, null, 'IdClient', 'ID');
// Alias auto → j1
// SELECT ... FROM commandes AS t1 LEFT OUTER JOIN clients AS j1 ON t1.IdClient = j1.ID
```

---

## Déboguer avec `ChargeListeNoExecute`

Pour visualiser le SQL généré sans l'exécuter :

```php
$commande->JoinTable($client, 'cl', 'IdClient', 'ID');

$sql = $commande->ChargeListeNoExecute(
    "t1.Statut = 'En cours'",
    "t1.Date DESC",
    "t1.*, cl.Nom"
);

echo $sql;
// SELECT t1.*, cl.Nom FROM `ma_base`.`commandes` AS t1
// LEFT OUTER JOIN `ma_base`.`clients` AS cl ON t1.IdClient = cl.ID
// WHERE t1.Id>0 AND t1.Statut = 'En cours' ORDER BY t1.Date DESC
```

---

## Réinitialisation automatique

Après l'appel à `JointureChargeListe()`, **les jointures sont automatiquement réinitialisées**. Vous pouvez réutiliser le même objet ORM pour une nouvelle requête :

```php
$produit = new xORMHelper($nabysy, null, true, "produits");

// 1ère requête avec jointure
$produit->JoinTable($cat, 'c', 'IdCategorie', 'ID');
$liste1 = $produit->JointureChargeListe("t1.Stock > 0");

// 2ème requête sans jointure (les joins ont été vidés)
$liste2 = $produit->ChargeListe("Prix > 100000");
```

---

## Comparaison avec les autres ORM

| Capacité | NAbySyGS | Doctrine | Eloquent |
|---|---|---|---|
| Jointures sans FK en base | ✅ | ❌ | ❌ |
| Chaînage fluide | ✅ | ✅ | ✅ |
| Tables dynamiques joignables | ✅ | ❌ | ❌ |
| SQL généré inspecatable | ✅ | ✅ | ✅ |
| Définition de relation nécessaire | ❌ | ✅ | ✅ |
| Lazy loading | ❌ | ✅ | ✅ |

**Avantage clé :** avec NAbySyGS, vous joignez une table créée 5 minutes auparavant sans modifier aucune configuration, aucune entité, aucun modèle.
