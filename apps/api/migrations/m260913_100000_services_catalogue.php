<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Services catalogue: sellable services, scheduled price changes,
 * org pricing rules, pupil-specific rates, lesson.service_id.
 */
class m260913_100000_services_catalogue extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%organisation_services}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'name' => $this->string(120)->notNull(),
            'kind' => $this->string(32)->notNull()->defaultValue('lesson'),
            'description' => $this->text()->null(),
            'duration_minutes' => $this->integer()->notNull(),
            'price_pence' => $this->integer()->notNull(),
            'status' => $this->string(16)->notNull()->defaultValue('active'),
            'visibility_public' => $this->boolean()->notNull()->defaultValue(true),
            'booking_access' => $this->string(32)->notNull()->defaultValue('instructor_only'),
            'is_default' => $this->boolean()->notNull()->defaultValue(false),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_org_services_org', '{{%organisation_services}}', ['organisation_id', 'status', 'sort_order']);
        $this->addForeignKey(
            'fk_org_services_org',
            '{{%organisation_services}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%service_price_changes}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'service_id' => $this->integer()->notNull(),
            'price_pence' => $this->integer()->notNull(),
            'effective_on' => $this->date()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_service_price_changes_lookup',
            '{{%service_price_changes}}',
            ['service_id', 'effective_on'],
        );
        $this->addForeignKey(
            'fk_service_price_changes_org',
            '{{%service_price_changes}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_service_price_changes_service',
            '{{%service_price_changes}}',
            'service_id',
            '{{%organisation_services}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%organisation_pricing_rules}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'label' => $this->string(120)->notNull(),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'days_of_week' => $this->string(32)->notNull()->defaultValue(''),
            'time_after' => $this->string(5)->null(),
            'time_before' => $this->string(5)->null(),
            'adjustment_kind' => $this->string(16)->notNull(),
            'adjustment_value' => $this->integer()->notNull(),
            'service_id' => $this->integer()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_pricing_rules_org', '{{%organisation_pricing_rules}}', ['organisation_id', 'active']);
        $this->addForeignKey(
            'fk_pricing_rules_org',
            '{{%organisation_pricing_rules}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_pricing_rules_service',
            '{{%organisation_pricing_rules}}',
            'service_id',
            '{{%organisation_services}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%learner_service_rates}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'service_id' => $this->integer()->null(),
            'price_pence' => $this->integer()->notNull(),
            'effective_from' => $this->date()->notNull(),
            'effective_to' => $this->date()->null(),
            'note' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_learner_service_rates_lookup',
            '{{%learner_service_rates}}',
            ['learner_id', 'service_id', 'effective_from'],
        );
        $this->addForeignKey(
            'fk_learner_service_rates_org',
            '{{%learner_service_rates}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_learner_service_rates_learner',
            '{{%learner_service_rates}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_learner_service_rates_service',
            '{{%learner_service_rates}}',
            'service_id',
            '{{%organisation_services}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->addColumn('{{%lessons}}', 'service_id', $this->integer()->null());
        $this->createIndex('idx_lessons_service', '{{%lessons}}', ['service_id']);
        $this->addForeignKey(
            'fk_lessons_service',
            '{{%lessons}}',
            'service_id',
            '{{%organisation_services}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_lessons_service', '{{%lessons}}');
        $this->dropIndex('idx_lessons_service', '{{%lessons}}');
        $this->dropColumn('{{%lessons}}', 'service_id');

        $this->dropTable('{{%learner_service_rates}}');
        $this->dropTable('{{%organisation_pricing_rules}}');
        $this->dropTable('{{%service_price_changes}}');
        $this->dropTable('{{%organisation_services}}');
    }
}
