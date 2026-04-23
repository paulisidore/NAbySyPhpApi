<?php
// ============================================================
//  NAbySyGS — CLI Principal
//  Fichier : src/NAbySyCLI.php
//
//  Usage : nsy <commande> [arguments] [options]
//  Alias : koro <commande> [arguments] [options]
//
//  Commandes :
//    create categorie <nom> [-a] [-o] [-t <table>]  (alias: c cat)
//    create action    <nom>                          (alias: c a)
//    create orm       <nom> <table> [dossier]        (alias: c o)
//    create route     <nom> <dossier>                (alias: c r)
//    version                                         (alias: v)
//    help                                            (alias: h)
//
//  Options globales :
//    --root <chemin>   Racine du projet hôte
//    --debug           Mode verbeux
// ============================================================

class NAbySyCLI
{
    // ── Couleurs ANSI ────────────────────────────────────────
    private const R  = "\033[0m";
    private const G  = "\033[32m";
    private const Y  = "\033[33m";
    private const C  = "\033[36m";
    private const R2 = "\033[31m";
    private const B  = "\033[1m";
    private const D  = "\033[2m";

    private const VERSION = '1.1.0';

    // ── Alias de commandes ───────────────────────────────────
    private const ALIASES = [
        'c'   => 'create',
        'h'   => 'help',
        'v'   => 'version',
        // sous-commandes create
        'cat' => 'categorie',
        'a'   => 'action',
        'o'   => 'orm',
        'r'   => 'route',
    ];

    private static bool  $debug    = false;
    private static string $root    = '';

