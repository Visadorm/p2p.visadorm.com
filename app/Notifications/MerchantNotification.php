<?php

namespace App\Notifications;

use App\Notifications\Channels\P2pDatabaseChannel;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Services\TemplateRenderer;
use App\Settings\EmailTemplateSettings;
use App\Settings\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class MerchantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The notification template type (e.g. 'trade_initiated').
     */
    abstract public function getType(): string;

    /**
     * Variables for template placeholder replacement.
     */
    abstract public function getVariables(): array;

    /**
     * Render the template via TemplateRenderer.
     */
    protected function renderTemplate(): array
    {
        return TemplateRenderer::render($this->getType(), $this->getVariables());
    }

    /**
     * Build the data array expected by the emails.layout Blade view.
     */
    protected function getEmailLayoutData(string $actionUrl = '', string $actionText = ''): array
    {
        $settings = rescue(fn () => app(EmailTemplateSettings::class), null);
        $rendered = $this->renderTemplate();

        return [
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'logo' => $settings?->logo_path ? asset('storage/' . $settings->logo_path) : '',
            'headerImage' => $settings?->header_image_path ? asset('storage/' . $settings->header_image_path) : '',
            'primaryColor' => $settings?->primary_color ?? '#8288bf',
            'footer' => strip_tags($settings?->footer_text ?? '', '<p><a><br><strong><em><b><i><ul><ol><li><span>'),
            'actionUrl' => $actionUrl,
            'actionText' => $actionText,
        ];
    }

    /**
     * Determine which channels to send on based on merchant preferences
     * and admin settings.
     */
    public function via(object $notifiable): array
    {
        $channels = [P2pDatabaseChannel::class]; // Always in-app

        $adminSettings = rescue(fn () => app(NotificationSettings::class), null);
        $emailGloballyEnabled = $adminSettings?->email_notifications_enabled ?? true;

        // Email: admin master switch ON + merchant prefers email + has an email
        if ($emailGloballyEnabled && $notifiable->notify_email && $notifiable->email) {
            $channels[] = 'mail';
        }

        // SMS: admin must configure Twilio + merchant must have notify_sms ON + have a phone
        $smsEnabled = $adminSettings?->sms_notifications_enabled ?? false;

        if ($smsEnabled && $notifiable->notify_sms && $notifiable->phone) {
            $channels[] = TwilioSmsChannel::class;
        }

        return $channels;
    }

    /**
     * In-app notification data.
     */
    public function toP2p(object $notifiable): array
    {
        $r = $this->renderTemplate();

        return [
            'type' => $this->getType(),
            'title' => $r['subject'],
            'body' => $r['body'],
            'trade_id' => $this->getTradeId(),
        ];
    }

    /**
     * Email notification via branded layout.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->getEmailLayoutData($this->getActionUrl(), $this->getActionText());

        return (new MailMessage)->subject($data['subject'])->view('emails.layout', $data);
    }

    /**
     * SMS text.
     */
    public function toSms(object $notifiable): string
    {
        return $this->renderTemplate()['sms'];
    }

    /**
     * Trade ID for in-app notifications (override in trade-based notifications).
     */
    protected function getTradeId(): ?int
    {
        return null;
    }

    /**
     * CTA button URL for email (override in subclasses).
     */
    protected function getActionUrl(): string
    {
        return '';
    }

    /**
     * CTA button text for email (override in subclasses).
     */
    protected function getActionText(): string
    {
        return '';
    }
}
