<?php

declare(strict_types=1);

namespace App\Service\Notification;

/**
 * Replaces {{PLACEHOLDER}} tokens; unknown keys become empty string.
 */
final class NotificationTemplateRenderer
{
    /** Allow optional spaces (e.g. WYSIWYG/HTML editors: "{{ ETA }}"). */
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([A-Z0-9_]+)\s*\}\}/';

    /**
     * @param array<string, string> $variables
     */
    public function render(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static function (array $m) use ($variables): string {
                $key = $m[1] ?? '';
                if ($key === '') {
                    return '';
                }
                $value = $variables[$key] ?? '';

                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            },
            $template
        );
    }

    public function toPlainText(string $html): string
    {
        $text = preg_replace('/<\\s*br\\s*\\/?>/i', "\n", $html) ?? $html;

        return trim(strip_tags($text));
    }
}
