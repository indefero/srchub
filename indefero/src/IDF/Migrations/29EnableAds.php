<?php

function IDF_Migrations_29EnableAds_up()
{
    $table = Pluf::factory('IDF_Project')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " ADD COLUMN `enableads` int(11) NULL AFTER `current_activity`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }

    $db->execute($sql[$engine]);

}

function IDF_Migrations_28OTPKey_down()
{
    $table = Pluf::factory('IDF_Project')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " DROP COLUMN `enableads`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }

    $db->execute($sql[$engine]);
}