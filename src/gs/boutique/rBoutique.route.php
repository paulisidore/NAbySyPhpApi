<?php

use NAbySy\GS\Boutique\xBoutique;
use NAbySy\Router\Url\xNAbySyUrlRouterHelper;
use NAbySy\xNotification;

/**
 * Gestion des route basée sur l'url des boutiques
 * @package 
 */
class rBoutique extends xNAbySyUrlRouterHelper{
    public function __construct(string $routerName, string $fileSource, string $FriendlyName='Gestion des Boutiques NAbySyGS', string $Description='Permet la gestion des Boutiques NAbySyGS') {
        parent:: __construct($routerName, $fileSource, $FriendlyName, $Description);
        $this->setupRoute();
    }

    private function setupRoute(){
        $this->get('api/boutiques', function(){
            $Rep=new xNotification();
            $Rep->OK=1;
            $Bout=new xBoutique(N::getInstance());
            //$Lst=$Bout->ChargeListe();
            $Liste=[];
            /* if($Lst){
                while($rw=$Lst->fetch_assoc()){
                    $Liste[]=$rw;
                }
            } */
            foreach(N::getInstance()->Boutiques as $Bout){
                $Liste[]=$Bout->ToObject;
            }
            $Rep->Contenue = $Liste ;
            return json_encode($Rep) ;
        });

        $this->get('api/boutiques/{id}', function($id){
            $Rep=new xNotification();
            $Rep->OK=0;
            $Bout=new xBoutique(N::getInstance());
            //$Lst=$Bout->ChargeListe();
            $Liste=[];
            /* if($Lst){
                while($rw=$Lst->fetch_assoc()){
                    $Liste[]=$rw;
                }
            } */
            foreach(N::getInstance()->Boutiques as $Bout){
                if($id==$Bout->Id){
                    $Liste[]=$Bout->ToObject;
                    $Rep->OK=1;
                }
            }
            $Rep->Contenue = $Liste ;
            if($Rep->OK>0){
                $Rep->TxErreur="Boutique Id ".$id." introuvable !";
            }
            return json_encode($Rep) ;
        });

        $this->post('api/boutiques', function(){
            $Rep=new xNotification();
            $Rep->OK=0;
            // Récupérer les données envoyées par le client
            // file_get_contents('php://input') lit le corps de la requête
            // json_decode() transforme le JSON en tableau PHP
            $donnees = json_decode(file_get_contents('php://input'), true);
            // Validation : vérifier que les données requises sont présentes
            if (empty($donnees['Nom']) ) {
                http_response_code(422); // 422 = Unprocessable Entity (données invalides)
                $Rep->TxErreur="Le champ Nom est obligatoir.";
                return json_encode($Rep);
            }

            $nBout=new xBoutique(N::getInstance());
            $nBout->Nom=$donnees['Nom'];
            $ListeChampIn=[];
            foreach ($donnees as $key => $value) {
                if($nBout->ChampsExisteInTable($key)){
                    $nBout->$key = $value;
                }else{
                    $ListeChampIn[]=$key." introuvable !";
                }
            }
            if(count($ListeChampIn)){
                http_response_code(422); // 422 = Unprocessable Entity (données invalides)
                $Rep->TxErreur="Des champs inexistants sont présent dans la requettes ";
                $Rep->Autres = $ListeChampIn ;
                return json_encode($Rep);
            }else{
                //On crée la boutique ?
                // 201 = Created (ressource créée avec succès)
                http_response_code(201);
                $Rep->OK=1;
                $Rep->TxErreur=null;
                $Rep->Autres="Boutique crée correctement.";
                $Rep->Contenue=$nBout->ToObject();
            }
            return json_encode($Rep);
        });
    }
}
?>