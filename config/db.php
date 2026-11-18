<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'pgsql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . (getenv('DB_NAME') ?: 'simrs'),
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'workingspace',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
