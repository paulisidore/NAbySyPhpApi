<?php
namespace NAbySy\Lib\ModulePaie\Amana;

use NAbySy\ORM\xORMHelper;
use NAbySy\xNAbySyGS;
use NAbySy\xNotification;
use NAbySy\Lib\ModulePaie\Wave\xCheckOutParam;

/**
 * Cette classe gère l'API de paiement en ligne B2B AmanaTa.
 * Elle permet l'intégration des paiements AmanaTa dans le framework NAbySyPhpApi.
 * 
 * API : AmanaTa - Amana Transfert d'Argent
 * Éditeur API : 2iSoft
 * Version Doc : 1.0
 * 
 * Endpoints disponibles :
 *  - POST /v1/auth                  : Authentification et obtention du token JWT
 *  - POST /v1/make-payment          : Initier un paiement
 *  - POST /v1/check-status-payment  : Vérifier le statut d'un paiement
 *  - GET  /v1/my-balance            : Consulter le solde du compte B2B
 *  - POST /v1/list-payments         : Liste des transactions
 */
class xApiNAbySyAmanaConnect {

    public static xNAbySyGS $Main;
    public xORMHelper $Config;
    public bool $IsReady;

    /** Token JWT en cours de session */
    private string $Token = '';

    /** Timestamp d'expiration du token (UNIX) */
    private int $TokenExpires = 0;

    // -------------------------------------------------------------------------
    // Constantes des statuts de transaction AmanaTa
    // -------------------------------------------------------------------------
    public const STATUT_SUCCESS = 'SUCCESS';
    public const STATUT_PENDING = 'PENDING';
    public const STATUT_FAILED  = 'FAILED';

    // -------------------------------------------------------------------------
    // Constantes des types de période pour la liste des transactions
    // -------------------------------------------------------------------------
    public const PERIODE_JOUR    = 'JOUR';
    public const PERIODE_SEMAINE = 'SEMAINE';
    public const PERIODE_MOIS    = 'MOIS';

    // -------------------------------------------------------------------------
    // Constantes des frais
    // -------------------------------------------------------------------------
    public const FRAIS_INCLUS     = 'OUI';
    public const FRAIS_NON_INCLUS = 'NON';

    // -------------------------------------------------------------------------
    // Constantes des routes API
    // -------------------------------------------------------------------------
    private const ROUTE_AUTH         = '/v1/auth';
    private const ROUTE_MAKE_PAYMENT = '/v1/make-payment';
    private const ROUTE_CHECK_STATUS = '/v1/check-status-payment';
    private const ROUTE_MY_BALANCE   = '/v1/my-balance';
    private const ROUTE_LIST_PAYMENT = '/v1/list-payments';

    /**
     * Constructeur - Charge la configuration depuis la table 'amanaconfig'
     * et prépare la classe pour les appels API.
     * 
     * @param xNAbySyGS $NAbySy : Instance principale du framework
     */
    public function __construct(xNAbySyGS $NAbySy) {
        self::$Main  = $NAbySy;
        $this->Config = new xORMHelper($NAbySy, 1, true, "amanaconfig");

        if (!$this->Config->MySQL->TableExiste($this->Config->Table)) {
            $IdC = $this->CreateNewSetup();
            if ($IdC > 0) {
                $this->Config = new xORMHelper($NAbySy, $IdC, true, "amanaconfig");
            }
        }

        if ($this->Config->Id == 0) {
            self::$Main::$Log->Write("Aucune configuration disponible pour " . __CLASS__);
            $this->IsReady = false;
            return;
        }

        $this->IsReady = true;
    }

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * Crée une configuration initiale vide dans la table amanaconfig.
     * À remplir manuellement (userlogin, userpass, apikey, urlbase).
     * 
     * @return int : L'identifiant de la ligne créée
     */
    private function CreateNewSetup(): int {
        $NewConfig = new xORMHelper(
            self::$Main,
            null,
            self::$Main::GLOBAL_AUTO_CREATE_DBTABLE,
            $this->Config->Table
        );
        $NewConfig->userlogin = '';
        $NewConfig->userpass  = '';
        $NewConfig->apikey    = '';
        $NewConfig->urlbase   = '';
        $NewConfig->Enregistrer();
        return $NewConfig->Id;
    }

