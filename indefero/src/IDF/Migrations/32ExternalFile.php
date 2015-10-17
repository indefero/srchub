<?php

function IDF_Migrations_32ExternalFile_up()
{
    $table = Pluf::factory('IDF_Upload')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " ADD COLUMN `ext_file` VARCHAR(250) NULL AFTER `modif_dtime`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}

function IDF_Migrations_32ExternalFile_down()
{
    $table = Pluf::factory('IDF_Upload')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " DROP COLUMN `ext_file`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}