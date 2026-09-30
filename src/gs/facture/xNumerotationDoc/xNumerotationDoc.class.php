<?php
/**
 * @file xNumerotationDoc.class.php
 * Emplacement : gs/facture/xNumerotationDoc/xNumerotationDoc.class.php
 * (Convention NAbySyPhpApi : une classe = un dossier du même nom. Les classes
 *  annexes éventuelles de ce dossier sont incluses depuis ce fichier.)
 * Numérotation annuelle configurable des documents commerciaux (Facture, Proforma, ...)
 *
 * Format généré : [PREFIXE][SEP]ANNEE[SEP]SEQUENCE
 *   ex. FAC-2026-00001 | 2026-00001 | 26/0001 | PRO-26-001
 *   NBCHIFFRES = 0 : compteur sans zéros devant -> FAC20261, FAC202612 (avec SEPARATEUR vide)
 *
 * - Un compteur par TYPEDOC et par ANNEE (remis à NUMERODEPART chaque 1er janvier).
 * - L'incrément est atomique (INSERT ... ON DUPLICATE KEY UPDATE + LAST_INSERT_ID)
 *   donc deux caisses qui valident en même temps n'obtiennent jamais le même numéro.
 * - Le numéro est stocké dans la table du document (champ NUMERO_AFFICHE),
 *   l'ID interne reste inchangé : aucune rupture avec l'existant.
 *
 * Author: Paul Isidore A. NIAMIE
 * Version: 1.0.0
 */
namespace NAbySy\GS\Facture ;

use NAbySy\xNAbySyGS;

class xNumerotationDoc
{
    public const TYPE_FACTURE  = 'FACTURE';
    public const TYPE_PROFORMA = 'PROFORMA';

    public const TABLE_CONFIG   = 'numerotation_config';
    public const TABLE_COMPTEUR = 'numerotation_compteur';

    /** Valeurs par défaut par type de document */
    private const DEFAUTS = [
        self::TYPE_FACTURE  => ['ACTIVE'=>0,'PREFIXE'=>'FAC','SEPARATEUR'=>'-','NBCHIFFRES'=>5,'ANNEECOURTE'=>0,'NUMERODEPART'=>1],
        self::TYPE_PROFORMA => ['ACTIVE'=>0,'PREFIXE'=>'PRO','SEPARATEUR'=>'-','NBCHIFFRES'=>5,'ANNEECOURTE'=>0,'NUMERODEPART'=>1],
    ];

    /** Table du document selon son type */
    private const TABLES_DOC = [
        self::TYPE_FACTURE  => 'facture',
        self::TYPE_PROFORMA => 'factureproforma',
    ];

    private xNAbySyGS $Main;
    private string $DBName;
    private ?string $LastError = null;

    public function __construct(?xNAbySyGS $NabySy = null, ?string $DBName = null)
    {
        $this->Main   = $NabySy ?? xNAbySyGS::getInstance();
        $this->DBName = $DBName ?? $this->Main->MaBoutique->DBName;
        $this->VerifierStructure();
    }

    public function LastError(): ?string { return $this->LastError; }

    /* =====================================================================
     *  STRUCTURE
     * ===================================================================*/

    private function Tbl(string $Table): string
    {
        return "`".$this->DBName."`.`".$Table."`";
    }

    private function Query(string $Sql)
    {
        try {
            $Res = $this->Main::$db_link->query($Sql);
            if ($Res === false) {
                $this->LastError = $this->Main::$db_link->error;
                $this->Main::$Log->AddToLog(__CLASS__." SQL: ".$this->LastError." | ".$Sql);
            }
            return $Res;
        } catch (\Throwable $th) {
            $this->LastError = $th->getMessage();
            $this->Main::$Log->AddToLog(__CLASS__." EXCEPTION: ".$this->LastError." | ".$Sql);
            return false;
        }
    }

    /** Crée les tables de config/compteur et le champ NUMERO_AFFICHE si besoin */
    private function VerifierStructure(): void
    {
        $this->Query("CREATE TABLE IF NOT EXISTS ".$this->Tbl(self::TABLE_CONFIG)." (
            TYPEDOC VARCHAR(20) NOT NULL PRIMARY KEY,
            ACTIVE TINYINT(1) NOT NULL DEFAULT 0,
            PREFIXE VARCHAR(10) NOT NULL DEFAULT '',
            SEPARATEUR VARCHAR(3) NOT NULL DEFAULT '-',
            NBCHIFFRES TINYINT NOT NULL DEFAULT 5,
            ANNEECOURTE TINYINT(1) NOT NULL DEFAULT 0,
            NUMERODEPART INT NOT NULL DEFAULT 1,
            DATEMAJ DATETIME NULL
        )");

        $this->Query("CREATE TABLE IF NOT EXISTS ".$this->Tbl(self::TABLE_COMPTEUR)." (
            TYPEDOC VARCHAR(20) NOT NULL,
            ANNEE SMALLINT NOT NULL,
            VALEUR INT NOT NULL DEFAULT 0,
            PRIMARY KEY (TYPEDOC, ANNEE)
        )");

        foreach (self::TABLES_DOC as $Table) {
            $this->AjouteChampSiAbsent($Table, 'NUMERO_AFFICHE', "VARCHAR(30) NULL DEFAULT NULL");
        }
    }

