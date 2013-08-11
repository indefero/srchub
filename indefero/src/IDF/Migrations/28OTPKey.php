<?php

function IDF_Migrations_28OTPKey_up()
{
    $table = Pluf::factory('Pluf_User')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " ADD COLUMN `otpkey` VARCHAR(50) NULL AFTER `last_login`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }

    $db->execute($sql[$engine]);

}

function IDF_Migrations_28OTPKey_down()
{
    $table = Pluf::factory('Pluf_User')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = "ALTER TABLE " . $table . " DROP COLUMN `otpkey`;";

    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }

    $db->execute($sql[$engine]);
}