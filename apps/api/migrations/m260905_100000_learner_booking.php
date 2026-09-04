<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Learner self-booking: organisation rules + lesson booking requests.
 */
class m260905_100000_learner_booking extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%organisations}}',
            'booking_mode',
            $this->string(16)->notNull()->defaultValue('manual'),
        );
        $this->addColumn(
            '{{%organisations}}',
            'learner_reschedule_mode',
            $this->string(16)->notNull()->defaultValue('manual'),
        );
        $this->addColumn(
            '{{%organisations}}',
            'learner_can_cancel',
            $this->boolean()->notNull()->defaultValue(true),
        );
        $this->addColumn(
            '{{%organisations}}',
            'booking_minimum_notice_hours',
            $this->integer()->notNull()->defaultValue(12),
        );
        $this->addColumn(
            '{{%organisations}}',
            'booking_advance_weeks',
            $this->integer()->notNull()->defaultValue(4),
        );
        $this->addColumn(
            '{{%organisations}}',
            'booking_slot_increment_minutes',
            $this->integer()->notNull()->defaultValue(30),
        );
        $this->addColumn(
            '{{%organisations}}',
            'booking_allowed_durations',
            $this->string(128)->null(),
        );

        $this->createTable('{{%lesson_booking_requests}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'type' => $this->string(16)->notNull()->defaultValue('book'),
            'original_lesson_id' => $this->integer()->null(),
            'requested_starts_at' => $this->dateTime()->notNull(),
            'duration_minutes' => $this->integer()->notNull(),
            'pickup_address' => $this->text()->null(),
            'status' => $this->string(24)->notNull()->defaultValue('pending'),
            'suggested_starts_at' => $this->dateTime()->null(),
            'decline_reason' => $this->text()->null(),
            'lesson_id' => $this->integer()->null(),
            'client_mutation_id' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'responded_at' => $this->dateTime()->null(),
            'expires_at' => $this->dateTime()->null(),
        ]);

        $this->createIndex(
            'idx_lesson_booking_requests_org_status',
            '{{%lesson_booking_requests}}',
            ['organisation_id', 'status'],
        );
        $this->createIndex(
            'idx_lesson_booking_requests_learner',
            '{{%lesson_booking_requests}}',
            ['learner_id', 'status'],
        );
        $this->createIndex(
            'idx_lesson_booking_requests_starts',
            '{{%lesson_booking_requests}}',
            'requested_starts_at',
        );
        $this->createIndex(
            'idx_lesson_booking_requests_mutation',
            '{{%lesson_booking_requests}}',
            ['organisation_id', 'client_mutation_id'],
            true,
        );

        $this->addForeignKey(
            'fk_lesson_booking_requests_org',
            '{{%lesson_booking_requests}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_booking_requests_instructor',
            '{{%lesson_booking_requests}}',
            'instructor_id',
            '{{%instructors}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_booking_requests_learner',
            '{{%lesson_booking_requests}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_booking_requests_lesson',
            '{{%lesson_booking_requests}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_booking_requests_original_lesson',
            '{{%lesson_booking_requests}}',
            'original_lesson_id',
            '{{%lessons}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%lesson_booking_requests}}');
        $this->dropColumn('{{%organisations}}', 'booking_allowed_durations');
        $this->dropColumn('{{%organisations}}', 'booking_slot_increment_minutes');
        $this->dropColumn('{{%organisations}}', 'booking_advance_weeks');
        $this->dropColumn('{{%organisations}}', 'booking_minimum_notice_hours');
        $this->dropColumn('{{%organisations}}', 'learner_can_cancel');
        $this->dropColumn('{{%organisations}}', 'learner_reschedule_mode');
        $this->dropColumn('{{%organisations}}', 'booking_mode');
    }
}
