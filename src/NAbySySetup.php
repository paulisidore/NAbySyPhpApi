<?php
// ============================================================
//  NAbySyGS — Composer Plugin + Setup Launcher
//  Fichier : src/NAbySySetup.php
//
//  En tant que composer-plugin, ce fichier est automatiquement
//  chargé par Composer lors de l'installation chez l'utilisateur.
// ============================================================

namespace NAbySy\xNAbySyGS;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Package\PackageInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;

class NAbySySetup implements PluginInterface, EventSubscriberInterface
{
    // ── Couleurs ANSI pour le terminal ───────────────────────
    private const C_RESET  = "\033[0m";
    private const C_GREEN  = "\033[32m";
    private const C_YELLOW = "\033[33m";
    private const C_CYAN   = "\033[36m";
    private const C_BOLD   = "\033[1m";
    private const C_DIM    = "\033[2m";

    // Nom du package pour le filtrer dans les événements
    private const PACKAGE_NAME = 'nabysyphpapi/xnabysygs';

    // Chemin relatif public vers setup.html
    private const SETUP_PUBLIC_PATH = 'vendor/nabysyphpapi/xnabysygs/src/setup.html';

    private Composer $composer;
    private IOInterface $io;

    // ── PluginInterface ──────────────────────────────────────

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    // ── EventSubscriberInterface ─────────────────────────────

    public static function getSubscribedEvents(): array
    {
        return [
            // Déclenché après l'installation d'un package individuel
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
        ];
    }

    /**
     * Déclenché après l'installation de chaque package.
     * On filtre pour n'agir que sur notre propre package.
     */
    public function onPostPackageInstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();

        // On n'agit que si c'est notre propre package qui vient d'être installé
        if ($package->getName() !== self::PACKAGE_NAME) {
            return;
        }

        // Copier les fichiers de démarrage vers la racine hôte
        $this->runBootstrapInstall();
        