    // =========================================================================
    // SÉCURITÉ : HMAC + HEADERS
    // =========================================================================

    /**
     * Génère la signature HMAC-SHA256 selon les spécifications AmanaTa.
     * Chaîne signée : X-Timestamp + HTTP_METHOD + URI + Body
     * 
     * @param string $timestamp   : Timestamp UNIX de la requête
     * @param string $method      : Méthode HTTP (POST, GET...)
     * @param string $uri         : URI de la route (ex: /v1/make-payment)
     * @param string $body        : Corps JSON de la requête (vide si GET)
     * 
     * @return string : La signature HMAC-SHA256 en hexadécimal
     */
    private function GenererSignatureHMAC(string $timestamp, string $method, string $uri, string $body = ''): string {
        $dataToSign = $timestamp . $method . $uri . $body;
        return hash_hmac('sha256', $dataToSign, $this->Config->apikey);
    }

    /**
     * Construit les en-têtes HTTP requis pour chaque requête protégée AmanaTa.
     * Inclut : X-Timestamp, X-APIKey, X-Signature, Authorization, Content-Type.
     * 
     * @param string $method    : Méthode HTTP
     * @param string $uri       : URI de la route
     * @param string $body      : Corps de la requête (pour le calcul HMAC)
     * @param bool   $avecToken : true si le token JWT doit être inclus (false pour /v1/auth)
     * 
     * @return array : Tableau d'en-têtes prêts pour cURL
     */
    private function BuildHeaders(string $method, string $uri, string $body = '', bool $avecToken = true): array {
        $timestamp = (string) time();
        $signature = $this->GenererSignatureHMAC($timestamp, $method, $uri, $body);

        $headers = [
            'Content-Type: application/json',
            'X-Timestamp: '  . $timestamp,
            'X-APIKey: '     . $this->Config->apikey,
            'X-Signature: '  . $signature,
        ];

        if ($avecToken && $this->Token !== '') {
            $headers[] = 'Authorization: ' . $this->Token;
        }

        return $headers;
    }

    // =========================================================================
    // GESTION DU TOKEN JWT
    // =========================================================================

    /**
     * Vérifie si le token JWT actuel est encore valide.
     * La signature AmanaTa est valide 5 minutes ; on prend une marge de 30 secondes.
     * 
     * @return bool
     */
    private function TokenEstValide(): bool {
        if ($this->Token === '') return false;
        return (time() < ($this->TokenExpires - 30));
    }

    /**
     * Authentifie la classe auprès de l'API AmanaTa et stocke le token JWT.
     * Appelé automatiquement avant chaque requête si le token est absent/expiré.
     * 
     * @return xNotification : OK=1 si succès, OK=0 avec TxErreur si échec
     */
    public function Authentifier(): xNotification {
        $Retour = new xNotification();
        $Retour->OK = 0;

        $uri  = self::ROUTE_AUTH;
        $body = json_encode([
            'userlogin' => $this->Config->userlogin,
            'userpass'  => $this->Config->userpass,
        ]);

        // Pour /v1/auth : la signature est requise mais pas le token
        $headers = $this->BuildHeaders('POST', $uri, $body, false);
        $url     = $this->Config->urlbase . $uri;

        $reponseRaw = self::$Main::$CURL->EnvoieRequette($url, [], $headers, CURLOPT_POST, $body);
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Réponse : " . $reponseRaw);

        $rep = json_decode($reponseRaw, true);

        if (!$rep || !isset($rep['status']) || $rep['status'] !== true) {
            $msg = $rep['message'] ?? 'Erreur inconnue lors de l\'authentification AmanaTa';
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : " . $msg);
            $Retour->TxErreur = $msg;
            return $Retour;
        }

        // Stockage du token et calcul de l'expiration (5 minutes = 300 secondes)
        $this->Token        = $rep['token'];
        $this->TokenExpires = time() + 300;

        $Retour->OK      = 1;
        $Retour->Contenue = $rep['token'];
        return $Retour;
    }

