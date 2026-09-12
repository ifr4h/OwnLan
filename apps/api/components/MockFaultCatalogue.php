<?php

declare(strict_types=1);

namespace app\components;

/**
 * DVSA-aligned fault taxonomy mapped to OwnLane progress_skills codes.
 *
 * Each fault item links to one canonical skill for evidence tracking.
 * Terminology follows driving instruction practice, not official DVSA forms.
 */
class MockFaultCatalogue
{
    /**
     * @return list<array{code: string, area: string, aspect: string, label: string, skill_code: string}>
     */
    public static function items(): array
    {
        return [
            // Precautions / eyesight
            ['code' => 'eyesight_check', 'area' => 'Eyesight', 'aspect' => 'Eyesight test', 'label' => 'Eyesight · Test', 'skill_code' => 'hazards'],
            // Junctions
            ['code' => 'junction_observation', 'area' => 'Junctions', 'aspect' => 'Observation', 'label' => 'Junctions · Observation', 'skill_code' => 'crossroads'],
            ['code' => 'junction_approach_speed', 'area' => 'Junctions', 'aspect' => 'Approach speed', 'label' => 'Junctions · Approach speed', 'skill_code' => 't_junctions'],
            ['code' => 'junction_positioning', 'area' => 'Junctions', 'aspect' => 'Positioning', 'label' => 'Junctions · Positioning', 'skill_code' => 't_junctions'],
            ['code' => 'junction_turning_right', 'area' => 'Junctions', 'aspect' => 'Turning right', 'label' => 'Junctions · Turning right', 'skill_code' => 't_junctions'],
            ['code' => 'junction_cutting_corners', 'area' => 'Junctions', 'aspect' => 'Cutting corners', 'label' => 'Junctions · Cutting corners', 'skill_code' => 't_junctions'],
            ['code' => 'roundabout_observation', 'area' => 'Junctions', 'aspect' => 'Roundabout observation', 'label' => 'Roundabouts · Observation', 'skill_code' => 'roundabouts'],
            ['code' => 'roundabout_lane', 'area' => 'Junctions', 'aspect' => 'Lane choice', 'label' => 'Roundabouts · Lane choice', 'skill_code' => 'roundabouts'],
            ['code' => 'meeting_situation', 'area' => 'Junctions', 'aspect' => 'Meeting', 'label' => 'Meeting situations', 'skill_code' => 'meeting'],
            ['code' => 'dual_carriageway', 'area' => 'Junctions', 'aspect' => 'Dual carriageway', 'label' => 'Dual carriageways', 'skill_code' => 'dual_carriageways'],
            // Mirrors
            ['code' => 'mirrors_before_change', 'area' => 'Mirrors', 'aspect' => 'Before changing direction', 'label' => 'Mirrors · Before changing direction', 'skill_code' => 'mirrors'],
            ['code' => 'mirrors_signalling', 'area' => 'Mirrors', 'aspect' => 'Before signalling', 'label' => 'Mirrors · Before signalling', 'skill_code' => 'mirrors'],
            ['code' => 'mirrors_speed_change', 'area' => 'Mirrors', 'aspect' => 'Before speed change', 'label' => 'Mirrors · Before speed change', 'skill_code' => 'mirrors'],
            // Signals
            ['code' => 'signals_timing', 'area' => 'Signals', 'aspect' => 'Timing', 'label' => 'Signals · Timing', 'skill_code' => 'signals'],
            ['code' => 'signals_cancel', 'area' => 'Signals', 'aspect' => 'Cancelling', 'label' => 'Signals · Cancelling', 'skill_code' => 'signals'],
            ['code' => 'signals_correct', 'area' => 'Signals', 'aspect' => 'Correct signal', 'label' => 'Signals · Correct signal', 'skill_code' => 'signals'],
            // Position
            ['code' => 'position_normal', 'area' => 'Position', 'aspect' => 'Normal road position', 'label' => 'Position · Normal road position', 'skill_code' => 'positioning'],
            ['code' => 'position_lane', 'area' => 'Position', 'aspect' => 'Lane discipline', 'label' => 'Position · Lane discipline', 'skill_code' => 'lane_choice'],
            ['code' => 'clearance_other', 'area' => 'Position', 'aspect' => 'Clearance / other vehicles', 'label' => 'Clearance · Other vehicles', 'skill_code' => 'positioning'],
            // Speed / progress
            ['code' => 'speed_appropriate', 'area' => 'Speed', 'aspect' => 'Appropriate speed', 'label' => 'Speed · Appropriate speed', 'skill_code' => 'speed'],
            ['code' => 'speed_limits', 'area' => 'Speed', 'aspect' => 'Speed limits', 'label' => 'Speed · Limits', 'skill_code' => 'speed'],
            ['code' => 'following_distance', 'area' => 'Speed', 'aspect' => 'Following distance', 'label' => 'Following distance', 'skill_code' => 'speed'],
            ['code' => 'progress_hesitation', 'area' => 'Progress', 'aspect' => 'Undue hesitation', 'label' => 'Progress · Undue hesitation', 'skill_code' => 'decision_making'],
            // Response to signs
            ['code' => 'signs_road_markings', 'area' => 'Response to signs', 'aspect' => 'Road markings', 'label' => 'Signs · Road markings', 'skill_code' => 'hazards'],
            ['code' => 'signs_traffic_lights', 'area' => 'Response to signs', 'aspect' => 'Traffic lights', 'label' => 'Signs · Traffic lights', 'skill_code' => 'hazards'],
            ['code' => 'signs_traffic_controllers', 'area' => 'Response to signs', 'aspect' => 'Traffic controllers', 'label' => 'Signs · Traffic controllers', 'skill_code' => 'hazards'],
            ['code' => 'pedestrian_crossings', 'area' => 'Response to signs', 'aspect' => 'Pedestrian crossings', 'label' => 'Pedestrian crossings', 'skill_code' => 'hazards'],
            // Judgement
            ['code' => 'judgement_overtaking', 'area' => 'Judgement', 'aspect' => 'Overtaking', 'label' => 'Judgement · Overtaking', 'skill_code' => 'meeting'],
            ['code' => 'judgement_crossing', 'area' => 'Judgement', 'aspect' => 'Crossing traffic', 'label' => 'Judgement · Crossing', 'skill_code' => 'meeting'],
            ['code' => 'awareness_planning', 'area' => 'Awareness', 'aspect' => 'Planning', 'label' => 'Awareness · Planning', 'skill_code' => 'hazards'],
            // Manoeuvres
            ['code' => 'manoeuvre_parallel', 'area' => 'Manoeuvres', 'aspect' => 'Parallel park', 'label' => 'Manoeuvres · Parallel park', 'skill_code' => 'parallel_park'],
            ['code' => 'manoeuvre_bay', 'area' => 'Manoeuvres', 'aspect' => 'Bay park', 'label' => 'Manoeuvres · Bay park', 'skill_code' => 'bay_park'],
            ['code' => 'manoeuvre_pull_up', 'area' => 'Manoeuvres', 'aspect' => 'Pull up on right', 'label' => 'Manoeuvres · Pull up on right', 'skill_code' => 'pull_up_right'],
            ['code' => 'manoeuvre_emergency', 'area' => 'Manoeuvres', 'aspect' => 'Emergency stop', 'label' => 'Manoeuvres · Emergency stop', 'skill_code' => 'emergency_stop'],
            // Car control
            ['code' => 'control_moving_off', 'area' => 'Car control', 'aspect' => 'Moving off — control', 'label' => 'Moving off · Control', 'skill_code' => 'moving_off'],
            ['code' => 'control_moving_off_safety', 'area' => 'Car control', 'aspect' => 'Moving off — safety', 'label' => 'Moving off · Safety', 'skill_code' => 'moving_off'],
            ['code' => 'control_clutch', 'area' => 'Car control', 'aspect' => 'Clutch control', 'label' => 'Car control · Clutch', 'skill_code' => 'clutch_control'],
            ['code' => 'control_steering', 'area' => 'Car control', 'aspect' => 'Steering', 'label' => 'Car control · Steering', 'skill_code' => 'steering'],
            ['code' => 'control_gears', 'area' => 'Car control', 'aspect' => 'Gears', 'label' => 'Car control · Gears', 'skill_code' => 'gears'],
            ['code' => 'control_accelerator', 'area' => 'Car control', 'aspect' => 'Accelerator', 'label' => 'Car control · Accelerator', 'skill_code' => 'clutch_control'],
            ['code' => 'control_footbrake', 'area' => 'Car control', 'aspect' => 'Footbrake', 'label' => 'Car control · Footbrake', 'skill_code' => 'steering'],
            ['code' => 'control_parking_brake', 'area' => 'Car control', 'aspect' => 'Parking brake', 'label' => 'Car control · Parking brake', 'skill_code' => 'moving_off'],
            // Independent
            ['code' => 'independent_sat_nav', 'area' => 'Independent driving', 'aspect' => 'Sat nav', 'label' => 'Independent · Sat nav', 'skill_code' => 'sat_nav'],
            ['code' => 'independent_directions', 'area' => 'Independent driving', 'aspect' => 'Following directions', 'label' => 'Independent · Directions', 'skill_code' => 'follow_directions'],
            ['code' => 'independent_decisions', 'area' => 'Independent driving', 'aspect' => 'Decision making', 'label' => 'Independent · Decisions', 'skill_code' => 'decision_making'],
        ];
    }

    /**
     * @return array<string, array{code: string, area: string, aspect: string, label: string, skill_code: string}>
     */
    public static function byCode(): array
    {
        $map = [];
        foreach (self::items() as $item) {
            $map[$item['code']] = $item;
        }

        return $map;
    }

    /**
     * Grouped by area for fault capture UI.
     *
     * @return list<array{area: string, items: list<array{code: string, aspect: string, label: string, skill_code: string}>}>
     */
    public static function groupedByArea(): array
    {
        $groups = [];
        foreach (self::items() as $item) {
            $area = $item['area'];
            if (!isset($groups[$area])) {
                $groups[$area] = ['area' => $area, 'items' => []];
            }
            $groups[$area]['items'][] = [
                'code' => $item['code'],
                'aspect' => $item['aspect'],
                'label' => $item['label'],
                'skill_code' => $item['skill_code'],
            ];
        }

        return array_values($groups);
    }

    public static function find(string $code): ?array
    {
        return self::byCode()[$code] ?? null;
    }
}
