<?php

namespace backend\controllers;

use Yii;
use common\models\User;
use common\models\Apple;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Class UserController
 *
 * Контроллер для работы с пользователем и его яблоками.
 *
 * @package backend\controllers
 */
class UserController extends Controller
{
    /**
     * @var bool Отключаем CSRF валидацию для API запросов
     */
    public $enableCsrfValidation = false;

    /**
     * Настройка поведений контроллера.
     *
     * @return array
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'generate' => ['POST'],
                    'apples' => ['GET'],
                ],
            ],
        ];
    }

    /**
     * Действие перед выполнением любого action.
     * Форсируем формат ответа JSON.
     *
     * @param \yii\base\Action $action
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }

    /**
     * @api {get} /apples Получить список яблок
     * @apiName GetUserApples
     * @apiGroup User
     *
     * @apiDescription Возвращает список яблок текущего авторизованного пользователя.
     * Также обновляет статус гнилых яблок "на лету".
     *
     * @apiSuccess {Object[]} items Список яблок.
     * @apiSuccess {Integer} items.id ID яблока.
     * @apiSuccess {String} items.color Цвет яблока (HEX).
     * @apiSuccess {Integer} items.status Статус (0-Tree, 1-Ground, 2-Rotten).
     * @apiSuccess {String} items.statusLabel Текстовое описание статуса.
     * @apiSuccess {Integer} items.integrity Целостность в %.
     * @apiSuccess {Integer} items.created_at Дата появления (Timestamp).
     * @apiSuccess {Integer|null} items.fall_at Дата падения (Timestamp).
     * @apiSuccess {Object[]} items.actions Доступные действия.
     * @apiSuccess {String} items.actions.method Метод API (eat/status).
     * @apiSuccess {String} items.actions.title Название кнопки.
     * @apiSuccess {String} items.actions.color Цвет кнопки (Bootstrap class).
     *
     * @return array
     */
    public function actionApples()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Получаем яблоки через связь
        $apples = $user->apples;

        // Пробегаемся по яблокам, чтобы обновить статус гнилых "на лету"
        foreach ($apples as $apple) {
            $apple->checkRottenState();
        }

        return array_map(function (Apple $apple) {
            $data = $apple->toArray();
            $data['actions'] = $apple->getAvailableActions();
            $data['statusLabel'] = Apple::STATUSES[$apple->status] ?? 'Unknown';

            return $data;
        }, $apples);
    }

    /**
     * @api {post} /generate Сгенерировать новые яблоки
     * @apiName GenerateApples
     * @apiGroup User
     *
     * @apiDescription Удаляет все текущие яблоки пользователя и генерирует новые
     * в случайном количестве (от MIN_APPLES_COUNT до MAX_APPLES_COUNT).
     *
     * @apiSuccess {Object[]} items Список новых яблок (см. GetUserApples).
     *
     * @return array
     * @throws \yii\db\Exception
     */
    public function actionGenerate()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Получаем настройки из ENV или берем дефолтные
        $min = getenv('MIN_APPLES_COUNT') ?: 2;
        $max = getenv('MAX_APPLES_COUNT') ?: 10;

        // Генерируем новые яблоки
        $newApples = $user->generateApples((int)$min, (int)$max);

        return array_map(function (Apple $apple) {
            $data = $apple->toArray();
            $data['actions'] = $apple->getAvailableActions();
            $data['statusLabel'] = Apple::STATUSES[$apple->status] ?? 'Unknown';

            return $data;
        }, $newApples);
    }
}