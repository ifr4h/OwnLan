<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Core identity and tenancy tables for the instructor daily loop.
 */
class m260831_190000_create_auth_and_tenancy_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%users}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'name' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%organisations}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'timezone' => $this->string(64)->notNull()->defaultValue('Europe/London'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%memberships}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'organisation_id' => $this->integer()->notNull(),
            'role' => $this->string(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_memberships_user_org', '{{%memberships}}', ['user_id', 'organisation_id'], true);
        $this->createIndex('ix_memberships_organisation_id', '{{%memberships}}', 'organisation_id');
        $this->addForeignKey(
            'fk_memberships_user',
            '{{%memberships}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_memberships_organisation',
            '{{%memberships}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%instructors}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'display_name' => $this->string(255)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_instructors_org_user', '{{%instructors}}', ['organisation_id', 'user_id'], true);
        $this->addForeignKey(
            'fk_instructors_organisation',
            '{{%instructors}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_instructors_user',
            '{{%instructors}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%instructors}}');
        $this->dropTable('{{%memberships}}');
        $this->dropTable('{{%organisations}}');
        $this->dropTable('{{%users}}');
    }
}
