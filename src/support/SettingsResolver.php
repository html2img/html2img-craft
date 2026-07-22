<?php

namespace html2img\ogimages\support;

/**
 * Resolves the render settings for a section: plugin defaults first, then
 * any per-section override for template, width, height and dpi.
 */
final class SettingsResolver
{
    private const OVERRIDABLE = ['template', 'width', 'height', 'dpi'];

    /**
     * @param array{template: string, width: int, height: int, dpi: int} $defaults
     * @param array<int|string, mixed> $overrides Either a map keyed by section
     * handle (config file) or a list of rows with a `section` key (CP table).
     * Values are unvalidated config input, so rows are checked at runtime.
     * @return array{template: string, width: int, height: int, dpi: int}
     */
    public static function resolve(array $defaults, array $overrides, ?string $sectionHandle): array
    {
        $resolved = $defaults;
        $override = $sectionHandle !== null ? self::overrideFor($overrides, $sectionHandle) : null;

        foreach (self::OVERRIDABLE as $key) {
            $value = $override[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $resolved[$key] = $key === 'template' ? (string)$value : (int)$value;
        }

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $overrides
     * @return array<string, mixed>|null
     */
    private static function overrideFor(array $overrides, string $sectionHandle): ?array
    {
        if (isset($overrides[$sectionHandle]) && is_array($overrides[$sectionHandle])) {
            return $overrides[$sectionHandle];
        }

        foreach ($overrides as $row) {
            if (is_array($row) && ($row['section'] ?? null) === $sectionHandle) {
                return $row;
            }
        }

        return null;
    }
}
