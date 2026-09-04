<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Single-use password reset token (hashed at rest).
 *
 * @property int $id
 * @property string $account_type instructor|portal
 * @property int $account_id
 * @property string $token_hash
 * @property string $expires_at
 * @property string|null $used_at
 * @property string $created_at
 */
class PasswordResetToken extends ActiveRecord
{
    public const TYPE_INSTRUCTOR = 'instructor';
    public const TYPE_PORTAL = 'portal';

    public static function tableName(): string
    {
        return '{{%password_reset_tokens}}';
    }

    public function rules(): array
    {
        return [
            [['account_type', 'account_id', 'token_hash', 'expires_at', 'created_at'], 'required'],
            [['account_id'], 'integer'],
            [['account_type'], 'in', 'range' => [self::TYPE_INSTRUCTOR, self::TYPE_PORTAL]],
            [['token_hash'], 'string', 'max' => 64],
            [['expires_at', 'used_at', 'created_at'], 'safe'],
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at < gmdate('Y-m-d H:i:s');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
