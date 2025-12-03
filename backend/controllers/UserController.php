<?php

namespace backend\controllers;

use Yii;
use common\models\User;
use common\models\Apple;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class UserController extends Controller
{
    // ОТКЛЮЧАЕМ CSRF для этого контроллера
    public $enableCsrfValidation = false;

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
                    'generate' => ['POST'], // Генерация меняет данные - POST
                    'apples' => ['GET'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return parent::beforeAction($action);
    }

    /**
     * Получить список яблок текущего пользователя
     * GET /user/apples
     */
    public function actionApples()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Получаем яблоки через связь (там уже фильтр deleted_at is null)
        $apples = $user->apples;

        // ВАЖНО: Пробегаемся по яблокам, чтобы обновить статус гнилых "на лету"
        // (согласно примечанию 2 из вашего плана)
        foreach ($apples as $apple) {
            $apple->checkRottenState();
        }

        // Возвращаем массив яблок с дополнительными полями (actions)
        // Для этого нужно, чтобы Apple реализовывал fields() или extraFields(),
        // либо мы формируем ответ вручную.
        // Стандартный JSON сериализатор возьмет публичные свойства.
        // Чтобы добавить actions, лучше всего использовать API Resources,
        // но здесь сделаем простой map.

        return array_map(function(Apple $apple) {
            $data = $apple->toArray();
            $data['actions'] = $apple->getAvailableActions();
            // Можно добавить текстовое описание статуса
            $data['statusLabel'] = Apple::STATUSES[$apple->status] ?? 'Unknown';
            return $data;
        }, $apples);
    }

    /**
     * Сгенерировать новые яблоки
     * POST /user/generate
     */
    public function actionGenerate()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Получаем настройки из ENV или берем дефолтные
        $min = getenv('MIN_APPLES_COUNT') ?: 2;
        $max = getenv('MAX_APPLES_COUNT') ?: 10;

        // Генерируем (метод вернет новые модели)
        $newApples = $user->generateApples((int)$min, (int)$max);

        // Форматируем ответ так же, как в actionApples
        return array_map(function(Apple $apple) {
            $data = $apple->toArray();
            $data['actions'] = $apple->getAvailableActions();
            $data['statusLabel'] = Apple::STATUSES[$apple->status] ?? 'Unknown';
            return $data;
        }, $newApples);
    }
}