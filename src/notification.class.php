<?php

/* 
 * Application developpé par Paul Isidore A. NIAMIE
 * paul_isidore@hotmail.com
 */
namespace NAbySy ;
Class xNotification extends xErreur
{
    public $Contenue ;

    public function __construct($jsonData=null){
        $this->OK=1;
        $js=$jsonData;
        if (isset($jsonData)){
            if (is_string($jsonData)){
                $js=json_decode($jsonData);
            }
            foreach ($js AS $key => $value) $this->{$key} = $value;
        }
    }
    
}
