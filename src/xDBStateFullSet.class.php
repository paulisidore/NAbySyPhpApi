<?php
use NAbySy\xNAbySyGS;

class xDBStateFullSet {
    private static array $schema = [];
    private static string $cachePath = "dbcache_schema.php";
    public static xNAbySyGS $NAbySy;

    private static bool $isReady = false ;

    /**
     * Si Vrai, les echec de vérification de champs seront logger
     * @var bool
     */
    public static bool $LogFailled = false;

    /**
     * Si Vrai, toutes les vérifications de champs seront logger
     * @var bool
     */
    public static bool $LogAll = false;

    public function __construct(xNAbySyGS $nabysy, string $cachePath = "dbcache_schema.php", bool $LogAllRequest = false, bool $LogFailledRequest = true) {
        self::$LogAll = $LogAllRequest ;
        self::$LogFailled = $LogFailledRequest ;
        self::$NAbySy = $nabysy;
        self::$cachePath = $cachePath;
        if (file_exists(self::$cachePath)) {
            // Chargement ultra-rapide via OPCache
            self::$schema = include(self::$cachePath);
            if(is_array(self::$schema) && isset(self::$schema['databases']) && isset(self::$schema['fields'])){
                self::$isReady = true ;
            }else{
                $nabysy::$Log->AddToLog("Le cache de schéma est corrompu ou incomplet, reconstruction nécessaire.", 3);
            }
        }else{
            $nabysy::$Log->AddToLog("ATTENTION: Absence du Cache de schéma, un cache de schéma permet de gagner en performance.", 3);
        }
    }

    public static function init(xNAbySyGS $nabysy, string $filecachePath = "dbcache_schema.php", bool $LogAllRequest = false, bool $LogFailledRequest = false): void {
        // Si déjà chargé durant cette requête HTTP, on ne refait rien
        if (self::$isReady) return;

        self::$NAbySy = $nabysy;
        self::$cachePath = $filecachePath;
        self::$LogAll = $LogAllRequest ;
        self::$LogFailled = $LogFailledRequest ;

        if ($nabysy::$CREATE_DBCACHE_ON_LOAD) {
            $isCreate=self::createCacheFile($nabysy, $filecachePath);
            $nabysy::$Log->AddToLog("Création du cache de la structure de base de donnée à la volée: ".($isCreate ? "Succès" : "Echec"), 3);
        }

        if (file_exists(self::$cachePath)) {
            // Chargement ultra-rapide via OPCache
            self::$schema = include(self::$cachePath);
            if(is_array(self::$schema) && isset(self::$schema['databases']) && isset(self::$schema['fields'])){
                self::$isReady = true ;
            }else{
                $nabysy::$Log->AddToLog("Le cache de schéma est corrompu ou incomplet, reconstruction nécessaire.", 3);
            }
        }else{
            $nabysy::$Log->AddToLog("Dossier courant [".__DIR__."], un cache de schéma permet de gagner en performance.", 3);
            $nabysy::$Log->AddToLog("ATTENTION: Absence du Cache de schéma [".self::$cachePath."], un cache de schéma permet de gagner en performance.", 3);
        }
    }

    private static function createCacheFile(xNAbySyGS $nabysy, string $dbcache_file="dbcache_schema.php"):bool{
        if($nabysy::$CREATE_DBCACHE_ON_LOAD){
            $retour = false ;
            $fichierGeneration= $nabysy::$NABYSY_DIRECTORY . 'generate_cache.php' ;
            if(file_exists($fichierGeneration)){
                $nabysy::$Log->AddToLog("Génération du cache de la structure de base de donnée...") ;
                require_once($fichierGeneration);
                if(function_exists('rebuildSchemaCache')){
                    $retour=rebuildSchemaCache($nabysy::$db_link, $dbcache_file);
                    $nabysy::$CREATE_DBCACHE_ON_LOAD = false ;
                    if($retour){
                        $nabysy::$Log->AddToLog("Cache de la structure de base de donnée généré avec succès.") ;
                    }
                }else{
                    $nabysy::$Log->AddToLog("La fonction [rebuildSchemaCache] de reconstruction du cache est absente du fichier de génération, impossible de reconstruire le cache.", 3);
                }
            }else{
                $nabysy::$Log->AddToLog("Le fichier de génération du cache de la base de donnée est absent: ".$fichierGeneration, 3);
            }
            if ($nabysy::$CREATE_DBCACHE_ON_LOAD == true){
                $nabysy::$Log->AddToLog("ATTENTION: Echec de la génération du cache de la base de donnée.", 3);
            }
            return $retour ;
        }
        return true ;
    }

