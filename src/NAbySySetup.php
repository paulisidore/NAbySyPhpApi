<?php
// ============================================================
//  NAbySyGS — Post-Install Setup Launcher
//  Fichier : src/NAbySySetup.php
//
//  Déclenché automatiquement par Composer après installation.
//  - Détecte l'URL publique du projet hôte
//  - Tente d'ouvrir le navigateur (si environnement graphique)
//  - Sinon affiche le lien dans le terminal
// ============================================================

namespace NAbySy\xNAbySyGS;

use Composer\Script\Event;

class NAbySySetup
{
    // Chemin relatif public vers setup.html depuis la racine du projet hôte
    // vendor/{vendor-name}/{package-name}/src/setup.html
    private const SETUP_PUBLIC_PATH = 'vendor/nabysyphpapi/xnabysygs/src/setup.html';


    private const C_RESET  = "\033[0m";
    private const C_GREEN  = "\033[32m";
    private const C_YELLOW = "\033[33m";
    private const C_CYAN   = "\033[36m";
    private const C_BOLD   = "\033[1m";
    private const C_DIM    = "\033[2m";

    /**
     * Point d'entrée Composer post-install / post-update
     */
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();

        self::printBanner($io);

        // Vérifier si appinfos.php existe déjà dans le projet hôte
        $hostRoot    = self::getHostRoot($event);
        $appinfos    = $hostRoot . 'appinfos.php';
        // __DIR__ pointe vers le dossier src/ du package, où setup.html est aussi placé.
        // Cette approche est robuste quel que soit le nom du vendor ou la structure du projet hôte.
        $setupFile   = __DIR__ . DIRECTORY_SEPARATOR . 'setup.html';

        if (file_exists($appinfos)) {
            $io->write(
                self::C_GREEN . "  ✔  NAbySyGS est déjà configuré (appinfos.php trouvé)." . self::C_RESET
            );
            $io->write(
                self::C_DIM   . "     Supprimez appinfos.php et relancez si vous souhaitez reconfigurer." . self::C_RESET
            );
            self::printSeparator($io);
            return;
        }

        if (!file_exists($setupFile)) {
            $io->writeError(
                self::C_YELLOW . "  ⚠  setup.html introuvable dans le package. Installation incomplète ?" . self::C_RESET
            );
            self::printSeparator($io);
            return;
        }

        // Détecter l'URL publique
        $setupUrl = self::detectSetupUrl($hostRoot);

        $io->write(self::C_BOLD . self::C_YELLOW
            . "  ➜  Configuration initiale requise !" . self::C_RESET);
        $io->write("");

        // Tenter d'ouvrir le navigateur si environnement graphique disponible
        if (self::hasGraphicEnvironment()) {
            $io->write("  Ouverture du navigateur pour la configuration...");
            $opened = self::openBrowser($setupUrl);

            if ($opened) {
                $io->write(self::C_GREEN
                    . "  ✔  Navigateur ouvert : " . $setupUrl . self::C_RESET);
            } else {
                // Fallback : afficher le lien
                self::printManualLink($io, $setupUrl);
            }
        } else {
            // Pas d'environnement graphique (serveur headless)
            self::printManualLink($io, $setupUrl);
        }

