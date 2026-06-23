<?php
namespace NAbySy\Lib\Evenement;

use Exception;
use NAbySy\xNAbySyGS;
use xErreur;

include_once 'WsNotifications.i.php';

class WsNotifier
{
    private static string $wsInternalUrl = 'http://127.0.0.1:8091';
    private static string $appref        = 'nabysy';
    private static string $appKey        = 'nby_live_xxxxxxxxxxxx';

    private static bool $dejaSetup = false;

    public function __construct(?string $internalUrl = 'http://127.0.0.1:8091', ?string $AppRef='nabysy', ?string  $AppKey='nby_live_xxxxxxxxxxxx')
    {
        if (isset($internalUrl) && $internalUrl !== '') {
            self::$wsInternalUrl = $internalUrl;
        }
        if(isset($AppRef) && trim($AppRef) !== ""){
            self::$appref = trim($AppRef);
        }
        if(isset($AppKey) && trim($AppKey) !== ""){
            self::$appKey = trim($AppKey);
        }
        self::$dejaSetup=true;
    }

    public static function Setup(?string $internalUrl = 'http://127.0.0.1:8091', ?string $AppRef='nabysy', ?string  $AppKey='nby_live_xxxxxxxxxxxx'):bool
    {
        if (isset($internalUrl) && $internalUrl !== '') {
            self::$wsInternalUrl = $internalUrl;
        }
        if(isset($AppRef) && trim($AppRef) !== ""){
            self::$appref = trim($AppRef);
        }
        if(isset($AppKey) && trim($AppKey) !== ""){
            self::$appKey = trim($AppKey);
        }
        self::$dejaSetup=true;
        return self::$dejaSetup;
    }