    /**
     * S'assure qu'un token valide est disponible avant tout appel protégé.
     * Ré-authentifie automatiquement si nécessaire.
     * 
     * @return xNotification : OK=1 si token prêt, OK=0 si échec d'auth
     */
    private function AssurerToken(): xNotification {
        if ($this->TokenEstValide()) {
            $ok = new xNotification();
            $ok->OK = 1;
            return $ok;
        }
        return $this->Authentifier();
    }

    // =========================================================================
    // API : FAIRE UN PAIEMENT
    // =========================================================================

    /**
     * Initie une demande de paiement AmanaTa.
     * Le paiement sera validé par le payeur sur l'application AmanaTa.
     * 
     * @param int    $montant            : Montant en entier (ex: 5000)
     * @param string $telephonePayeur    : Téléphone du payeur (ex: 0022780xxxxxx)
     * @param string $externalReference  : Référence unique de la transaction dans votre système
     * @param string $description        : Description du paiement (max 255 car.)
     * @param string $fraisInclus        : FRAIS_INCLUS ('OUI') ou FRAIS_NON_INCLUS ('NON')
     * @param string $webhookUpdate      : URL de votre webhook (optionnel)
     * 
     * @return xNotification : OK=1 avec Contenue = tableau paiement AmanaTa, OK=0 si erreur
     */
    public function FairePaiement(
        int    $montant,
        string $telephonePayeur,
        string $externalReference,
        string $description,
        string $fraisInclus   = self::FRAIS_NON_INCLUS,
        string $webhookUpdate = ''
    ): xNotification {

        $Retour = new xNotification();
        $Retour->OK = 0;

        $checkToken = $this->AssurerToken();
        if ($checkToken->OK == 0) {
            $Retour->TxErreur = 'Token indisponible : ' . $checkToken->TxErreur;
            return $Retour;
        }

        $uri        = self::ROUTE_MAKE_PAYMENT;
        $bodyArray  = [
            'montantPaiement'      => $montant,
            'descriptionPaiement'  => $description,
            'externalReference'    => $externalReference,
            'telephonePayeur'      => $telephonePayeur,
            'fraisInclus'          => $fraisInclus,
        ];
        if ($webhookUpdate !== '') {
            $bodyArray['webhookUpdate'] = $webhookUpdate;
        }

        $body    = json_encode($bodyArray);
        $headers = $this->BuildHeaders('POST', $uri, $body);
        $url     = $this->Config->urlbase . $uri;

        $reponseRaw = self::$Main::$CURL->EnvoieRequette($url, [], $headers, CURLOPT_POST, $body);
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Réponse : " . $reponseRaw);

        $rep = json_decode($reponseRaw, true);

        if (!$rep || !isset($rep['status']) || $rep['status'] !== true) {
            $msg = $rep['message'] ?? 'Erreur lors de l\'initiation du paiement AmanaTa';
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : " . $msg);
            $Retour->TxErreur = $msg;
            return $Retour;
        }

        $Retour->OK      = 1;
        $Retour->Contenue = $rep['paiement'] ?? $rep;
        return $Retour;
    }

    // =========================================================================
    // API : VÉRIFIER LE STATUT D'UN PAIEMENT
    // =========================================================================

