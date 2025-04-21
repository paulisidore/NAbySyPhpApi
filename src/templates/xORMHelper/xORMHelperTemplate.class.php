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
        public function __construct(xNAbySyGS $NabySy,?int $Id=null,$AutoCreate=true,$TableName="ModelTable", $DBName=null){
            if ($TableName==''){
                $TableName="ModelTable";
            }
            parent::__construct($NabySy,(int)$Id,$AutoCreate,$TableName,$DBName);
        }
    }
    

?>