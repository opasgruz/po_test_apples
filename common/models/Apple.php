<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\base\UserException;
use yii\db\ActiveQuery;
use yii\web\NotFoundHttpException;

/**
 * This is the model class for table "apples".
 *
 * @property int $id
 * @property int $user_id
 * @property string $color HEX code
 * @property int $status 0-Tree, 1-Ground, 2-Rotten
 * @property int $integrity Целостность в % (100 - целое)
 * @property int $created_at
 * @property int|null $fall_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 *
 * @property User $user
 *
 * @package common\models
 */
class Apple extends ActiveRecord
{
    /** Статус: Висит на дереве */
    const STATUS_ON_TREE = 0;

    /** Статус: Лежит на земле */
    const STATUS_ON_GROUND = 1;

    /** Статус: Гнилое */
    const STATUS_ROTTEN = 2;

    /** @var array Расшифровка статусов */
    const STATUSES = [
        self::STATUS_ON_TREE => 'Висит на дереве',
        self::STATUS_ON_GROUND => 'Лежит на земле',
        self::STATUS_ROTTEN => 'Гнилое яблоко',
    ];

    /** Константы для действия "Упасть" */
    const ACTION_FALL_METHOD = 'status';
    const ACTION_FALL_TITLE = 'Уронить';
    const ACTION_FALL_COLOR = 'warning';

