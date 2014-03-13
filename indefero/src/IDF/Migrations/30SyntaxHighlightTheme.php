<?php

function IDF_Migrations_30SyntaxHighlightTheme_up()
{
    $table = Pluf::factory('IDF_Project')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " ADD COLUMN `syntaxtheme` VARCHAR(50) NULL AFTER `enableads`;";

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

    $sql["MySQL"] = "ALTER TABLE " . $table . " DROP COLUMN `syntaxtheme`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }

    $db->execute($sql[$engine]);
}