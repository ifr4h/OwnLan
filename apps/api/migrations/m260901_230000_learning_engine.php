<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Learning Engine foundations: teaching resources, route moments,
 * private practice, and published learning content.
 */
class m260901_230000_learning_engine extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%teaching_resources}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'created_by_instructor_id' => $this->integer()->null(),
            'kind' => $this->string(32)->notNull(), // board | real_road | article_link
            'title' => $this->string(200)->notNull(),
            'category' => $this->string(64)->null(),
            'description' => $this->text()->null(),
            'template_code' => $this->string(64)->null(),
            'scene_json' => $this->text()->notNull(),
            'skill_codes_json' => $this->text()->null(),
            'is_favourite' => $this->boolean()->notNull()->defaultValue(false),
            'archived_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_teaching_resources_org', '{{%teaching_resources}}', ['organisation_id', 'archived_at']);
        $this->addForeignKey('fk_tr_org', '{{%teaching_resources}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%lesson_resources}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'source_resource_id' => $this->integer()->null(),
            'kind' => $this->string(32)->notNull(),
            'title' => $this->string(200)->notNull(),
            'scene_json' => $this->text()->notNull(),
            'learner_visible_note' => $this->text()->null(),
            'skill_codes_json' => $this->text()->null(),
            'route_moment_id' => $this->integer()->null(),
            'learner_visible' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_lesson_resources_lesson', '{{%lesson_resources}}', ['lesson_id', 'learner_visible']);
        $this->addForeignKey('fk_lr_org', '{{%lesson_resources}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_lr_lesson', '{{%lesson_resources}}', 'lesson_id', '{{%lessons}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_lr_learner', '{{%lesson_resources}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_lr_source', '{{%lesson_resources}}', 'source_resource_id', '{{%teaching_resources}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%route_moments}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'lesson_route_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'recorded_at' => $this->dateTime()->notNull(),
            'offset_seconds' => $this->integer()->null(),
            'lat' => $this->double()->notNull(),
            'lng' => $this->double()->notNull(),
            'kind' => $this->string(32)->notNull()->defaultValue('review'), // review|good|explain|hazard|roundabout|custom
            'label' => $this->string(200)->null(),
            'learner_note' => $this->text()->null(),
            'learner_visible' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_route_moments_route', '{{%route_moments}}', 'lesson_route_id');
        $this->addForeignKey('fk_rm_org', '{{%route_moments}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_rm_route', '{{%route_moments}}', 'lesson_route_id', '{{%lesson_routes}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_rm_lesson', '{{%route_moments}}', 'lesson_id', '{{%lessons}}', 'id', 'CASCADE', 'CASCADE');

        // lesson_resources.route_moment_id FK after route_moments exists
        $this->addForeignKey(
            'fk_lr_moment',
            '{{%lesson_resources}}',
            'route_moment_id',
            '{{%route_moments}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%private_practice_sessions}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'practised_at' => $this->dateTime()->notNull(),
            'duration_minutes' => $this->integer()->notNull(),
            'skill_codes_json' => $this->text()->null(),
            'feeling' => $this->string(32)->null(), // difficult|okay|comfortable
            'note' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_private_practice_learner', '{{%private_practice_sessions}}', ['organisation_id', 'learner_id', 'practised_at']);
        $this->addForeignKey('fk_pp_org', '{{%private_practice_sessions}}', 'organisation_id', '{{%organisations}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_pp_learner', '{{%private_practice_sessions}}', 'learner_id', '{{%learners}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%learning_contents}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->null(), // null = OwnLane platform library
            'slug' => $this->string(120)->notNull(),
            'title' => $this->string(200)->notNull(),
            'category' => $this->string(64)->notNull(),
            'summary' => $this->text()->null(),
            'blocks_json' => $this->text()->notNull(),
            'skill_codes_json' => $this->text()->null(),
            'transmission' => $this->string(16)->null(), // manual|automatic|null=both
            'status' => $this->string(16)->notNull()->defaultValue('draft'), // draft|published|archived
            'version' => $this->integer()->notNull()->defaultValue(1),
            'published_at' => $this->dateTime()->null(),
            'source_note' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_learning_contents_slug', '{{%learning_contents}}', 'slug', true);
        $this->createIndex('ix_learning_contents_status', '{{%learning_contents}}', ['status', 'category']);

        $this->seedPlatformContent();
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learning_contents}}');
        $this->dropTable('{{%private_practice_sessions}}');
        $this->dropForeignKey('fk_lr_moment', '{{%lesson_resources}}');
        $this->dropTable('{{%route_moments}}');
        $this->dropTable('{{%lesson_resources}}');
        $this->dropTable('{{%teaching_resources}}');
    }

    private function seedPlatformContent(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $items = [
            [
                'slug' => 'roundabouts-basics',
                'title' => 'Roundabouts — the basics',
                'category' => 'roundabouts',
                'summary' => 'Approach, position, observe, and leave safely.',
                'skills' => ['roundabouts', 'lane_choice'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'What matters on approach'],
                    ['type' => 'text', 'text' => 'Plan your exit early. Use MSM — mirrors, signal, manoeuvre — and choose the correct lane before you enter.'],
                    ['type' => 'callout', 'text' => 'Never change lanes on the roundabout itself unless road markings clearly allow it.'],
                    ['type' => 'checklist', 'items' => [
                        'Check mirrors early',
                        'Signal if needed',
                        'Position for your exit',
                        'Give way to traffic from the right',
                        'Stay in lane until you leave',
                    ]],
                    ['type' => 'question', 'prompt' => 'You are taking the third exit. Where should you usually position on a multi-lane approach?', 'options' => [
                        ['id' => 'a', 'label' => 'Left lane', 'correct' => false, 'feedback' => 'Left is often for turning left or going ahead on some layouts — check signs.'],
                        ['id' => 'b', 'label' => 'Follow signs and road markings for the third exit', 'correct' => true, 'feedback' => 'Always follow local signs and markings — layouts vary.'],
                        ['id' => 'c', 'label' => 'Any lane is fine', 'correct' => false, 'feedback' => 'Lane choice matters for a safe exit.'],
                    ]],
                ],
            ],
            [
                'slug' => 'spiral-roundabouts',
                'title' => 'Spiral roundabouts',
                'category' => 'roundabouts',
                'summary' => 'Follow the spiral — stay in your lane as markings guide you around.',
                'skills' => ['roundabouts', 'lane_choice', 'decision_making'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Follow the spiral'],
                    ['type' => 'text', 'text' => 'Spiral roundabouts use road markings that guide you around. Get in the correct lane early and stay with the markings.'],
                    ['type' => 'steps', 'items' => [
                        'Read the signs on approach',
                        'Choose your lane early',
                        'Enter when safe',
                        'Follow lane markings around',
                        'Signal left before your exit',
                        'Leave in the correct lane',
                    ]],
                    ['type' => 'callout', 'text' => 'If you miss your exit, go around again — do not cut across lanes.'],
                ],
            ],
            [
                'slug' => 'meeting-situations',
                'title' => 'Meeting traffic',
                'category' => 'junctions',
                'summary' => 'Who gives way when the road narrows?',
                'skills' => ['meeting', 'positioning'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Hold back early'],
                    ['type' => 'text', 'text' => 'If there is an obstruction on your side, be prepared to stop and give way. Look for passing places and communicate clearly.'],
                    ['type' => 'checklist', 'items' => [
                        'Spot the obstruction early',
                        'Check mirrors',
                        'Be ready to stop',
                        'Use clear signals / courtesy',
                    ]],
                ],
            ],
            [
                'slug' => 'independent-lane-choice',
                'title' => 'Independent lane choice',
                'category' => 'independent',
                'summary' => 'Decide earlier — and trust the plan.',
                'skills' => ['lane_choice', 'decision_making', 'sat_nav'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Decide earlier'],
                    ['type' => 'text', 'text' => 'Independent driving asks you to plan without prompts. Read the road further ahead and commit to a lane before the junction gets busy.'],
                ],
            ],
            [
                'slug' => 'dual-carriageways-merging',
                'title' => 'Dual carriageways — merging',
                'category' => 'dual_carriageways',
                'summary' => 'Build speed, match traffic, join smoothly.',
                'skills' => ['dual_carriageways', 'speed', 'mirrors'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Use the slip road'],
                    ['type' => 'text', 'text' => 'Build up speed on the slip road, check mirrors and blind spot, and join when there is a safe gap — without stopping at the end unless you must.'],
                ],
            ],
        ];

        foreach ($items as $item) {
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
                'source_note' => 'OwnLane original — not copied from third-party teaching apps',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
