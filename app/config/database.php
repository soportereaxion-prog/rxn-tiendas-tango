<?php

return [
    'host'    => getenv('DB_HOST')     ?: '127.0.0.1',
    'port'    => getenv('DB_PORT')     ?: '3306',
    'dbname'  => getenv('DB_NAME')     ?: '',
    'user'    => getenv('DB_USER')     ?: '',
    'pass'    => getenv('DB_PASS')     ?: '',
    'charset' => getenv('DB_CHARSET')  ?: 'utf8mb4',
];