    /**
     * Vérifie le statut d'un paiement AmanaTa à partir de sa référence.
     * Statuts possibles : SUCCESS, PENDING, FAILED
     * 
     * @param string $referenceTransaction : Référence AmanaTa de la transaction
     * 
     * @return xNotification : OK=1 avec Contenue = tableau paiement, OK=0 si erreur
     */
    public function VerifierStatutPaiement(string $referenceTransaction): xNotification {
        $Retour = new xNotification();
        $Retour->OK = 0;

        $checkToken = $this->AssurerToken();
        if ($checkToken->OK == 0) {
            $Retour->TxErreur = 'Token indisponible : ' . $checkToken->TxErreur;
            return $Retour;
        }

        $uri       = self::ROUTE_CHECK_STATUS;
        $bodyArray = ['referenceTransaction' => $referenceTransaction];
        $body      = json_encode($bodyArray);
        $headers   = $this->BuildHeaders('POST', $uri, $body);
        $url       = $this->Config->urlbase . $uri;

        $reponseRaw = self::$Main::$CURL->EnvoieRequette($url, [], $headers, CURLOPT_POST, $body);
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Réponse : " . $reponseRaw);

        $rep = json_decode($reponseRaw, true);

        if (!$rep || !isset($rep['status']) || $rep['status'] !== true) {
            $msg = $rep['message'] ?? 'Erreur lors de la vérification du statut AmanaTa';
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : " . $msg);
            $Retour->TxErreur = $msg;
            return $Retour;
        }

        $Retour->OK      = 1;
        $Retour->Contenue = $rep['paiement'] ?? $rep;
        return $Retour;
    }

    // =========================================================================
    // API : CONSULTER LE SOLDE
    // =========================================================================

    /**
     * Retourne le solde du compte B2B AmanaTa.
     * (Requête GET - body vide)
     * 
     * @return xNotification : OK=1 avec Extra = solde (int), OK=0 si erreur
     */
    public function GetSolde(): xNotification {
        $Retour = new xNotification();
        $Retour->OK = 0;

        $checkToken = $this->AssurerToken();
        if ($checkToken->OK == 0) {
            $Retour->TxErreur = 'Token indisponible : ' . $checkToken->TxErreur;
            return $Retour;
        }

        $uri     = self::ROUTE_MY_BALANCE;
        // Body vide obligatoire pour GET /v1/my-balance
        $headers = $this->BuildHeaders('GET', $uri, '');
        $url     = $this->Config->urlbase . $uri;

        // GET : on passe null comme Method pour forcer CURLOPT_HTTPGET
        $reponseRaw = self::$Main::$CURL->EnvoieRequette($url, [], $headers, null, '');
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Réponse : " . $reponseRaw);

        $rep = json_decode($reponseRaw, true);

        if (!$rep || !isset($rep['status']) || $rep['status'] !== true) {
            $msg = $rep['message'] ?? 'Erreur lors de la récupération du solde AmanaTa';
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : " . $msg);
            $Retour->TxErreur = $msg;
            return $Retour;
        }

        $Retour->OK    = 1;
        $Retour->Extra = $rep['solde'] ?? 0;
        return $Retour;
    }

    // =========================================================================
    // API : LISTE DES TRANSACTIONS
    // =========================================================================

