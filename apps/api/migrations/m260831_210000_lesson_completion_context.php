<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Lesson completion teaching fields + pupil next-focus context.
 * Structured DVSA skill ratings remain deferred until an official syllabus is approved.
 */
class m260831_210000_lesson_completion_context extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%lessons}}', 'instructor_notes', $this->text()->null());
        $this->addColumn('{{%lessons}}', 'learner_summary', $this->text()->null());
        $this->addColumn('{{%lessons}}', 'next_focus', $this->text()->null());
        $this->addColumn('{{%lessons}}', 'client_mutation_id', $this->string(64)->null());

        $this->createIndex(
            'ux_lessons_org_client_mutation',
            '{{%lessons}}',
            ['organisation_id', 'client_mutation_id'],
            true,
        );

        $this->addColumn('{{%learners}}', 'next_focus', $this->text()->null());
        $this->addColumn('{{%learners}}', 'last_lesson_summary', $this->text()->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%learners}}', 'last_lesson_summary');
        $this->dropColumn('{{%learners}}', 'next_focus');
        $this->dropIndex('ux_lessons_org_client_mutation', '{{%lessons}}');
        $this->dropColumn('{{%lessons}}', 'client_mutation_id');
        $this->dropColumn('{{%lessons}}', 'next_focus');
        $this->dropColumn('{{%lessons}}', 'learner_summary');
        $this->dropColumn('{{%lessons}}', 'instructor_notes');
    }
}
