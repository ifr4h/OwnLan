<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Flagship Learning Engine: activity tracking, companions, temporary shares,
 * richer interactive learning content.
 */
class m260901_240000_flagship_learning extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learning_activities}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'content_id' => $this->integer()->null(),
            'content_slug' => $this->string(120)->null(),
            'activity_type' => $this->string(32)->notNull(), // opened|completed|scenario_completed|quick_review
            'result_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_learning_activities_learner', '{{%learning_activities}}', ['organisation_id', 'learner_id', 'created_at']);
        $this->addForeignKey('fk_la_org', '{{%learning_activities}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_la_learner', '{{%learning_activities}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_la_content', '{{%learning_activities}}', 'content_id', '{{%learning_contents}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%companion_accounts}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->notNull(),
            'name' => $this->string(120)->notNull(),
            'password_hash' => $this->string(255)->null(),
            'auth_key' => $this->string(32)->notNull(),
            'invite_token_hash' => $this->string(64)->null(),
            'invite_expires_at' => $this->dateTime()->null(),
            'activated_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_companion_accounts_email', '{{%companion_accounts}}', 'email', true);

        $this->createTable('{{%learner_companions}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'companion_account_id' => $this->integer()->notNull(),
            'display_name' => $this->string(120)->notNull(),
            'relationship_label' => $this->string(40)->null(),
            'permissions_json' => $this->text()->notNull(),
            'invited_by' => $this->string(16)->notNull()->defaultValue('learner'), // learner|instructor
            'revoked_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_learner_companions',
            '{{%learner_companions}}',
            ['learner_id', 'companion_account_id'],
            true,
        );
        $this->createIndex('ix_learner_companions_companion', '{{%learner_companions}}', ['companion_account_id', 'revoked_at']);
        $this->addForeignKey('fk_lc_org', '{{%learner_companions}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_lc_learner', '{{%learner_companions}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_lc_companion', '{{%learner_companions}}', 'companion_account_id', '{{%companion_accounts}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%temporary_shares}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'resource_type' => $this->string(32)->notNull(), // route|lesson_resource|content|practice_plan
            'resource_id' => $this->integer()->notNull(),
            'token_hash' => $this->string(64)->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'revoked_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_temporary_shares_token', '{{%temporary_shares}}', 'token_hash', true);
        $this->addForeignKey('fk_ts_org', '{{%temporary_shares}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_ts_learner', '{{%temporary_shares}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');

        // Optional link from private practice to a recorded route.
        $this->addColumn('{{%private_practice_sessions}}', 'lesson_route_id', $this->integer()->null());
        $this->addColumn('{{%private_practice_sessions}}', 'companion_note', $this->text()->null());
        $this->addColumn('{{%private_practice_sessions}}', 'companion_account_id', $this->integer()->null());
        $this->addForeignKey(
            'fk_pp_route',
            '{{%private_practice_sessions}}',
            'lesson_route_id',
            '{{%lesson_routes}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_pp_companion',
            '{{%private_practice_sessions}}',
            'companion_account_id',
            '{{%companion_accounts}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->seedInteractiveContent();
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_pp_companion', '{{%private_practice_sessions}}');
        $this->dropForeignKey('fk_pp_route', '{{%private_practice_sessions}}');
        $this->dropColumn('{{%private_practice_sessions}}', 'companion_account_id');
        $this->dropColumn('{{%private_practice_sessions}}', 'companion_note');
        $this->dropColumn('{{%private_practice_sessions}}', 'lesson_route_id');
        $this->dropTable('{{%temporary_shares}}');
        $this->dropTable('{{%learner_companions}}');
        $this->dropTable('{{%companion_accounts}}');
        $this->dropTable('{{%learning_activities}}');
    }

    private function seedInteractiveContent(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $items = [
            [
                'slug' => 'interactive-third-exit',
                'title' => 'Taking the third exit',
                'category' => 'roundabouts',
                'summary' => 'Choose your approach lane and path — then see why.',
                'skills' => ['roundabouts', 'lane_choice'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Interactive: third exit'],
                    ['type' => 'text', 'text' => 'You want the third exit on a multi-lane roundabout. Work through each step.'],
                    [
                        'type' => 'scenario',
                        'template' => 'multi_roundabout',
                        'title' => 'Third exit',
                        'steps' => [
                            [
                                'id' => 's1',
                                'prompt' => 'Which approach lane would you usually choose for the third exit?',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'left', 'label' => 'Left lane', 'correct' => false, 'feedback' => 'Left is often for turning left or going ahead — check signs and markings.'],
                                    ['id' => 'follow', 'label' => 'The lane shown by signs and road markings for the third exit', 'correct' => true, 'feedback' => 'Always follow local signs and markings — layouts vary.'],
                                    ['id' => 'any', 'label' => 'Any lane is fine', 'correct' => false, 'feedback' => 'Lane choice matters for a safe exit.'],
                                ],
                            ],
                            [
                                'id' => 's2',
                                'prompt' => 'Before you enter, what are you checking?',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'right', 'label' => 'Traffic from the right (and your mirrors)', 'correct' => true, 'feedback' => 'Give way to traffic from the right, and keep checking mirrors.'],
                                    ['id' => 'phone', 'label' => 'Your phone for directions', 'correct' => false, 'feedback' => 'Set navigation before you move — stay focused on the road.'],
                                    ['id' => 'horn', 'label' => 'Whether to use the horn', 'correct' => false, 'feedback' => 'Observation and timing matter more than the horn.'],
                                ],
                            ],
                            [
                                'id' => 's3',
                                'prompt' => 'Stay in lane until you leave — signal left before your exit.',
                                'mode' => 'explain',
                                'explanation' => 'Follow the markings around. If you miss the exit, go around again — do not cut across lanes.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'interactive-give-way',
                'title' => 'Give Way vs Stop',
                'category' => 'signs',
                'summary' => 'Know the difference — and what it means for your wheels.',
                'skills' => ['t_junctions', 'observation'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Signs at junctions'],
                    [
                        'type' => 'scenario',
                        'template' => 't_junction',
                        'title' => 'Junction control',
                        'steps' => [
                            [
                                'id' => 's1',
                                'prompt' => 'You see an inverted triangle Give Way sign. Must you stop every time?',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'yes', 'label' => 'Yes — always stop', 'correct' => false, 'feedback' => 'Give Way means be ready to stop if needed — you may proceed when clear.'],
                                    ['id' => 'no', 'label' => 'No — stop only if other traffic requires it', 'correct' => true, 'feedback' => 'Correct. Stop signs require a full stop; Give Way requires yielding when needed.'],
                                ],
                            ],
                            [
                                'id' => 's2',
                                'prompt' => 'A Stop sign means…',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'crawl', 'label' => 'Slow to a crawl if the road looks empty', 'correct' => false, 'feedback' => 'You must come to a complete stop behind the line.'],
                                    ['id' => 'full', 'label' => 'Come to a complete stop, then go when safe', 'correct' => true, 'feedback' => 'Full stop first — then proceed when clear.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'interactive-traffic-lights',
                'title' => 'Traffic lights — what now?',
                'category' => 'traffic_lights',
                'summary' => 'Amber is not a race. Know what each light asks of you.',
                'skills' => ['hazards', 'decision_making'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Light sequences'],
                    [
                        'type' => 'scenario',
                        'template' => 'traffic_lights',
                        'title' => 'Amber decision',
                        'steps' => [
                            [
                                'id' => 's1',
                                'prompt' => 'The light turns amber as you approach. What should you usually do if you can stop safely?',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'stop', 'label' => 'Stop if you can do so safely', 'correct' => true, 'feedback' => 'Amber means stop unless you are so close that stopping may cause a hazard.'],
                                    ['id' => 'speed', 'label' => 'Speed up to clear the junction', 'correct' => false, 'feedback' => 'Do not race the amber.'],
                                ],
                            ],
                            [
                                'id' => 's2',
                                'prompt' => 'Red and amber together means…',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'go', 'label' => 'Go immediately', 'correct' => false, 'feedback' => 'Prepare to go — but wait for green.'],
                                    ['id' => 'prepare', 'label' => 'Prepare to go — wait for green', 'correct' => true, 'feedback' => 'Get ready, but do not move until green.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'interactive-meeting',
                'title' => 'Meeting on a narrow road',
                'category' => 'junctions',
                'summary' => 'Hold back early when the obstruction is on your side.',
                'skills' => ['meeting', 'positioning'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Who gives way?'],
                    [
                        'type' => 'scenario',
                        'template' => 'blank',
                        'title' => 'Meeting traffic',
                        'steps' => [
                            [
                                'id' => 's1',
                                'prompt' => 'Parked cars block your side of the road. Oncoming traffic is approaching. What should you do?',
                                'mode' => 'choice',
                                'options' => [
                                    ['id' => 'push', 'label' => 'Keep going — they should stop', 'correct' => false, 'feedback' => 'If the obstruction is on your side, be prepared to stop and give way.'],
                                    ['id' => 'hold', 'label' => 'Hold back and give way', 'correct' => true, 'feedback' => 'Spot it early, check mirrors, and be ready to stop.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($items as $item) {
            $exists = (new \yii\db\Query())->from('{{%learning_contents}}')->where(['slug' => $item['slug']])->exists();
            if ($exists) {
                continue;
            }
            $this->insert('{{%learning_contents}}', [
                'organisation_id' => null,
                'slug' => $item['slug'],
                'title' => $item['title'],
                'category' => $item['category'],
                'summary' => $item['summary'],
                'blocks_json' => json_encode($item['blocks'], JSON_THROW_ON_ERROR),
                'skill_codes_json' => json_encode($item['skills'], JSON_THROW_ON_ERROR),
                'transmission' => null,
                'status' => 'published',
                'version' => 1,
                'published_at' => $now,
                'source_note' => 'OwnLane original interactive — not copied from third-party apps',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
