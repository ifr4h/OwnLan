<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Instructor teaching notes against a skill or skill category for a learner.
 */
class m260917_100000_learner_progress_notes extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learner_progress_notes}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'note_key' => $this->string(96)->notNull(),
            'skill_id' => $this->integer()->null(),
            'category_code' => $this->string(64)->null(),
            'body' => $this->text()->notNull(),
            'learner_visible' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_lpn_key',
            '{{%learner_progress_notes}}',
            ['organisation_id', 'learner_id', 'note_key'],
            true,
        );
        $this->createIndex(
            'ix_lpn_learner',
            '{{%learner_progress_notes}}',
            ['organisation_id', 'learner_id'],
        );

        $this->addForeignKey(
            'fk_lpn_organisation',
            '{{%learner_progress_notes}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lpn_learner',
            '{{%learner_progress_notes}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lpn_skill',
            '{{%learner_progress_notes}}',
            'skill_id',
            '{{%progress_skills}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learner_progress_notes}}');
    }
}
