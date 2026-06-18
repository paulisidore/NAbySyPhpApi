<?php
namespace NAbySy\Lib\Evenement ;

class WsNotifier
{
    private static string $wsInternalUrl = 'http://127.0.0.1:8091';
    private static string $appref        = 'nabisy';
    private static string $appKey        = 'nby_live_xxxxxxxxxxxx';

    public function __construct(?string $internalUrl='http://127.0.0.1:8091'){
        if(isset($internalUrl) && $internalUrl !==''){
            self::$wsInternalUrl = $internalUrl;
        }
    }
   
    /**
     * Permet l'envoie de notification aux utilisateurs connectés au serveur de notification
     * @param string $event 
     * @param array $payload 
     * @param array $userIds 
     * @param string $topic
     * @return bool 
     */
    public static function send(string $event,array  $payload,array  $userIds = [],?string $topic   = null): bool {
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
        return $code === 200;
    }

    public static function getConnectedUsers(): array
    {
        $ch = curl_init(self::$wsInternalUrl . '/connected-users'); // GET
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['connected_users'] ?? [];
    }

    // Filtrer par rôle ou boutique directement
    public static function getConnectedByRole(string $role): array
    {
        return array_filter(
            self::getConnectedUsers(),
            fn($u) => $u['role'] === $role
        );
    }

    public static function getConnectedByBoutique(int $boutiqueId): array
    {
        return array_filter(
            self::getConnectedUsers(),
            fn($u) => $u['boutique_id'] === $boutiqueId
        );
    }
}