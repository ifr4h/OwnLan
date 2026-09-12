<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TenantContext;
use app\models\DiaryBlock;
use app\models\Instructor;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class DiaryBlockService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listInRange(string $fromUtc, string $toUtc): array
    {
        $org = $this->requireOrganisation();
        $instructor = $this->requireCurrentInstructor($org);

        /** @var DiaryBlock[] $rows */
        $rows = TenantContext::scopeByOrganisation(DiaryBlock::find())
            ->andWhere(['instructor_id' => (int) $instructor->id])
            ->andWhere(['>=', 'starts_at', $fromUtc])
            ->andWhere(['<', 'starts_at', $toUtc])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        return array_map(fn (DiaryBlock $row) => $this->toArray($row, $org), $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $org = $this->requireOrganisation();
        $instructor = $this->requireCurrentInstructor($org);

        $block = new DiaryBlock();
        $block->organisation_id = (int) $org->id;
        $block->instructor_id = (int) $instructor->id;
        $block->starts_at = OrganisationTime::formatUtc(
            OrganisationTime::localToUtc((string) ($data['starts_at_local'] ?? ''), $org),
        );
        $block->duration_minutes = max(15, min(12 * 60, (int) ($data['duration_minutes'] ?? 60)));
        $label = trim((string) ($data['label'] ?? 'Private'));
        $block->label = $label !== '' ? mb_substr($label, 0, 120) : 'Private';
        $block->kind = DiaryBlock::KIND_PRIVATE;
        $now = gmdate('Y-m-d H:i:s');
        $block->created_at = $now;
        $block->updated_at = $now;

        if (!$block->save()) {
            throw new BadRequestHttpException('Could not save that block.');
        }

        return $this->toArray($block, $org);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $org = $this->requireOrganisation();
        $block = $this->findOwned($id);

        if (array_key_exists('starts_at_local', $data)) {
            $block->starts_at = OrganisationTime::formatUtc(
                OrganisationTime::localToUtc((string) $data['starts_at_local'], $org),
            );
        }
        if (array_key_exists('duration_minutes', $data)) {
            $block->duration_minutes = max(15, min(12 * 60, (int) $data['duration_minutes']));
        }
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            $block->label = $label !== '' ? mb_substr($label, 0, 120) : 'Private';
        }
        $block->updated_at = gmdate('Y-m-d H:i:s');
        if (!$block->save()) {
            throw new BadRequestHttpException('Could not update that block.');
        }

        return $this->toArray($block, $org);
    }

    public function delete(int $id): array
    {
        $block = $this->findOwned($id);
        $block->delete();

        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(DiaryBlock $block, Organisation $org): array
    {
        $local = OrganisationTime::utcToLocal($block->starts_at, $org);
        $ends = $local->modify('+' . (int) $block->duration_minutes . ' minutes');
        $startsUtc = new DateTimeImmutable($block->starts_at, new DateTimeZone('UTC'));
        $endsUtc = $startsUtc->modify('+' . (int) $block->duration_minutes . ' minutes');

        return [
            'id' => (int) $block->id,
            'kind' => $block->kind,
            'label' => $block->label,
            'is_private' => true,
            'starts_at' => $startsUtc->format(DATE_ATOM),
            'ends_at' => $endsUtc->format(DATE_ATOM),
            'starts_at_local' => OrganisationTime::formatLocalIso($local),
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_time' => $local->format('H:i'),
            'ends_at_time' => $ends->format('H:i'),
            'duration_minutes' => (int) $block->duration_minutes,
            'date' => $local->format('Y-m-d'),
        ];
    }

    private function findOwned(int $id): DiaryBlock
    {
        $org = $this->requireOrganisation();
        $instructor = $this->requireCurrentInstructor($org);
        $block = TenantContext::scopeByOrganisation(DiaryBlock::find())
            ->andWhere(['id' => $id, 'instructor_id' => (int) $instructor->id])
            ->one();
        if (!$block instanceof DiaryBlock) {
            throw new NotFoundHttpException('Block not found.');
        }

        return $block;
    }

    private function requireOrganisation(): Organisation
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }

    private function requireCurrentInstructor(Organisation $organisation): Instructor
    {
        $instructor = Instructor::find()
            ->where([
                'organisation_id' => (int) $organisation->id,
                'user_id' => (int) Yii::$app->user->id,
            ])
            ->one();
        if (!$instructor instanceof Instructor) {
            throw new ForbiddenHttpException('Instructor profile required.');
        }

        return $instructor;
    }
}