        // Ouvrir le navigateur pour la config        
        $this->runSetup();
    }

    // ── Setup ────────────────────────────────────────────────

    private function runSetup(): void
    {
        $this->printBanner();

        // Chemin racine du projet hôte
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $hostRoot  = dirname($vendorDir) . DIRECTORY_SEPARATOR;
        $appinfos  = $hostRoot . 'appinfos.php';
        $setupFile = __DIR__ . DIRECTORY_SEPARATOR . 'setup.html';

        // Vérifier si déjà configuré
        if (file_exists($appinfos)) {
            $this->io->write(
                self::C_GREEN . "  ✔  NAbySyGS est déjà configuré (appinfos.php trouvé)." . self::C_RESET
            );
            $this->io->write(
                self::C_DIM . "     Supprimez appinfos.php et relancez si vous souhaitez reconfigurer." . self::C_RESET
            );
            $this->printSeparator();
            return;
        }

        // Vérifier que setup.html est présent
        if (!file_exists($setupFile)) {
            $this->io->writeError(
                self::C_YELLOW . "  ⚠  setup.html introuvable dans le package. Installation incomplète ?" . self::C_RESET
            );
            $this->printSeparator();
            return;
        }

        // Construire l'URL file:///
        $setupUrl = $this->buildFileUrl($setupFile);

        $this->io->write(self::C_BOLD . self::C_YELLOW
            . "  ➜  Configuration initiale requise !" . self::C_RESET);
        $this->io->write("");

        // Ouvrir le navigateur ou afficher le lien
        if ($this->hasGraphicEnvironment()) {
            $this->io->write("  Ouverture du navigateur pour la configuration...");
            $opened = $this->openBrowser($setupUrl);

            if ($opened) {
                $this->io->write(self::C_GREEN
                    . "  ✔  Navigateur ouvert : " . $setupUrl . self::C_RESET);
            } else {
                $this->printManualLink($setupUrl);
            }
        } else {
            $this->printManualLink($setupUrl);
        }

        $this->printSeparator();
    }

    private function runBootstrapInstall(): void
    {
        $vendorDir    = $this->composer->getConfig()->get('vendor-dir');
        $hostRoot     = rtrim(dirname($vendorDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $templateDir  = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        $appinfosFile = $hostRoot . 'appinfos.php';

        // Si déjà configuré, rien à copier
        if (file_exists($appinfosFile)) {
            return;
        }

        // ── index.php ──
        $mainEntry = $hostRoot . 'index.php';
        if (!file_exists($mainEntry)) {
            $src = $templateDir . 'template_setup.php';
            if (file_exists($src)) {
                copy($src, $mainEntry);
                $this->io->write("  ✔  index.php généré");
            }
        }

        // ── index_new.php ──
        $indexNew = $hostRoot . 'index_new.php';
        if (!file_exists($indexNew)) {
            $src = $templateDir . 'template_index.php';
            if (file_exists($src)) {
                $template = file_get_contents($src);
                $updated  = str_replace(
                    ['{DATE}', '{MODULE_NAME}'],
                    [date('d/M/Y H:i:s'), 'Mon Application NAbySyGS'],
                    $template
                );
                file_put_contents($indexNew, $updated);
                $this->io->write("  ✔  index_new.php généré");
            }
        }

        // ── setup.html ──
        $setupDest = $hostRoot . 'setup.html';
        $setupSrc  = __DIR__ . DIRECTORY_SEPARATOR . 'setup.html';
        if (!file_exists($setupDest) && file_exists($setupSrc)) {
            copy($setupSrc, $setupDest);
            $this->io->write("  ✔  setup.html copié");
        }

        // ── .htaccess ──
        $htaccess = $hostRoot . '.htaccess';
        if (!file_exists($htaccess)) {
            $src = $templateDir . 'template_htaccess';
            if (file_exists($src)) {
                $template = file_get_contents($src);
                $updated  = str_replace('{NABYSYROOT}', $hostRoot, $template);
                file_put_contents($htaccess, $updated);
                $this->io->write("  ✔  .htaccess généré");
            }
        }

        // ── tmp/ ──
        $tmpDir = $hostRoot . 'tmp' . DIRECTORY_SEPARATOR;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
            $this->io->write("  ✔  Dossier tmp/ créé");
        }

        // ── tmp/.htaccess ──
        $htaccessTmp = $tmpDir . '.htaccess';
        if (!file_exists($htaccessTmp)) {
            $src = $templateDir . 'templateimagetmp_htaccess';
            if (file_exists($src)) {
                $template = file_get_contents($src);
                $updated  = str_replace('{NABYSYROOT}', $hostRoot, $template);
                file_put_contents($htaccessTmp, $updated);
                $this->io->write("  ✔  tmp/.htaccess généré");
            }
        }

        // ── nsy.bat et koro.bat (Windows) ──────────────────────────
        if (PHP_OS_FAMILY === 'Windows') {
            $bats = [
                'nsy.bat'  => 'nsy',
                'koro.bat' => 'koro',
            ];
            foreach ($bats as $batFile => $bin) {
                $batPath = $hostRoot . $batFile;
                if (!file_exists($batPath)) {
                    $content = '@echo off' . "\r\n"
                        . 'php "%~dp0vendor\bin\\' . $bin . '" %*' . "\r\n";
                    file_put_contents($batPath, $content);
                    $this->io->write("  ✔  {$batFile} généré — vous pouvez utiliser '{$bin}' depuis la racine du projet");
                }
            }
            // ── nsy.ps1 et koro.ps1 (PowerShell) ──────────────────────
            $ps1s = ['nsy', 'koro'];
            foreach ($ps1s as $bin) {
                $ps1Path = $hostRoot . $bin . '.ps1';
                if (!file_exists($ps1Path)) {
                    $content = 'php "$PSScriptRoot\vendor\bin\\' . $bin . '" @args' . "\r\n";
                    file_put_contents($ps1Path, $content);
                    $this->io->write("  ✔  {$bin}.ps1 généré — vous pouvez utiliser '{$bin}' dans PowerShell");
                }
            }
        } else {
            // ── Linux / Mac : scripts shell ────────────────────────
            $scripts = ['nsy', 'koro'];
            foreach ($scripts as $bin) {
                $scriptPath = $hostRoot . $bin;
                if (!file_exists($scriptPath)) {
                    $content = '#!/bin/sh' . "\n"
                        . 'php "$(dirname "$0")/vendor/bin/' . $bin . '" "$@"' . "\n";
                    file_put_contents($scriptPath, $content);
                    chmod($scriptPath, 0755);
                    $this->io->write("  ✔  {$bin} généré — vous pouvez utiliser './{$bin}' depuis la racine du projet");
                }
            }
        }

        // ── Injecter bootstrap.php dans l'autoload du projet hôte ──
        $hostComposer = $hostRoot . 'composer.json';
        if (file_exists($hostComposer)) {
            $json = json_decode(file_get_contents($hostComposer), true);
            $bootstrapPath = 'vendor/nabysyphpapi/xnabysygs/src/bootstrap.php';
            
            // Vérifier s'il n'est pas déjà injecté
            $alreadyThere = in_array(
                $bootstrapPath,
                $json['autoload']['files'] ?? []
            );
            
            if (!$alreadyThere) {
                $json['autoload']['files'][] = $bootstrapPath;
                file_put_contents(
                    $hostComposer,
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                $this->io->write("  ✔  bootstrap.php enregistré dans l'autoload");
            }
        }
    }

    // ────────────────────────────────────────────────────────
    //  Construction URL file:///
    // ────────────────────────────────────────────────────────
    private function buildFileUrl(string $absolutePath): string
    {
        $real = realpath($absolutePath);
        if (!$real) $real = $absolutePath;

        if (PHP_OS_FAMILY === 'Windows') {
            $real = str_replace('\\', '/', $real);
            return 'file:///' . $real;
        }

        return 'file://' . $real;
    }

    // ────────────────────────────────────────────────────────
    //  Détection environnement graphique
    // ────────────────────────────────────────────────────────
    private function hasGraphicEnvironment(): bool
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') return true;
        if ($os === 'Darwin')  return true;

        if ($os === 'Linux') {
            return !empty(getenv('DISPLAY'))
                || !empty(getenv('WAYLAND_DISPLAY'))
                || !empty(getenv('XDG_CURRENT_DESKTOP'));
        }

        return false;
    }

    // ────────────────────────────────────────────────────────
    //  Ouverture navigateur
    // ────────────────────────────────────────────────────────
    private function openBrowser(string $url): bool
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') {
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
                "xdg-open {$safeUrl}",
                "gnome-open {$safeUrl}",
                "kde-open {$safeUrl}",
                "sensible-browser {$safeUrl}",
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
    //  Affichage lien manuel
    // ────────────────────────────────────────────────────────
    private function printManualLink(string $url): void
    {
        $this->io->write(
            "  " . self::C_DIM . "Environnement sans interface graphique détecté." . self::C_RESET
        );
        $this->io->write(
            "  Copiez le chemin ci-dessous et ouvrez-le dans votre navigateur :"
        );
        $this->io->write("");
        $this->io->write(
            "  " . self::C_BOLD . self::C_CYAN . "  ➜  " . $url . self::C_RESET
        );
        $this->io->write("");
        $this->io->write(
            self::C_YELLOW
            . "  ⚠  Le formulaire s'ouvrira en local (file://).\n"
            . "     Assurez-vous que votre serveur web est démarré avant de soumettre\n"
            . "     le formulaire, car l'appel API nécessite un serveur HTTP actif."
            . self::C_RESET
        );
    }

    // ────────────────────────────────────────────────────────
    //  Bannière Koro
    // ────────────────────────────────────────────────────────
    private function printBanner(): void
    {
        $this->io->write("");
        $this->io->write(self::C_GREEN . self::C_BOLD
. "  ╔══════════════════════════════════════════════╗
  ║   _  _   _   _           _____        _____  ║
  ║  | \| | /_\ | |__ _  _ / __\ \  __  / ____|  ║
  ║  | .  |/ _ \| '_ \ || |\__ \\ \/  \| |  _    ║
  ║  |_|\_/_/ \_\_.__/\_, ||___/ \__/\_/|_____/  ║
  ║                   |__/        by Koro 🦅      ║
  ╚══════════════════════════════════════════════╝"
        . self::C_RESET);
        $this->io->write("");
    }

    private function printSeparator(): void
    {
        $this->io->write(self::C_DIM
            . "  ──────────────────────────────────────────────"
            . self::C_RESET);
        $this->io->write("");
    }
}