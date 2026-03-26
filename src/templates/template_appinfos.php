<?php
    /**
     * CUSTUMISE YOUR DATABASE IN THIS FILE FOR {MODULE_NAME}
     * This file is used to create the database structure for your application.
     * Date: {DATE}
     * Version: 1.0.0
     */

    const __DUREE_TOKEN__ = 3600; //Token expire time in second (defaut is 1 hour)
    $ACTIVE_DEBUG = false; //Enable/Desable debug mode.
    $DEBUG_LEVEL = 0; // Debug level (0: None, 1: Error, 2: Warning, 3: Notification, 4: full debug)


    if ($ACTIVE_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', $DEBUG_LEVEL);
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

     /**
      * Replace according with your database connection informations
      */
     //$nabysy = N::Init("YOUR_APP_NAME","ORGANISATION_NAME","ORGANISATION_ADDRESS","ORGANISATION_CONTACT","DATABASE_NAME","MASTER_DATABASE_NAME","DATABASE_SERVER","DATABSE_USER","DATABASE_PASSWORD",DATABASE_PORT);

     /**
      * Creation of defaut module xClent for NAbySyGS. It's used for NAbySyGS modules xPanier, xVente, xComptabilite and xProforma
      * If you don't planed to use them, you can delete or comment this line.
      */
     //N::$GSModManager::CreateCategorie("client",true,true,"client");

    /**
     * Craate new Module category: 
     * N::$GSModManager::CreateCategorie("category_name")
     * Your can create multiple categories for your application.
     */
   //N::$GSModManager::CreateCategorie("category_name");

    /**
     * Create new NAbySyGS ORM Class:
     * N::$GSModManager::GenerateORMClass("class_name","category_name", "table_name")
     * Your can create multiple ORM Class for your application.
     */
   //N::$GSModManager::GenerateORMClass("class_name","category_name","table_name");

   /**
    * YOUR CAN INCLUDE FROM FILE YOUR Module/Database SHEMA
    * include_once 'db_structure.php';
    * include_once 'db_structure2.php';
    * //include_once .... ;
    */

  N::SetShowDebug($ACTIVE_DEBUG, $DEBUG_LEVEL);
  N::SetAuthSessionTime(__DUREE_TOKEN__);
  N::$SendAuthReponse = true; // if true, NAbySyPhpApi will send authentification response.
  N::ReadHttpAuthRequest(); //NAbySyPhpApi will check Authentificationbefore any request to the API.

  //N::$UrlRouter::resolveUrlRoute(true); //Start URL Based router
  N::ReadHttpRequest(); //Start Action based router.(The defaut router)

   
?>