        self::printSeparator($io);
    }

    // ────────────────────────────────────────────────────────
    //  Détection du dossier racine du projet HÔTE
    // ────────────────────────────────────────────────────────
    private static function getHostRoot(Event $event): string
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        // vendor-dir est dans la racine du projet hôte
        $root = dirname($vendorDir) . DIRECTORY_SEPARATOR;
        return $root;
    }

    // ────────────────────────────────────────────────────────
    //  Détection de l'URL publique du projet hôte
    // ────────────────────────────────────────────────────────
    /**
     * Construit l'URL file:/// vers setup.html pour ouverture directe dans le navigateur.
     * Évite toute tentative de deviner le virtual host du projet hôte.
     */
    private static function detectSetupUrl(string $hostRoot): string
    {
        // Chemin absolu vers setup.html dans le package
        $absolutePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'setup.html');

        if ($absolutePath) {
            // Convertir en URL file:/// selon l'OS
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows : C:\path\to\file → file:///C:/path/to/file
                $absolutePath = str_replace('\\', '/', $absolutePath);
                return 'file:///' . $absolutePath;
            } else {
                // Linux / macOS : /path/to/file → file:///path/to/file
                return 'file://' . $absolutePath;
            }
        }

        // Fallback : chemin HTTP avec le nom du dossier si realpath échoue
        $folderName = basename(rtrim($hostRoot, DIRECTORY_SEPARATOR));
        return 'http://' . $folderName . '/' . self::SETUP_PUBLIC_PATH;
    }

    // ────────────────────────────────────────────────────────
    //  Détection d'un environnement graphique
    // ────────────────────────────────────────────────────────
    private static function hasGraphicEnvironment(): bool
    {
        $os = PHP_OS_FAMILY; // 'Windows', 'Linux', 'Darwin'

        if ($os === 'Windows') {
            return true; // Windows a toujours un bureau
        }

        if ($os === 'Darwin') {
            return true; // macOS a toujours un bureau
        }

        if ($os === 'Linux') {
            // Vérifier la présence d'un serveur d'affichage X11 ou Wayland
            $hasDisplay  = !empty(getenv('DISPLAY'));
            $hasWayland  = !empty(getenv('WAYLAND_DISPLAY'));
            // Certains environnements Desktop définissent XDG_CURRENT_DESKTOP
            $hasDesktop  = !empty(getenv('XDG_CURRENT_DESKTOP'));
            return $hasDisplay || $hasWayland || $hasDesktop;
        }

        return false;
    }

    // ────────────────────────────────────────────────────────
    //  Ouverture du navigateur selon l'OS
    // ────────────────────────────────────────────────────────
    private static function openBrowser(string $url): bool
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') {
            // Sur Windows on utilise cmd /c start pour éviter l'erreur
            // "canal inexistant" causée par popen/pclose avec la commande start.
            // escapeshellarg ajoute des guillemets simples incompatibles avec cmd.exe,
            // on échappe donc manuellement les caractères dangereux.
            $safeUrl = str_replace(['"', '^', '&', '<', '>', '|'], '', $url);
            $cmd = 'cmd /c start "" "' . $safeUrl . '" > NUL 2>&1';
            @exec($cmd);
            return true;
        }

        if ($os === 'Darwin') {
            $safeUrl = escapeshellarg($url);
            @exec("open {$safeUrl} > /dev/null 2>&1 &");
            return true;
        }

        if ($os === 'Linux') {
            $safeUrl  = escapeshellarg($url);
            $commands = [
                "xdg-open {$safeUrl}",         // Standard Linux Desktop
                "gnome-open {$safeUrl}",        // GNOME fallback
                "kde-open {$safeUrl}",          // KDE fallback
                "sensible-browser {$safeUrl}",  // Debian/Ubuntu fallback
            ];
            foreach ($commands as $cmd) {
                $result = -1;
                @exec("{$cmd} > /dev/null 2>&1 &", result_code: $result);
                if ($result === 0) return true;
            }
        }

        return false;
    }

    // ────────────────────────────────────────────────────────
    //  Affichage du lien manuel dans le terminal
    // ────────────────────────────────────────────────────────
    private static function printManualLink($io, string $url): void
    {
        $io->write(
            "  " . self::C_DIM . "Environnement sans interface graphique détecté." . self::C_RESET
        );
        $io->write(
            "  Copiez le chemin ci-dessous et ouvrez-le dans votre navigateur :"
        );
        $io->write("");
        $io->write(
            "  " . self::C_BOLD . self::C_CYAN . "  ➜  " . $url . self::C_RESET
        );
        $io->write("");
        $io->write(
            self::C_YELLOW
            . "  ⚠  Le formulaire s'ouvrira en local (file://).\n"
            . "     Assurez-vous que votre serveur web est démarré avant de soumettre\n"
            . "     le formulaire, car l'appel API nécessite un serveur HTTP actif."
            . self::C_RESET
        );
    }

    // ────────────────────────────────────────────────────────
    //  Affichage de la bannière Koro
    // ────────────────────────────────────────────────────────
    private static function printBanner($io): void
    {
        $io->write("");
        $io->write(self::C_GREEN . self::C_BOLD
. "  ╔══════════════════════════════════════════════╗
  ║   _  _   _   _           _____        _____  ║
  ║  | \| | /_\ | |__ _  _ / __\ \  __  / ____|  ║
  ║  | .  |/ _ \| '_ \ || |\__ \\ \/  \| |  _    ║
  ║  |_|\_/_/ \_\_.__/\_, ||___/ \__/\_/|_____/  ║
  ║                   |__/        by Koro 🦅      ║
  ╚══════════════════════════════════════════════╝"
        . self::C_RESET);
        $io->write("");
    }

    private static function printSeparator($io): void
    {
        $io->write(self::C_DIM
            . "  ──────────────────────────────────────────────"
            . self::C_RESET);
        $io->write("");
    }
}