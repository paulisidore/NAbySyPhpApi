<?php

/****************************************************************************
*   About       :   Convertit jusqu'à  999 999 999 999 999 (billion)        *
*                   avec respect des accords                                *
*_________________________________________________________________________  *			
*               Transposed from JS to PHP and optimised by                  *
*                 Hamza BENDALI BRAHAM <hbendali@ya.ru>                     *
*       Inspired from GALA OUSSE Brice, nombre_en_lettre.js project         *
*           Github: https://github.com/luxigo/number-to-letters             *
*****************************************************************************
*/

function NumberToLetter( $nombre, $U = null, $D = null){

    $toLetter = [
        0 => "zéro",
        1 => "un",
        2 => "deux",
        3 => "trois",
        4 => "quatre",
        5 => "cinq",
        6 => "six",
        7 => "sept",
        8 => "huit",
        9 => "neuf",
        10 => "dix",
        11 => "onze",
        12 => "douze",
        13 => "treize",
        14 => "quatorze",
        15 => "quinze",
        16 => "seize",
        17 => "dix-sept",
        18 => "dix-huit",
        19 => "dix-neuf",
        20 => "vingt",
        30 => "trente",
        40 => "quarante",
        50 => "cinquante",
        60 => "soixante",
        70 => "soixante-dix",
        80 => "quatre-vingt",
        90 => "quatre-vingt-dix",
    ];
    
    //global $toLetter;
    $numberToLetter='';
    $nombre = strtr((string)$nombre, [" "=>""]);
    $nb = floatval($nombre);

    if( strlen($nombre) > 15 ) return "dépassement de capacité";
    if( !is_numeric($nombre) ) return "Nombre non valide";
	if( ceil($nb) != $nb ){
        $nb = explode('.',$nombre);
        return NumberToLetter($nb[0]) . ($U ? " $U et " : " virgule ") . NumberToLetter($nb[1]) . ($D ? " $D" : "");
    }

	$n = strlen($nombre);
	switch( $n ){
        case 1:
            $numberToLetter = $toLetter[$nb];
            break;
        case 2:
            if(  $nb > 19  ){
                $quotient = floor($nb / 10);
                $reste = $nb % 10;
                if(  $nb < 71 || ($nb > 79 && $nb < 91)  ){
                    if(  $reste == 0  ) $numberToLetter = $toLetter[$quotient * 10];
                    if(  $reste == 1  ) $numberToLetter = $toLetter[$quotient * 10] . "-et-" . $toLetter[$reste];
                    if(  $reste > 1   ) $numberToLetter = $toLetter[$quotient * 10] . "-" . $toLetter[$reste];
                }else $numberToLetter = $toLetter[($quotient - 1) * 10] . "-" . $toLetter[10 + $reste];
            }else $numberToLetter = $toLetter[$nb];
            break;

        case 3:
            $quotient = floor($nb / 100);
            $reste = $nb % 100;
            if(  $quotient == 1 && $reste == 0   ) $numberToLetter = "cent";
            if(  $quotient == 1 && $reste != 0   ) $numberToLetter = "cent" . " " . NumberToLetter($reste);
            if(  $quotient > 1 && $reste == 0    ) $numberToLetter = $toLetter[$quotient] . " cents";
            if(  $quotient > 1 && $reste != 0    ) $numberToLetter = $toLetter[$quotient] . " cent " . NumberToLetter($reste);
            break;
        case 4 :
        case 5 :
        case 6 :
            $quotient = floor($nb / 1000);
            $reste = $nb - $quotient * 1000;
            if(  $quotient == 1 && $reste == 0   ) $numberToLetter = "mille";
            if(  $quotient == 1 && $reste != 0   ) $numberToLetter = "mille" . " " . NumberToLetter($reste);
            if(  $quotient > 1 && $reste == 0    ) $numberToLetter = NumberToLetter($quotient) . " mille";
            if(  $quotient > 1 && $reste != 0    ) $numberToLetter = NumberToLetter($quotient) . " mille " . NumberToLetter($reste);
            break;
        case 7:
        case 8:
        case 9:
            $quotient = floor($nb / 1000000);
            $reste = $nb % 1000000;
            if(  $quotient == 1 && $reste == 0  ) $numberToLetter = "un million";
            if(  $quotient == 1 && $reste != 0  ) $numberToLetter = "un million" . " " . NumberToLetter($reste);
            if(  $quotient > 1 && $reste == 0   ) $numberToLetter = NumberToLetter($quotient) . " millions";
            if(  $quotient > 1 && $reste != 0   ) $numberToLetter = NumberToLetter($quotient) . " millions " . NumberToLetter($reste);
            break;
        case 10:
        case 11:
        case 12:
            $quotient = floor($nb / 1000000000);
            $reste = $nb - $quotient * 1000000000;
            if(  $quotient == 1 && $reste == 0  ) $numberToLetter = "un milliard";
            if(  $quotient == 1 && $reste != 0  ) $numberToLetter = "un milliard" . " " . NumberToLetter($reste);
            if(  $quotient > 1 && $reste == 0   ) $numberToLetter = NumberToLetter($quotient) . " milliards";
            if(  $quotient > 1 && $reste != 0   ) $numberToLetter = NumberToLetter($quotient) . " milliards " . NumberToLetter($reste);
            break;
        case 13:
        case 14:
        case 15:
            $quotient = floor($nb / 1000000000000);
            $reste = $nb - $quotient * 1000000000000;
            if(  $quotient == 1 && $reste == 0  ) $numberToLetter = "un billion";
            if(  $quotient == 1 && $reste != 0  ) $numberToLetter = "un billion" . " " . NumberToLetter($reste);
            if(  $quotient > 1 && $reste == 0   ) $numberToLetter = NumberToLetter($quotient) . " billions";
            if(  $quotient > 1 && $reste != 0   ) $numberToLetter = NumberToLetter($quotient) . " billions " . NumberToLetter($reste);
            break;
    }
	/*respect de l'accord de quatre-vingt*/
    if( substr($numberToLetter, strlen($numberToLetter)-12, 12 ) == "quatre-vingt" ) $numberToLetter .= "s";

    return $numberToLetter;
}//-----------------------------------------------------------------------