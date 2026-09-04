<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Payments 2.0 — Stripe Connect, checkouts, booking holds, package offerings.
 */
class m260910_100000_payments_2_stripe extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%organisations}}', 'booking_payment_policy', $this->string(32)->notNull()->defaultValue('none'));

        $this->createTable('{{%instructor_payment_accounts}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->null(),
            'provider' => $this->string(32)->notNull()->defaultValue('stripe'),
            'provider_account_id' => $this->string(64)->null(),
            'status' => $this->string(32)->notNull()->defaultValue('not_started'),
            'charges_enabled' => $this->boolean()->notNull()->defaultValue(false),
            'payouts_enabled' => $this->boolean()->notNull()->defaultValue(false),
            'details_submitted' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_payment_account_org', '{{%instructor_payment_accounts}}', 'organisation_id', true);
        $this->addForeignKey(
            'fk_payment_account_org',
            '{{%instructor_payment_accounts}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->addColumn('{{%payments}}', 'provider', $this->string(32)->null());
        $this->addColumn('{{%payments}}', 'provider_payment_id', $this->string(128)->null());
        $this->addColumn('{{%payments}}', 'provider_charge_id', $this->string(128)->null());
        $this->addColumn('{{%payments}}', 'provider_status', $this->string(32)->null());
        $this->addColumn('{{%payments}}', 'payment_status', $this->string(32)->notNull()->defaultValue('succeeded'));
        $this->addColumn('{{%payments}}', 'platform_fee_pence', $this->integer()->null());
        $this->addColumn('{{%payments}}', 'processor_fee_pence', $this->integer()->null());
        $this->addColumn('{{%payments}}', 'net_pence', $this->integer()->null());
        $this->addColumn('{{%payments}}', 'refund_provider_id', $this->string(128)->null());
        $this->addColumn('{{%payments}}', 'refunded_at', $this->dateTime()->null());
        $this->addColumn('{{%payments}}', 'idempotency_key', $this->string(128)->null());
        $this->createIndex('idx_payments_idempotency', '{{%payments}}', 'idempotency_key', true);
        $this->createIndex('idx_payments_provider_payment', '{{%payments}}', 'provider_payment_id');

        $this->createTable('{{%package_offerings}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'label' => $this->string(120)->notNull(),
            'purchased_minutes' => $this->integer()->notNull(),
            'price_pence' => $this->integer()->notNull(),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'portal_visible' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(
            'fk_package_offering_org',
            '{{%package_offerings}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%payment_checkouts}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'token' => $this->string(64)->notNull(),
            'purpose' => $this->string(32)->notNull(),
            'amount_pence' => $this->integer()->notNull(),
            'currency' => $this->string(3)->notNull()->defaultValue('gbp'),
            'lesson_charge_id' => $this->integer()->null(),
            'package_offering_id' => $this->integer()->null(),
            'booking_hold_id' => $this->integer()->null(),
            'payment_id' => $this->integer()->null(),
            'provider_payment_intent_id' => $this->string(128)->null(),
            'provider_checkout_session_id' => $this->string(128)->null(),
            'status' => $this->string(32)->notNull()->defaultValue('created'),
            'idempotency_key' => $this->string(128)->notNull(),
            'expires_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_payment_checkout_token', '{{%payment_checkouts}}', 'token', true);
        $this->createIndex('idx_payment_checkout_idempotency', '{{%payment_checkouts}}', 'idempotency_key', true);
        $this->addForeignKey('fk_payment_checkout_org', '{{%payment_checkouts}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_payment_checkout_learner', '{{%payment_checkouts}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%booking_holds}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'starts_at' => $this->dateTime()->notNull(),
            'duration_minutes' => $this->integer()->notNull(),
            'price_pence' => $this->integer()->notNull(),
            'pickup_address' => $this->string(255)->null(),
            'payment_checkout_id' => $this->integer()->null(),
            'lesson_id' => $this->integer()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'expires_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_booking_hold_org_expires', '{{%booking_holds}}', ['organisation_id', 'expires_at']);
        $this->addForeignKey('fk_booking_hold_org', '{{%booking_holds}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%stripe_webhook_events}}', [
            'id' => $this->primaryKey(),
            'event_id' => $this->string(128)->notNull(),
            'event_type' => $this->string(64)->notNull(),
            'processed_at' => $this->dateTime()->notNull(),
            'payment_checkout_id' => $this->integer()->null(),
        ]);
        $this->createIndex('idx_stripe_webhook_event_id', '{{%stripe_webhook_events}}', 'event_id', true);

        $this->createTable('{{%payment_audit_log}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'payment_id' => $this->integer()->null(),
            'checkout_id' => $this->integer()->null(),
            'action' => $this->string(64)->notNull(),
            'actor_user_id' => $this->integer()->null(),
            'meta_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_payment_audit_org', '{{%payment_audit_log}}', ['organisation_id', 'created_at']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%payment_audit_log}}');
        $this->dropTable('{{%stripe_webhook_events}}');
        $this->dropTable('{{%booking_holds}}');
        $this->dropTable('{{%payment_checkouts}}');
        $this->dropTable('{{%package_offerings}}');
        $this->dropColumn('{{%payments}}', 'idempotency_key');
        $this->dropColumn('{{%payments}}', 'refunded_at');
        $this->dropColumn('{{%payments}}', 'refund_provider_id');
        $this->dropColumn('{{%payments}}', 'net_pence');
        $this->dropColumn('{{%payments}}', 'processor_fee_pence');
        $this->dropColumn('{{%payments}}', 'platform_fee_pence');
        $this->dropColumn('{{%payments}}', 'payment_status');
        $this->dropColumn('{{%payments}}', 'provider_status');
        $this->dropColumn('{{%payments}}', 'provider_charge_id');
        $this->dropColumn('{{%payments}}', 'provider_payment_id');
        $this->dropColumn('{{%payments}}', 'provider');
        $this->dropTable('{{%instructor_payment_accounts}}');
        $this->dropColumn('{{%organisations}}', 'booking_payment_policy');
    }
}
