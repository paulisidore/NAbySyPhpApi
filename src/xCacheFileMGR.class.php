<?php

use NAbySy\xNAbySyGS;

    class xCacheFileMGR {
        public static xNAbySyGS $xMain;
        public function __construct(xNAbySyGS $NAbySy){
            self::$xMain=$NAbySy;
        }

        /**
         * Permet de mettre à jour un fichier mit en cache ou de l'ajouter au cache s'il n'existe pas
         * @param string $cacheFile : Le fichier du cache
         * @param string $sourceFile : Le fichier à copier dans le cache
         * @return void 
         */
        public static function refreshCacheFile(string $cacheFile, string $sourceFile) {
            // --- Configuration des chemins ---
            //$sourceFile = '/chemin/vers/votre/dossier/source/image-source.jpg'; 
            //$cacheFile = '/chemin/vers/votre/dossier/cache/image-source.jpg'; 

            // 1. Déterminer si le fichier cache existe
            if (file_exists($cacheFile)) {
                // 2. Récupérer les horodatages (timestamps)
                $sourceTime = filemtime($sourceFile);
                $cacheTime = filemtime($cacheFile);

                // 3. Comparaison de l'horodatage
                // Si le fichier source est plus récent (timestamp plus grand) que le fichier cache, 
                // ou si la lecture des horodatages a échoué (par précaution)
                if ($sourceTime === false || $cacheTime === false || $sourceTime > $cacheTime) {
                    // Le cache est périmé. On le met à jour.
                    self::updateCache($sourceFile, $cacheFile);
                    
                } else {
                    // Le cache est toujours valide. On ne fait rien.
                    // On passe directement à l'étape de service du fichier cache.
                }
                
            } else {
                // Le fichier cache n'existe pas du tout. On le crée.
                self::updateCache($sourceFile, $cacheFile);
            }
        }
        // --- Fonction de Mise à Jour (Copie) ---
        private static function updateCache($src, $dst) {
            // Utilisez copy() pour supprimer l'ancien fichier s'il existe et copier le nouveau.
            // Cette fonction est plus simple que d'utiliser unlink() puis copy().
            if (copy($src, $dst)) {
                // Optionnel: Log ou message de confirmation
                // echo "Mise à jour du cache effectuée.";
            } else {
                // Important: Gestion d'erreur (permissions d'écriture)
                error_log("ERREUR: Impossible de copier le fichier source vers le cache.");
            }
        }
    }
?>