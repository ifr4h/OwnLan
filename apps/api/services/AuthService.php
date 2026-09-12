<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Instructor;
use app\models\Membership;
use app\models\Organisation;
use app\models\User;
use Yii;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Lean auth + organisation bootstrap for solo instructors.
 */
class AuthService
{
    /**
     * Creates User, Organisation, Membership (owner) and Instructor in one transaction.
     *
     * @param array{name:string,email:string,password:string} $data
     * @throws BadRequestHttpException
     * @throws ConflictHttpException
     * @throws Exception
     */
    public function register(array $data): User
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            throw new BadRequestHttpException('Name, email and password are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Enter a valid email address.');
        }

        if (mb_strlen($password) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }

        if (User::findByEmail($email) !== null) {
            throw new ConflictHttpException('An account with this email already exists.');
        }

        $now = gmdate('Y-m-d H:i:s');

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new User();
            $user->email = $email;
            $user->name = $name;
            $user->setPassword($password);
            $user->generateAuthKey();
            $user->created_at = $now;
            $user->updated_at = $now;
            if (!$user->save()) {
                throw new BadRequestHttpException($this->firstError($user));
            }

            $organisation = new Organisation();
            $organisation->name = $this->defaultOrganisationName($name);
            $organisation->timezone = Organisation::DEFAULT_TIMEZONE;
            $organisation->default_hourly_rate_pence = Organisation::DEFAULT_HOURLY_RATE_PENCE;
            $organisation->default_lesson_duration_minutes = Organisation::DEFAULT_DURATION_MINUTES;
            $organisation->contact_email = $email;
            $organisation->work_days = json_encode(Organisation::DEFAULT_WORK_DAYS, JSON_THROW_ON_ERROR);
            $organisation->work_start_time = Organisation::DEFAULT_WORK_START;
            $organisation->work_end_time = Organisation::DEFAULT_WORK_END;
            $organisation->created_at = $now;
            $organisation->updated_at = $now;
            if (!$organisation->save()) {
                throw new BadRequestHttpException($this->firstError($organisation));
            }

            $membership = new Membership();
            $membership->user_id = (int) $user->id;
            $membership->organisation_id = (int) $organisation->id;
            $membership->role = Membership::ROLE_OWNER;
            $membership->created_at = $now;
            if (!$membership->save()) {
                throw new BadRequestHttpException($this->firstError($membership));
            }

            $instructor = new Instructor();
            $instructor->organisation_id = (int) $organisation->id;
            $instructor->user_id = (int) $user->id;
            $instructor->display_name = $name;
            $instructor->created_at = $now;
            $instructor->updated_at = $now;
            if (!$instructor->save()) {
                throw new BadRequestHttpException($this->firstError($instructor));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->loginUser($user);

        return $user;
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws BadRequestHttpException
     */
    public function login(array $data): User
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new BadRequestHttpException('Email and password are required.');
        }

        $user = User::findByEmail($email);
        if ($user === null || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Incorrect email or password.');
        }

        $this->loginUser($user);

        return $user;
    }

    public function logout(): void
    {
        Yii::$app->user->logout();
        TenantContext::clear();
    }

    /**
     * Current user payload for GET /me.
     *
     * @return array<string, mixed>
     * @throws UnauthorizedHttpException
     */
    public function currentUserPayload(): array
    {
        /** @var User|null $user */
        $user = Yii::$app->user->identity;
        if ($user === null) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        $organisationId = TenantContext::organisationId();
        $membership = Membership::find()
            ->where([
                'user_id' => (int) $user->id,
                'organisation_id' => $organisationId,
            ])
            ->one();

        $organisation = $organisationId
            ? Organisation::findOne(['id' => $organisationId])
            : null;

        $instructor = Instructor::find()
            ->where([
                'user_id' => (int) $user->id,
                'organisation_id' => $organisationId,
            ])
            ->one();

        return [
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'organisation' => $organisation ? [
                'id' => (int) $organisation->id,
                'name' => $organisation->name,
                'timezone' => $organisation->timezone,
                'default_lesson_duration_minutes' => $organisation->defaultLessonDurationMinutes(),
                'default_hourly_rate_pence' => $organisation->default_hourly_rate_pence !== null
                    ? (int) $organisation->default_hourly_rate_pence
                    : null,
                'work_days' => $organisation->workDays(),
                'work_start_time' => $organisation->workStartTime(),
                'work_end_time' => $organisation->workEndTime(),
                'week_starts_on' => $organisation->weekStartsOn(),
            ] : null,
            'membership' => $membership ? [
                'role' => $membership->role,
            ] : null,
            'instructor' => $instructor ? [
                'id' => (int) $instructor->id,
                'display_name' => $instructor->display_name,
            ] : null,
            'onboarding' => (new OnboardingService())->statusFor($organisation, $instructor),
        ];
    }

    private function loginUser(User $user): void
    {
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        if (Yii::$app->has('companionUser')) {
            Yii::$app->companionUser->logout();
        }
        Yii::$app->user->login($user, 0);
        TenantContext::bootstrapFromUser($user);
    }

    private function defaultOrganisationName(string $instructorName): string
    {
        $first = trim(explode(' ', $instructorName)[0] ?? $instructorName);

        return $first === '' ? 'My driving school' : "{$first}'s driving school";
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save.';
    }
}
