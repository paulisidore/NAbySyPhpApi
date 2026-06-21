<?php
/**
 * @file ModelTemplate.class.php
 * Contains Generique Class Module for NAbySyGS
 * Author: 
 * Mail: 
 * Date: {DATE}
 * Version: 1.0.0
 */
    namespace NAbySy ;

    use NAbySy\ORM\xORMHelper;

    class ModelTemplate extends xORMHelper {
        public function __construct(?xNAbySyGS $NabySy = null,?int $Id=null,$AutoCreate=true,$TableName="ModelTable", $DBName=null){
            if(!isset($NabySy)){
                $NabySy = xNAbySyGS::getInstance();
            }
            if (!isset($TableName)){
                $TableName="";
            } 
            if ($TableName==''){
                $TableName="ModelTable";
            }
            if(!isset($DBName)){
                $DBName=$NabySy->MaBoutique->DBName;
            }elseif(trim($DBName)==''){
                $DBName=$NabySy->MaBoutique->DBName;
            }
            if(is_string($DBName) && trim($DBName)=="" && trim($NabySy->MaBoutique->DBase) !==""){
                 $DBName=$NabySy->MaBoutique->DBase ;
            }
            parent::__construct($NabySy,(int)$Id,$AutoCreate,$TableName,$DBName);
        }
    }
    

?>