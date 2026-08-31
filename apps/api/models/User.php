<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property string $name
 * @property string $auth_key
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Membership[] $memberships
 * @property-read Instructor|null $instructor
 */
class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%users}}';
    }

    public function rules(): array
    {
        return [
            [['email', 'password_hash', 'name', 'auth_key', 'created_at', 'updated_at'], 'required'],
            [['email', 'name'], 'string', 'max' => 255],
            ['email', 'email'],
            ['email', 'unique'],
            ['auth_key', 'string', 'max' => 32],
        ];
    }

    public function getMemberships(): ActiveQuery
    {
        return $this->hasMany(Membership::class, ['user_id' => 'id']);
    }

    public function getInstructor(): ActiveQuery
    {
        return $this->hasOne(Instructor::class, ['user_id' => 'id']);
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

    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }
}
