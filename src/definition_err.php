<?php
    // ── Lecture dynamique de la version depuis composer.json ─
    if (!function_exists('getVersion')){
        function getVersion(): string
        {
            $MY_VERSION="1.2.6";
            // Chercher le composer.json du package CLI lui-même
            $composerJson = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($composerJson)) {
                $data = json_decode(file_get_contents($composerJson), true);
                if (!empty($data['version'])) return $data['version'];
            }

            // Fallback : lire depuis le composer.lock du projet hôte
            $lockFile = "./". 'composer.lock';
            if (file_exists($lockFile)) {
                $lock = json_decode(file_get_contents($lockFile), true);
                foreach ($lock['packages'] ?? [] as $pkg) {
                    if ($pkg['name'] === 'nabysyphpapi/xnabysygs') {
                        return ltrim($pkg['version'], 'v');
                    }
                }
            }
            return $MY_VERSION ; // Dernier recours
        }
    }

    define('NABYSY_VERSION',getVersion());

    define('ERR_UNCKNOW',-1);
    define('ERR_SYSTEM',0);

    define('ERR_STARTUP_INFO_MISSING',1);
    define('ERR_STARTUP_INFO_CONN_MISSING',2);
    define('ERR_MISSING_DB_SERVER',3);
    
    define('ERR_MISSING_MODULE_TYPE',4);

    define('ERR_FILE_SYSTEM',5);
    
?>