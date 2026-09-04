<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\mail\MailerInterface;

/**
 * Transactional email delivery abstraction.
 *
 * Production: configure MAIL_DSN (Symfony mailer DSN).
 * Development: file transport writes to runtime/mail when DSN is unset.
 */
class EmailDeliveryService
{
    /**
     * True when a real SMTP/API transport is configured (not file-only dev mode).
     */
    public function canDeliver(): bool
    {
        $dsn = getenv('MAIL_DSN');

        return is_string($dsn) && trim($dsn) !== '';
    }

    /**
     * @return array{sent: bool, mode: 'email'|'file'|'skipped', error: string|null}
     */
    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): array
    {
        $to = mb_strtolower(trim($to));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'mode' => 'skipped', 'error' => 'Invalid recipient.'];
        }

        if (!$this->canDeliver()) {
            return ['sent' => false, 'mode' => 'skipped', 'error' => null];
        }

        try {
            /** @var MailerInterface $mailer */
            $mailer = Yii::$app->mailer;
            $from = $this->senderAddress();
            $message = $mailer->compose()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setTextBody($textBody);
            if ($htmlBody !== null && $htmlBody !== '') {
                $message->setHtmlBody($htmlBody);
            }
            $sent = (bool) $message->send();

            return [
                'sent' => $sent,
                'mode' => 'email',
                'error' => $sent ? null : 'Email could not be sent.',
            ];
        } catch (\Throwable $e) {
            Yii::warning('Transactional email failed: ' . $e->getMessage(), __METHOD__);

            return [
                'sent' => false,
                'mode' => 'email',
                'error' => 'Email could not be sent.',
            ];
        }
    }

    /**
     * @return array{address: string, name: string}
     */
    private function senderAddress(): array
    {
        $params = Yii::$app->params;
        $email = (string) ($params['senderEmail'] ?? 'noreply@ownlane.app');
        $name = (string) ($params['senderName'] ?? 'OwnLane');

        return ['address' => $email, 'name' => $name];
    }
}
