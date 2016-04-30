<?php

function IDF_Migrations_34GitSSHTracking_up()
{
    $table = Pluf::factory('IDF_Key')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = <<<EOD
ALTER TABLE `$table`
	ADD COLUMN `last_used` DATETIME NOT NULL AFTER `content`,
	ADD COLUMN `creation_dtime` DATETIME NOT NULL AFTER `last_used`,
	ADD COLUMN `ipaddress` VARCHAR(255) NOT NULL AFTER `creation_dtime`,
	ADD INDEX `last_used` (`last_used`),
	ADD INDEX `creation_dtime` (`creation_dtime`);

UPDATE indefero_idf_keys SET creation_dtime = '2016-04-30 03:19:54';
EOD;


    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}

function IDF_Migrations_34GitSSHTracking_down()
{
    $table = Pluf::factory('IDF_Key')->getSqlTable();

    $sql = array();

    $sql["MySQL"] = <<<EOD
ALTER TABLE `$table`
	DROP COLUMN `last_used` DATETIME NOT NULL AFTER `content`,
	DROP COLUMN `creation_dtime` DATETIME NOT NULL AFTER `last_used`,
	DROP COLUMN `ipaddress` VARCHAR(255) NOT NULL AFTER `creation_dtime`,
	DROP INDEX `last_used` (`last_used`),
	DROP INDEX `creation_dtime` (`creation_dtime`);
EOD;


    $db = Pluf::db();
    $engine = Pluf::f('db_engine');

    $db->execute($sql[$engine]);
}