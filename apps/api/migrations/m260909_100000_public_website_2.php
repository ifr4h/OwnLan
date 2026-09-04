<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Public website 2.0 — branding, services, FAQ, contact, analytics.
 */
class m260909_100000_public_website_2 extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%organisations}}', 'profile_business_name', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_accent_colour', $this->string(7)->null());
        $this->addColumn('{{%organisations}}', 'profile_cover_path', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_teaching_styles', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_services', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_faqs', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_social_links', $this->text()->null());
        $this->addColumn('{{%organisations}}', 'profile_contact_phone', $this->string(32)->null());
        $this->addColumn('{{%organisations}}', 'profile_contact_email', $this->string(255)->null());
        $this->addColumn('{{%organisations}}', 'profile_whatsapp', $this->string(32)->null());
        $this->addColumn('{{%organisations}}', 'profile_show_phone', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%organisations}}', 'profile_show_email', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%organisations}}', 'profile_dual_controls', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%organisations}}', 'profile_allow_indexing', $this->boolean()->notNull()->defaultValue(true));

        $this->addColumn('{{%enquiries}}', 'service_interest', $this->string(120)->null());

        $this->createTable('{{%profile_analytics_events}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'event_type' => $this->string(32)->notNull(),
            'source_tag' => $this->string(32)->null(),
            'session_hash' => $this->string(64)->null(),
            'occurred_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_profile_analytics_org_occurred',
            '{{%profile_analytics_events}}',
            ['organisation_id', 'occurred_at'],
        );
        $this->addForeignKey(
            'fk_profile_analytics_org',
            '{{%profile_analytics_events}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%profile_analytics_events}}');
        $this->dropColumn('{{%enquiries}}', 'service_interest');
        $this->dropColumn('{{%organisations}}', 'profile_allow_indexing');
        $this->dropColumn('{{%organisations}}', 'profile_dual_controls');
        $this->dropColumn('{{%organisations}}', 'profile_show_email');
        $this->dropColumn('{{%organisations}}', 'profile_show_phone');
        $this->dropColumn('{{%organisations}}', 'profile_whatsapp');
        $this->dropColumn('{{%organisations}}', 'profile_contact_email');
        $this->dropColumn('{{%organisations}}', 'profile_contact_phone');
        $this->dropColumn('{{%organisations}}', 'profile_social_links');
        $this->dropColumn('{{%organisations}}', 'profile_faqs');
        $this->dropColumn('{{%organisations}}', 'profile_services');
        $this->dropColumn('{{%organisations}}', 'profile_teaching_styles');
        $this->dropColumn('{{%organisations}}', 'profile_cover_path');
        $this->dropColumn('{{%organisations}}', 'profile_accent_colour');
        $this->dropColumn('{{%organisations}}', 'profile_business_name');
    }
}
