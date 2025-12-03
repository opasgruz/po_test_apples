<?php

use common\models\User;
use yii\db\Migration;

class m251203_181657_create_apples_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%apples}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->comment('Владелец яблока'),
            'color' => $this->string(7)->notNull()->comment('Цвет яблока'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0-Tree, 1-Ground, 2-Rotten'),
            'integrity' => $this->tinyInteger()->notNull()->defaultValue(100)->comment('Целостность в % (100 - целое)'),

            // Даты (храним как Unix Timestamp, т.к. в ТЗ упомянут unixTimeStamp)
            'created_at' => $this->integer()->notNull()->comment('Дата появления'),
            'fall_at' => $this->integer()->defaultValue(null)->comment('Дата падения'),
            'deleted_at' => $this->integer()->defaultValue(null)->comment('Дата когда съели полностью (Soft delete)'),

            // Добавил updated_at, так как он был в вашем примере JSON ответа
            'updated_at' => $this->integer()->defaultValue(null),
        ]);

        // Индекс и внешний ключ для связи с пользователем
        $this->createIndex(
            '{{%idx-apples-user_id}}',
            '{{%apples}}',
            'user_id'
        );

        $this->addForeignKey(
            '{{%fk-apples-user_id}}',
            '{{%apples}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE', // Если удалить юзера, удалятся и яблоки
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Сначала удаляем внешний ключ, потом таблицу
        $this->dropForeignKey(
            '{{%fk-apples-user_id}}',
            '{{%apples}}'
        );

        $this->dropIndex(
            '{{%idx-apples-user_id}}',
            '{{%apples}}'
        );

        $this->dropTable('{{%apples}}');
    }
}