    // ============================================================
    //  Point d'entrée
    // ============================================================
    public static function run(array $argv): void
    {
        // Nom de la commande appelée (nsy ou koro)
        $bin = basename($argv[0]);

        // Extraire options globales avant de parser les commandes
        [$args, $opts] = self::parseArgs(array_slice($argv, 1));

        self::$debug = isset($opts['debug']);

        // --root fourni manuellement
        if (isset($opts['root'])) {
            $root = rtrim($opts['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!is_dir($root)) {
                self::error("Le chemin spécifié avec --root est invalide : {$root}");
                exit(1);
            }
            self::$root = $root;
        } else {
            // Remonter depuis getcwd() pour trouver la racine du projet
            self::$root = self::findHostRoot(getcwd()) ?? '';
        }

        // Résoudre les alias de commande principale
        $cmd = strtolower($args[0] ?? 'help');
        $cmd = self::ALIASES[$cmd] ?? $cmd;

        self::printBanner($bin);

        match ($cmd) {
            'create'  => self::cmdCreate(array_slice($args, 1), $opts),
            'version' => self::cmdVersion(),
            'help'    => self::cmdHelp($bin),
            default   => self::cmdUnknown($cmd, $bin),
        };
    }

    // ============================================================
    //  Commande : create
    // ============================================================
    private static function cmdCreate(array $args, array $opts): void
    {
        $sub = strtolower($args[0] ?? '');
        $sub = self::ALIASES[$sub] ?? $sub;

        match ($sub) {
            'categorie' => self::createCategorie(array_slice($args, 1), $opts),
            'action'    => self::createAction(array_slice($args, 1), $opts),
            'orm'       => self::createOrm(array_slice($args, 1), $opts),
            'route'     => self::createRoute(array_slice($args, 1), $opts),
            default     => self::error(
                "Sous-commande 'create {$sub}' inconnue.\n"
                . "  Utilisez: categorie (cat) | action (a) | orm (o) | route (r)"
            ),
        };
    }

    // ── create categorie ────────────────────────────────────
    private static function createCategorie(array $args, array $opts): void
    {
        $nom = $args[0] ?? '';
        if (empty($nom)) {
            self::error("Nom de catégorie requis.\n  Usage: nsy create categorie <nom> [-a] [-o] [-t <table>]");
            exit(1);
        }

        $createAction = isset($opts['a']) || isset($opts['action']);
        $createOrm    = isset($opts['o']) || isset($opts['orm']);
        $table        = $opts['t'] ?? $opts['table'] ?? null;

        if ($createOrm && empty($table)) {
            self::error("L'option -o (ORM) nécessite -t <table>.\n  Exemple: nsy create categorie {$nom} -o -t ma_table");
            exit(1);
        }

        self::info("Création de la catégorie " . self::B . self::C . $nom . self::R . "...");
        if ($createAction) self::dim("  → Fichier action activé");
        if ($createOrm)    self::dim("  → Classe ORM activée (table: {$table})");

        $N = self::loadNAbySy();
        if (!$N) return;

        try {
            $result = \N::$GSModManager::CreateCategorie($nom, $createAction, $createOrm, $table);
            if ($result) {
                self::success("Catégorie " . self::B . $nom . self::R . self::G . " créée avec succès !");
                self::dim("  Dossier : " . self::$root . "gs" . DIRECTORY_SEPARATOR . $nom);
            } else {
                self::error("Échec de la création de la catégorie '{$nom}'.");
            }
        } catch (\Throwable $e) {
            self::error("Erreur : " . $e->getMessage());
            if (self::$debug) self::dim($e->getTraceAsString());
        }
    }

    // ── create action ───────────────────────────────────────
    private static function createAction(array $args, array $opts): void
    {
        $nom = $args[0] ?? '';
        if (empty($nom)) {
            self::error("Nom requis.\n  Usage: nsy create action <nom>");
            exit(1);
        }

        self::info("Création du fichier action " . self::B . self::C . $nom . self::R . "...");

        $N = self::loadNAbySy();
        if (!$N) return;

        try {
            $result = \N::$GSModManager::GenerateActionAPIFile($nom);
            if ($result && (is_bool($result) ? $result : $result->OK == 1)) {
                $src = is_object($result) ? $result->Source : '';
                self::success("Fichier action " . self::B . $nom . self::R . self::G . " créé !");
                if ($src) self::dim("  Fichier : {$src}");
            } else {
                $msg = is_object($result) ? ($result->TxErreur ?? '') : '';
                self::error("Échec : {$msg}");
            }
        } catch (\Throwable $e) {
            self::error("Erreur : " . $e->getMessage());
            if (self::$debug) self::dim($e->getTraceAsString());
        }
    }

    // ── create orm ──────────────────────────────────────────
    private static function createOrm(array $args, array $opts): void
    {
        $nom     = $args[0] ?? '';
        $table   = $args[1] ?? '';
        $dossier = $args[2] ?? '';

        if (empty($nom) || empty($table)) {
            self::error("Nom et table requis.\n  Usage: nsy create orm <nom> <table> [dossier]");
            exit(1);
        }

        // Si dossier non fourni, utiliser gs/<nom> par défaut
        if (empty($dossier)) {
            $dossier = self::$root . 'gs' . DIRECTORY_SEPARATOR . strtolower($nom);
        }

        self::info("Création de la classe ORM " . self::B . self::C . $nom . self::R
            . " (table: " . self::Y . $table . self::R . ")...");

        $N = self::loadNAbySy();
        if (!$N) return;

        try {
            $result = \N::$GSModManager::GenerateORMClass($nom, $dossier, $table);
            if ($result && $result->OK == 1) {
                self::success("Classe ORM " . self::B . $nom . self::R . self::G . " créée !");
                if ($result->Source) self::dim("  Fichier : {$result->Source}");
            } else {
                self::error("Échec : " . ($result->TxErreur ?? ''));
            }
        } catch (\Throwable $e) {
            self::error("Erreur : " . $e->getMessage());
            if (self::$debug) self::dim($e->getTraceAsString());
        }
    }

    // ── create route ────────────────────────────────────────
    private static function createRoute(array $args, array $opts): void
    {
        $nom     = $args[0] ?? '';
        $dossier = $args[1] ?? '';

        if (empty($nom)) {
            self::error("Nom requis.\n  Usage: nsy create route <nom> <dossier>");
            exit(1);
        }

        if (empty($dossier)) {
            $dossier = self::$root . 'gs' . DIRECTORY_SEPARATOR . strtolower($nom);
        }

        self::info("Création du contrôleur de route " . self::B . self::C . $nom . self::R . "...");

        $N = self::loadNAbySy();
        if (!$N) return;

        try {
            $result = \N::$GSModManager::GenerateUrlRouteController($nom, $dossier);
            if ($result && $result->OK == 1) {
                self::success("Contrôleur de route " . self::B . $nom . self::R . self::G . " créé !");
                if ($result->Source) self::dim("  Fichier : {$result->Source}");
            } else {
                self::error("Échec : " . ($result->TxErreur ?? ''));
            }
        } catch (\Throwable $e) {
            self::error("Erreur : " . $e->getMessage());
            if (self::$debug) self::dim($e->getTraceAsString());
        }
    }

    // ============================================================
    //  Commande : version
    // ============================================================
    private static function cmdVersion(): void
    {
        echo self::G . self::B . "NAbySyGS CLI" . self::R
            . " version " . self::Y . self::VERSION . self::R
            . " 🦅 Koro\n";
    }

    // ============================================================
    //  Commande : help
    // ============================================================
    private static function cmdHelp(string $bin): void
    {
        $b = self::B; $r = self::R; $g = self::G;
        $y = self::Y; $c = self::C; $d = self::D;

        echo <<<HELP

{$b}Usage:{$r}
  {$g}{$bin}{$r} <commande> [sous-commande] [arguments] [options]
  {$g}koro{$r}  <commande> [sous-commande] [arguments] [options]

{$b}Commandes disponibles:{$r}

  {$g}create{$r} {$d}(alias: c){$r}
    {$y}categorie{$r} {$d}(cat){$r}  <nom> [-a] [-o] [-t <table>]
        Crée une catégorie NAbySyGS avec fichier action et/ou classe ORM.
        {$d}-a, --action    Générer le fichier action API
        -o, --orm       Générer la classe ORM (nécessite -t)
        -t, --table     Nom de la table associée{$r}

    {$y}action{$r} {$d}(a){$r}      <nom>
        Génère uniquement le fichier action API pour une catégorie.

    {$y}orm{$r} {$d}(o){$r}         <nom> <table> [dossier]
        Génère une classe ORM. Dossier optionnel (défaut: gs/<nom>).

    {$y}route{$r} {$d}(r){$r}       <nom> [dossier]
        Crée un contrôleur de route URL. Dossier optionnel (défaut: gs/<nom>).

  {$g}version{$r} {$d}(v){$r}
    Affiche la version du CLI.

  {$g}help{$r} {$d}(h){$r}
    Affiche cette aide.

{$b}Options globales:{$r}
  {$y}--root{$r} <chemin>   Racine du projet hôte (sinon détectée automatiquement)
  {$y}--debug{$r}           Afficher les détails d'exécution

{$b}Exemples:{$r}
  {$c}{$bin} create categorie client -a -o -t clients{$r}
  {$c}{$bin} c cat client -a -o -t clients{$r}
  {$c}koro c cat client -a -o -t clients{$r}

  {$c}{$bin} create action produit{$r}
  {$c}{$bin} c a produit{$r}

  {$c}{$bin} create orm xProduit produits gs/produit{$r}
  {$c}{$bin} c o xProduit produits gs/produit{$r}

  {$c}{$bin} create route produit gs/produit{$r}
  {$c}{$bin} c r produit gs/produit{$r}

  {$c}{$bin} create categorie client --root /var/www/monprojet{$r}

HELP;
    }

    // ============================================================
    //  Chargement de NAbySyGS
    // ============================================================
    private static function loadNAbySy(): mixed
    {
        if (empty(self::$root)) {
            self::error(
                "Racine du projet introuvable.\n\n"
                . "  " . self::D . "NAbySyGS recherche un dossier contenant vendor/ + composer.json\n"
                . "  en remontant depuis le dossier courant." . self::R . "\n\n"
                . "  Solutions :\n"
                . "  • Lancez la commande depuis votre projet : " . self::Y . "cd /votre/projet && nsy ..." . self::R . "\n"
                . "  • Ou spécifiez la racine : " . self::Y . "nsy ... --root /chemin/projet" . self::R
            );
            return null;
        }

        $appinfos = self::$root . 'appinfos.php';

        if (!file_exists($appinfos)) {
            self::error(
                "appinfos.php introuvable dans : " . self::$root . "\n\n"
                . "  " . self::D . "Votre projet NAbySyGS n'est pas encore configuré." . self::R . "\n\n"
                . "  Lancez le setup en ouvrant " . self::Y . "setup.html" . self::R
                . " dans votre navigateur."
            );
            return null;
        }

        // Charger le framework
        try {
            require_once self::$root . 'vendor/autoload.php';
            include_once $appinfos;

            if (!class_exists('N')) {
                self::error("La classe N (NAbySyGS) n'a pas pu être chargée.");
                return null;
            }

            if (!isset(\N::$GSModManager)) {
                self::error(
                    "N::\$GSModManager n'est pas initialisé.\n"
                    . "  Vérifiez que votre appinfos.php initialise correctement NAbySyGS."
                );
                return null;
            }

            self::dim("✔ NAbySyGS chargé depuis : " . self::$root);
            return true;

        } catch (\Throwable $e) {
            self::error("Erreur lors du chargement de NAbySyGS : " . $e->getMessage());
            if (self::$debug) self::dim($e->getTraceAsString());
            return null;
        }
    }

    // ============================================================
    //  Détection dynamique de la racine du projet hôte
    //  Remonte depuis $startDir jusqu'à trouver vendor/ + composer.json
    // ============================================================
    private static function findHostRoot(string $startDir, int $maxLevels = 10): ?string
    {
        $current = rtrim($startDir, DIRECTORY_SEPARATOR);
        for ($i = 0; $i <= $maxLevels; $i++) {
            if (is_dir($current . DIRECTORY_SEPARATOR . 'vendor')
                && file_exists($current . DIRECTORY_SEPARATOR . 'composer.json')
            ) {
                return $current . DIRECTORY_SEPARATOR;
            }
            $parent = dirname($current);
            if ($parent === $current) break; // racine système
            $current = $parent;
        }
        return null;
    }

    // ============================================================
    //  Parser d'arguments CLI
    //  Retourne [$args_positionnels, $options]
    // ============================================================
    private static function parseArgs(array $argv): array
    {
        $args = [];
        $opts = [];
        $i    = 0;

        while ($i < count($argv)) {
            $arg = $argv[$i];

            if (str_starts_with($arg, '--')) {
                // Option longue : --root /chemin  ou --debug
                $key = substr($arg, 2);
                if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $opts[$key] = $argv[$i + 1];
                    $i++;
                } else {
                    $opts[$key] = true;
                }
            } elseif (str_starts_with($arg, '-') && strlen($arg) === 2) {
                // Option courte : -a -o -t clients
                $key = substr($arg, 1);
                if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $opts[$key] = $argv[$i + 1];
                    $i++;
                } else {
                    $opts[$key] = true;
                }
            } else {
                $args[] = $arg;
            }
            $i++;
        }