    /** Константы для действия "Съесть" */
    const ACTION_EAT_METHOD = 'eat';
    const ACTION_EAT_TITLE = 'Съесть';
    const ACTION_EAT_COLOR = 'success';

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%apples}}';
    }

    /**
     * Поведения модели.
     *
     * @return array
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => false, // created_at мы генерируем вручную
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * Правила валидации.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            [['user_id', 'color', 'created_at'], 'required'],
            [['user_id', 'status', 'integrity', 'created_at', 'fall_at', 'deleted_at', 'updated_at'], 'integer'],
            [['color'], 'string', 'max' => 7], // #RRGGBB
            [['status'], 'in', 'range' => array_keys(self::STATUSES)],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * Связь с пользователем.
     *
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    // -------------------------------------------------------------------------
    // HELPERS FOR STATUS
    // -------------------------------------------------------------------------

    /**
     * Проверка: На дереве?
     *
     * @return bool
     */
    public function isStatusOnTree(): bool
    {
        return $this->status === self::STATUS_ON_TREE;
    }

    /**
     * Проверка: На земле?
     *
     * @return bool
     */
    public function isStatusOnGround(): bool
    {
        return $this->status === self::STATUS_ON_GROUND;
    }

    /**
     * Проверка: Гнилое?
     *
     * @return bool
     */
    public function isStatusRotten(): bool
    {
        return $this->status === self::STATUS_ROTTEN;
    }

    /**
     * Проверка: Удалено (съедено полностью)?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Получить время гниения из настроек (в секундах).
     *
     * @return int
     */
    protected function getRottenTimeLimit(): int
    {
        // Значение по умолчанию 300 секунд (5 минут), если в env пусто
        return getenv('ROTTEN_TIME_LIMIT') ?: 300;
    }

    /**
     * Упасть на землю.
     * Меняет статус и фиксирует время падения.
     *
     * @return bool
     * @throws UserException
     */
    public function fallToGround(): bool
    {
        if (!$this->isStatusOnTree()) {
            throw new UserException('AppleAlreadyFallenException');
        }

        $this->status = self::STATUS_ON_GROUND;
        $this->fall_at = time();

        return $this->save();
    }

    /**
     * Откусить часть яблока.
     *
     * @param int $percent Процент откусываемой части
     * @return bool
     * @throws UserException
     */
    public function eat(int $percent): bool
    {
        // Актуализируем статус перед попыткой съесть
        $this->checkRottenState();

        if ($this->isStatusOnTree()) {
            throw new UserException('AppleOnTreeException');
        }

        if ($this->isStatusRotten()) {
            throw new UserException('AppleRottenException');
        }

        if ($this->isDeleted()) {
            throw new UserException('AppleAlreadyEatenException');
        }

        if ($percent <= 0) {
            throw new UserException('InvalidPercentException');
        }

        $this->integrity -= $percent;

        if ($this->integrity <= 0) {
            $this->integrity = 0;
            $this->deleted_at = time();
        }

        return $this->save();
    }

    /**
     * Проверка на гнилость (Time-based logic).
     * Если яблоко лежит на земле дольше лимита, оно становится гнилым.
     *
     * @return void
     */
    public function checkRottenState(): void
    {
        if ($this->isStatusOnGround() && !$this->isDeleted()) {
            $timeOnGround = time() - $this->fall_at;

            if ($timeOnGround > $this->getRottenTimeLimit()) {
                $this->status = self::STATUS_ROTTEN;
                $this->save(false, ['status']);
            }
        }
    }

    /**
     * API Response Helper: Список доступных действий для фронтенда.
     *
     * @return array
     */
    public function getAvailableActions(): array
    {
        $actions = [];

        if ($this->isDeleted()) {
            return $actions;
        }

        // Кнопка "Упасть" (если на дереве)
        if ($this->isStatusOnTree()) {
            $actions[] = [
                'method' => self::ACTION_FALL_METHOD,
                'title' => self::ACTION_FALL_TITLE,
                'color' => self::ACTION_FALL_COLOR,
            ];
        }

        // Кнопка "Съесть" (если на земле)
        if ($this->isStatusOnGround()) {
            // Проверка "на лету", чтобы не рисовать кнопку если оно только что сгнило
            $this->checkRottenState();

            if (!$this->isStatusRotten()) {
                $actions[] = [
                    'method' => self::ACTION_EAT_METHOD,
                    'title' => self::ACTION_EAT_TITLE,
                    'color' => self::ACTION_EAT_COLOR,
                ];
            }
        }

        return $actions;
    }

    /**
     * Фабричный метод: Создает экземпляр яблока с полностью заполненными атрибутами.
     * Явно задаем null для необязательных полей, чтобы getAttributes() вернул полную структуру.
     *
     * @param int $userId
     * @return self
     */
    public static function create(int $userId): self
    {
        $apple = new self();

        // Заполняем обязательные поля
        $apple->user_id = $userId;
        $apple->color = self::generateRandomHexColor();
        $apple->status = self::STATUS_ON_TREE;
        $apple->integrity = 100;

        // Генерируем время
        $currentTime = time();
        $createdAt = $currentTime - rand(0, 18000);

        $apple->created_at = $createdAt;
        $apple->updated_at = $createdAt;

        // Явно инициализируем Nullable поля, чтобы они попали в attributes
        $apple->fall_at = null;
        $apple->deleted_at = null;

        return $apple;
    }

    /**
     * Генерация случайного HEX цвета.
     *
     * @return string
     */
    private static function generateRandomHexColor(): string
    {
        $palettes = [
            'green' => ['#32CD32', '#008000', '#228B22', '#ADFF2F', '#7CFC00'],
            'red' => ['#FF0000', '#DC143C', '#B22222', '#CD5C5C', '#FF6347'],
            'yellow' => ['#FFFF00', '#FFD700', '#FFFFE0', '#EEE8AA', '#F0E68C'],
            'maroon' => ['#800000', '#8B0000', '#A52A2A', '#A0522D', '#8B4513'],
        ];

        $randomPaletteKey = array_rand($palettes);

        return $palettes[$randomPaletteKey][array_rand($palettes[$randomPaletteKey])];
    }

    /**
     * Универсальное массовое сохранение (Batch Insert).
     * Берет структуру колонок из первой модели в массиве.
     *
     * @param Apple[] $models Массив объектов Apple
     * @return void
     * @throws \yii\db\Exception
     */
    public static function saveBatch(array $models): void
    {
        if (empty($models)) {
            return;
        }

        // 1. Берем первую модель как образец структуры
        $firstModel = reset($models);

        // Получаем имена атрибутов (колонок БД)
        $attributes = $firstModel->getAttributes();
        $columns = array_keys($attributes);

        $rows = [];

        // 2. Формируем массив данных
        foreach ($models as $model) {
            // Получаем значения строго в том же порядке, что и $columns
            $rows[] = array_values($model->getAttributes($columns));
        }

        // 3. Выполняем запрос
        Yii::$app->db->createCommand()
            ->batchInsert(self::tableName(), $columns, $rows)
            ->execute();
    }

    /**
     * Поиск яблока по ID и UserID (чужие есть/ронять нельзя).
     *
     * @param int $id
     * @return Apple
     * @throws NotFoundHttpException
     */
    public static function findModel(int $id): Apple
    {
        if (($model = Apple::findOne(['id' => $id, 'user_id' => Yii::$app->user->id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Яблоко не найдено или не принадлежит вам.');
    }
}