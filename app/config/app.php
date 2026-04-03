<?php

return [
    'app_name' => getenv('APP_NAME') ?: 'rxn_suite',
    'env'      => getenv('APP_ENV')  ?: 'development',
    'debug'    => getenv('APP_DEBUG') === 'true',
];
