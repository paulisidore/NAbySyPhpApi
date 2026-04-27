<?php
namespace NAbySy\Lib\ModulePaie;

/**
 * Interface de Demande de Session de paiement
 */
interface ICheckOutParam
{
    /**
     * Créer un nouvel objet de demande de paiement sans l'enregistrer dans la base de donnée.
     * @param int $Montant : Le montant de la transaction à demander
     * @param string $Monnaie : la monnaie de paiement. XOF pour la zone UEMOA
     * @param string $success_url : l'url qui sera ouvert sur le téléphone du client en cas de réussite
     * @param string $error_url : l'url qui sera ouvert sur le telephone du client en cas d'echec
     * @param string|null $RefFacture : La réference de la facture qui doit etre soldée par le client
     * @param string|null $aggregated_merchant_id : Le code merchand utilisé par la boutique afin que ca soit son nom qui soit affiché
     * sur le téléphone du client
     * @return ICheckOutParam : Un objet contenant les paramètres à envoyer. cet objet n'est pas encore enregistré dans la base de donnée.
     */
    public function CreateCheckOut(
        int $Montant,
        string $Monnaie = 'XOF',
        string $success_url = '',
        string $error_url = '',
        ?string $RefFacture = null,
        ?string $aggregated_merchant_id = null,
        ?string $IndicatifTel = null,
        ?string $TelDestinataire = null,
        ?string $Description = null
    ): ICheckOutParam;

    /**
     * Retourne la demande au Format JSON pour l'API
     * @return string
     */
    public function GetDemandeJSON(): string;
}
