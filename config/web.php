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
                'POST api/auth/login' => 'auth/login',
                'GET api/auth/me' => 'auth/me',

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/patient'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/doctor'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/polyclinic'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/doctor-schedule'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medical-procedure'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medicine'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/user'],

                'POST api/visits/register' => 'api/visit/register',
                'POST api/visits/<id>/complete' => 'api/visit/complete',
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/visit'],

                'GET api/medical-records/by-visit/<visitId>' => 'api/medical-record/by-visit',
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/medical-record'],

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/visit-procedure'],

                'POST api/prescriptions/<id>/dispense' => 'api/prescription/dispense',
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/prescription'],

                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/lab-request'],
                ['class' => \yii\rest\UrlRule::class, 'controller' => 'api/lab-result'],

                'GET api/invoices/by-visit/<visitId>' => 'api/invoice/by-visit',
                'POST api/invoices/<id>/pay' => 'api/invoice/pay',
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
