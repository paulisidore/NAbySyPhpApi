<?php
    /**
     * {MODULE_NAME}
     * This file is the main entry for your application.
     * Date: {DATE}
     * Version: 1.0.0
     * 
     * BEFORE START CHECKLIST: 
     * 
     * 1-Enable mod_rewrite on your your web server
     * 
     *  sudo a2enmod rewrite
     * 
     * 2-Edit /etc/apache2/sites-available/000-default.conf ou /etc/apache2/apache2.conf
     * 
     *  in <Directory /var/www/html> section, replace[ AllowOverride None] by [AllowOverride All]
     * 
     * 3-Reload your Web server
     * 
     *  sudo systemctl restart apache2
     */

    /**
     * If your API base url is in a sub-domain, please define it here
     *  Example: If NAbySyPhpApi is loaded from  https://ma-website.app/test/ ('test' is the subdirectory),
     *  your __BASEDIR__ will be 'test'. So your must put it here before loading NAbySyPhpApi.
     * 
     *  define('__BASEDIR__', "test");
     */

      /**
       * PLEASE ALLOW READWRITE ACCESS TO YOUR BASE URL BECAUSE NAbySyPhpApi WILL CREATE YOUR DECLARED ORM FILE,  WRITE LOGS AND SOME TEMPORARY FILES.
       */

  /**
   * WARNING: DO NOT DELETE THIS LINES
   */
  require_once __DIR__ . '/vendor/autoload.php';
  include_once 'appinfos.php';
  /************************************************************************************************* */

   
?>