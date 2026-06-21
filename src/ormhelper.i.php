<?php
namespace NAbySy\ORM ;

use JsonSerializable;
use NAbySy\xNAbySyGS;

interface IORMHelper extends IORM, JsonSerializable
{
    /** Evènement exécuté après ajout d'une nouvelle ligne dans la table */
    public const EVENTS_ADD = '_ADD';

    /** Evènement exécuté après modification d'un enregistrement dans la table */
    public const EVENTS_EDIT = '_EDIT';

    /** Evènement exécuté après suppression d'un enregistrement dans la table */
    public const EVENTS_DELETE = '_DEL';

    /** Evènement exécuté avant ajout d'une nouvelle ligne dans la table */
    public const EVENTS_BEFORE_ADD = '_BEFORE_ADD';

    /** Evènement exécuté avant modification d'un enregistrement dans la table */
    public const EVENTS_BEFORE_EDIT = '_BEFORE_EDIT';

    /** Evènement exécuté avant suppression d'un enregistrement dans la table */
    public const EVENTS_BEFORE_DELETE = '_BEFORE_DEL';

    /**
     * Constructeur de la classe ORM
     */
    public function __construct(
        ?xNAbySyGS $NAbySy = null,
        ?int $Id = null,
        ?bool $CreationChampAuto = true,
        ?string $TableName = null,
        ?string $DBName = null
    );

    /**
     * Définit ou Désactive l'utilisation de la connexion Master
     */
    public function UseMasterDataBaseLink(?bool $UseMasterDBLink = true): bool;

    /**
     * Retourne le nom complet de la table échappé (ex: `bdd`.`table`)
     */
    public function FullTableName(): string;

    /**
     * Retourne le nombre de lignes dans la table
     */
    public function count(): int;

    /**
     * Retourne l'élément courant (Itérateur)
     */
    public function current(): mixed;

    /**
     * Passe à l'élément suivant (Itérateur)
     */
    public function next(): void;

    /**
     * Retourne la clé de l'élément courant (Itérateur)
     */
    public function key(): mixed;

    /**
     * Vérifie si la position actuelle est valide (Itérateur)
     */
    public function valid(): bool;

    /**
     * Réinitialise la position de l'itérateur (Itérateur)
     */
    public function rewind(): void;

    /**
     * Vérifie si un champ existe (ArrayAccess)
     */
    public function offsetExists(mixed $NChamp): bool;

    /**
     * Récupère la valeur d'un champ (ArrayAccess)
     */
    public function offsetGet(mixed $NChamp): mixed;

    /**
     * Définit la valeur d'un champ (ArrayAccess)
     */
    public function offsetSet(mixed $NChamp, mixed $value): void;

    /**
     * Supprime un champ de la liste (ArrayAccess)
     */
    public function offsetUnset(mixed $NChamp): void;

    /**
     * Sérialise l'objet au format JSON
     */
    public function jsonSerialize(): mixed;

    /**
     * Retourne le nombre total de lignes d'une table donnée
     */
    public static function TotalLines(string $TableName = '', string $DBName = ''): int;

    /**
     * Récupère le type numérique interne d'un champ
     */
    public function GetTypeChampInDB($NomChamp): int;

    /**
     * Vérifie si le type d'un champ est numérique
     */
    public function IsTypeChampNumeric($NomChamp): bool;

    /**
     * Vérifie si le type d'un champ est une date ou heure
     */
    public function IsTypeChampDate($NomChamp): bool;

    /**
     * Force la création physique de la table dans la base de données
     */
    public function FlushMeToDB();

    /**
     * Méthode magique de lecture dynamique de propriété
     */
    public function __get($NomChamp);

    /**
     * Méthode magique d'écriture dynamique de propriété
     */
    public function __set($NomChamp, $Valeur);

    /**
     * Enregistre l'état actuel de l'objet (ajoute ou modifie)
     */
    public function Enregistrer(): bool;

    /**
     * Alias de la méthode Enregistrer
     */
    public function Save(): bool;

    /**
     * Retourne la requête SQL d'insertion brute
     */
    public function GetInsertSQLString(
        $IgnoreTableShema = false,
        $OnlyTableShema = false,
        $TargetDataBase = null,
        bool $IgnoreID = false
    ): string;

    /**
     * Retourne la requête SQL de mise à jour brute
     */
    public function GetUpDateSQLString(): string;

    /**
     * Supprime définitivement l'enregistrement lié à l'objet
     */
    public function Supprimer(): bool;

    /**
     * Alias de la méthode Supprimer
     */
    public function Delete(): bool;

    /**
     * Charge les données d'une ligne d'après son identifiant unique
     */
    public function ChargeOne(int $Id): ?\mysqli_result;

    /**
     * Charge l'enregistrement et retourne l'instance fluide de l'objet
     */
    public function Load(int $IdToLoad): xORMHelper;

    /**
     * Récupère la liste des résultats filtrés sous forme d'objet MySQLi
     */
    public function ChargeListe(
        string $Critere = null,
        $Ordre = null,
        $SelectChamp = "*",
        $GroupBy = null,
        ?string $Limit = null
    ): ?\mysqli_result;

