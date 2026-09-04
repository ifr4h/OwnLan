<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Trusted-person identity — not a learner, not an instructor.
 *
 * @property int $id
 * @property string $email
 * @property string $name
 * @property string|null $password_hash
 * @property string $auth_key
 * @property string|null $invite_token_hash
 * @property string|null $invite_expires_at
 * @property string|null $activated_at
 * @property string $created_at
 * @property string $updated_at
 */
class CompanionAccount extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%companion_accounts}}';
    }

    public function rules(): array
    {
        return [
            [['email', 'name', 'auth_key', 'created_at', 'updated_at'], 'required'],
            [['email'], 'email'],
            [['email'], 'string', 'max' => 255],
            [['name'], 'string', 'max' => 120],
            [['password_hash'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['invite_token_hash'], 'string', 'max' => 64],
            [['invite_expires_at', 'activated_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getIsActivated(): bool
    {
        return $this->activated_at !== null && $this->password_hash !== null;
    }

    public static function findIdentity($id): ?self
    {
        return static::findOne(['id' => (int) $id]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return null;
    }

    public static function findByEmail(string $email): ?self
    {
        return static::find()
            ->where('LOWER(email) = :email', [':email' => mb_strtolower(trim($email))])
            ->one();
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(string $password): bool
    {
        return $this->password_hash !== null
            && Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }
}
