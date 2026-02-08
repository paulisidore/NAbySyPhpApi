<?php
// generate_cache.php

function rebuildSchemaCache(mysqli $pdo, $outputFile):bool {
    $schema = [
        'databases' => [],
        'fields' => [] // On regroupe tables et champs ici pour gagner un niveau de boucle
    ];

    // On récupère tout le dictionnaire en une seule requête SQL
    $sql = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME 
            FROM information_schema.columns 
            WHERE TABLE_SCHEMA NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys')";
    
    $query = $pdo->query($sql);

    while ($row = $query->fetch_assoc()) {
        $db = $row['TABLE_SCHEMA'];
        $table = $row['TABLE_NAME'];
        $col = $row['COLUMN_NAME'];

        // On indexe par les noms pour utiliser isset() plus tard
        $schema['databases'][$db] = true;
        $schema['fields'][$db][$table][$col] = true;
    }

    // Génération du fichier PHP
    $export = var_export($schema, true);
    $content = "<?php\n// Généré le " . date('Y-m-d H:i:s') . "\nreturn " . $export . ";";
    try {
        file_put_contents($outputFile, $content);
        return true;
    } catch (\Throwable $th) {
        //throw $th;
    }
    return false;
}

