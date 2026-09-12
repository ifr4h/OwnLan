<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\ServicePriceResolver;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerPackage;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\Organisation;
use app\models\OrganisationService;
use app\models\PackageCreditUsage;
use app\models\PackageOffering;
use app\models\Payment;
use app\models\PaymentAllocation;
use DateTimeImmutable;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Pupil packages, lesson credit and manual payments.
 *
 * Concepts stay separate:
 * - payment received
 * - package purchased
 * - lesson completed (owned by LessonService)
 * - package credit consumed
 * - accounting income (flag on payment only — not auto-derived from lessons)
 */
class FinanceService
{
    public const SETTLEMENT_PACKAGE = 'package';
    public const SETTLEMENT_OUTSTANDING = 'outstanding';
    public const SETTLEMENT_PAID = 'paid';
    public const SETTLEMENT_WAIVED = 'waived';

    /**
     * Compact answers for “credit left?” and “do they owe?”.
     *
     * @return array<string, mixed>
     */
    public function summaryForLearner(int $learnerId): array
    {
        $learner = $this->findLearner($learnerId);
        $creditMinutes = $this->remainingCreditMinutes($learnerId);
        $owedPence = $this->amountOwedPence($learnerId);

        return [
            'learner_id' => $learnerId,
            'learner_name' => $learner->fullName,
            'credit_minutes' => $creditMinutes,
            'credit_label' => $this->creditLabel($creditMinutes),
            'credit_remaining_line' => $this->remainingCreditLine($creditMinutes),
            'amount_owed_pence' => $owedPence,
            'amount_owed_label' => Money::formatPence($owedPence),
            'owes_money' => $owedPence > 0,
            'money_status_label' => $owedPence > 0
                ? 'Owes ' . Money::formatPence($owedPence)
                : 'Nothing owed',
        ];
    }

    /**
     * Compact finance for teaching surfaces (lesson / Today). Not an accounts panel.
     *
     * @return array<string, mixed>
     */
    public function compactSnapshot(int $learnerId): array
    {
        $summary = $this->summaryForLearner($learnerId);
        $credit = (int) $summary['credit_minutes'];
        $owes = (bool) $summary['owes_money'];

        return [
            'credit_minutes' => $credit,
            'credit_label' => $summary['credit_label'],
            'credit_remaining_line' => $summary['credit_remaining_line'],
            'has_prepaid_credit' => $credit > 0,
            'owes_money' => $owes,
            'amount_owed_pence' => (int) $summary['amount_owed_pence'],
            'amount_owed_label' => $summary['amount_owed_label'],
            'money_status_label' => $summary['money_status_label'],
            'show_on_teaching' => $credit > 0 || $owes,
            'teaching_line' => $this->teachingLine($credit, $owes, (string) $summary['money_status_label']),
        ];
    }

    /**
     * Post-completion strip: credit remaining → book next; otherwise record payment.
     *
     * @param array<string, mixed> $settlement settleCompletedLesson core fields
     * @return array<string, mixed>
     */
    public function withCompletionAftermath(Lesson $lesson, array $settlement): array
    {
        $summary = $this->summaryForLearner((int) $lesson->learner_id);
        $learner = $this->findLearner((int) $lesson->learner_id);
        $firstName = $this->firstName($learner->fullName);
        $credit = (int) $summary['credit_minutes'];
        $hasCredit = $credit > 0;
        $bookPath = '/lessons/new?learner_id=' . (int) $lesson->learner_id
            . '&from_lesson=' . (int) $lesson->id;
        $payPath = '/pupils/' . (int) $lesson->learner_id . '?pay=1';

        $aftermath = [
            'headline' => 'Lesson complete',
            'learner_first_name' => $firstName,
            'learner_name' => $learner->fullName,
            'credit_minutes' => $credit,
            'credit_line' => $hasCredit
                ? $this->remainingCreditLine($credit)
                : 'No prepaid credit remaining',
            'has_prepaid_credit' => $hasCredit,
            'owes_money' => (bool) $summary['owes_money'],
            'amount_owed_pence' => (int) $summary['amount_owed_pence'],
            'amount_owed_label' => $summary['amount_owed_label'],
            'money_status_label' => $summary['money_status_label'],
            'settlement' => $settlement['settlement'] ?? null,
            'primary_cta' => $hasCredit ? 'book_next' : 'record_payment',
            'primary_cta_label' => $hasCredit ? 'Book next lesson' : 'Record payment',
            'primary_cta_path' => $hasCredit ? $bookPath : $payPath,
            'secondary_cta' => $hasCredit ? null : 'book_next',
            'secondary_cta_label' => $hasCredit ? null : 'Book next lesson',
            'secondary_cta_path' => $hasCredit ? null : $bookPath,
        ];

        return array_merge($settlement, [
            'summary' => $summary,
            'aftermath' => $aftermath,
        ]);
    }

