<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Public instructor profiles + structured learner enquiries.
 */
class m260908_100000_public_profiles_enquiries extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%organisations}}', 'profile_slug', $this->string(96)->null()->unique());
        $this->addColumn('{{%organisations}}', 'profile_status', $this->string(16)->notNull()->defaultValue('draft'));
        $this->addColumn('{{%organisations}}', 'profile_acquisition_mode', $this->string(24)->notNull()->defaultValue('closed'));
        $this->addColumn('{{%organisations}}', 'profile_intro', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_photo_path', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_transmission', $this->string(16)->null());
        $this->addColumn('{{%organisations}}', 'profile_teaching_areas', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_languages', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_adi_status', $this->string(32)->null());
        $this->addColumn('{{%organisations}}', 'profile_years_teaching', $this->integer()->null());
        $this->addColumn('{{%organisations}}', 'profile_vehicle_summary', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_public_pricing', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_allow_waiting_list', $this->boolean()->notNull()->defaultValue(true));

        $this->createTable('{{%profile_slug_redirects}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'old_slug' => $this->string(96)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_profile_slug_redirects_old_slug',
            '{{%profile_slug_redirects}}',
            'old_slug',
            true,
        );
        $this->addForeignKey(
            'fk_profile_slug_redirects_org',
            '{{%profile_slug_redirects}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%enquiries}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->null(),
            'first_name' => $this->string(100)->notNull(),
            'last_name' => $this->string(100)->notNull(),
            'email' => $this->string(255)->null(),
            'mobile' => $this->string(32)->notNull(),
            'postcode' => $this->string(16)->notNull(),
            'transmission' => $this->string(16)->null(),
            'experience_band' => $this->string(64)->null(),
            'availability_json' => $this->text()->null(),
            'desired_start' => $this->string(32)->null(),
            'theory_status' => $this->string(32)->null(),
            'practical_test_date' => $this->date()->null(),
            'message' => $this->text()->null(),
            'source' => $this->string(32)->notNull()->defaultValue('profile'),
            'source_tag' => $this->string(32)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('new'),
            'decline_reason' => $this->text()->null(),
            'converted_learner_id' => $this->integer()->null(),
            'intake_id' => $this->integer()->null(),
            'contacted_at' => $this->dateTime()->null(),
            'reviewed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_enquiries_org_status', '{{%enquiries}}', ['organisation_id', 'status']);
        $this->createIndex('idx_enquiries_org_created', '{{%enquiries}}', ['organisation_id', 'created_at']);
        $this->addForeignKey(
            'fk_enquiries_org',
            '{{%enquiries}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_enquiries_learner',
            '{{%enquiries}}',
            'converted_learner_id',
            '{{%learners}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_enquiries_intake',
            '{{%enquiries}}',
            'intake_id',
            '{{%learner_intakes}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%enquiries}}');
        $this->dropTable('{{%profile_slug_redirects}}');
        $this->dropColumn('{{%organisations}}', 'profile_allow_waiting_list');
        $this->dropColumn('{{%organisations}}', 'profile_public_pricing');
        $this->dropColumn('{{%organisations}}', 'profile_vehicle_summary');
        $this->dropColumn('{{%organisations}}', 'profile_years_teaching');
        $this->dropColumn('{{%organisations}}', 'profile_adi_status');
        $this->dropColumn('{{%organisations}}', 'profile_languages');
        $this->dropColumn('{{%organisations}}', 'profile_teaching_areas');
        $this->dropColumn('{{%organisations}}', 'profile_transmission');
        $this->dropColumn('{{%organisations}}', 'profile_photo_path');
        $this->dropColumn('{{%organisations}}', 'profile_intro');
        $this->dropColumn('{{%organisations}}', 'profile_acquisition_mode');
        $this->dropColumn('{{%organisations}}', 'profile_status');
        $this->dropColumn('{{%organisations}}', 'profile_slug');
    }
}
