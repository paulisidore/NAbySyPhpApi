<?php
namespace NAbySy\Lib\Evenement;

use NAbySy\ORM\IORMHelper;
use NAbySy\xNAbySyGS;

interface IwsNotifications extends IORMHelper {
    /** Notification pas encore envoyée */
    public const ETAT_NON_ENVOYE = 'NON_ENVOYE';

    /** Notification déjà envoyée */
    public const ETAT_ENVOYE = 'ENVOYE';

    /** Notification déjà lue par le destinataire */
    public const NOTIFICATION_LUE = 'LUE';

    /** Notification pas encore lue par le destinataire */
    public const NOTIFICATION_NON_LUE = 'NON_LUE';

    /**
     * Constructeur spécifique à la classe xNotifications
     */
    public function __construct(
        ?xNAbySyGS $NabySy = null, 
        ?int $Id = null, 
        $AutoCreate = true, 
        $TableName = "notifications", 
        $DBName = null
    );
}

include_once 'xNotifications.class.php';
?>