    private function AjouteChampSiAbsent(string $Table, string $Champ, string $Definition): void
    {
        $Db = $this->Main::$db_link->real_escape_string($this->DBName);
        $T  = $this->Main::$db_link->real_escape_string($Table);
        $Res = $this->Query("SELECT COUNT(*) AS NB FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA='".$Db."' AND TABLE_NAME='".$T."'");
        if (!$Res || (int)$Res->fetch_assoc()['NB'] === 0) {
            return; // table pas encore créée : elle sera traitée au prochain appel
        }
        $Res = $this->Query("SELECT COUNT(*) AS NB FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA='".$Db."' AND TABLE_NAME='".$T."' AND COLUMN_NAME='".$Champ."'");
        if ($Res && (int)$Res->fetch_assoc()['NB'] === 0) {
            $this->Query("ALTER TABLE ".$this->Tbl($Table)." ADD `".$Champ."` ".$Definition);
        }
    }

    /* =====================================================================
     *  CONFIGURATION
     * ===================================================================*/

    /** Retourne la configuration d'un type (valeurs par défaut si absente) */
    public function GetConfig(string $TypeDoc): array
    {
        $TypeDoc = strtoupper($TypeDoc);
        $Conf = self::DEFAUTS[$TypeDoc] ?? self::DEFAUTS[self::TYPE_FACTURE];
        $Conf['TYPEDOC'] = $TypeDoc;

        $T = $this->Main::$db_link->real_escape_string($TypeDoc);
        $Res = $this->Query("SELECT * FROM ".$this->Tbl(self::TABLE_CONFIG)." WHERE TYPEDOC='".$T."'");
        if ($Res && ($Row = $Res->fetch_assoc())) {
            $Conf['ACTIVE']       = (int)$Row['ACTIVE'];
            $Conf['PREFIXE']      = (string)$Row['PREFIXE'];
            $Conf['SEPARATEUR']   = (string)$Row['SEPARATEUR'];
            $Conf['NBCHIFFRES']   = (int)$Row['NBCHIFFRES'];
            $Conf['ANNEECOURTE']  = (int)$Row['ANNEECOURTE'];
            $Conf['NUMERODEPART'] = (int)$Row['NUMERODEPART'];
        }
        $Conf['PROCHAIN_NUMERO'] = $this->ApercuProchainNumero($Conf);
        return $Conf;
    }

    /** Retourne la configuration de tous les types gérés */
    public function GetAllConfig(): array
    {
        $Liste = [];
        foreach (array_keys(self::DEFAUTS) as $Type) {
            $Liste[] = $this->GetConfig($Type);
        }
        return $Liste;
    }

