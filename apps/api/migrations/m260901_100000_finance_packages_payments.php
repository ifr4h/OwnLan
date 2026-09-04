<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Packages, payments, lesson charges and credit usage.
 *
 * Keeps distinct: payment received, package purchased, lesson completed,
 * package credit consumed, and (later) accounting income.
 * All money columns are integer pence — never floating point.
 */
class m260901_100000_finance_packages_payments extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%organisations}}',
            'default_hourly_rate_pence',
            $this->integer()->null(),
        );

        $this->addColumn('{{%lessons}}', 'price_pence', $this->integer()->null());
        $this->addColumn(
            '{{%lessons}}',
            'settlement',
            $this->string(32)->null(),
        );

        $this->createTable('{{%learner_packages}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'label' => $this->string(120)->null(),
            'purchased_minutes' => $this->integer()->notNull(),
            'remaining_minutes' => $this->integer()->notNull(),
            'price_pence' => $this->integer()->notNull(),
            'payment_id' => $this->integer()->null(),
            'purchased_at' => $this->dateTime()->notNull(),
            'notes' => $this->text()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'voided_at' => $this->dateTime()->null(),
            'void_reason' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_packages_org_learner', '{{%learner_packages}}', ['organisation_id', 'learner_id']);
        $this->createIndex('ix_packages_org_status', '{{%learner_packages}}', ['organisation_id', 'status']);
        $this->addForeignKey(
            'fk_packages_organisation',
            '{{%learner_packages}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_packages_learner',
            '{{%learner_packages}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );

        $this->createTable('{{%payments}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'amount_pence' => $this->integer()->notNull(),
            'method' => $this->string(32)->notNull(),
            'purpose' => $this->string(32)->notNull(),
            'recorded_at' => $this->dateTime()->notNull(),
            'notes' => $this->text()->null(),
            'lesson_id' => $this->integer()->null(),
            'package_id' => $this->integer()->null(),
            // Distinct from operational receipt: instructor may later exclude from books.
            'counts_as_income' => $this->boolean()->notNull()->defaultValue(true),
            'created_by_user_id' => $this->integer()->null(),
            'voided_at' => $this->dateTime()->null(),
            'void_reason' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_payments_org_learner', '{{%payments}}', ['organisation_id', 'learner_id']);
        $this->createIndex('ix_payments_org_recorded', '{{%payments}}', ['organisation_id', 'recorded_at']);
        $this->addForeignKey(
            'fk_payments_organisation',
            '{{%payments}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payments_learner',
            '{{%payments}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payments_lesson',
            '{{%payments}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payments_package',
            '{{%payments}}',
            'package_id',
            '{{%learner_packages}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payments_user',
            '{{%payments}}',
            'created_by_user_id',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->addForeignKey(
            'fk_packages_payment',
            '{{%learner_packages}}',
            'payment_id',
            '{{%payments}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%lesson_charges}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'amount_pence' => $this->integer()->notNull(),
            'amount_paid_pence' => $this->integer()->notNull()->defaultValue(0),
            'status' => $this->string(32)->notNull()->defaultValue('outstanding'),
            'voided_at' => $this->dateTime()->null(),
            'void_reason' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_lesson_charges_lesson', '{{%lesson_charges}}', 'lesson_id', true);
        $this->createIndex('ix_lesson_charges_org_learner', '{{%lesson_charges}}', ['organisation_id', 'learner_id']);
        $this->addForeignKey(
            'fk_lesson_charges_organisation',
            '{{%lesson_charges}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_charges_learner',
            '{{%lesson_charges}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_charges_lesson',
            '{{%lesson_charges}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%payment_allocations}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'payment_id' => $this->integer()->notNull(),
            'lesson_charge_id' => $this->integer()->notNull(),
            'amount_pence' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_payment_allocations_payment', '{{%payment_allocations}}', 'payment_id');
        $this->addForeignKey(
            'fk_payment_allocations_organisation',
            '{{%payment_allocations}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payment_allocations_payment',
            '{{%payment_allocations}}',
            'payment_id',
            '{{%payments}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_payment_allocations_charge',
            '{{%payment_allocations}}',
            'lesson_charge_id',
            '{{%lesson_charges}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%package_credit_usages}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'package_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'minutes' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'voided_at' => $this->dateTime()->null(),
            'void_reason' => $this->text()->null(),
        ]);
        $this->createIndex('ux_package_credit_usages_lesson', '{{%package_credit_usages}}', 'lesson_id', true);
        $this->createIndex(
            'ix_package_credit_usages_package',
            '{{%package_credit_usages}}',
            ['package_id', 'created_at'],
        );
        $this->addForeignKey(
            'fk_package_credit_usages_organisation',
            '{{%package_credit_usages}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_package_credit_usages_learner',
            '{{%package_credit_usages}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_package_credit_usages_package',
            '{{%package_credit_usages}}',
            'package_id',
            '{{%learner_packages}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_package_credit_usages_lesson',
            '{{%package_credit_usages}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%package_credit_usages}}');
        $this->dropTable('{{%payment_allocations}}');
        $this->dropTable('{{%lesson_charges}}');
        $this->dropForeignKey('fk_packages_payment', '{{%learner_packages}}');
        $this->dropTable('{{%payments}}');
        $this->dropTable('{{%learner_packages}}');
        $this->dropColumn('{{%lessons}}', 'settlement');
        $this->dropColumn('{{%lessons}}', 'price_pence');
        $this->dropColumn('{{%organisations}}', 'default_hourly_rate_pence');
    }
}