        return [$args, $opts];
    }

    // ============================================================
    //  Bannière Koro
    // ============================================================
    private static function printBanner(string $bin): void
    {
        $alias = ($bin === 'koro' || $bin === 'koro.bat') ? ' (koro 🦅)' : ' 🦅';
        echo self::G . self::B
. "
  ╔══════════════════════════════════════════════╗
  ║        NAbySyGS CLI{$alias}               ║
  ╚══════════════════════════════════════════════╝
" . self::R;
    }

    // ============================================================
    //  Helpers d'affichage
    // ============================================================
    private static function success(string $msg): void {
        echo self::G . "  ✔  " . $msg . self::R . "\n";
    }
    private static function error(string $msg): void {
        echo self::R2 . "  ✘  " . $msg . self::R . "\n";
    }
    private static function info(string $msg): void {
        echo self::Y . "  ➜  " . $msg . self::R . "\n";
    }
    private static function dim(string $msg): void {
        echo self::D . "     " . $msg . self::R . "\n";
    }
    private static function cmdUnknown(string $cmd, string $bin): void {
        self::error("Commande '{$cmd}' inconnue.");
        echo self::D . "  Tapez " . self::R . self::Y . "{$bin} help" . self::R
            . self::D . " pour voir les commandes disponibles." . self::R . "\n";
    }
}
