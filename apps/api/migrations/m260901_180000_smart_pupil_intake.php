<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Smart Pupil Intake — invites/submissions + waiting-list / learner-reported fields.
 */
class m260901_180000_smart_pupil_intake extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learner_intakes}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'created_by_instructor_id' => $this->integer()->null(),
            'invite_token_hash' => $this->string(64)->notNull(),
            'invite_expires_at' => $this->dateTime()->notNull(),
            'revoked_at' => $this->dateTime()->null(),
            'prefill_first_name' => $this->string(100)->null(),
            'prefill_last_name' => $this->string(100)->null(),
            'prefill_mobile' => $this->string(32)->null(),
            'prefill_email' => $this->string(255)->null(),
            'status' => $this->string(32)->notNull()->defaultValue('open'),
            'answers_json' => $this->text()->null(),
            'submitted_at' => $this->dateTime()->null(),
            'terms_acknowledged_at' => $this->dateTime()->null(),
            'learner_id' => $this->integer()->null(),
            'reviewed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_learner_intakes_token', '{{%learner_intakes}}', 'invite_token_hash', true);
        $this->createIndex('ix_learner_intakes_org_status', '{{%learner_intakes}}', ['organisation_id', 'status']);
        $this->addForeignKey(
            'fk_learner_intakes_organisation',
            '{{%learner_intakes}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_learner_intakes_learner',
            '{{%learner_intakes}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->addColumn('{{%learners}}', 'lifecycle', $this->string(32)->notNull()->defaultValue('active'));
        $this->addColumn('{{%learners}}', 'waiting_list_joined_at', $this->dateTime()->null());
        $this->addColumn('{{%learners}}', 'transmission', $this->string(16)->null());
        $this->addColumn('{{%learners}}', 'theory_status', $this->string(16)->null());
        $this->addColumn('{{%learners}}', 'theory_pass_date', $this->date()->null());
        $this->addColumn('{{%learners}}', 'practical_test_time', $this->string(5)->null());
        $this->addColumn('{{%learners}}', 'availability_is_variable', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%learners}}', 'preferred_contact', $this->string(16)->null());
        $this->addColumn('{{%learners}}', 'intake_id', $this->integer()->null());
        $this->addColumn('{{%learners}}', 'learner_reported_json', $this->text()->null());
        $this->addColumn('{{%learners}}', 'terms_acknowledged_at', $this->dateTime()->null());

        $this->createIndex('ix_learners_org_lifecycle', '{{%learners}}', ['organisation_id', 'lifecycle', 'archived_at']);
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%learners}}', 'terms_acknowledged_at');
        $this->dropColumn('{{%learners}}', 'learner_reported_json');
        $this->dropColumn('{{%learners}}', 'intake_id');
        $this->dropColumn('{{%learners}}', 'preferred_contact');
        $this->dropColumn('{{%learners}}', 'availability_is_variable');
        $this->dropColumn('{{%learners}}', 'practical_test_time');
        $this->dropColumn('{{%learners}}', 'theory_pass_date');
        $this->dropColumn('{{%learners}}', 'theory_status');
        $this->dropColumn('{{%learners}}', 'transmission');
        $this->dropColumn('{{%learners}}', 'waiting_list_joined_at');
        $this->dropColumn('{{%learners}}', 'lifecycle');
        $this->dropTable('{{%learner_intakes}}');
    }
}
