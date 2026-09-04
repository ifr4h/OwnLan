<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Learner portal accounts — separate from instructor User/Membership.
 * Operational Learner records can exist before portal signup.
 */
class m260901_140000_create_learner_portal_accounts extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learner_portal_accounts}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'email' => $this->string(255)->notNull(),
            'password_hash' => $this->string(255)->null(),
            'auth_key' => $this->string(32)->notNull(),
            'invite_token_hash' => $this->string(64)->null(),
            'invite_expires_at' => $this->dateTime()->null(),
            'activated_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ux_portal_accounts_learner', '{{%learner_portal_accounts}}', 'learner_id', true);
        $this->createIndex('ux_portal_accounts_email', '{{%learner_portal_accounts}}', 'email', true);
        $this->createIndex('ix_portal_accounts_org', '{{%learner_portal_accounts}}', 'organisation_id');

        $this->addForeignKey(
            'fk_portal_accounts_organisation',
            '{{%learner_portal_accounts}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_portal_accounts_learner',
            '{{%learner_portal_accounts}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learner_portal_accounts}}');
    }
}
