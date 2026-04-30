<?php
/**
 * @file ModelTemplate.class.php
 * Contains Generique URL based Router Controller for NAbySyGS
 * Author: 
 * Mail: 
 * Date: {DATE}
 * Version: 1.0.0
 * 
 * REMARK: URL parameter must avoid use of underscore character. So instend of $id_pdt, please use $idpdt
 */

use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\xNotification;

/**
 * Routed Request Manager for {ROUTENAME}
 * @package 
 */
class ModelTemplate extends xNAbySyUrlRouterHelper {
    
    public function __construct(string $routerName, string $fileSource, string $FriendlyName='', string $Description='') {
        parent::__construct($routerName, $fileSource, $FriendlyName, $Description);
        $this->setupRoute();
    }

    private function setupRoute() {

        /**Read and return list of {routename} */
        $this->get('/{routename}', function() {
            $Rep = new xNotification();
            $nabysy = N::getInstance();
            if(!isset($nabysy->User)){
                return "" ;
            }

            //YOUR LOGIC CODE HERE-------------------------/
            $Rep->OK = 1;
            $Rep->Contenue = [];
            //---------------------------------------------/
            $Rep->SendAsJSON();
        });

        /**Read and return one {routename} by id */
        $this->get('/{routename}/{id}', function($id) {
            $Rep = new xNotification();
            $Rep->OK = 0;            
           //YOUR LOGIC CODE HERE-------------------------/
            $Rep->OK = 1;
            $Rep->Contenue = [];
            //---------------------------------------------/
            $Rep->SendAsJSON();
        });


        /**Create new {routename} ressource on server */
        $this->post('/{routename}', function() {
            $Rep = new xNotification();
            $Rep->OK = 0;
            
            //load body content
            $donnees = json_decode(file_get_contents('php://input'), true);
            
             //YOUR LOGIC CODE HERE-------------------------/
            $Rep->OK = 1;
            $Rep->Contenue = [];
            //---------------------------------------------/
            $Rep->SendAsJSON();
        });

        /** Edit one {routename} */
        $this->put('/{routename}/{id}', function($id) {
            $Rep = new xNotification();
            $Rep->OK = 0;
            
            //load body content
            $donnees = json_decode(file_get_contents('php://input'), true);
            
             //YOUR LOGIC CODE HERE-------------------------/
            $Rep->OK = 1;
            $Rep->Contenue = [];
            //---------------------------------------------/
            $Rep->SendAsJSON();
        });

        /**Delete one {routename} on server */
        $this->delete('/{routename}/{id}', function($id) {
            $Rep = new xNotification();
            $Rep->OK = 0;
            
             //YOUR LOGIC CODE HERE-------------------------/
            $Rep->OK = 1;
            $Rep->Contenue = [];
            //---------------------------------------------/
            $Rep->SendAsJSON();
        });
    }
}
?>