<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Instructor business spending — separate from pupil payments and lesson charges.
 */
class m260901_120000_create_expenses_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%expenses}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'amount_pence' => $this->integer()->notNull(),
            'category' => $this->string(32)->notNull(),
            'spent_on' => $this->date()->notNull(),
            'notes' => $this->text()->null(),
            'voided_at' => $this->dateTime()->null(),
            'void_reason' => $this->text()->null(),
            'created_by_user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ix_expenses_org_spent', '{{%expenses}}', ['organisation_id', 'spent_on']);
        $this->createIndex('ix_expenses_org_category', '{{%expenses}}', ['organisation_id', 'category']);

        $this->addForeignKey(
            'fk_expenses_organisation',
            '{{%expenses}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_expenses_user',
            '{{%expenses}}',
            'created_by_user_id',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%expenses}}');
    }
}
