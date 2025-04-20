<?php
// src/bootstrap.php

// Code exécuté automatiquement dès que ton package est chargé
// Tu peux y mettre des hooks, des logs, du code d'initialisation

use NAbySy\xNAbySyGS;

define('XNABYSY_LOADED', true);


include_once 'definition_err.php';
include_once 'config.php' ;
include_once 'xModuleInfo.php';
include_once 'format.class.php' ;
include_once 'devises.class.php' ;
include_once 'xNabySyCustomListOf.class.php' ;

include_once 'erreur.php' ;
include_once 'notification.class.php';
include_once 'db.class.php' ;
require_once 'auth.class.php';
require_once "vendor/autoload.php";


include_once 'mod_ext/nombre_en_lettre.php' ;
include_once 'mod_ext/rb.php' ;

include_once 'autoload.i.php' ;
include_once 'autoload.class.php' ;
include_once 'log.class.php' ;
include_once 'orm.i.php' ;
include_once 'orm.class.php' ;
include_once 'user.class.php' ;

include_once 'photo.class.php';
include_once 'fileuploader.class.php';

include_once 'observgen.i.php' ;
include_once 'observgen.class.php' ;

include_once 'lib/sms/sms.i.php' ;
include_once 'lib/BonAchatManager/BonAchatManager.i.php';
include_once 'moduleexterne.i.class.php' ;
include_once 'lib/xCurlHelper/xCurlHelper.i.php';
include_once 'lib/ModulePaieManager/ModulePaieManager.i.php';

include_once 'GsModuleManager.class.php' ;

include_once 'startupinfo.php' ;


if (!class_exists('N')) {
	/**
	 * La Class static N regroupe l'ensemble des fonctions static de l'objet central NAbySyGS.
	 */
	class N extends xNAbySyGS { 
		/**
		 * Module Principal NAbySy GS
		 * @var xNAbySyGS
		 */
		public static xNAbySyGS $Main  ;

		final public function __get($key) {
			$method = 'get' . ucfirst($key);
			if (method_exists($this, $method)) {
			  return $this->$method($this->data[$key]);
			} else {
			  return self::$Main;
			}
		  }
	}
}

//echo "Package nabysyphpapi/xnabysygs chargé 🚀" . PHP_EOL;
