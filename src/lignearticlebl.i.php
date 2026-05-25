<?php
/**
 * Modèle d'une ligne d'Article dans le contenue d'un BL
 * By Paul & Aïcha Machinerie
 * paul_isidore@hotmail.com
 */
namespace NAbySy\GS\BL;

use NAbySy\ORM\xORMHelper;

interface ILigneArticleBL{
    /**
        * Constructeur de la ligne d'article du BL
        * @param int|null $IdPdt Identifiant le produit de la ligne d'article du BL
        * @param int|null $Qte Quantité de l'article dans le BL
        * @param int|null $QTE_UG Quantité d'unités de livraison gratuite si disponible de l'article dans le BL
        * @param int|null $PrixCession Prix de cession unitaire de l'article dans le BL
        * @param int|null $PrixVente Prix de vente unitaire de l'article au moment de 'larrivée de l'article dans le BL
        * @param bool|null $VENTEDETAILLEE Indique si l'achat est détaillée ou non
        * @param string|null $DATE_PEREMPTION Si article périssable, date de péremption de l'article dans le BL
        * @param int|null $TVA Montant total de la TVA si applicable à l'article dans le BL
        * @param int|null $TVAINCLUT Indique si le prix unitaire inclut la TVA ou non
        */
    public static function create(
        ?int $IdProduit =0,
        ?int $Qte = 1,
        ?int $QTE_UG = 0,
        ?int $PrixCession = 0,
        ?int $PrixVente = 0,
        ?bool $VENTEDETAILLEE = false,
        ?string $DATE_PEREMPTION = null,
        ?int $TVA = 0,
        ?int $TVAINCLUT = 0
    ): ILigneArticleBL ;

    /**
     * Converture une donnée JSON en article pour la ligne de detail BL
     * @param string $json 
     * @return xORMHelper | ILigneArticleBL 
     */
    public static function fromJSON(string $json): xORMHelper | ILigneArticleBL ;
    public function toJSON(): string;
}

interface IBonLivraison{
    public static function fromJSON(string $json): IBonLivraison ;
    public function toJSON(): string ;
}

?>