    public static function Ready(): bool {
        return self::$isReady;
    }

    // --- VÉRIFICATIONS VIA CACHE ---

    /**
     * Vérifie la présence d'une base de donnée
     * @param string $db 
     * @return bool 
     */
    public static function databaseExists(string $db): bool {
         if (isset(self::$schema['databases'][$db]) || isset(self::$schema['databases'][strtolower($db)])) {
            if(self::$LogAll){
                self::$NAbySy::$Log->AddToLog("Base de données '$db' trouvée dans le cache.", 5);
            }
            return true;
        }
        if(self::$LogAll || self::$LogFailled){
            self::$NAbySy::$Log->AddToLog("Base de données '$db' non trouvée dans le cache, vérification en temps réel.", 5);
        }
        return self::checkDataBaseInRealTime($db);
    }

    /**
     * Vérifie la présence d'une Table dans une base de donnée
     * @param string $db 
     * @param string $table 
     * @return bool 
     */
    public static function tableExists(string $db, string $table): bool {
        if (isset(self::$schema['fields'][$db][$table]) || isset(self::$schema['fields'][strtolower($db)][strtolower($table)])) {
            if(self::$LogAll){
                self::$NAbySy::$Log->AddToLog("Table '$db.$table' trouvée dans le cache.", 6);
            }
            return true;
        }
        if(self::$LogAll || self::$LogFailled){
            self::$NAbySy::$Log->AddToLog("Table '$db.$table' non trouvée dans le cache, vérification en temps réel.", 6);
        }
        return self::checkTableInRealTime($db,$table);
    }

    /**
     * Vérifie la présence d'un champ dans une Table d'une Base de donnée
     * @param string $db 
     * @param string $table 
     * @param string $field 
     * @return bool 
     */
    public static function fieldExists(string $db, string $table, string $field): bool {
        // Si trouvé dans le cache, on répond direct (0 CPU MariaDB)
        if (isset(self::$schema['fields'][$db][$table][$field]) || isset(self::$schema['fields'][strtolower($db)][strtolower($table)][strtolower($field)])) {
            if(self::$LogAll){
                self::$NAbySy::$Log->AddToLog("Champ '$db.$table.$field' trouvé dans le cache.", 5);
            }
            return true;
        }
        if(self::$LogAll || self::$LogFailled){
            self::$NAbySy::$Log->AddToLog("Champ '$db.$table.$field' non trouvé dans le cache, vérification en temps réel.", 5);
        }
        //echo "On recherche le champ '$db.$table.$field' hors du cache...<br>";
        // --- FALLBACK : Si absent du cache, on demande à MariaDB une seule fois ---
        return self::checkFieldInRealTime($db, $table, $field);
    }

    // --- MÉTHODE DE SECOURS (FALLBACK) ---

    private static function checkFieldInRealTime(string $db, string $table, string $field): bool {
        try {
            $db_link = self::$NAbySy::$db_link;
            // Préparation de la requête
            $stmt = $db_link->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            
            // "sss" signifie que nous lions 3 chaînes de caractères (strings)
            $stmt->bind_param("sss", $db, $table, $field);
            $stmt->execute();
            
            // On récupère le résultat
            $result = $stmt->get_result();
            $exists = ($result->num_rows > 0);
            
            $stmt->close();
            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function checkTableInRealTime(string $db, string $table): bool {
        try {
            $db_link = self::$NAbySy::$db_link;
            $stmt = $db_link->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1");
            $stmt->bind_param("ss", $db, $table);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = ($result->num_rows > 0);
            $stmt->close();
            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function checkDataBaseInRealTime(string $db): bool {
        try {
            $db_link = self::$NAbySy::$db_link;
            $stmt = $db_link->prepare("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME = ? LIMIT 1");
            $stmt->bind_param("s", $db);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = ($result->num_rows > 0);
            $stmt->close();
            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }
}