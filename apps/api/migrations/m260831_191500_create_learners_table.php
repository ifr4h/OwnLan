<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Organisation-scoped pupil (learner) records.
 */
class m260831_191500_create_learners_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learners}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'first_name' => $this->string(100)->notNull(),
            'last_name' => $this->string(100)->notNull(),
            'mobile' => $this->string(32)->notNull(),
            'email' => $this->string(255)->null(),
            'default_pickup_address' => $this->text()->null(),
            'test_date' => $this->date()->null(),
            'test_centre' => $this->string(255)->null(),
            'private_notes' => $this->text()->null(),
            'archived_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ix_learners_organisation_id', '{{%learners}}', 'organisation_id');
        $this->createIndex(
            'ix_learners_org_active_name',
            '{{%learners}}',
            ['organisation_id', 'archived_at', 'last_name', 'first_name'],
        );
        $this->addForeignKey(
            'fk_learners_organisation',
            '{{%learners}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learners}}');
    }
}
