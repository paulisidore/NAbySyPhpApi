<?php
/**
 * @file xNotifications.class.php
 * Contains Generique Class Module for NAbySyGS
 * Author: 
 * Mail: 
 * Date: 18/Jun/2026 20:38:42
 * Version: 1.0.0
 */
    namespace NAbySy\Lib\Evenement;

    use NAbySy\ORM\xORMHelper;
    use NAbySy\xNAbySyGS;

    class xWSNotifications extends xORMHelper implements IwsNotifications  {
        
        /** Notification pas encore envoyée */
        public const ETAT_NON_ENVOYE = 'NON_ENVOYE';

        /** Notification déjà envoyée */
        public const ETAT_ENVOYE = 'ENVOYE';

        /**Notification déjà lue par le destinataire */
        public const NOTIFICATION_LUE = 'LUE';

        /**Notification pas encore lue par le destinataire */
        public const NOTIFICATION_NON_LUE = 'NON_LUE';

        public function __construct(?xNAbySyGS $NabySy=null,?int $Id=null,$AutoCreate=true,$TableName="notifications", $DBName=null){
            if(!isset($NabySy)){
                $NabySy = xNAbySyGS::getInstance();
            }    
            if ($TableName==''){
                $TableName="notifications";
            }
            if(!isset($DBName)){
                $DBName=$NabySy->MaBoutique->DBName;
            }elseif(trim($DBName)==''){
                $DBName=$NabySy->MaBoutique->DBName;
            }
            parent::__construct($NabySy,(int)$Id,$AutoCreate,$TableName,$DBName);

        }
    }
    

?>