    /** Enregistre la configuration d'un type */
    public function SaveConfig(array $Conf): bool
    {
        $TypeDoc = strtoupper(trim((string)($Conf['TYPEDOC'] ?? '')));
        if (!isset(self::DEFAUTS[$TypeDoc])) {
            $this->LastError = "Type de document inconnu : ".$TypeDoc;
            return false;
        }
        $Active      = self::ToBool($Conf['ACTIVE'] ?? 0) ? 1 : 0;
        $Prefixe     = mb_substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($Conf['PREFIXE'] ?? '')), 0, 10);
        $Sep         = (string)($Conf['SEPARATEUR'] ?? '-');
        if (!in_array($Sep, ['-', '/', '.', '_', ''], true)) { $Sep = '-'; }
        // 0 = compteur sans zéros devant (FAC20261), sinon 3 à 8 chiffres (FAC-2026-00001)
        $NbChiffres  = (int)($Conf['NBCHIFFRES'] ?? 5);
        $NbChiffres  = $NbChiffres <= 0 ? 0 : max(3, min(8, $NbChiffres));
        $AnneeCourte = self::ToBool($Conf['ANNEECOURTE'] ?? 0) ? 1 : 0;
        $Depart      = max(1, (int)($Conf['NUMERODEPART'] ?? 1));

        $L = $this->Main::$db_link;
        $Sql = "INSERT INTO ".$this->Tbl(self::TABLE_CONFIG)."
                (TYPEDOC, ACTIVE, PREFIXE, SEPARATEUR, NBCHIFFRES, ANNEECOURTE, NUMERODEPART, DATEMAJ)
                VALUES ('".$TypeDoc."', ".$Active.", '".$L->real_escape_string($Prefixe)."', '".$L->real_escape_string($Sep)."',
                        ".$NbChiffres.", ".$AnneeCourte.", ".$Depart.", NOW())
                ON DUPLICATE KEY UPDATE ACTIVE=VALUES(ACTIVE), PREFIXE=VALUES(PREFIXE), SEPARATEUR=VALUES(SEPARATEUR),
                        NBCHIFFRES=VALUES(NBCHIFFRES), ANNEECOURTE=VALUES(ANNEECOURTE),
                        NUMERODEPART=VALUES(NUMERODEPART), DATEMAJ=NOW()";
        if ($this->Query($Sql) === false) {
            return false;
        }
        // Si le numéro de départ dépasse le compteur de l'année en cours, on remonte le compteur
        // (jamais l'inverse, pour ne pas créer de doublons).
        $Annee = (int)date('Y');
        $this->Query("UPDATE ".$this->Tbl(self::TABLE_COMPTEUR)." SET VALEUR = GREATEST(VALEUR, ".($Depart - 1).")
                      WHERE TYPEDOC='".$TypeDoc."' AND ANNEE=".$Annee);
        return true;
    }

    private static function ToBool($V): bool
    {
        if (is_bool($V)) { return $V; }
        return in_array(strtolower(trim((string)$V)), ['1', 'true', 'oui', 'on', 'yes'], true);
    }

    /* =====================================================================
     *  GENERATION
     * ===================================================================*/

    /** Formate un numéro à partir d'une config, d'une année et d'une séquence */
    public static function Formater(array $Conf, int $Annee, int $Sequence): string
    {
        $Sep   = (string)($Conf['SEPARATEUR'] ?? '-');
        $TxAn  = !empty($Conf['ANNEECOURTE']) ? substr((string)$Annee, -2) : (string)$Annee;
        $NbChiffres = (int)($Conf['NBCHIFFRES'] ?? 5);
        $TxSeq = $NbChiffres > 0 ? str_pad((string)$Sequence, $NbChiffres, '0', STR_PAD_LEFT) : (string)$Sequence;
        $Pref  = trim((string)($Conf['PREFIXE'] ?? ''));
        return ($Pref !== '' ? $Pref.$Sep : '').$TxAn.$Sep.$TxSeq;
    }

    /** Numéro qui sera attribué au prochain document (sans incrémenter) */
    public function ApercuProchainNumero(array $Conf): string
    {
        $Annee = (int)date('Y');
        $T = $this->Main::$db_link->real_escape_string($Conf['TYPEDOC']);
        $Res = $this->Query("SELECT VALEUR FROM ".$this->Tbl(self::TABLE_COMPTEUR)."
                             WHERE TYPEDOC='".$T."' AND ANNEE=".$Annee);
        $Prochain = (int)($Conf['NUMERODEPART'] ?? 1);
        if ($Res && ($Row = $Res->fetch_assoc())) {
            $Prochain = (int)$Row['VALEUR'] + 1;
        }
        return self::Formater($Conf, $Annee, $Prochain);
    }

    /** Incrémente de façon atomique et retourne la séquence obtenue */
    private function ProchaineSequence(string $TypeDoc, int $Annee, int $Depart): int
    {
        $T = $this->Main::$db_link->real_escape_string($TypeDoc);
        $Ok = $this->Query("INSERT INTO ".$this->Tbl(self::TABLE_COMPTEUR)." (TYPEDOC, ANNEE, VALEUR)
                            VALUES ('".$T."', ".$Annee.", LAST_INSERT_ID(".$Depart."))
                            ON DUPLICATE KEY UPDATE VALEUR = LAST_INSERT_ID(VALEUR + 1)");
        if ($Ok === false) {
            return 0;
        }
        $Res = $this->Query("SELECT LAST_INSERT_ID() AS SEQ");
        return $Res ? (int)$Res->fetch_assoc()['SEQ'] : 0;
    }

    /**
     * Attribue un numéro annuel au document s'il n'en a pas encore.
     * A appeler juste après la validation (SAVE_PANIER / PROFORMA_SAVE_PANIER).
     * @return string|null Le numéro attribué (ou existant), null si numérotation inactive / erreur
     */
    public function Attribuer(string $TypeDoc, int $IdDoc): ?string
    {
        $TypeDoc = strtoupper($TypeDoc);
        if ($IdDoc <= 0 || !isset(self::TABLES_DOC[$TypeDoc])) {
            return null;
        }
        $Table = self::TABLES_DOC[$TypeDoc];
        $this->AjouteChampSiAbsent($Table, 'NUMERO_AFFICHE', "VARCHAR(30) NULL DEFAULT NULL");

        // Déjà numéroté ? (cas d'une modification de proforma ou de facture)
        $Res = $this->Query("SELECT NUMERO_AFFICHE FROM ".$this->Tbl($Table)." WHERE ID=".(int)$IdDoc);
        if ($Res && ($Row = $Res->fetch_assoc()) && trim((string)$Row['NUMERO_AFFICHE']) !== '') {
            return (string)$Row['NUMERO_AFFICHE'];
        }

        $Conf = $this->GetConfig($TypeDoc);
        if ((int)$Conf['ACTIVE'] !== 1) {
            return null;
        }

        $Annee = (int)date('Y');
        $Seq = $this->ProchaineSequence($TypeDoc, $Annee, (int)$Conf['NUMERODEPART']);
        if ($Seq <= 0) {
            return null;
        }
        $Numero = self::Formater($Conf, $Annee, $Seq);
        $this->Query("UPDATE ".$this->Tbl($Table)." SET NUMERO_AFFICHE='".
                     $this->Main::$db_link->real_escape_string($Numero)."' WHERE ID=".(int)$IdDoc);
        return $Numero;
    }
}
?>
