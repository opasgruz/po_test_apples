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
use yii\web\ServerErrorHttpException;

/**
 * Class AppleController
 *
 * Контроллер для действий над конкретным яблоком.
 *
 * @package backend\controllers
 */
class AppleController extends Controller
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
     * @api {post} /apples/{id}/eat Съесть яблоко
     * @apiName EatApple
     * @apiGroup Apple
     *
     * @apiDescription Откусить часть яблока. Уменьшает целостность (integrity).
     * Если integrity становится <= 0, яблоко считается съеденным (удаляется).
     *
     * @apiParam {Integer} id ID яблока (в URL).
     * @apiParam {Integer} percent Процент откусываемой части (в Body JSON).
     *
     * @apiSuccess {Object} apple Обновленный объект яблока.
     *
     * @apiError (422) UnprocessableEntityHttpException Ошибки бизнес-логики (нельзя съесть на дереве, гнилое и т.д.).
     * @apiError (400) BadRequestHttpException Ошибка валидации входящих параметров.
     * @apiError (404) NotFoundHttpException Яблоко не найдено или не принадлежит пользователю.
     *
     * @param int $id
     * @return Apple
     * @throws BadRequestHttpException
     * @throws UnprocessableEntityHttpException
     * @throws ServerErrorHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionEat($id)
    {
        $model = Apple::findModel($id);
        $form = new EatForm();

        // Загружаем данные из POST и валидируем
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $model->eat($form->percent);

                return $model;
            } catch (UserException $e) {
                // Ошибки бизнес-логики
                throw new UnprocessableEntityHttpException($e->getMessage());
            } catch (\Exception $e) {
                throw new ServerErrorHttpException('Произошла ошибка при попытке съесть яблоко');
            }
        }

        // Ошибка валидации формы
        throw new BadRequestHttpException(json_encode($form->getErrors()));
    }

    /**
     * @api {post} /apples/{id}/status Уронить яблоко
     * @apiName StatusApple
     * @apiGroup Apple
     *
     * @apiDescription Сменить статус яблока. Используется для падения с дерева на землю.
     *
     * @apiParam {Integer} id ID яблока (в URL).
     * @apiParam {Integer} status Новый статус (1 - Ground) (в Body JSON).
     *
     * @apiSuccess {Object} apple Обновленный объект яблока.
     *
     * @apiError (422) UnprocessableEntityHttpException Ошибки бизнес-логики (уже упало и т.д.).
     * @apiError (400) BadRequestHttpException Ошибка валидации параметров.
     *
     * @param int $id
     * @return Apple
     * @throws BadRequestHttpException
     * @throws UnprocessableEntityHttpException
     * @throws \yii\web\NotFoundHttpException
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

                return $model;
            } catch (UserException $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }

        throw new BadRequestHttpException(json_encode($form->getErrors()));
    }
}