<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Richer pupil ops profile: contacts, eyesight, medical / payment notes.
 */
class m260920_100000_learner_ops_profile extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%learners}}', 'eyesight_status', $this->string(32)->null());
        $this->addColumn('{{%learners}}', 'eyesight_checked_on', $this->date()->null());
        $this->addColumn('{{%learners}}', 'wears_glasses', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%learners}}', 'medical_notes', $this->text()->null());
        $this->addColumn('{{%learners}}', 'referred_by', $this->string(120)->null());
        $this->addColumn('{{%learners}}', 'payment_notes', $this->text()->null());

        $this->createTable('{{%learner_contacts}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'kind' => $this->string(32)->notNull(),
            'name' => $this->string(120)->notNull(),
            'phone' => $this->string(32)->null(),
            'email' => $this->string(255)->null(),
            'relationship' => $this->string(64)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'notes' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_learner_contacts_learner', '{{%learner_contacts}}', ['learner_id', 'sort_order']);
        $this->addForeignKey(
            'fk_learner_contacts_learner',
            '{{%learner_contacts}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_learner_contacts_org',
            '{{%learner_contacts}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        // Carry existing single emergency contact into the new table.
        $this->execute(<<<'SQL'
INSERT INTO learner_contacts (
    organisation_id, learner_id, kind, name, phone, relationship, sort_order, created_at, updated_at
)
SELECT
    organisation_id,
    id,
    'emergency',
    emergency_contact_name,
    emergency_contact_phone,
    NULL,
    0,
    NOW(),
    NOW()
FROM learners
WHERE emergency_contact_name IS NOT NULL
  AND TRIM(emergency_contact_name) <> ''
SQL);

        // Pickup places: allow drop-off role tagging.
        $this->addColumn(
            '{{%learner_locations}}',
            'usage',
            $this->string(16)->notNull()->defaultValue('both'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%learner_locations}}', 'usage');
        $this->dropForeignKey('fk_learner_contacts_org', '{{%learner_contacts}}');
        $this->dropForeignKey('fk_learner_contacts_learner', '{{%learner_contacts}}');
        $this->dropTable('{{%learner_contacts}}');
        $this->dropColumn('{{%learners}}', 'payment_notes');
        $this->dropColumn('{{%learners}}', 'referred_by');
        $this->dropColumn('{{%learners}}', 'medical_notes');
        $this->dropColumn('{{%learners}}', 'wears_glasses');
        $this->dropColumn('{{%learners}}', 'eyesight_checked_on');
        $this->dropColumn('{{%learners}}', 'eyesight_status');
    }
}