    /**
     * Retourne la liste des paiements AmanaTa selon une période et un statut.
     * 
     * @param string $dateDebut         : Date de début au format dd-mm-yyyy (ex: 21-07-2025)
     * @param string $type              : PERIODE_JOUR, PERIODE_SEMAINE ou PERIODE_MOIS
     * @param string $statutTransaction : STATUT_SUCCESS, STATUT_FAILED ou STATUT_PENDING
     * 
     * @return xNotification : OK=1 avec Contenue = tableau des transactions, OK=0 si erreur
     */
    public function GetListeTransactions(
        string $dateDebut,
        string $type              = self::PERIODE_MOIS,
        string $statutTransaction = self::STATUT_SUCCESS
    ): xNotification {

        $Retour = new xNotification();
        $Retour->OK = 0;

        $checkToken = $this->AssurerToken();
        if ($checkToken->OK == 0) {
            $Retour->TxErreur = 'Token indisponible : ' . $checkToken->TxErreur;
            return $Retour;
        }

        $uri       = self::ROUTE_LIST_PAYMENT;
        $bodyArray = [
            'dateRechercheDebut' => $dateDebut,
            'type'               => $type,
            'statutTransaction'  => $statutTransaction,
        ];
        $body    = json_encode($bodyArray);
        $headers = $this->BuildHeaders('POST', $uri, $body);
        $url     = $this->Config->urlbase . $uri;

        $reponseRaw = self::$Main::$CURL->EnvoieRequette($url, [], $headers, CURLOPT_POST, $body);
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Réponse : " . $reponseRaw);

        $rep = json_decode($reponseRaw, true);

        if (!$rep || !isset($rep['status']) || $rep['status'] !== true) {
            $msg = $rep['message'] ?? 'Erreur lors de la récupération des transactions AmanaTa';
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : " . $msg);
            $Retour->TxErreur = $msg;
            return $Retour;
        }

        $Retour->OK      = 1;
        $Retour->Contenue = $rep['paiements'] ?? [];
        return $Retour;
    }

    // =========================================================================
    // WEBHOOK : RÉCEPTEUR DE NOTIFICATION AMANATA
    // =========================================================================

    /**
     * Traite la notification webhook envoyée par AmanaTa lors de l'évolution
     * d'un statut de paiement. À appeler depuis votre endpoint webhookUpdate.
     * 
     * Payload attendu de AmanaTa :
     *   { "referenceAmanata": "xxx", "statutTransaction": "SUCCESS"|"ECHEC", "motif": "" }
     * 
     * Réponse attendue par AmanaTa :
     *   { "status": true|false, "code": 200|400, "message": "..." }
     * 
     * @param string $jsonPayload : Corps brut de la requête POST reçue
     * 
     * @return array : Tableau à encoder en JSON et renvoyer à AmanaTa
     */
    public function TraiterWebhook(string $jsonPayload): array {
        self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " Payload reçu : " . $jsonPayload);

        $data = json_decode($jsonPayload, true);

        if (!$data || !isset($data['referenceAmanata'], $data['statutTransaction'])) {
            self::$Main::$Log->Write(__CLASS__ . " " . __FUNCTION__ . " ERREUR : Payload invalide");
            return [
                'status'  => false,
                'code'    => 400,
                'message' => 'Payload webhook invalide',
            ];
        }

        $reference = $data['referenceAmanata'];
        $statut    = $data['statutTransaction']; // SUCCESS ou ECHEC
        $motif     = $data['motif'] ?? '';

        self::$Main::$Log->Write(
            __CLASS__ . " Webhook reçu - Ref: {$reference} | Statut: {$statut}" .
            ($motif ? " | Motif: {$motif}" : '')
        );

        // ----------------------------------------------------------------
        // TODO : Implémenter ici la mise à jour de votre transaction locale
        // Exemple :
        //   $transaction = new xCheckOutParam(self::$Main, ...);
        //   $transaction->Etat = ($statut === 'SUCCESS')
        //       ? xCheckOutParam::PAIEMENT_VALIDER
        //       : xCheckOutParam::PAIEMENT_REFUSER;
        //   $transaction->Enregistrer();
        // ----------------------------------------------------------------

        return [
            'status'  => true,
            'code'    => 200,
            'message' => 'Webhook traité avec succès',
        ];
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    /**
     * Génère une référence externe unique pour une transaction.
     * Format : prefix + uniqid
     * 
     * @param string $prefix : Préfixe optionnel
     * @return string
     */
    public function GetUniqueReference(string $prefix = 'AMA'): string {
        return substr(uniqid($prefix, true), 0, 30);
    }
}

?>