    /**
     * Alias de la méthode ChargeListe
     */
    public function GetList(
        ?string $Criteria = null,
        ?string $OrderBy = null,
        ?string $ListColumnSelect = "*",
        ?string $GroupBy = null,
        ?string $Limit = null
    ): ?\mysqli_result;

    /**
     * Génère la requête de sélection avec les jointures actives sans l'exécuter
     */
    public function ChargeListeNoExecute(
        string $Critere = null,
        $Ordre = null,
        $SelectChamp = "*",
        $GroupBy = null,
        ?string $Limit = null
    ): string;

    /**
     * Alias de la méthode ChargeListeNoExecute
     */
    public function GetListNoExecute(
        ?string $Criteria = null,
        ?string $OrderBy = null,
        ?string $ListColumnSelect = "*",
        ?string $GroupBy = null,
        ?string $Limit = null
    ): string;

    /**
     * Exécute une requête SQL brute de lecture
     */
    public function ExecSQL($TxSQL): ?\mysqli_result;

    /**
     * Exécute une requête SQL brute d'écriture (INSERT, UPDATE, DELETE)
     */
    public function ExecUpdateSQL($TxSQL, $InsertTable = null);

    /**
     * Ajoute une note applicative dans le journal d'activité du système
     */
    public function AddToJournal(string $Tache, string $Note): bool;

    /**
     * Ajoute une entrée brute dans le fichier de Log
     */
    public function AddToLog(string $Note, int $DebugTraceLevel = 2): bool;

    /**
     * Indique si la table liée est totalement vide
     */
    public function TableIsEmpty(): bool;

    /**
     * Vérifie l'existence de la table via l'instance de configuration DB
     */
    public function TableExiste(): bool;

    /**
     * Encode une ressource SQL brute mysqli_result en chaîne de texte JSON
     */
    public function EncodeReponseSQLToJSON(\mysqli_result $Reponse);

    /**
     * Exporte les champs de l'objet ou sa structure brute au format texte JSON
     */
    public function ToJSON($TableStructure = false, $RemoveFieldList = []): string;

    /**
     * Convertit les propriétés chargées en tableau indexé par clé
     */
    public function ToArray($RemoveFieldList = []): array;

    /**
     * Convertit les propriétés chargées en tableau associatif nettoyé
     */
    public function ToArrayAssoc($RemoveFieldList = []): array;

    /**
     * Retourne la représentation littérale de l'état de l'objet sous forme de stdClass
     */
    public function ToObject(): ?object;

    /**
     * Copie l'enregistrement actuel vers une table ou base cible et retourne la nouvelle instance clonée
     */
    public function Clone(string $TargetDataBase = null, bool $IgnoreID = false): ?xORMHelper;

    /**
     * Supprime toutes les lignes de la table actuelle (TRUNCATE)
     */
    public function ViderTable(): bool;

    /**
     * Alias de la méthode ViderTable
     */
    public function TruncateTable(): bool;

    /**
     * Indique si la table existe physiquement dans la base de données
     */
    public function TableExisteInDataBase(): bool;

    /**
     * Alias de la méthode TableExisteInDataBase
     */
    public function TableExist(): bool;

    /**
     * Vérifie si une base de données spécifique existe sur le serveur
     */
    public function DBExiste(string $DBName = null): bool;

    /**
     * Détermine si une colonne existe dans la table actuelle
     */
    public function ChampsExisteInTable(string $Champ): bool;

    /**
     * Alias de la méthode ChampsExisteInTable
     */
    public function FieldExistInTable(string $FieldName): bool;

    /**
     * Force le rechargement des données de l'entité depuis la base via son ID actuel
     */
    public function Refresh(): bool;

    /**
     * Alias de la méthode Refresh
     */
    public function Actualise(): bool;

    /**
     * Modifie ou ajoute un champ structurel à la table à la volée
     */
    public function AlterTable(xChampDB $NewChamps): bool;

    /**
     * Altere dynamiquement le type SQL d'une colonne de données
     */
    public function ChangeTypeChamps(string $NomChamps, string $NewType, string $ValDefaut = null): bool;

    /**
     * Enregistre une spécification de jointure relationnelle (LEFT, INNER, etc.)
     */
    public function JoinTable(
        xORMHelper $TargetOrm,
        string $Alias = null,
        string $cleJointeSrc,
        string $cleJointeEtrangere = 'ID',
        $type = 'LEFT OUTER JOIN'
    ): xORMHelper;

    /**
     * Exécute et retourne le mysqli_result d'une requête de sélection construite avec des jointures actives
     */
    public function JointureChargeListe(
        string $Critere = null,
        $Ordre = null,
        $SelectChamp = "*",
        $GroupBy = null,
        ?string $Limit = null
    ): ?\mysqli_result;

    /**
     * Alias de la méthode JointureChargeListe
     */
    public function JointureLoadList(
        string $Criteria = null,
        ?string $OrderBy = null,
        ?string $ListColumnSelect = "*",
        ?string $GroupBy = null,
        ?string $Limit = null
    ): ?\mysqli_result;

    /*** Personnalise le tableau d'état inspecté lors d'un var_dump ou d'un débogage*/
    public function __debugInfo();

}

?>