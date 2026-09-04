<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Mock test studio + learner skill self-assessment (Progress 2.0).
 */
class m260904_100000_mock_tests extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%mock_tests}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('in_progress'),
            'result' => $this->string(24)->null(),
            'started_at' => $this->dateTime()->notNull(),
            'finished_at' => $this->dateTime()->null(),
            'driving_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'serious_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'dangerous_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'learner_summary' => $this->text()->null(),
            'instructor_note' => $this->text()->null(),
            'private_note' => $this->text()->null(),
            'suggested_next_focus' => $this->string(500)->null(),
            'client_session_id' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_mock_tests_org_learner', '{{%mock_tests}}', ['organisation_id', 'learner_id']);
        $this->createIndex('ix_mock_tests_lesson', '{{%mock_tests}}', 'lesson_id');
        $this->createIndex(
            'ux_mock_tests_client_session',
            '{{%mock_tests}}',
            ['organisation_id', 'client_session_id'],
            true,
        );
        $this->addForeignKey(
            'fk_mock_tests_org',
            '{{%mock_tests}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_mock_tests_learner',
            '{{%mock_tests}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_mock_tests_lesson',
            '{{%mock_tests}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%mock_test_faults}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'mock_test_id' => $this->integer()->notNull(),
            'skill_id' => $this->integer()->notNull(),
            'fault_type' => $this->string(16)->notNull(),
            'fault_code' => $this->string(64)->notNull(),
            'fault_label' => $this->string(160)->notNull(),
            'note' => $this->text()->null(),
            'recorded_at' => $this->dateTime()->notNull(),
            'elapsed_seconds' => $this->integer()->null(),
            'route_moment_id' => $this->integer()->null(),
            'client_op_id' => $this->string(64)->null(),
            'undone_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_mock_faults_mock', '{{%mock_test_faults}}', 'mock_test_id');
        $this->createIndex(
            'ux_mock_faults_client_op',
            '{{%mock_test_faults}}',
            ['mock_test_id', 'client_op_id'],
            true,
        );
        $this->addForeignKey(
            'fk_mock_faults_mock',
            '{{%mock_test_faults}}',
            'mock_test_id',
            '{{%mock_tests}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_mock_faults_skill',
            '{{%mock_test_faults}}',
            'skill_id',
            '{{%progress_skills}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );

        $this->createTable('{{%learner_skill_self_assessments}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'skill_id' => $this->integer()->notNull(),
            'confidence' => $this->string(32)->notNull(),
            'note' => $this->text()->null(),
            'recorded_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ix_learner_self_assessment',
            '{{%learner_skill_self_assessments}}',
            ['learner_id', 'skill_id', 'recorded_at'],
        );
        $this->addForeignKey(
            'fk_self_assessment_learner',
            '{{%learner_skill_self_assessments}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_self_assessment_skill',
            '{{%learner_skill_self_assessments}}',
            'skill_id',
            '{{%progress_skills}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learner_skill_self_assessments}}');
        $this->dropTable('{{%mock_test_faults}}');
        $this->dropTable('{{%mock_tests}}');
    }
}
