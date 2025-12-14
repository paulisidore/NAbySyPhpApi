<?php
/**
 * @file rAuth.class.php
 * Contains Authentification URL based Router Controller for NAbySyGS
 * Author: Paul Isidore A. NIAMIE
 * Mail: direction@groupe-pam.net
 * Date: 06/Oct/2025 22:34:48
 * Version: 1.0.0
 */

// include_once 'Firebase/xContactClient.class.php';
// include_once 'Firebase/xFirebase.class.php';
// include_once 'Firebase/xNotification.Firebase.class.php';
// include_once 'Firebase/xReductionAchat.class.php';

use NAbySy\GS\Stock\xProduit as StockXProduit;
use NAbySy\Messagerie\Firebase\xContactClient;
use NAbySy\ORM\xORMHelper;
use NAbySy\Router\Url\Controllers\xNAbySyUrlProxyController;
use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\xErreur;
use NAbySy\xNotification;
use NAbySy\xProduit;

/**
 * Routed Request Manager for PRODUIT
 * @package 
 */
class rAuth extends xNAbySyUrlRouterHelper {
    
    public function __construct(string $routerName) {
        $fileSource = __FILE__ ;
        parent::__construct($routerName, $fileSource);
        $this->setupRoute();
    }

    private function setupRoute() {
        /**Process to Authentification*/
        $this->get('/auth', function() {
            N::$NO_AUTH = true ;
            $Rep = new xNotification();
            $Rep->OK=0;
            $Rep->Contenue = [];
            N::$Log->AddToLog(__FILE__." L".__LINE__." Authentification en cour...") ;
            include_once 'auth.php';
        });

        $this->post('/auth', function() {
            N::$NO_AUTH = true ;
            $Rep = new xNotification();
            $Rep->OK=0;
            $Rep->Contenue = [];
            N::$Log->AddToLog(__FILE__." L".__LINE__." Authentification en cour...") ;
            include_once 'auth.php';
        });
    }
}
?>