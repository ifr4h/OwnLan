<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Official DVSA practical test results with structured fault ticks for rolling trends.
 */
class m260915_100000_practical_tests extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%practical_tests}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'test_date' => $this->date()->notNull(),
            'result' => $this->string(16)->notNull(),
            'test_centre' => $this->string(255)->null(),
            'accompanied' => $this->boolean()->notNull()->defaultValue(true),
            'examiner_action' => $this->boolean()->notNull()->defaultValue(false),
            'driving_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'serious_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'dangerous_faults_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'instructor_notes' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_practical_tests_org_date', '{{%practical_tests}}', ['organisation_id', 'test_date']);
        $this->createIndex('ix_practical_tests_org_learner', '{{%practical_tests}}', ['organisation_id', 'learner_id']);
        $this->addForeignKey(
            'fk_practical_tests_org',
            '{{%practical_tests}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_practical_tests_learner',
            '{{%practical_tests}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_practical_tests_instructor',
            '{{%practical_tests}}',
            'instructor_id',
            '{{%instructors}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%practical_test_faults}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'practical_test_id' => $this->integer()->notNull(),
            'fault_type' => $this->string(16)->notNull(),
            'fault_code' => $this->string(64)->notNull(),
            'fault_label' => $this->string(160)->notNull(),
            'area' => $this->string(64)->notNull(),
            'aspect' => $this->string(64)->notNull(),
            'skill_code' => $this->string(64)->null(),
            'count' => $this->smallInteger()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_practical_faults_test', '{{%practical_test_faults}}', 'practical_test_id');
        $this->createIndex(
            'ux_practical_faults_code_type',
            '{{%practical_test_faults}}',
            ['practical_test_id', 'fault_code', 'fault_type'],
            true,
        );
        $this->addForeignKey(
            'fk_practical_faults_test',
            '{{%practical_test_faults}}',
            'practical_test_id',
            '{{%practical_tests}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_practical_faults_org',
            '{{%practical_test_faults}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%practical_test_faults}}');
        $this->dropTable('{{%practical_tests}}');
    }
}