    /**
     * Full finance panel for a pupil.
     *
     * @return array<string, mixed>
     */
    public function panelForLearner(int $learnerId): array
    {
        $summary = $this->summaryForLearner($learnerId);

        return [
            'summary' => $summary,
            'packages' => $this->listPackages($learnerId),
            'history' => $this->history($learnerId, 40),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPackages(int $learnerId): array
    {
        $this->findLearner($learnerId);
        /** @var LearnerPackage[] $rows */
        $rows = TenantContext::scopeByOrganisation(LearnerPackage::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['purchased_at' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        return array_map(fn (LearnerPackage $p) => $this->serializePackage($p), $rows);
    }

    /**
     * Create a package (purchased hours). Optionally record the payment in the same step.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPackage(int $learnerId, array $data): array
    {
        $org = $this->requireOrganisation();
        $learner = $this->findLearner($learnerId);

        $hours = $data['purchased_hours'] ?? null;
        $minutes = $data['purchased_minutes'] ?? null;
        if ($hours !== null) {
            if (is_float($hours)) {
                throw new BadRequestHttpException('Purchased hours must be a whole number or half-hour string, not a float.');
            }
            $purchasedMinutes = $this->hoursToMinutes($hours);
        } elseif ($minutes !== null) {
            $purchasedMinutes = Money::requireNonNegativePence($minutes, 'Purchased minutes');
            if ($purchasedMinutes < 15) {
                throw new BadRequestHttpException('Package must include at least 15 minutes.');
            }
        } else {
            throw new BadRequestHttpException('purchased_hours is required.');
        }

        $pricePence = $this->resolveAmountPence($data, 'price_pence', 'price');
        if ($pricePence < 0) {
            throw new BadRequestHttpException('Package price cannot be negative.');
        }

        $label = isset($data['label']) ? trim((string) $data['label']) : '';
        if ($label === '') {
            $label = $this->defaultPackageLabel($purchasedMinutes);
        }

        $notes = $this->nullableText($data['notes'] ?? null);
        $recordPayment = !empty($data['record_payment']);
        $now = gmdate('Y-m-d H:i:s');
        $purchasedAt = $this->resolveRecordedAt($data['purchased_at_local'] ?? null, $org);

        $tx = Yii::$app->db->beginTransaction();
        try {
            $package = new LearnerPackage();
            $package->organisation_id = (int) $org->id;
            $package->learner_id = (int) $learner->id;
            $package->label = $label;
            $package->purchased_minutes = $purchasedMinutes;
            $package->remaining_minutes = $purchasedMinutes;
            $package->price_pence = $pricePence;
            $package->purchased_at = $purchasedAt;
            $package->notes = $notes;
            $package->status = LearnerPackage::STATUS_ACTIVE;
            $package->created_at = $now;
            $package->updated_at = $now;

            if (!$package->save()) {
                throw new BadRequestHttpException($this->firstError($package));
            }

            $paymentPayload = null;
            if ($recordPayment) {
                $payment = $this->insertPayment([
                    'learner_id' => (int) $learner->id,
                    'amount_pence' => $pricePence,
                    'method' => $data['payment_method'] ?? Payment::METHOD_BANK_TRANSFER,
                    'purpose' => Payment::PURPOSE_PACKAGE,
                    'package_id' => (int) $package->id,
                    'notes' => $data['payment_notes'] ?? ('Payment for ' . $label),
                    'recorded_at_local' => $data['purchased_at_local'] ?? null,
                    'counts_as_income' => array_key_exists('counts_as_income', $data)
                        ? (bool) $data['counts_as_income']
                        : true,
                ], $org);
                $package->payment_id = (int) $payment->id;
                $package->updated_at = $now;
                if (!$package->save(true, ['payment_id', 'updated_at'])) {
                    throw new BadRequestHttpException($this->firstError($package));
                }
                $paymentPayload = $this->serializePayment($payment, $org);
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'package' => $this->serializePackage($package),
            'payment' => $paymentPayload,
            'summary' => $this->summaryForLearner($learnerId),
        ];
    }

    /**
     * Record a manual payment against outstanding lesson charges (FIFO).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordPayment(int $learnerId, array $data): array
    {
        $org = $this->requireOrganisation();
        $this->findLearner($learnerId);

        $amountPence = $this->resolveAmountPence($data, 'amount_pence', 'amount');
        if ($amountPence <= 0) {
            throw new BadRequestHttpException('Payment amount must be greater than zero.');
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $payment = $this->insertPayment([
                'learner_id' => $learnerId,
                'amount_pence' => $amountPence,
                'method' => $data['method'] ?? Payment::METHOD_CASH,
                'purpose' => Payment::PURPOSE_LESSON_BALANCE,
                'lesson_id' => isset($data['lesson_id']) ? (int) $data['lesson_id'] : null,
                'notes' => $data['notes'] ?? null,
                'recorded_at_local' => $data['recorded_at_local'] ?? null,
                'counts_as_income' => array_key_exists('counts_as_income', $data)
                    ? (bool) $data['counts_as_income']
                    : true,
            ], $org);

            $allocated = $this->allocatePaymentToCharges($payment, $org);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'payment' => $this->serializePayment($payment, $org),
            'allocated_pence' => $allocated,
            'allocated_label' => Money::formatPence($allocated),
            'summary' => $this->summaryForLearner($learnerId),
        ];
    }

    /**
     * Void a payment — audit trail via void fields; reverses charge allocations.
     *
     * @return array<string, mixed>
     */
    public function voidPayment(int $paymentId, array $data = []): array
    {
        $org = $this->requireOrganisation();
        /** @var Payment|null $payment */
        $payment = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['id' => $paymentId])
            ->one();
        if ($payment === null) {
            throw new NotFoundHttpException('Payment not found.');
        }
        if ($payment->voided_at !== null) {
            throw new BadRequestHttpException('Payment is already voided.');
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new BadRequestHttpException('A reason is required to void a payment.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $tx = Yii::$app->db->beginTransaction();
        try {
            /** @var PaymentAllocation[] $allocations */
            $allocations = PaymentAllocation::find()
                ->andWhere(['payment_id' => (int) $payment->id])
                ->all();
            foreach ($allocations as $allocation) {
                /** @var LessonCharge|null $charge */
                $charge = LessonCharge::findOne(['id' => (int) $allocation->lesson_charge_id]);
                if ($charge !== null && $charge->status !== LessonCharge::STATUS_VOIDED) {
                    $charge->amount_paid_pence = max(
                        0,
                        (int) $charge->amount_paid_pence - (int) $allocation->amount_pence,
                    );
                    $charge->status = $charge->outstandingPence() > 0
                        ? LessonCharge::STATUS_OUTSTANDING
                        : LessonCharge::STATUS_PAID;
                    $charge->updated_at = $now;
                    if (!$charge->save(true, ['amount_paid_pence', 'status', 'updated_at'])) {
                        throw new BadRequestHttpException($this->firstError($charge));
                    }
                }
                $allocation->delete();
            }

            $payment->voided_at = $now;
            $payment->void_reason = $reason;
            $payment->counts_as_income = false;
            $payment->updated_at = $now;
            if (!$payment->save(true, ['voided_at', 'void_reason', 'counts_as_income', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($payment));
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'payment' => $this->serializePayment($payment, $org),
            'summary' => $this->summaryForLearner((int) $payment->learner_id),
        ];
    }

    /**
     * Void remaining unused package credit (does not rewrite past lesson usage).
     *
     * @return array<string, mixed>
     */
    public function voidPackage(int $packageId, array $data = []): array
    {
        /** @var LearnerPackage|null $package */
        $package = TenantContext::scopeByOrganisation(LearnerPackage::find())
            ->andWhere(['id' => $packageId])
            ->one();
        if ($package === null) {
            throw new NotFoundHttpException('Package not found.');
        }
        if ($package->status === LearnerPackage::STATUS_VOIDED) {
            throw new BadRequestHttpException('Package is already voided.');
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new BadRequestHttpException('A reason is required to void a package.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $package->remaining_minutes = 0;
        $package->status = LearnerPackage::STATUS_VOIDED;
        $package->voided_at = $now;
        $package->void_reason = $reason;
        $package->updated_at = $now;
        if (!$package->save()) {
            throw new BadRequestHttpException($this->firstError($package));
        }

        return [
            'package' => $this->serializePackage($package),
            'summary' => $this->summaryForLearner((int) $package->learner_id),
        ];
    }

    /**
     * Called inside LessonService::complete transaction after lesson is saved as completed.
     *
     * @param array<string, mixed> $data complete payload
     * @return array{settlement: string, package_usage: ?array, charge: ?array, payment: ?array}
     */
    public function settleCompletedLesson(Lesson $lesson, Organisation $org, array $data = []): array
    {
        $existingUsage = PackageCreditUsage::find()
            ->andWhere(['lesson_id' => (int) $lesson->id, 'voided_at' => null])
            ->one();
        if ($existingUsage !== null) {
            return $this->withCompletionAftermath($lesson, [
                'settlement' => self::SETTLEMENT_PACKAGE,
                'package_usage' => $this->serializeUsage($existingUsage),
                'charge' => null,
                'payment' => null,
            ]);
        }
        $existingCharge = LessonCharge::find()
            ->andWhere(['lesson_id' => (int) $lesson->id])
            ->andWhere(['not', ['status' => LessonCharge::STATUS_VOIDED]])
            ->one();
        if ($existingCharge !== null) {
            return $this->withCompletionAftermath($lesson, [
                'settlement' => (int) $existingCharge->outstandingPence() > 0
                    ? self::SETTLEMENT_OUTSTANDING
                    : self::SETTLEMENT_PAID,
                'package_usage' => null,
                'charge' => $this->serializeCharge($existingCharge),
                'payment' => null,
            ]);
        }

        $duration = (int) $lesson->duration_minutes;
        $preferPackage = ($data['settlement'] ?? null) !== 'charge'
            && ($data['settlement'] ?? null) !== 'paid';

        if ($preferPackage && $this->remainingCreditMinutes((int) $lesson->learner_id) >= $duration) {
            $usage = $this->consumeCreditForLesson($lesson, $duration);
            $lesson->settlement = self::SETTLEMENT_PACKAGE;
            $lesson->price_pence = 0;
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save(true, ['settlement', 'price_pence', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            return $this->withCompletionAftermath($lesson, [
                'settlement' => self::SETTLEMENT_PACKAGE,
                'package_usage' => $this->serializeUsage($usage),
                'charge' => null,
                'payment' => null,
            ]);
        }

        $pricePence = null;
        try {
            $pricePence = $this->resolveLessonPricePence($lesson, $org, $data);
        } catch (BadRequestHttpException) {
            // Lesson still completes; money can be settled later from the pupil panel.
            $lesson->settlement = null;
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save(true, ['settlement', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            return $this->withCompletionAftermath($lesson, [
                'settlement' => null,
                'package_usage' => null,
                'charge' => null,
                'payment' => null,
            ]);
        }

        $lesson->price_pence = $pricePence;

        $now = gmdate('Y-m-d H:i:s');
        $charge = new LessonCharge();
        $charge->organisation_id = (int) $org->id;
        $charge->learner_id = (int) $lesson->learner_id;
        $charge->lesson_id = (int) $lesson->id;
        $charge->amount_pence = $pricePence;
        $charge->amount_paid_pence = 0;
        $charge->status = LessonCharge::STATUS_OUTSTANDING;
        $charge->created_at = $now;
        $charge->updated_at = $now;
        if (!$charge->save()) {
            throw new BadRequestHttpException($this->firstError($charge));
        }

        $paymentPayload = null;
        $settlement = self::SETTLEMENT_OUTSTANDING;

        $markPaid = ($data['settlement'] ?? null) === 'paid'
            || !empty($data['record_payment']);
        if ($markPaid) {
            $payAmount = isset($data['payment_amount_pence']) || isset($data['payment_amount'])
                ? $this->resolveAmountPence($data, 'payment_amount_pence', 'payment_amount')
                : $pricePence;
            if ($payAmount <= 0) {
                throw new BadRequestHttpException('Payment amount must be greater than zero.');
            }
            $payment = $this->insertPayment([
                'learner_id' => (int) $lesson->learner_id,
                'amount_pence' => $payAmount,
                'method' => $data['payment_method'] ?? Payment::METHOD_CASH,
                'purpose' => Payment::PURPOSE_LESSON_BALANCE,
                'lesson_id' => (int) $lesson->id,
                'notes' => $data['payment_notes'] ?? null,
                'recorded_at_local' => $data['payment_recorded_at_local'] ?? null,
                'counts_as_income' => true,
            ], $org);
            $this->allocatePaymentToCharges($payment, $org);
            $charge->refresh();
            $paymentPayload = $this->serializePayment($payment, $org);
            $settlement = $charge->outstandingPence() > 0
                ? self::SETTLEMENT_OUTSTANDING
                : self::SETTLEMENT_PAID;
        }

        $lesson->settlement = $settlement;
        $lesson->updated_at = $now;
        if (!$lesson->save(true, ['settlement', 'price_pence', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        return $this->withCompletionAftermath($lesson, [
            'settlement' => $settlement,
            'package_usage' => null,
            'charge' => $this->serializeCharge($charge),
            'payment' => $paymentPayload,
        ]);
    }

    /**
     * Financial outcome when a pupil did not attend (no-show). Does not complete the lesson.
     *
     * @param array<string, mixed> $data charge: waived|outstanding|package
     * @return array<string, mixed>
     */
    public function settleNoShowLesson(Lesson $lesson, Organisation $org, array $data = []): array
    {
        $existing = $this->findExistingLessonSettlement((int) $lesson->id);
        if ($existing !== null) {
            return $this->withNoShowAftermath($lesson, $existing);
        }

        if ($lesson->settlement !== null && $lesson->settlement !== '') {
            return $this->withNoShowAftermath($lesson, [
                'settlement' => (string) $lesson->settlement,
                'package_usage' => null,
                'charge' => null,
                'payment' => null,
            ]);
        }

        return $this->withNoShowAftermath(
            $lesson,
            $this->applyMissedLessonCharge($lesson, $org, $data),
        );
    }

    /**
     * Optional charge when a future lesson is cancelled (late cancellation).
     *
     * @param array<string, mixed> $data charge: waived|outstanding|package
     * @return array<string, mixed>|null Null when no financial action (omit charge).
     */
    public function settleCancelledLesson(Lesson $lesson, Organisation $org, array $data = []): ?array
    {
        if (!array_key_exists('charge', $data)) {
            return null;
        }

        $charge = $this->normalizeMissedLessonCharge($data, defaultWaived: false);
        if ($charge === 'waived') {
            $existing = $this->findExistingLessonSettlement((int) $lesson->id);
            if ($existing !== null) {
                return $existing;
            }

            return $this->applyMissedLessonCharge($lesson, $org, ['charge' => 'waived']);
        }

        $existing = $this->findExistingLessonSettlement((int) $lesson->id);
        if ($existing !== null) {
            return $existing;
        }

        return $this->applyMissedLessonCharge($lesson, $org, $data);
    }

    /**
     * Compact financial line for lesson history (instructor).
     */
    public function financialLineForLesson(Lesson $lesson): ?string
    {
        $settlement = $lesson->settlement;
        if ($settlement === null || $settlement === '') {
            return null;
        }
        if ($settlement === self::SETTLEMENT_WAIVED) {
            return 'No charge';
        }
        if ($settlement === self::SETTLEMENT_PACKAGE) {
            return 'Charged to block credit';
        }
        if ($settlement === self::SETTLEMENT_PAID) {
            return 'Paid';
        }
        if ($settlement === self::SETTLEMENT_OUTSTANDING) {
            $owed = $this->chargeOutstandingForLesson((int) $lesson->id);
            if ($owed > 0) {
                return Money::formatPence($owed) . ' due';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{settlement: string, package_usage: ?array, charge: ?array, payment: ?array}
     */
    private function applyMissedLessonCharge(Lesson $lesson, Organisation $org, array $data): array
    {
        $charge = $this->normalizeMissedLessonCharge($data, defaultWaived: false);

        if ($charge === 'waived') {
            $lesson->settlement = self::SETTLEMENT_WAIVED;
            $lesson->price_pence = 0;
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save(true, ['settlement', 'price_pence', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            return [
                'settlement' => self::SETTLEMENT_WAIVED,
                'package_usage' => null,
                'charge' => null,
                'payment' => null,
            ];
        }

        if ($charge === 'package') {
            $duration = (int) $lesson->duration_minutes;
            if ($this->remainingCreditMinutes((int) $lesson->learner_id) < $duration) {
                throw new BadRequestHttpException('Not enough lesson credit for this lesson.');
            }
            $usage = $this->consumeCreditForLesson($lesson, $duration);
            $lesson->settlement = self::SETTLEMENT_PACKAGE;
            $lesson->price_pence = 0;
            $lesson->updated_at = gmdate('Y-m-d H:i:s');
            if (!$lesson->save(true, ['settlement', 'price_pence', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($lesson));
            }

            return [
                'settlement' => self::SETTLEMENT_PACKAGE,
                'package_usage' => $this->serializeUsage($usage),
                'charge' => null,
                'payment' => null,
            ];
        }

        $pricePence = $this->resolveLessonPricePence($lesson, $org, $data);
        $lesson->price_pence = $pricePence;
        $now = gmdate('Y-m-d H:i:s');

        $existingCharge = LessonCharge::find()
            ->andWhere(['lesson_id' => (int) $lesson->id])
            ->andWhere(['not', ['status' => LessonCharge::STATUS_VOIDED]])
            ->one();
        if ($existingCharge instanceof LessonCharge) {
            $lesson->settlement = $existingCharge->outstandingPence() > 0
                ? self::SETTLEMENT_OUTSTANDING
                : self::SETTLEMENT_PAID;
            $lesson->updated_at = $now;
            $lesson->save(true, ['settlement', 'price_pence', 'updated_at']);

            return [
                'settlement' => (string) $lesson->settlement,
                'package_usage' => null,
                'charge' => $this->serializeCharge($existingCharge),
                'payment' => null,
            ];
        }

        $chargeRow = new LessonCharge();
        $chargeRow->organisation_id = (int) $org->id;
        $chargeRow->learner_id = (int) $lesson->learner_id;
        $chargeRow->lesson_id = (int) $lesson->id;
        $chargeRow->amount_pence = $pricePence;
        $chargeRow->amount_paid_pence = 0;
        $chargeRow->status = LessonCharge::STATUS_OUTSTANDING;
        $chargeRow->created_at = $now;
        $chargeRow->updated_at = $now;
        if (!$chargeRow->save()) {
            throw new BadRequestHttpException($this->firstError($chargeRow));
        }

        $lesson->settlement = self::SETTLEMENT_OUTSTANDING;
        $lesson->updated_at = $now;
        if (!$lesson->save(true, ['settlement', 'price_pence', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($lesson));
        }

        return [
            'settlement' => self::SETTLEMENT_OUTSTANDING,
            'package_usage' => null,
            'charge' => $this->serializeCharge($chargeRow),
            'payment' => null,
        ];
    }

    /**
     * @return array{settlement: string, package_usage: ?array, charge: ?array, payment: ?array}|null
     */
    private function findExistingLessonSettlement(int $lessonId): ?array
    {
        $usage = PackageCreditUsage::find()
            ->andWhere(['lesson_id' => $lessonId, 'voided_at' => null])
            ->one();
        if ($usage !== null) {
            return [
                'settlement' => self::SETTLEMENT_PACKAGE,
                'package_usage' => $this->serializeUsage($usage),
                'charge' => null,
                'payment' => null,
            ];
        }

        $charge = LessonCharge::find()
            ->andWhere(['lesson_id' => $lessonId])
            ->andWhere(['not', ['status' => LessonCharge::STATUS_VOIDED]])
            ->one();
        if ($charge instanceof LessonCharge) {
            return [
                'settlement' => $charge->outstandingPence() > 0
                    ? self::SETTLEMENT_OUTSTANDING
                    : self::SETTLEMENT_PAID,
                'package_usage' => null,
                'charge' => $this->serializeCharge($charge),
                'payment' => null,
            ];
        }

        return null;
    }

    private function chargeOutstandingForLesson(int $lessonId): int
    {
        $charge = LessonCharge::find()
            ->andWhere(['lesson_id' => $lessonId])
            ->andWhere(['status' => LessonCharge::STATUS_OUTSTANDING])
            ->one();

        return $charge instanceof LessonCharge ? $charge->outstandingPence() : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizeMissedLessonCharge(array $data, bool $defaultWaived): string
    {
        $raw = $data['charge'] ?? ($defaultWaived ? 'waived' : null);
        if ($raw === null || $raw === '') {
            throw new BadRequestHttpException('charge is required (waived, outstanding or package).');
        }
        $charge = strtolower(trim((string) $raw));
        if (!in_array($charge, ['waived', 'outstanding', 'package'], true)) {
            throw new BadRequestHttpException('charge must be waived, outstanding or package.');
        }

        return $charge;
    }

    /**
     * @param array{settlement: string, package_usage: ?array, charge: ?array, payment: ?array} $settlement
     * @return array<string, mixed>
     */
    public function withNoShowAftermath(Lesson $lesson, array $settlement): array
    {
        $summary = $this->summaryForLearner((int) $lesson->learner_id);
        $learner = $this->findLearner((int) $lesson->learner_id);
        $payPath = '/pupils/' . (int) $lesson->learner_id . '?pay=1';

        $financialLine = $this->financialLineForLesson($lesson);
        $aftermath = [
            'headline' => 'Marked no-show',
            'learner_first_name' => $this->firstName($learner->fullName),
            'learner_name' => $learner->fullName,
            'financial_line' => $financialLine,
            'settlement' => $settlement['settlement'] ?? null,
            'owes_money' => (bool) $summary['owes_money'],
            'amount_owed_pence' => (int) $summary['amount_owed_pence'],
            'amount_owed_label' => $summary['amount_owed_label'],
            'primary_cta' => ($settlement['settlement'] ?? null) === self::SETTLEMENT_OUTSTANDING
                ? 'record_payment'
                : null,
            'primary_cta_label' => ($settlement['settlement'] ?? null) === self::SETTLEMENT_OUTSTANDING
                ? 'Record payment'
                : null,
            'primary_cta_path' => ($settlement['settlement'] ?? null) === self::SETTLEMENT_OUTSTANDING
                ? $payPath
                : null,
        ];

        return array_merge($settlement, [
            'summary' => $summary,
            'aftermath' => $aftermath,
        ]);
    }

    /**
     * Unified history for the pupil money panel.
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $learnerId, int $limit = 40): array
    {
        $org = $this->requireOrganisation();
        $this->findLearner($learnerId);
        $items = [];

        /** @var Payment[] $payments */
        $payments = TenantContext::scopeByOrganisation(Payment::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['recorded_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
        foreach ($payments as $payment) {
            $items[] = [
                'id' => 'payment:' . (int) $payment->id,
                'kind' => 'payment_received',
                'at' => $payment->recorded_at,
                'at_display' => $this->formatLocal($payment->recorded_at, $org),
                'title' => $payment->voided_at !== null
                    ? 'Payment voided'
                    : 'Payment received',
                'detail' => $this->paymentDetail($payment),
                'amount_pence' => (int) $payment->amount_pence,
                'amount_label' => Money::formatPence((int) $payment->amount_pence),
                'voided' => $payment->voided_at !== null,
                'counts_as_income' => (bool) $payment->counts_as_income && $payment->voided_at === null,
                'payment_id' => (int) $payment->id,
            ];
        }

        /** @var LearnerPackage[] $packages */
        $packages = TenantContext::scopeByOrganisation(LearnerPackage::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['purchased_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
        foreach ($packages as $package) {
            $items[] = [
                'id' => 'package:' . (int) $package->id,
                'kind' => 'package_purchased',
                'at' => $package->purchased_at,
                'at_display' => $this->formatLocal($package->purchased_at, $org),
                'title' => $package->status === LearnerPackage::STATUS_VOIDED
                    ? 'Package voided'
                    : 'Package purchased',
                'detail' => ($package->label ?: 'Package')
                    . ' · '
                    . $this->creditLabel((int) $package->purchased_minutes)
                    . ' · '
                    . Money::formatPence((int) $package->price_pence),
                'amount_pence' => (int) $package->price_pence,
                'amount_label' => Money::formatPence((int) $package->price_pence),
                'voided' => $package->status === LearnerPackage::STATUS_VOIDED,
                'package_id' => (int) $package->id,
            ];
        }

        /** @var PackageCreditUsage[] $usages */
        $usages = TenantContext::scopeByOrganisation(PackageCreditUsage::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
        foreach ($usages as $usage) {
            $items[] = [
                'id' => 'usage:' . (int) $usage->id,
                'kind' => 'credit_consumed',
                'at' => $usage->created_at,
                'at_display' => $this->formatLocal($usage->created_at, $org),
                'title' => $usage->voided_at !== null ? 'Credit restored' : 'Lesson credit used',
                'detail' => $this->creditLabel((int) $usage->minutes)
                    . ' · lesson #'
                    . (int) $usage->lesson_id,
                'minutes' => (int) $usage->minutes,
                'voided' => $usage->voided_at !== null,
                'lesson_id' => (int) $usage->lesson_id,
                'package_id' => (int) $usage->package_id,
            ];
        }

        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
        foreach ($charges as $charge) {
            $items[] = [
                'id' => 'charge:' . (int) $charge->id,
                'kind' => 'lesson_charge',
                'at' => $charge->created_at,
                'at_display' => $this->formatLocal($charge->created_at, $org),
                'title' => $charge->status === LessonCharge::STATUS_VOIDED
                    ? 'Lesson charge voided'
                    : 'Lesson charged',
                'detail' => Money::formatPence((int) $charge->amount_pence)
                    . ($charge->status === LessonCharge::STATUS_OUTSTANDING
                        ? ' · still owed ' . Money::formatPence($charge->outstandingPence())
                        : ($charge->status === LessonCharge::STATUS_PAID ? ' · paid' : '')),
                'amount_pence' => (int) $charge->amount_pence,
                'amount_label' => Money::formatPence((int) $charge->amount_pence),
                'voided' => $charge->status === LessonCharge::STATUS_VOIDED,
                'lesson_id' => (int) $charge->lesson_id,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['at'], (string) $a['at']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $b['id'], (string) $a['id']);
        });

        return array_slice($items, 0, $limit);
    }

    public function remainingCreditMinutes(int $learnerId): int
    {
        $sum = TenantContext::scopeByOrganisation(LearnerPackage::find())
            ->andWhere([
                'learner_id' => $learnerId,
                'status' => LearnerPackage::STATUS_ACTIVE,
            ])
            ->sum('remaining_minutes');

        return (int) ($sum ?? 0);
    }

    public function amountOwedPence(int $learnerId): int
    {
        return $this->amountOwedByLearners([$learnerId])[$learnerId] ?? 0;
    }

    /**
     * Outstanding lesson charges per learner — single query, no N+1.
     *
     * @param list<int> $learnerIds
     * @return array<int, int>
     */
    public function amountOwedByLearners(array $learnerIds): array
    {
        $learnerIds = array_values(array_unique(array_map('intval', $learnerIds)));
        if ($learnerIds === []) {
            return [];
        }

        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere([
                'learner_id' => $learnerIds,
                'status' => LessonCharge::STATUS_OUTSTANDING,
            ])
            ->all();

        $totals = [];
        foreach ($charges as $charge) {
            $lid = (int) $charge->learner_id;
            $totals[$lid] = ($totals[$lid] ?? 0) + $charge->outstandingPence();
        }

        return $totals;
    }

    public function organisationOutstandingPence(): int
    {
        /** @var LessonCharge[] $charges */
        $charges = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere(['status' => LessonCharge::STATUS_OUTSTANDING])
            ->all();

        $total = 0;
        foreach ($charges as $charge) {
            $total += $charge->outstandingPence();
        }

        return $total;
    }

    private function consumeCreditForLesson(Lesson $lesson, int $minutes): PackageCreditUsage
    {
        /** @var LearnerPackage[] $packages */
        $packages = TenantContext::scopeByOrganisation(LearnerPackage::find())
            ->andWhere([
                'learner_id' => (int) $lesson->learner_id,
                'status' => LearnerPackage::STATUS_ACTIVE,
            ])
            ->andWhere(['>', 'remaining_minutes', 0])
            ->orderBy(['purchased_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $remainingNeeded = $minutes;
        $chosen = null;
        foreach ($packages as $package) {
            if ((int) $package->remaining_minutes >= $remainingNeeded) {
                $chosen = $package;
                break;
            }
        }
        if ($chosen === null) {
            throw new BadRequestHttpException('Not enough lesson credit for this lesson.');
        }

        // V1: one package covers the whole lesson (no split across packages mid-lesson).
        $now = gmdate('Y-m-d H:i:s');
        $chosen->remaining_minutes = (int) $chosen->remaining_minutes - $minutes;
        if ($chosen->remaining_minutes === 0) {
            $chosen->status = LearnerPackage::STATUS_EXHAUSTED;
        }
        $chosen->updated_at = $now;
        if (!$chosen->save(true, ['remaining_minutes', 'status', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($chosen));
        }

        $usage = new PackageCreditUsage();
        $usage->organisation_id = (int) $lesson->organisation_id;
        $usage->learner_id = (int) $lesson->learner_id;
        $usage->package_id = (int) $chosen->id;
        $usage->lesson_id = (int) $lesson->id;
        $usage->minutes = $minutes;
        $usage->created_at = $now;
        if (!$usage->save()) {
            throw new BadRequestHttpException($this->firstError($usage));
        }

        return $usage;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertPayment(array $data, Organisation $org): Payment
    {
        $method = (string) ($data['method'] ?? Payment::METHOD_CASH);
        if (!in_array($method, [
            Payment::METHOD_CASH,
            Payment::METHOD_BANK_TRANSFER,
            Payment::METHOD_OTHER,
        ], true)) {
            throw new BadRequestHttpException('Payment method must be cash, bank_transfer or other.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $payment = new Payment();
        $payment->organisation_id = (int) $org->id;
        $payment->learner_id = (int) $data['learner_id'];
        $payment->amount_pence = (int) $data['amount_pence'];
        $payment->method = $method;
        $payment->purpose = (string) $data['purpose'];
        $payment->recorded_at = $this->resolveRecordedAt($data['recorded_at_local'] ?? null, $org);
        $payment->notes = $this->nullableText($data['notes'] ?? null);
        $payment->lesson_id = isset($data['lesson_id']) ? (int) $data['lesson_id'] : null;
        $payment->package_id = isset($data['package_id']) ? (int) $data['package_id'] : null;
        $payment->counts_as_income = (bool) ($data['counts_as_income'] ?? true);
        $payment->created_by_user_id = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $payment->created_at = $now;
        $payment->updated_at = $now;

        if (!$payment->save()) {
            throw new BadRequestHttpException($this->firstError($payment));
        }

        return $payment;
    }

    private function allocatePaymentToCharges(Payment $payment, Organisation $org, mixed $lessonChargeId = null): int
    {
        if ($payment->purpose !== Payment::PURPOSE_LESSON_BALANCE) {
            return 0;
        }

        $remaining = (int) $payment->amount_pence;
        $allocated = 0;
        $now = gmdate('Y-m-d H:i:s');

        $query = TenantContext::scopeByOrganisation(LessonCharge::find())
            ->andWhere([
                'learner_id' => (int) $payment->learner_id,
                'status' => LessonCharge::STATUS_OUTSTANDING,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]);

        if ($payment->lesson_id !== null) {
            $query->andWhere(['lesson_id' => (int) $payment->lesson_id]);
        } elseif ($lessonChargeId !== null) {
            $query->andWhere(['id' => (int) $lessonChargeId]);
        }

        /** @var LessonCharge[] $charges */
        $charges = $query->all();
        foreach ($charges as $charge) {
            if ($remaining <= 0) {
                break;
            }
            $due = $charge->outstandingPence();
            if ($due <= 0) {
                continue;
            }
            $take = min($remaining, $due);

            $allocation = new PaymentAllocation();
            $allocation->organisation_id = (int) $org->id;
            $allocation->payment_id = (int) $payment->id;
            $allocation->lesson_charge_id = (int) $charge->id;
            $allocation->amount_pence = $take;
            $allocation->created_at = $now;
            if (!$allocation->save()) {
                throw new BadRequestHttpException($this->firstError($allocation));
            }

            $charge->amount_paid_pence = (int) $charge->amount_paid_pence + $take;
            $charge->status = $charge->outstandingPence() > 0
                ? LessonCharge::STATUS_OUTSTANDING
                : LessonCharge::STATUS_PAID;
            $charge->updated_at = $now;
            if (!$charge->save(true, ['amount_paid_pence', 'status', 'updated_at'])) {
                throw new BadRequestHttpException($this->firstError($charge));
            }

            $remaining -= $take;
            $allocated += $take;
        }

        return $allocated;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveLessonPricePence(Lesson $lesson, Organisation $org, array $data): int
    {
        try {
            $service = null;
            if ($lesson->service_id !== null) {
                $service = OrganisationService::findOne([
                    'id' => (int) $lesson->service_id,
                    'organisation_id' => (int) $org->id,
                ]);
            } elseif (isset($data['service_id'])) {
                $service = OrganisationService::findOne([
                    'id' => (int) $data['service_id'],
                    'organisation_id' => (int) $org->id,
                ]);
            }
            if ($service === null) {
                $service = OrganisationService::find()
                    ->andWhere([
                        'organisation_id' => (int) $org->id,
                        'status' => OrganisationService::STATUS_ACTIVE,
                        'is_default' => true,
                    ])
                    ->one();
            }

            $local = OrganisationTime::utcToLocal((string) $lesson->starts_at, $org);
            $pence = ServicePriceResolver::resolvePence(
                $org,
                $lesson,
                $service,
                (int) $lesson->learner_id,
                $local,
                (int) $lesson->duration_minutes,
                $data,
            );
            if ($pence <= 0) {
                throw new BadRequestHttpException(
                    'Set a service price or organisation hourly rate before charging.',
                );
            }

            return $pence;
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveAmountPence(array $data, string $penceKey, string $poundsKey): int
    {
        if (array_key_exists($penceKey, $data) && $data[$penceKey] !== null && $data[$penceKey] !== '') {
            try {
                return Money::requireNonNegativePence($data[$penceKey], 'Amount');
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }
        if (array_key_exists($poundsKey, $data) && $data[$poundsKey] !== null && $data[$poundsKey] !== '') {
            try {
                $pence = Money::poundsToPence($data[$poundsKey]);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
            if ($pence < 0) {
                throw new BadRequestHttpException('Amount cannot be negative.');
            }

            return $pence;
        }

        throw new BadRequestHttpException("{$penceKey} or {$poundsKey} is required.");
    }

    private function hoursToMinutes(mixed $hours): int
    {
        if (is_int($hours)) {
            if ($hours < 1) {
                throw new BadRequestHttpException('Package must be at least 1 hour.');
            }

            return $hours * 60;
        }
        if (is_string($hours)) {
            $raw = trim($hours);
            if (!preg_match('/^\d+(\.5)?$/', $raw)) {
                throw new BadRequestHttpException('Purchased hours must be a whole number or .5 (e.g. 10 or 10.5).');
            }
            if (str_ends_with($raw, '.5')) {
                return ((int) $raw) * 60 + 30;
            }
            $h = (int) $raw;
            if ($h < 1) {
                throw new BadRequestHttpException('Package must be at least 1 hour.');
            }

            return $h * 60;
        }

        throw new BadRequestHttpException('Purchased hours must be a whole number or half hour.');
    }

    private function resolveRecordedAt(?string $local, Organisation $org): string
    {
        if ($local === null || trim($local) === '') {
            return gmdate('Y-m-d H:i:s');
        }
        try {
            return OrganisationTime::localToUtc(trim($local), $org)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new BadRequestHttpException('recorded_at_local must be YYYY-MM-DD HH:MM.');
        }
    }

    private function creditLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'No lesson credit';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1 hour left' : $hours . ' hours left';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm left';
        }

        return $minutes . ' minutes left';
    }

    /** Phrasing for post-complete / teaching surfaces: “2 hours remaining”. */
    private function remainingCreditLine(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'No prepaid credit remaining';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1 hour remaining' : $hours . ' hours remaining';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm remaining';
        }

        return $minutes . ' minutes remaining';
    }

    private function teachingLine(int $creditMinutes, bool $owesMoney, string $owedLabel): ?string
    {
        if ($creditMinutes > 0) {
            return $this->remainingCreditLine($creditMinutes);
        }
        if ($owesMoney) {
            return $owedLabel;
        }

        return null;
    }

    private function defaultPackageLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($rem === 0) {
            return $hours . '-hour package';
        }

        return $hours . 'h ' . $rem . 'm package';
    }

    private function paymentDetail(Payment $payment): string
    {
        $method = match ($payment->method) {
            Payment::METHOD_CASH => 'Cash',
            Payment::METHOD_BANK_TRANSFER => 'Bank transfer',
            default => 'Other',
        };
        $purpose = $payment->purpose === Payment::PURPOSE_PACKAGE ? 'package' : 'lessons';
        $parts = [$method, $purpose, Money::formatPence((int) $payment->amount_pence)];
        if ($payment->void_reason) {
            $parts[] = $payment->void_reason;
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePackage(LearnerPackage $package): array
    {
        $used = (int) $package->purchased_minutes - (int) $package->remaining_minutes;

        return [
            'id' => (int) $package->id,
            'learner_id' => (int) $package->learner_id,
            'label' => $package->label,
            'purchased_minutes' => (int) $package->purchased_minutes,
            'remaining_minutes' => (int) $package->remaining_minutes,
            'used_minutes' => max(0, $used),
            'purchased_label' => $this->hoursMinutesLabel((int) $package->purchased_minutes),
            'remaining_label' => $this->creditLabel((int) $package->remaining_minutes),
            'price_pence' => (int) $package->price_pence,
            'price_label' => Money::formatPence((int) $package->price_pence),
            'payment_id' => $package->payment_id !== null ? (int) $package->payment_id : null,
            'purchased_at' => $package->purchased_at,
            'notes' => $package->notes,
            'status' => $package->status,
            'voided_at' => $package->voided_at,
            'void_reason' => $package->void_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $payment, Organisation $org): array
    {
        return [
            'id' => (int) $payment->id,
            'learner_id' => (int) $payment->learner_id,
            'amount_pence' => (int) $payment->amount_pence,
            'amount_label' => Money::formatPence((int) $payment->amount_pence),
            'method' => $payment->method,
            'method_label' => $payment->method === Payment::METHOD_CARD ? 'Paid online' : match ($payment->method) {
                Payment::METHOD_CASH => 'Cash',
                Payment::METHOD_BANK_TRANSFER => 'Bank transfer',
                default => 'Other',
            },
            'payment_status' => $payment->payment_status ?? Payment::STATUS_SUCCEEDED,
            'provider' => $payment->provider,
            'purpose' => $payment->purpose,
            'recorded_at' => $payment->recorded_at,
            'recorded_at_display' => $this->formatLocal($payment->recorded_at, $org),
            'notes' => $payment->notes,
            'lesson_id' => $payment->lesson_id !== null ? (int) $payment->lesson_id : null,
            'package_id' => $payment->package_id !== null ? (int) $payment->package_id : null,
            'counts_as_income' => (bool) $payment->counts_as_income,
            'voided_at' => $payment->voided_at,
            'void_reason' => $payment->void_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCharge(LessonCharge $charge): array
    {
        return [
            'id' => (int) $charge->id,
            'lesson_id' => (int) $charge->lesson_id,
            'amount_pence' => (int) $charge->amount_pence,
            'amount_label' => Money::formatPence((int) $charge->amount_pence),
            'amount_paid_pence' => (int) $charge->amount_paid_pence,
            'outstanding_pence' => $charge->outstandingPence(),
            'outstanding_label' => Money::formatPence($charge->outstandingPence()),
            'status' => $charge->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUsage(PackageCreditUsage $usage): array
    {
        return [
            'id' => (int) $usage->id,
            'package_id' => (int) $usage->package_id,
            'lesson_id' => (int) $usage->lesson_id,
            'minutes' => (int) $usage->minutes,
            'minutes_label' => $this->hoursMinutesLabel((int) $usage->minutes),
        ];
    }

    private function hoursMinutesLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem === 0) {
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }
        if ($hours > 0) {
            return $hours . 'h ' . $rem . 'm';
        }

        return $minutes . ' minutes';
    }

    private function formatLocal(string $utc, Organisation $org): string
    {
        return OrganisationTime::utcToLocal($utc, $org)->format('D j M · H:i');
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return ($parts[0] ?? '') !== '' ? $parts[0] : $fullName;
    }

    private function findLearner(int $id): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    /**
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }

    /**
     * Record a verified online payment — idempotent via idempotency_key.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyOnlinePayment(array $data, Organisation $org): array
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = Payment::findOne([
                'organisation_id' => (int) $org->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            if ($existing !== null) {
                return [
                    'payment' => $this->serializePayment($existing, $org),
                    'allocated_pence' => 0,
                    'package' => null,
                ];
            }
        }

        $learnerId = (int) ($data['learner_id'] ?? 0);
        $this->findLearner($learnerId);
        $amountPence = (int) ($data['amount_pence'] ?? 0);
        if ($amountPence <= 0) {
            throw new BadRequestHttpException('Payment amount must be greater than zero.');
        }

        $purpose = (string) ($data['purpose'] ?? Payment::PURPOSE_LESSON_BALANCE);
        $tx = Yii::$app->db->beginTransaction();
        try {
            $packagePayload = null;
            $packageId = null;

            if ($purpose === Payment::PURPOSE_PACKAGE) {
                $offeringId = (int) ($data['package_offering_id'] ?? 0);
                $offering = PackageOffering::findOne([
                    'id' => $offeringId,
                    'organisation_id' => (int) $org->id,
                    'active' => true,
                ]);
                if ($offering === null) {
                    throw new BadRequestHttpException('Package not available.');
                }
                if ((int) $offering->price_pence !== $amountPence) {
                    throw new BadRequestHttpException('Package price has changed.');
                }

                $package = new LearnerPackage();
                $package->organisation_id = (int) $org->id;
                $package->learner_id = $learnerId;
                $package->label = $offering->label;
                $package->purchased_minutes = (int) $offering->purchased_minutes;
                $package->remaining_minutes = (int) $offering->purchased_minutes;
                $package->price_pence = (int) $offering->price_pence;
                $package->purchased_at = gmdate('Y-m-d H:i:s');
                $package->status = LearnerPackage::STATUS_ACTIVE;
                $package->created_at = gmdate('Y-m-d H:i:s');
                $package->updated_at = gmdate('Y-m-d H:i:s');
                if (!$package->save()) {
                    throw new BadRequestHttpException($this->firstError($package));
                }
                $packageId = (int) $package->id;
                $packagePayload = $this->serializePackage($package);
            }

            $payment = $this->insertOnlinePayment([
                'learner_id' => $learnerId,
                'amount_pence' => $amountPence,
                'purpose' => $purpose,
                'lesson_id' => isset($data['lesson_id']) ? (int) $data['lesson_id'] : null,
                'package_id' => $packageId,
                'lesson_charge_id' => isset($data['lesson_charge_id']) ? (int) $data['lesson_charge_id'] : null,
                'notes' => $data['notes'] ?? 'Online payment',
                'provider' => $data['provider'] ?? Payment::PROVIDER_STRIPE,
                'provider_payment_id' => $data['provider_payment_id'] ?? null,
                'provider_charge_id' => $data['provider_charge_id'] ?? null,
                'provider_status' => $data['provider_status'] ?? Payment::STATUS_SUCCEEDED,
                'payment_status' => Payment::STATUS_SUCCEEDED,
                'platform_fee_pence' => $data['platform_fee_pence'] ?? null,
                'processor_fee_pence' => $data['processor_fee_pence'] ?? null,
                'net_pence' => $data['net_pence'] ?? null,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            ], $org);

            if ($packageId !== null) {
                $package = LearnerPackage::findOne(['id' => $packageId]);
                if ($package !== null) {
                    $package->payment_id = (int) $payment->id;
                    $package->updated_at = gmdate('Y-m-d H:i:s');
                    $package->save(false, ['payment_id', 'updated_at']);
                }
            }

            $allocated = $this->allocatePaymentToCharges($payment, $org, $data['lesson_charge_id'] ?? null);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'payment' => $this->serializePayment($payment, $org),
            'allocated_pence' => $allocated,
            'package' => $packagePayload,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertOnlinePayment(array $data, Organisation $org): Payment
    {
        $now = gmdate('Y-m-d H:i:s');
        $payment = new Payment();
        $payment->organisation_id = (int) $org->id;
        $payment->learner_id = (int) $data['learner_id'];
        $payment->amount_pence = (int) $data['amount_pence'];
        $payment->method = Payment::METHOD_CARD;
        $payment->purpose = (string) $data['purpose'];
        $payment->recorded_at = $now;
        $payment->notes = $this->nullableText($data['notes'] ?? null);
        $payment->lesson_id = isset($data['lesson_id']) ? (int) $data['lesson_id'] : null;
        $payment->package_id = isset($data['package_id']) ? (int) $data['package_id'] : null;
        $payment->counts_as_income = true;
        $payment->provider = (string) ($data['provider'] ?? Payment::PROVIDER_STRIPE);
        $payment->provider_payment_id = $data['provider_payment_id'] ?? null;
        $payment->provider_charge_id = $data['provider_charge_id'] ?? null;
        $payment->provider_status = $data['provider_status'] ?? null;
        $payment->payment_status = (string) ($data['payment_status'] ?? Payment::STATUS_SUCCEEDED);
        $payment->platform_fee_pence = isset($data['platform_fee_pence']) ? (int) $data['platform_fee_pence'] : null;
        $payment->processor_fee_pence = isset($data['processor_fee_pence']) ? (int) $data['processor_fee_pence'] : null;
        $payment->net_pence = isset($data['net_pence']) ? (int) $data['net_pence'] : null;
        $payment->idempotency_key = $data['idempotency_key'] ?? null;
        $payment->created_at = $now;
        $payment->updated_at = $now;

        if (!$payment->save()) {
            throw new BadRequestHttpException($this->firstError($payment));
        }

        return $payment;
    }
}
