<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Password reset tokens + portal invite delivery metadata.
 */
class m260903_100000_auth_recovery extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%password_reset_tokens}}', [
            'id' => $this->primaryKey(),
            'account_type' => $this->string(16)->notNull(),
            'account_id' => $this->integer()->notNull(),
            'token_hash' => $this->string(64)->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'used_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_password_reset_tokens_hash',
            '{{%password_reset_tokens}}',
            'token_hash',
            true,
        );
        $this->createIndex(
            'ix_password_reset_tokens_account',
            '{{%password_reset_tokens}}',
            ['account_type', 'account_id'],
        );

        $this->addColumn(
            '{{%learner_portal_accounts}}',
            'invite_sent_at',
            $this->dateTime()->null()->after('invite_expires_at'),
        );
        $this->addColumn(
            '{{%learner_portal_accounts}}',
            'last_invite_at',
            $this->dateTime()->null()->after('invite_sent_at'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%learner_portal_accounts}}', 'last_invite_at');
        $this->dropColumn('{{%learner_portal_accounts}}', 'invite_sent_at');
        $this->dropTable('{{%password_reset_tokens}}');
    }
}
