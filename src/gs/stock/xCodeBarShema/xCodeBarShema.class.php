<?php
    /**
     * Module de gestion des Produits Non Enregistrés dans la base de donnée du stock
     * mais vendu sous forme générique tel que les fruits et légume et autre articles pas encore crée mais disponible
     * dans la salle de vente.
     * Cette utilise contient le paramétrage pour chaque type de produit
     */
    namespace NAbySy\GS\Stock ;

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\ORM\xORMHelper;

    class xCodeBarShema extends xORMHelper {
        public function __construct(\xNAbySyGS $NAbySy,int $Id=null,$AutoCreateTable=false,$TableName='codebarshema', xBoutique $Boutique=null){

            parent::__construct($NAbySy,$Id,$AutoCreateTable,$TableName);

            if (!$this->MySQL->ChampsExiste($this->Table,"TVA")){
                $this->MySQL->AlterTable($this->Table,"TVA","int(11)","ADD","18");
            }
            if (!$this->MySQL->ChampsExiste($this->Table,"RETIRER_TVA")){
                $this->MySQL->AlterTable($this->Table,"RETIRER_TVA","int(11)","ADD","1");
            }
            
        }
    }
?>