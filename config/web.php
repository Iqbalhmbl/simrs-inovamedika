<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'simrs',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', \app\components\ErrorResponseFormatter::class],
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,
                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => 'PaYQ2dqPnt-zIqeIYy_U2E__ZNGeGNCl',
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        // Pure JWT API: the user component resolves identity per-request via
        // JwtHttpBearerAuth, so no session/cookie-based login is used.
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'authManager' => [
            'class' => \yii\rbac\DbManager::class,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // Custom (non-REST-CRUD) routes are declared with an explicit
                // 'verb' list that always includes OPTIONS - a plain 'POST
                // api/...' pattern only matches POST, so the browser's CORS
                // preflight (always OPTIONS) would 404 here before ever
                // reaching the controller/CORS filter. yii\rest\UrlRule
                // (used for the CRUD routes below) already adds OPTIONS to
                // every route it generates, so those don't need this.
                ['class' => \yii\web\UrlRule::class, 'verb' => ['POST', 'OPTIONS'], 'pattern' => 'api/auth/login', 'route' => 'auth/login'],
                ['class' => \yii\web\UrlRule::class, 'verb' => ['GET', 'OPTIONS'], 'pattern' => 'api/auth/me', 'route' => 'auth/me'],

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/patient'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/doctor'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/polyclinic'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/doctor-schedule'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medical-procedure'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medicine'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/user'],

                ['class' => \yii\web\UrlRule::class, 'verb' => ['POST', 'OPTIONS'], 'pattern' => 'api/visits/register', 'route' => 'api/visit/register'],
                ['class' => \yii\web\UrlRule::class, 'verb' => ['POST', 'OPTIONS'], 'pattern' => 'api/visits/<id>/complete', 'route' => 'api/visit/complete'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/visit'],

                ['class' => \yii\web\UrlRule::class, 'verb' => ['GET', 'OPTIONS'], 'pattern' => 'api/medical-records/by-visit/<visitId>', 'route' => 'api/medical-record/by-visit'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medical-record'],

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/visit-procedure'],

                ['class' => \yii\web\UrlRule::class, 'verb' => ['POST', 'OPTIONS'], 'pattern' => 'api/prescriptions/<id>/dispense', 'route' => 'api/prescription/dispense'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/prescription'],

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/lab-request'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/lab-result'],

                ['class' => \yii\web\UrlRule::class, 'verb' => ['GET', 'OPTIONS'], 'pattern' => 'api/invoices/by-visit/<visitId>', 'route' => 'api/invoice/by-visit'],
                ['class' => \yii\web\UrlRule::class, 'verb' => ['POST', 'OPTIONS'], 'pattern' => 'api/invoices/<id>/pay', 'route' => 'api/invoice/pay'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/invoice'],
            ],
        ],
    ],
    'modules' => [
        'api' => [
            'class' => \app\modules\api\Module::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
}

return $config;
