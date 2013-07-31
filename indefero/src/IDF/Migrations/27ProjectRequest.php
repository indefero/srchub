<?php
function IDF_Migrations_27ProjectRequest_up($params=null)
{
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    $schema->model = new IDF_ProjectRequest();
    $schema->createTables();
}

function IDF_Migrations_27ProjectRequest_down($params=null)
{
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    $schema->model = new IDF_ProjectRequest();
    $schema->dropTables();

}