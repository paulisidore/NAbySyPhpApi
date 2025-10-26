<?php
/**
 * ProxyController - Contrôleur Passerelle API pour NAbySyPhp Api
 * 
 * Ce contrôleur transmet les requêtes vers une autre API
 * en conservant tous les headers, paramètres et le body
 * 
 * @package NAbySy\Controllers
 */

namespace NAbySy\Router\Url\Controllers;
use NAbySy\xErreur;
use NAbySy\xNAbySyGS;

class xNAbySyUrlProxyController  {
    
    /**
     * URL de base de l'API cible
     * ⚠️ À configurer selon votre besoin
     */
   private string $targetApiUrl;
    private int $timeout = 30;
    
    /**
     * SOLUTION 1 : Diagnostiquer le problème exact
     */
    public function fullDiagnostic(): string {
        $tests = [];
        
        // Test 1 : Vérifier que cURL est activé
        $tests['curl_loaded'] = extension_loaded('curl');
        $tests['curl_version'] = function_exists('curl_version') ? curl_version() : null;
        
        // Test 2 : Configuration PHP
        $tests['php_config'] = [
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'max_execution_time' => ini_get('max_execution_time'),
            'default_socket_timeout' => ini_get('default_socket_timeout'),
            'openssl_loaded' => extension_loaded('openssl')
        ];
        
        // Test 3 : Test cURL vers un site externe simple
        $tests['external_http'] = $this->testUrl('http://example.com', 5);
        
        // Test 4 : Test cURL vers un site HTTPS
        $tests['external_https'] = $this->testUrl('https://www.google.com', 5);
        
        // Test 5 : Test avec file_get_contents
        $tests['file_get_contents_http'] = $this->testFileGetContents('http://example.com', 5);
        
        // Test 6 : Vérifier les certificats SSL
        $tests['ssl_cert_file'] = [
            'ini_setting' => ini_get('curl.cainfo'),
            'openssl_setting' => ini_get('openssl.cafile'),
            'exists' => false
        ];
        
        $certFile = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if ($certFile && file_exists($certFile)) {
            $tests['ssl_cert_file']['exists'] = true;
            $tests['ssl_cert_file']['path'] = $certFile;
        }
        
        // Test 7 : Variables d'environnement proxy
        $tests['proxy_env'] = [
            'http_proxy' => getenv('HTTP_PROXY') ?: getenv('http_proxy'),
            'https_proxy' => getenv('HTTPS_PROXY') ?: getenv('https_proxy'),
            'no_proxy' => getenv('NO_PROXY') ?: getenv('no_proxy')
        ];
        
        // Test 8 : Test DNS
        $tests['dns'] = [
            'google.com' => gethostbyname('google.com'),
            'example.com' => gethostbyname('example.com')
        ];
        
        return json_encode($tests, JSON_PRETTY_PRINT);
    }
    
