<?php
    /**
     * CUSTUMISE YOUR DATABASE IN THIS FILE FOR {MODULE_NAME}
     * This file is used to create the database structure for your application.
     * Date: {DATE}
     * Version: 1.0.0
     */

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

   
?>