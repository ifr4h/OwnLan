<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Minimum organisation settings that feed real MVP workflows.
 */
class m260901_160000_organisation_mvp_settings extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%organisations}}',
            'contact_phone',
            $this->string(32)->null(),
        );
        $this->addColumn(
            '{{%organisations}}',
            'contact_email',
            $this->string(255)->null(),
        );
        $this->addColumn(
            '{{%organisations}}',
            'default_lesson_duration_minutes',
            $this->integer()->notNull()->defaultValue(60),
        );
        $this->addColumn(
            '{{%organisations}}',
            'service_area',
            $this->text()->null(),
        );
        $this->addColumn(
            '{{%organisations}}',
            'cancellation_policy',
            $this->text()->null(),
        );
        $this->addColumn(
            '{{%organisations}}',
            'work_days',
            $this->string(64)->notNull()->defaultValue('[1,2,3,4,5,6]'),
        );
        $this->addColumn(
            '{{%organisations}}',
            'work_start_time',
            $this->string(5)->notNull()->defaultValue('09:00'),
        );
        $this->addColumn(
            '{{%organisations}}',
            'work_end_time',
            $this->string(5)->notNull()->defaultValue('18:00'),
        );

        // Sensible default hourly rate for orgs that never set one (£35).
        $this->update(
            '{{%organisations}}',
            ['default_hourly_rate_pence' => 3500],
            ['default_hourly_rate_pence' => null],
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%organisations}}', 'work_end_time');
        $this->dropColumn('{{%organisations}}', 'work_start_time');
        $this->dropColumn('{{%organisations}}', 'work_days');
        $this->dropColumn('{{%organisations}}', 'cancellation_policy');
        $this->dropColumn('{{%organisations}}', 'service_area');
        $this->dropColumn('{{%organisations}}', 'default_lesson_duration_minutes');
        $this->dropColumn('{{%organisations}}', 'contact_email');
        $this->dropColumn('{{%organisations}}', 'contact_phone');
    }
}