    /**
     * Test d'une URL avec cURL
     */
    private function testUrl(string $url, int $timeout): array {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true, // Tester avec vérification
            CURLOPT_NOBODY => true // HEAD request
        ]);
        
        $start = microtime(true);
        $result = curl_exec($ch);
        $duration = microtime(true) - $start;
        
        $info = [
            'success' => $result !== false,
            'duration_ms' => round($duration * 1000, 2),
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'error' => curl_error($ch),
            'errno' => curl_errno($ch)
        ];
        
        curl_close($ch);
        
        return $info;
    }
    
    /**
     * Test avec file_get_contents
     */
    private function testFileGetContents(string $url, int $timeout): array {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => true
            ]
        ]);
        
        $start = microtime(true);
        $result = @file_get_contents($url, false, $ctx);
        $duration = microtime(true) - $start;
        
        return [
            'success' => $result !== false,
            'duration_ms' => round($duration * 1000, 2),
            'length' => $result !== false ? strlen($result) : 0
        ];
    }
    
    /**
     * SOLUTION 2 : Proxy avec toutes les corrections Windows + ENCODAGE
     */
    public function proxy(string $targetUrl, ?array $options = null): string {
        try {
            error_log("PROXY: Attempting to call $targetUrl");
            $method = $_SERVER['REQUEST_METHOD'];
            $headers = $this->getAllHeaders();
            $body = $this->getRequestBody();
            $queryParams = $_GET;
            
            if (!empty($queryParams)) {
                if(!str_contains($targetUrl,"?")){
                    $targetUrl .= '?' . http_build_query($queryParams);
                }else{
                    //Il y a déjà au moins un paramètre dans l'URL principale, On ajoute ceux de l'appel au proxy
                     $targetUrl .= "&" .  http_build_query($queryParams);
                }
            }
            // var_dump($targetUrl);
            // exit;
            $nReponse = xNAbySyGS::$CURL->EnvoieRequette($targetUrl, $queryParams,$headers,$method,$body);
            //var_dump($nReponse);
            return $nReponse;
            //var_dump($targetUrl);
            //exit;
            $ch = curl_init($targetUrl);

            // ===================================
            // CONFIGURATION WINDOWS-FRIENDLY
            // ===================================
            
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false, // Désactiver en dev C'etais true
                CURLOPT_SSL_VERIFYHOST => false, //2,
                CURLOPT_HEADER => true,
                
                // Timeouts
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                
                // DNS et résolution
                //CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Forcer IPv4
                //CURLOPT_DNS_CACHE_TIMEOUT => 120,
                
                // SSL - SOLUTION POUR WINDOWS
                //CURLOPT_SSL_VERIFYPEER => false, // Désactiver en dev
                //CURLOPT_SSL_VERIFYHOST => false,
                
                // HTTP Version
                //CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                
                // ⭐ SOLUTION ENCODAGE - NE PAS ACCEPTER L'ENCODAGE AUTOMATIQUE
                // CURLOPT_ENCODING => '',  ← RETIRER CETTE LIGNE !
                // On va gérer l'encodage manuellement
                
                // Redirections
                // CURLOPT_FOLLOWLOCATION => false,
                // CURLOPT_MAXREDIRS => 0,
                 CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$result) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) >= 2) {
                        $result['response_headers'][trim($header[0])] = trim($header[1]);
                    }
                    return $len;
                },
            ]);
            
            //var_dump("Je vérifie le certificat SSL de Wamp...");
            //exit;
            // Vérifier si on a un certificat SSL valide
            $certFile = $this->findCertFile();
            if ($certFile && file_exists($certFile)) {
                curl_setopt($ch, CURLOPT_CAINFO, $certFile);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                error_log("PROXY: Using SSL cert: $certFile");
            } else {
                error_log("PROXY: SSL verification disabled (no cert file)");
            }
            
            //var_dump("Recomposition du Header ...");
            //exit;
            // ===================================
            // HEADERS - IMPORTANT : NE PAS DEMANDER GZIP
            // ===================================
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                // Ignorer les headers problématiques
                $ignoredHeaders = ['Host', 'Connection', 'Content-Length', 'Accept-Encoding'];
                if (!in_array($name, $ignoredHeaders)) {
                    $curlHeaders[] = "$name: $value";
                }
            }
            
            // ⭐ NE PAS demander d'encodage compressé
            // Le serveur enverra du texte brut
            // $curlHeaders[] = "Accept-Encoding: identity";
            // $curlHeaders[] = "Connection: close";
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            //var_dump("Recomposition de Header...", $curlHeaders);
            //exit;
            
            // Body
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            
            // Verbose pour debug
            if (($options['verbose'] ?? false)) {
                $verbose = fopen('php://temp', 'w+');
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_STDERR, $verbose);
            }
            
            // Exécuter
            error_log("PROXY: Executing cURL...");
            //var_dump("PROXY: Executing cURL...");
            $start = microtime(true);
            var_dump("Je commence a executer ... </br>");
            $response = curl_exec($ch);
            var_dump("Je Retourne: ".$response." </br>");
            //exit;
            $duration = microtime(true) - $start;
            error_log("PROXY: Request completed in " . round($duration, 2) . "s");
            var_dump("Durée de la requette cURL: ". round($duration, 2)."s </br>");
            exit;
            
            // Verbose output
            if (isset($verbose)) {
                rewind($verbose);
                $verboseLog = stream_get_contents($verbose);
                error_log("PROXY Verbose:\n$verboseLog");
                fclose($verbose);
            }
            
            if ($response === false) {
                $error = curl_error($ch);
                $errno = curl_errno($ch);
                $info = curl_getinfo($ch);
                
                curl_close($ch);
                
                error_log("PROXY ERROR ($errno): $error");
                error_log("PROXY Info: " . json_encode($info));
                
                // Messages d'erreur détaillés
                $errorMessages = [
                    6 => "Impossible de résoudre l'hôte (DNS)",
                    7 => "Connexion refusée",
                    28 => "Timeout de connexion",
                    35 => "Erreur SSL/TLS",
                    51 => "Certificat SSL invalide",
                    52 => "Réponse vide du serveur",
                    56 => "Échec de réception des données"
                ];
                
                $errorMsg = $errorMessages[$errno] ?? $error;
                $Rep=new xErreur();
                $Rep->TxErreur="Erreur cURL ($errno): $errorMsg";
                return json_encode($Rep);
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            curl_close($ch);
            
            // Séparer headers et body
            $responseHeaders = substr($response, 0, $headerSize);
            $responseBody = substr($response, $headerSize);
            
            // ===================================
            // GÉRER L'ENCODAGE SI NÉCESSAIRE
            // ===================================
            $parsedHeaders = $this->parseResponseHeaders($responseHeaders);
            
            // Vérifier si le contenu est compressé malgré tout
            if (isset($parsedHeaders['Content-Encoding'])) {
                $encoding = strtolower($parsedHeaders['Content-Encoding']);
                
                error_log("PROXY: Content-Encoding detected: $encoding");
                
                if ($encoding === 'gzip' || $encoding === 'x-gzip') {
                    $responseBody = gzdecode($responseBody);
                    if ($responseBody === false) {
                        error_log("PROXY ERROR: Failed to decode gzip content");
                    } else {
                        error_log("PROXY: Gzip decoded successfully");
                        // Retirer le header Content-Encoding
                        unset($parsedHeaders['Content-Encoding']);
                    }
                } elseif ($encoding === 'deflate') {
                    $responseBody = gzinflate($responseBody);
                    if ($responseBody === false) {
                        error_log("PROXY ERROR: Failed to decode deflate content");
                    } else {
                        error_log("PROXY: Deflate decoded successfully");
                        unset($parsedHeaders['Content-Encoding']);
                    }
                } elseif ($encoding === 'br') {
                    error_log("PROXY WARNING: Brotli encoding not supported, content may be corrupted");
                }
            }
            
            // Définir le code HTTP
            http_response_code($httpCode);
            
            // Transmettre les headers (sans Content-Encoding)
            foreach ($parsedHeaders as $name => $value) {
                if (!in_array($name, ['Transfer-Encoding', 'Content-Encoding'])) {
                    header("$name: $value", false);
                }
            }
            
            // Forcer Content-Type si non présent
            if (!isset($parsedHeaders['Content-Type'])) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            // Définir la bonne taille de contenu
            header('Content-Length: ' . strlen($responseBody));
            
            return $responseBody;
            
        } catch (\Exception $e) {
            error_log("PROXY EXCEPTION: " . $e->getMessage());
            $Rep=new xErreur();
            $Rep->TxErreur="PROXY EXCEPTION: " . $e->getMessage();
            return json_encode($Rep);
        }
    }
    
    /**
     * SOLUTION 3 : Alternative avec file_get_contents
     */
    public function proxyWithFileGetContents(string $targetUrl): string {
        try {
            error_log("PROXY (file_get_contents): $targetUrl");
            
            $method = $_SERVER['REQUEST_METHOD'];
            $headers = $this->getAllHeaders();
            $body = $this->getRequestBody();
            $queryParams = $_GET;
            
            if (!empty($queryParams)) {
                $targetUrl .= '?' . http_build_query($queryParams);
            }
            
            // Construire les headers
            $httpHeaders = [];
            foreach ($headers as $name => $value) {
                $httpHeaders[] = "$name: $value";
            }
            
            // Configuration
            $opts = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $httpHeaders),
                    'timeout' => $this->timeout,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            
            if ($body !== null) {
                $opts['http']['content'] = $body;
            }
            
            $context = stream_context_create($opts);
            
            $start = microtime(true);
            $response = @file_get_contents($targetUrl, false, $context);
            $duration = microtime(true) - $start;
            
            error_log("PROXY: Request completed in " . round($duration, 2) . "s");
            
            if ($response === false) {
                $error = error_get_last();
                error_log("PROXY ERROR: " . ($error['message'] ?? 'Unknown'));
                $Rep=new xErreur();
                $Rep->TxErreur='Erreur file_get_contents: ' . ($error['message'] ?? 'Unknown');
                return json_encode($Rep);
            }
            
            // Extraire le code HTTP
            $httpCode = 200;
            if (isset($http_response_header[0])) {
                preg_match('/\d{3}/', $http_response_header[0], $matches);
                $httpCode = (int)($matches[0] ?? 200);
            }
            
            http_response_code($httpCode);
            
            return $response;
            
        } catch (\Exception $e) {
            error_log("PROXY EXCEPTION: " . $e->getMessage());
            $Rep=new xErreur();
            $Rep->TxErreur="PROXY EXCEPTION: " . $e->getMessage();
            return json_encode($Rep);
        }
    }
    
    /**
     * SOLUTION 4 : Télécharger et installer le certificat SSL
     */
    public function downloadCertificateBundle(): string {
        $certUrl = 'https://curl.se/ca/cacert.pem';
        $certPath = __DIR__ . '/../config/cacert.pem';
        
        // Créer le dossier si nécessaire
        $dir = dirname($certPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        try {
            // Télécharger (on désactive temporairement la vérification SSL)
            $ch = curl_init($certUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $certData = curl_exec($ch);
            
            if ($certData === false) {
                throw new \Exception(curl_error($ch));
            }
            
            curl_close($ch);
            
            // Sauvegarder
            file_put_contents($certPath, $certData);
            
            return json_encode([
                'success' => true,
                'message' => 'Certificat téléchargé',
                'path' => $certPath,
                'size' => strlen($certData),
                'instruction' => "Ajoutez dans php.ini: curl.cainfo = \"$certPath\""
            ]);
            
        } catch (\Exception $e) {
            $Rep=new xErreur();
            $Rep->TxErreur='Échec téléchargement : ' . $e->getMessage();
            return json_encode($Rep);
        }
    }
    
    /**
     * Cherche un fichier de certificats SSL valide
     */
    private function findCertFile(): ?string {
        $possiblePaths = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            'C:/wamp64/bin/php/php' . PHP_VERSION . '/extras/ssl/cacert.pem',
            'C:/wamp64/bin/php/cacert.pem',
            __DIR__ . '/../config/cacert.pem',
            __DIR__ . '/cacert.pem'
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Test simple sans configuration
     */
    public function simpleTest(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        
        curl_close($ch);
        
        return json_encode([
            'success' => $result !== false,
            'error' => $error,
            'errno' => $errno,
            'http_code' => $info['http_code'],
            'total_time' => $info['total_time'],
            'namelookup_time' => $info['namelookup_time'],
            'connect_time' => $info['connect_time'],
            'response_length' => $result !== false ? strlen($result) : 0
        ], JSON_PRETTY_PRINT);
    }
    
    // ===================================
    // Méthodes utilitaires
    // ===================================
    
    private function getAllHeaders(): array {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        return [];
    }
    
    private function getRequestBody(): ?string {
        $method = $_SERVER['REQUEST_METHOD'];
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return file_get_contents('php://input') ?: null;
        }
        return null;
    }

    private function parseResponseHeaders(string $headerString): array {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        
        return $headers;
    }

    private function forwardResponse(array $response): string {
        http_response_code($response['status']);
        
        foreach ($response['headers'] as $name => $value) {
            if (!in_array($name, ['Host', 'Connection', 'Transfer-Encoding'])) {
                header("$name: $value");
            }
        }
        
        if (!isset($response['headers']['Content-Type'])) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        return $response['body'];
    }
    
}