    public static function IsReady():bool{
        if(!self::$dejaSetup){
            if(xNAbySyGS::$Main->ActiveDebug && xNAbySyGS::$LogLevel>3){
                $traces= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2) ;
                $trace = $traces[0];
                if(count($traces)>1){
                    $trace = $traces[count($traces)-1] ;
                }
                //$trace = $traces;
                $Err=[
                    "OK" => 0,
                    "TxErreur" => __CLASS__." n'est pas encore configuré. Executer d'abord un appel à ".__CLASS__."::Setup()",
                    "Source" => $trace
                ];
                echo json_encode($Err);
            }else{
                $Err=[
                    "OK" => 0,
                    "TxErreur" => "Le service de notification n'est pas encore disponible. Contactez le support technique."
                ];
                echo json_encode($Err);
            }
            exit;
            return false;
        }
        return true;
    }

    /**
     * Permet l'envoi de notification aux utilisateurs connectés au serveur de notification
     * @param string $event
     * @param array $payload
     * @param array $userIds
     * @param string|null $topic
     * @param string|null $titre
     * @param string|null $message
     * @return bool true si l'appel au ws-server a réussi (indépendamment du nombre de livrés)
     */
    public static function send(
        string  $event,
        array   $payload,
        array   $userIds = [],
        ?string $topic   = null,
        ?string $titre   = null,
        ?string $message = null
    ): bool {
        
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        // 1. Sauvegarder en BDD pour chaque destinataire
        if (!empty($userIds)) {
            $Notifs = self::saveToDatabase($event, $payload, $userIds, $titre, $message);
            if (!empty($Notifs)) {
                // 2. Push WS temps réel
                $resultat = self::pushToWsServer($event, $payload, $userIds, $topic);
                if ($resultat === null) {
                    return false;
                }

                // 3. Marquer ETAT_ENVOYE pour les destinataires réellement livrés
                self::marquerEnvoyees($Notifs, $resultat['delivered'] ?? []);

                return true;
            }
        } elseif (isset($topic)) {
            $resultat = self::pushToWsServer($event, $payload, [], $topic);
            return $resultat !== null;
        }
        return false;
    }

    /**
     * Met à jour l'ETAT des notifications dont le destinataire a été livré (connecté au moment de l'envoi)
     * Utilise un UPDATE SQL direct en masse via IN() plutôt qu'une boucle objet par objet (perf).
     * @param IwsNotifications[] $Notifs
     * @param array $deliveredIds user_ids réellement livrés selon le ws-server
     */
    private static function marquerEnvoyees(array $Notifs, array $deliveredIds): void
    {
        if (empty($deliveredIds) || empty($Notifs)) return;

        try {
            $Table = $Notifs[0]->FullTableName();

            // IDs des notifications dont le user_id fait partie des livrés
            $idsAMarquer = [];
            foreach ($Notifs as $Notif) {
                if (in_array((int)$Notif->userId, $deliveredIds, true)) {
                    $idsAMarquer[] = (int)$Notif->Id;
                }
            }

            if (empty($idsAMarquer)) return;

            $sql = "UPDATE " . $Table . " SET ETAT = '" . IwsNotifications::ETAT_ENVOYE . "'"
                 . " WHERE ID IN (" . implode(',', $idsAMarquer) . ")";

            // ExecUpdateSQL est une méthode de l'ORM, pas de $nabysy
            $Notifs[0]->ExecUpdateSQL($sql);
        } catch (\Exception $e) {
            error_log("[WsNotifier] Erreur marquerEnvoyees: " . $e->getMessage());
        }
    }

    /**
     * Sauvegarde les notifications en BDD — une instance par destinataire
     * @return IwsNotifications[] tableau des notifications créées
     */
    private static function saveToDatabase(
        string  $event,
        array   $payload,
        array   $userIds,
        ?string $titre,
        ?string $message
    ): array {
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        $notifs = [];
        try {
            $nabysy         = xNAbySyGS::getInstance();
            $titreDefault   = $titre   ?? self::getTitreFromEvent($event);
            $messageDefault = $message ?? json_encode($payload);

            foreach ($userIds as $userId) {
                $Notif                = new xWSNotifications($nabysy); // nouvelle instance par destinataire
                $Notif->appref        = self::$appref;
                $Notif->DateCreation  = date('Y-m-d H:i:s');
                $Notif->HeureCreation = date('H:i:s');
                $Notif->userId        = $userId;
                $Notif->event         = $event;
                $Notif->titre         = $titreDefault;
                $Notif->message       = $messageDefault;
                $Notif->payload       = json_encode($payload);
                $Notif->ETAT          = xWSNotifications::ETAT_NON_ENVOYE;
                $Notif->Lue           = xWSNotifications::NOTIFICATION_NON_LUE;
                $Notif->Enregistrer();
                $notifs[] = $Notif;
            }
        } catch (\Exception $e) {
            error_log("[WsNotifier] Erreur BDD: " . $e->getMessage());
        }
        return $notifs;
    }

    /**
     * Titre par défaut selon l'événement
     */
    private static function getTitreFromEvent(string $event): string
    {
        return match($event) {
            'stock.validation.demande'  => 'Nouvelle précommande à valider',
            'stock.validation.reponse'  => 'Réponse de validation stock',
            'stock.validation.complete' => 'Validation stock terminée',
            'rappel.client'             => 'Rappel client',
            'notification.new'          => 'Nouvelle notification',
            default                     => ucfirst(str_replace('.', ' ', $event))
        };
    }

    /**
     * Push vers le WS-Server
     * @return array|null Tableau décodé de la réponse (avec 'delivered'/'not_delivered') ou null si échec
     */
    private static function pushToWsServer(
        string  $event,
        array   $payload,
        array   $userIds,
        ?string $topic
    ): ?array {

        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        $data = json_encode([
            'event'    => $event,
            'payload'  => $payload,
            'user_ids' => $userIds,
            'appref'   => self::$appref,
            'topic'    => $topic
        ]);

        $ch = curl_init(self::$wsInternalUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-App-Ref: ' . self::$appref,
                'X-App-Key: ' . self::$appKey,
            ],
        ]);

        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || $result === false) {
            return null;
        }

        $decoded = json_decode($result, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Récupérer les notifications d'un utilisateur depuis la BDD
     * Appelé par GET /notifications de l'API métier
     * Bascule au passage les notifications encore ETAT_NON_ENVOYE vers ETAT_ENVOYE,
     * puisque l'utilisateur vient de les recevoir via cette liste.
     */
    public static function getNotifications(
        int  $userId,
        bool $nonLuesUniquement = false,
        int  $limite            = 50
    ): array {
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        try {
            $nabysy  = xNAbySyGS::getInstance();
            $Notif   = new xWSNotifications($nabysy);
            $Critere = "user_id = " . $userId . " AND appref like '" . self::$appref . "'";

            if ($nonLuesUniquement) {
                $Critere .= " AND lue = '" . xWSNotifications::NOTIFICATION_NON_LUE . "'";
            }

            $Order   = "DateCreation DESC";
            $vLimite = $limite > 0 ? "LIMIT " . $limite : null;

            $Lst = $Notif->ChargeListe($Critere, $Order, "*", null, $vLimite);

            // Marquer comme envoyées celles qui étaient encore ETAT_NON_ENVOYE
            self::marquerRecuesParConsultation($nabysy, $userId);

            return xNAbySyGS::EncodeReponseSQL($Lst);
        } catch (\Exception $e) {
            error_log("[WsNotifier] Erreur getNotifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bascule ETAT_NON_ENVOYE → ETAT_ENVOYE pour toutes les notifications
     * non encore envoyées d'un utilisateur (il vient de les consulter via GET /notifications)
     * Utilise un UPDATE SQL direct en masse plutôt qu'une boucle objet par objet (perf).
     */
    private static function marquerRecuesParConsultation(xNAbySyGS $nabysy, int $userId): void
    {
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();
        try {
            $NotifModel = new xWSNotifications($nabysy);
            $Table      = $NotifModel->FullTableName();

            $sql = "UPDATE " . $Table . " SET ETAT = '" . xWSNotifications::ETAT_ENVOYE . "'"
                 . " WHERE user_id = " . $userId
                 . " AND appref like '" . self::$appref . "'"
                 . " AND ETAT = '" . xWSNotifications::ETAT_NON_ENVOYE . "'";

            // ExecUpdateSQL est une méthode de l'ORM, pas de $nabysy
            $NotifModel->ExecUpdateSQL($sql);
        } catch (\Exception $e) {
            error_log("[WsNotifier] Erreur marquerRecuesParConsultation: " . $e->getMessage());
        }
    }

    /**
     * Compter les notifications non lues d'un utilisateur
     */
    public static function countNonLues(int $userId): int
    {
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        try {
            $nabysy  = xNAbySyGS::getInstance();
            $Notif   = new xWSNotifications($nabysy);
            $Critere = "user_id = " . $userId . " AND appref like '" . self::$appref . "'";
            $Critere .= " AND lue like '" . xWSNotifications::NOTIFICATION_NON_LUE . "'";

            $Lst = $Notif->ChargeListe($Critere, "DateCreation ASC", "count(ID) as 'NB'");
            if ($Lst && $Lst->num_rows > 0) {
                $rw = $Lst->fetch_assoc();
                return (int)($rw['NB'] ?? 0);
            }
        } catch (\Exception $e) {
            error_log("[WsNotifier] Erreur countNonLues: " . $e->getMessage());
        }
        return 0;
    }

    /**
     * Récupérer les utilisateurs connectés au WS-Server
     */
    public static function getConnectedUsers(): array
    {
        //Si le module n'est pas encore configurer on génère une exception
        self::IsReady();

        $ch = curl_init(self::$wsInternalUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_HTTPHEADER     => [
                'X-App-Ref: ' . self::$appref,
                'X-App-Key: ' . self::$appKey,
            ],
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['connected_users'] ?? [];
    }

    /**
     * Filtrer les connectés par rôle
     * Compatible multi-rôles : le champ 'role' peut être un string ou un tableau
     */
    public static function getConnectedByRole(string $role): array
    {
        return array_values(array_filter(
            self::getConnectedUsers(),
            fn($u) => isset($u['role']) && (
                is_array($u['role'])
                    ? in_array($role, $u['role'], true)
                    : $u['role'] === $role
            )
        ));
    }

    /**
     * Filtrer les connectés par boutique
     */
    public static function getConnectedByBoutique(int $boutiqueId): array
    {
        return array_values(array_filter(
            self::getConnectedUsers(),
            fn($u) => (int)($u['boutique_id'] ?? 0) === $boutiqueId
        ));
    }

    /**
     * Filtrer les connectés par rôle ET boutique
     * Compatible multi-rôles
     */
    public static function getConnectedByRoleAndBoutique(string $role, int $boutiqueId): array
    {
        return array_values(array_filter(
            self::getConnectedUsers(),
            fn($u) => (int)($u['boutique_id'] ?? 0) === $boutiqueId
                   && isset($u['role']) && (
                       is_array($u['role'])
                           ? in_array($role, $u['role'], true)
                           : $u['role'] === $role
                   )
        ));
    }

    public static function getAppref(): string
    {
        return self::$appref;
    }
}