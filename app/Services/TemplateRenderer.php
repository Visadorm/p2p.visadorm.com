<?php

namespace App\Services;

use App\Models\MessageTemplate;

class TemplateRenderer
{
    /**
     * Render a notification template with variable replacement.
     * Returns ['subject' => ..., 'body' => ..., 'sms' => ...].
     * Falls back to lang file if no DB template exists.
     */
    public static function render(string $type, array $variables = []): array
    {
        $template = MessageTemplate::resolve($type);

        if ($template) {
            return [
                'subject' => self::replace($template->email_subject ?? '', $variables),
                'body' => self::replace($template->email_body ?? '', $variables),
                'sms' => self::replace($template->sms_text ?? '', $variables),
            ];
        }

        // Fallback to lang files
        return [
            'subject' => self::replace(__("notifications.title.{$type}"), $variables),
            'body' => self::replace(__("notifications.body.{$type}"), $variables),
            'sms' => self::replace(__("notifications.body.{$type}"), $variables),
        ];
    }

    /**
     * Replace :placeholder variables in a template string.
     */
    private static function replace(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace(":{$key}", (string) $value, $text);
        }

        return $text;
    }
}
