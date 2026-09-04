<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Accounts 2.0 — vehicles, mileage, financial goals, expense receipts.
 */
class m260906_100000_accounts_2 extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%vehicles}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'registration' => $this->string(16)->notNull(),
            'make' => $this->string(64)->null(),
            'model' => $this->string(64)->null(),
            'transmission' => $this->string(16)->notNull()->defaultValue('manual'),
            'is_primary' => $this->boolean()->notNull()->defaultValue(false),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'notes' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_vehicles_org', '{{%vehicles}}', ['organisation_id', 'is_active']);
        $this->addForeignKey(
            'fk_vehicles_organisation',
            '{{%vehicles}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%mileage_logs}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'vehicle_id' => $this->integer()->notNull(),
            'logged_on' => $this->date()->notNull(),
            'distance_miles' => $this->decimal(8, 1)->notNull(),
            'purpose' => $this->string(32)->notNull()->defaultValue('business'),
            'start_reading' => $this->integer()->null(),
            'end_reading' => $this->integer()->null(),
            'notes' => $this->text()->null(),
            'created_by_user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_mileage_org_date', '{{%mileage_logs}}', ['organisation_id', 'logged_on']);
        $this->addForeignKey(
            'fk_mileage_organisation',
            '{{%mileage_logs}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_mileage_vehicle',
            '{{%mileage_logs}}',
            'vehicle_id',
            '{{%vehicles}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_mileage_user',
            '{{%mileage_logs}}',
            'created_by_user_id',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%financial_goals}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'period_year' => $this->smallInteger()->notNull(),
            'period_month' => $this->smallInteger()->notNull(),
            'target_pence' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_financial_goals_org_period',
            '{{%financial_goals}}',
            ['organisation_id', 'period_year', 'period_month'],
            true,
        );
        $this->addForeignKey(
            'fk_financial_goals_organisation',
            '{{%financial_goals}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->addColumn('{{%expenses}}', 'supplier', $this->string(120)->null()->after('category'));
        $this->addColumn('{{%expenses}}', 'payment_method', $this->string(32)->null()->after('supplier'));
        $this->addColumn('{{%expenses}}', 'vehicle_id', $this->integer()->null()->after('payment_method'));
        $this->addColumn('{{%expenses}}', 'receipt_path', $this->string(255)->null()->after('vehicle_id'));
        $this->addColumn('{{%expenses}}', 'receipt_original_name', $this->string(255)->null()->after('receipt_path'));
        $this->addForeignKey(
            'fk_expenses_vehicle',
            '{{%expenses}}',
            'vehicle_id',
            '{{%vehicles}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        // Legacy category "car" → vehicle_repairs
        $this->update('{{%expenses}}', ['category' => 'vehicle_repairs'], ['category' => 'car']);
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_expenses_vehicle', '{{%expenses}}');
        $this->dropColumn('{{%expenses}}', 'receipt_original_name');
        $this->dropColumn('{{%expenses}}', 'receipt_path');
        $this->dropColumn('{{%expenses}}', 'vehicle_id');
        $this->dropColumn('{{%expenses}}', 'payment_method');
        $this->dropColumn('{{%expenses}}', 'supplier');

        $this->dropTable('{{%financial_goals}}');
        $this->dropTable('{{%mileage_logs}}');
        $this->dropTable('{{%vehicles}}');
    }
}
