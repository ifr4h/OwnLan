<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Portal login identity for a single Learner — not an instructor User.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $learner_id
 * @property string $email
 * @property string|null $password_hash
 * @property string $auth_key
 * @property string|null $invite_token_hash
 * @property string|null $invite_expires_at
 * @property string|null $invite_sent_at
 * @property string|null $last_invite_at
 * @property string|null $activated_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read Learner $learner
 * @property-read Organisation $organisation
 */
class LearnerPortalAccount extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%learner_portal_accounts}}';
    }

    public function rules(): array
    {
        return [
            [['organisation_id', 'learner_id', 'email', 'auth_key', 'created_at', 'updated_at'], 'required'],
            [['organisation_id', 'learner_id'], 'integer'],
            // Format is enforced on invite; local demo logins may use a short id.
            [['email'], 'string', 'max' => 255],
            [['password_hash'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['invite_token_hash'], 'string', 'max' => 64],
            [['invite_expires_at', 'invite_sent_at', 'last_invite_at', 'activated_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getLearner(): ActiveQuery
    {
        return $this->hasOne(Learner::class, ['id' => 'learner_id']);
    }

    public function getOrganisation(): ActiveQuery
    {
        return $this->hasOne(Organisation::class, ['id' => 'organisation_id']);
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
        if ($this->password_hash === null || $this->password_hash === '') {
            return false;
        }

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
