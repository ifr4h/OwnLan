<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Learner companion foundations: DVSA-aligned skill taxonomy, progress history,
 * lesson skill tags, and explicitly recorded lesson GPS routes.
 */
class m260901_220000_learner_progress_and_routes extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%progress_skills}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull(),
            'category_code' => $this->string(64)->notNull(),
            'category_label' => $this->string(120)->notNull(),
            'label' => $this->string(160)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'active' => $this->boolean()->notNull()->defaultValue(true),
        ]);
        $this->createIndex('ux_progress_skills_code', '{{%progress_skills}}', 'code', true);
        $this->createIndex('ix_progress_skills_category', '{{%progress_skills}}', 'category_code');

        $this->createTable('{{%learner_skill_progress}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'skill_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->null(),
            'rating' => $this->string(32)->notNull(),
            'recorded_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ix_learner_skill_progress_learner',
            '{{%learner_skill_progress}}',
            ['organisation_id', 'learner_id', 'skill_id', 'recorded_at'],
        );
        $this->createIndex(
            'ix_learner_skill_progress_lesson',
            '{{%learner_skill_progress}}',
            'lesson_id',
        );
        $this->addForeignKey(
            'fk_lsp_organisation',
            '{{%learner_skill_progress}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lsp_learner',
            '{{%learner_skill_progress}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lsp_skill',
            '{{%learner_skill_progress}}',
            'skill_id',
            '{{%progress_skills}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lsp_lesson',
            '{{%learner_skill_progress}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%lesson_skills}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'skill_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_lesson_skills',
            '{{%lesson_skills}}',
            ['lesson_id', 'skill_id'],
            true,
        );
        $this->addForeignKey(
            'fk_lesson_skills_org',
            '{{%lesson_skills}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_skills_lesson',
            '{{%lesson_skills}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_skills_skill',
            '{{%lesson_skills}}',
            'skill_id',
            '{{%progress_skills}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );

        $this->createTable('{{%lesson_routes}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'status' => $this->string(32)->notNull(),
            'started_at' => $this->dateTime()->null(),
            'ended_at' => $this->dateTime()->null(),
            'duration_seconds' => $this->integer()->null(),
            'distance_metres' => $this->integer()->null(),
            'point_count' => $this->integer()->notNull()->defaultValue(0),
            'encoded_polyline' => $this->text()->null(),
            'bounds_json' => $this->text()->null(),
            'label' => $this->string(255)->null(),
            'learner_visible' => $this->boolean()->notNull()->defaultValue(false),
            'shared_at' => $this->dateTime()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_lesson_routes_lesson', '{{%lesson_routes}}', 'lesson_id', true);
        $this->createIndex(
            'ix_lesson_routes_learner_visible',
            '{{%lesson_routes}}',
            ['organisation_id', 'learner_id', 'learner_visible', 'deleted_at'],
        );
        $this->addForeignKey(
            'fk_lesson_routes_org',
            '{{%lesson_routes}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_routes_lesson',
            '{{%lesson_routes}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_routes_learner',
            '{{%lesson_routes}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->seedSkills();
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%lesson_routes}}');
        $this->dropTable('{{%lesson_skills}}');
        $this->dropTable('{{%learner_skill_progress}}');
        $this->dropTable('{{%progress_skills}}');
    }

    private function seedSkills(): void
    {
        $skills = [
            // Car control
            ['moving_off', 'car_control', 'Car control', 'Moving off', 10],
            ['stopping', 'car_control', 'Car control', 'Stopping', 20],
            ['clutch_control', 'car_control', 'Car control', 'Clutch control', 30],
            ['steering', 'car_control', 'Car control', 'Steering', 40],
            ['gears', 'car_control', 'Car control', 'Gears', 50],
            // Road awareness
            ['mirrors', 'road_awareness', 'Road awareness', 'Mirrors', 110],
            ['signals', 'road_awareness', 'Road awareness', 'Signals', 120],
            ['positioning', 'road_awareness', 'Road awareness', 'Positioning', 130],
            ['speed', 'road_awareness', 'Road awareness', 'Speed awareness', 140],
            ['hazards', 'road_awareness', 'Road awareness', 'Hazard awareness', 150],
            // Junctions
            ['t_junctions', 'junctions', 'Junctions', 'T-junctions', 210],
            ['crossroads', 'junctions', 'Junctions', 'Crossroads', 220],
            ['roundabouts', 'junctions', 'Junctions', 'Roundabouts', 230],
            ['meeting', 'junctions', 'Junctions', 'Meeting situations', 240],
            ['dual_carriageways', 'junctions', 'Junctions', 'Dual carriageways', 250],
            // Manoeuvres
            ['bay_park', 'manoeuvres', 'Manoeuvres', 'Bay parking', 310],
            ['parallel_park', 'manoeuvres', 'Manoeuvres', 'Parallel parking', 320],
            ['pull_up_right', 'manoeuvres', 'Manoeuvres', 'Pull up on the right', 330],
            ['forward_bay', 'manoeuvres', 'Manoeuvres', 'Forward bay park', 340],
            ['emergency_stop', 'manoeuvres', 'Manoeuvres', 'Emergency stop', 350],
            // Independent driving
            ['follow_directions', 'independent', 'Independent driving', 'Following directions', 410],
            ['sat_nav', 'independent', 'Independent driving', 'Sat-nav driving', 420],
            ['lane_choice', 'independent', 'Independent driving', 'Lane choice', 430],
            ['decision_making', 'independent', 'Independent driving', 'Decision making', 440],
        ];

        $rows = [];
        foreach ($skills as [$code, $cat, $catLabel, $label, $sort]) {
            $rows[] = [$code, $cat, $catLabel, $label, $sort, true];
        }
        $this->batchInsert(
            '{{%progress_skills}}',
            ['code', 'category_code', 'category_label', 'label', 'sort_order', 'active'],
            $rows,
        );
    }
}
