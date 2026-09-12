<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Shared lesson note (chat) between instructor and pupil.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $lesson_id
 * @property string $author_role instructor|learner
 * @property int|null $author_user_id
 * @property int|null $author_portal_account_id
 * @property string $body
 * @property string $created_at
 *
 * @property-read Lesson $lesson
 */
class LessonMessage extends ActiveRecord
{
    public const ROLE_INSTRUCTOR = 'instructor';
    public const ROLE_LEARNER = 'learner';

    public static function tableName(): string
    {
        return '{{%lesson_messages}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'lesson_id', 'author_role', 'body', 'created_at'], 'required'],
            [['organisation_id', 'lesson_id', 'author_user_id', 'author_portal_account_id'], 'integer'],
            [['author_role'], 'in', 'range' => [self::ROLE_INSTRUCTOR, self::ROLE_LEARNER]],
            [['body'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function getLesson(): ActiveQuery
    {
        return $this->hasOne(Lesson::class, ['id' => 'lesson_id']);
    }
}
