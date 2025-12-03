<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'advanced-backend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true, // Включаем красивые URL
            'showScriptName' => false, // Убираем index.php из адреса
            'enableStrictParsing' => false, // Разрешаем стандартные роуты, если правило не найдено
            'rules' => [
                // 1. Съесть яблоко: POST /apples/1/eat -> AppleController::actionEat(1)
                'POST apples/<id:\d+>/eat' => 'apple/eat',

                // 2. Уронить яблоко: POST /apples/1/status -> AppleController::actionStatus(1)
                'POST apples/<id:\d+>/status' => 'apple/status',

                // 3. Список яблок: GET /apples -> UserController::actionApples()
                'GET apples' => 'user/apples',

                // 4. Генерация: POST /generate -> UserController::actionGenerate()
                'POST generate' => 'user/generate',
            ],
        ],
    ],
    'params' => $params,
];
