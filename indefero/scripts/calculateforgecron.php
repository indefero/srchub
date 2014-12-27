<?php

/**
 * Get a forge size.
 *
 * @return array Associative array with the size of each element
 */
function IDF_Views_Admin_getForgeSize($force=false)
{
    $conf = new IDF_Gconf();
    $conf->setModel((object) array('_model'=>'IDF_Forge', 'id'=> 1));
    $res = array();
    $res['repositories'] = 0;
    foreach (Pluf::factory('IDF_Project')->getList() as $prj) {
        $size = $prj->getRepositorySize($force);
        if ($size != -1) {
            $res['repositories'] += $size;
        }
    }
    $last_eval = $conf->getVal('downloads_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['downloads'] = $conf->getVal('downloads_size', 0);
    } else {
        $conf->setVal('downloads_size_check_date', time());
        $total = 0;
        foreach(Pluf::factory("IDF_Upload")->getList() as $issuefile)
        {
            $total += $issuefile->filesize;
        }
        $res['downloads'] = $total;
        $conf->setVal('downloads_size', $res['downloads']);
    }
    $last_eval = $conf->getVal('attachments_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['attachments'] = $conf->getVal('attachments_size', 0);
    } else {
        $total = 0;
        foreach(Pluf::factory("IDF_IssueFile")->getList() as $issuefile)
        {
            $total += $issuefile->filesize;
        }
        $res['attachments'] = $total;
        $conf->setVal('attachments_size_check_date', time());
        $conf->setVal('attachments_size', $res['attachments']);
    }
    $last_eval = $conf->getVal('database_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['database'] = $conf->getVal('database_size', 0);
    } else {
        $conf->setVal('database_size_check_date', time());
        $res['database'] = IDF_Views_Admin_getForgeDbSize();
        $conf->setVal('database_size', $res['database']);
    }
    $res['total'] = $res['repositories'] + $res['downloads'] + $res['attachments'] + $res['database'];
    return $res;
}

/**
 * Get the database size as given by the database.
 *
 * @return int Database size
 */
function IDF_Views_Admin_getForgeDbSize()
{
    $db = Pluf::db();
    if (Pluf::f('db_engine') == 'SQLite') {
        return filesize(Pluf::f('db_database'));
    }
    switch (Pluf::f('db_engine')) {
        case 'PostgreSQL':
            $sql = 'SELECT relname, pg_total_relation_size(CAST(relname AS
TEXT)) AS size FROM pg_class AS pgc, pg_namespace AS pgn
     WHERE pg_table_is_visible(pgc.oid) IS TRUE AND relkind = \'r\'
     AND pgc.relnamespace = pgn.oid
     AND pgn.nspname NOT IN (\'information_schema\', \'pg_catalog\')';
            break;
        case 'MySQL':
        default:
            $sql = 'SHOW TABLE STATUS FROM `'.Pluf::f('db_database').'`';
            break;
    }
    $rs = $db->select($sql);
    $total = 0;
    switch (Pluf::f('db_engine')) {
        case 'PostgreSQL':
            foreach ($rs as $table) {
                $total += $table['size'];
            }
            break;
        case 'MySQL':
        default:
            foreach ($rs as $table) {
                $total += $table['Data_length'] + $table['Index_length'];
            }
            break;
    }
    return $total;
}


require dirname(__FILE__).'/../src/IDF/conf/path.php';
require 'Pluf.php';
Pluf::start(dirname(__FILE__).'/../src/IDF/conf/idf.php');


$lock_file = Pluf::f('idf_queuecron_lock',
    Pluf::f('tmp_folder', '/tmp').'/calcforgecron.lock');

if (file_exists($lock_file)) {
    return;
}

file_put_contents($lock_file, time(), LOCK_EX);


Pluf_Dispatcher::loadControllers(Pluf::f('idf_views'));

IDF_Views_Admin_getForgeSize(true);

unlink($lock_file);