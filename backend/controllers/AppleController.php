<?php

namespace backend\controllers;

use Yii;
use common\models\Apple;
use backend\models\forms\EatForm;
use backend\models\forms\StatusForm;
use yii\base\UserException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;

class AppleController extends Controller
{
    // ОТКЛЮЧАЕМ CSRF для этого контроллера
    public $enableCsrfValidation = false;

    /**
     * Настройка поведений
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Только авторизованные
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'eat' => ['POST'],
                    'status' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Форсируем JSON ответ для всех действий
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }

    /**
     * Съесть яблоко
     * POST /apple/eat?id=1 body: {percent: 25}
     */
    public function actionEat($id)
    {
        $model = Apple::findModel($id);
        $form = new EatForm();

        // Загружаем данные из POST
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                // Вызываем метод модели
                $model->eat($form->percent);

                // Возвращаем обновленный объект
                return $model;
            } catch (UserException $e) {
                // Ошибки бизнес-логики (нельзя съесть, гнилое и т.д.)
                // 422 Unprocessable Entity - сервер понимает тип содержимого, но не может выполнить
                throw new UnprocessableEntityHttpException($e->getMessage());
            } catch (\Exception $e) {
                throw new \yii\web\ServerErrorHttpException('Произошла ошибка при попытке съесть яблоко');
            }
        }

        // Ошибка валидации формы
        throw new BadRequestHttpException(json_encode($form->getErrors()));
    }

    /**
     * Сменить статус (Упасть)
     * POST /apple/status?id=1 body: {status: 1}
     */
    public function actionStatus($id)
    {
        $model = Apple::findModel($id);
        $form = new StatusForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                // Если пришел статус "На земле", роняем яблоко
                if ((int)$form->status === Apple::STATUS_ON_GROUND) {
                    $model->fallToGround();
                }
                // Здесь можно добавить обработку других смен статусов, если понадобятся

                return $model;
            } catch (UserException $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }

        throw new BadRequestHttpException(json_encode($form->getErrors()));
    }
}