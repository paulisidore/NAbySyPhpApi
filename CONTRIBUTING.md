# Guide de Contribution — NAbySyGS

Merci de l'intérêt que vous portez à NAbySyGS ! Toute contribution est bienvenue : rapport de bug, suggestion, correction de doc, nouvelle fonctionnalité.

---

## Comment contribuer

| Type | Comment |
|---|---|
| Rapport de bug | Ouvrir une [Issue](https://github.com/paulisidore/NAbySyPhpApi/issues) |
| Amélioration de la doc | Modifier un fichier `.md` et ouvrir une PR |
| Nouvelle fonctionnalité | Discuter d'abord via une Issue, puis PR |
| Correction de code | Fork → Branche → PR |

---

## Signaler un bug

Une bonne issue contient :

```
**Version NAbySyGS :** x.x.x
**Version PHP :** 8.x.x
**Base de données :** MySQL x.x / MariaDB x.x
**Système :** Linux / Windows / macOS

**Description :** [Problème clair]

**Comment reproduire :**
1. ...
2. ...

**Comportement attendu :** ...
**Comportement obtenu :** ...

**Logs :** [Coller ici]
```

---

## Soumettre du code

### 1. Fork et clone

```bash
git clone https://github.com/VOTRE_COMPTE/NAbySyPhpApi.git
cd NAbySyPhpApi
```

### 2. Créer une branche

```bash
git checkout -b fix/bug-orm-null-field
git checkout -b feature/add-redis-cache
git checkout -b docs/improve-router-guide
```

### 3. Checklist avant PR

- [ ] Compatible PHP 8.1+
- [ ] L'ORM crée toujours les tables automatiquement
- [ ] Les jointures `JoinTable()` fonctionnent
- [ ] Le routeur URL résout correctement les routes
- [ ] Le cache `xCacheFileMGR` fonctionne
- [ ] L'authentification JWT fonctionne
- [ ] Pas de régression sur les modules intégrés

### 4. Soumettre

```bash
git push origin feature/ma-fonctionnalite
# → Ouvrir une Pull Request sur GitHub
```

Décrivez dans la PR : **quoi**, **pourquoi**, **comment tester**.

---

## Standards de code

- Standard **PSR-12** pour le formatage
- PHP **8.1+** — types stricts, `match`, `enum`...
- Nommage :
  - Classes : `PascalCase` → `xORMHelper`, `xBoutique`
  - Méthodes publiques : `PascalCase` → `Enregistrer()`, `JoinTable()`
  - Propriétés ORM : `PascalCase` → `$Nom`, `$Prix`
- Commentaires en français
- Commits : `feat: ...`, `fix: ...`, `docs: ...`

---

## Structure du projet

```
NAbySyPhpApi/
└── src/
    ├── nabysy.php                  # Classe principale xNAbySyGS
    ├── orm.class.php               # ORM + jointures (JoinTable, JointureChargeListe)
    ├── auth.class.php              # JWT
    ├── user.class.php              # Utilisateurs
    ├── observgen.class.php         # Système d'événements
    ├── nabysyurlrouter.class.php   # Routeur URL (xNAbySyUrlRouterHelper)
    ├── GSUrlRouterManager.class.php # Gestionnaire de routes
    ├── xCacheFileMGR.class.php     # Cache fichier
    ├── GsModuleManager.class.php   # Générateur de modules
    ├── gs/                         # Modules métier intégrés
    └── setup.html                  # Interface de configuration
```

---

## Questions ?

- Issue avec le label `question`
- Contact : [paul.isidore@gmail.com](mailto:paul.isidore@gmail.com)

**Merci de contribuer à NAbySyGS !** 🦅
