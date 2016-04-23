<?php

function IDF_Migrations_32ExternalFile_up()
{
    $table = Pluf::factory('IDF_Scm_Cache_Git')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " CHANGE COLUMN `githash` TEXT NOT NULL DEFAULT '' AFTER `project`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}

function IDF_Migrations_32ExternalFile_down()
{
    $table = Pluf::factory('IDF_Scm_Cache_Git')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " CHANGE COLUMN `githash` VARCHAR(40) NOT NULL AFTER `project`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}