<?php

declare(strict_types=1);

namespace Caelrik\Ui\Theme;

/**
 * Writes a theme's stylesheet: custom properties for the light and dark palettes and the
 * shape tokens, loaded after caelrik.css.
 *
 * The output is deterministic: resources/js/theme-css.js produces the same text in the
 * ThemeBuilder, and the tests compare both. Every value comes from a validated Theme, so
 * nothing in it can break out of a declaration.
 */
final class Stylesheet
{
    /** Container shadows: --caelrik-surface-shadow. */
    public const SHADOWS = [
        'none' => 'none',
        'subtle' => '0 1px 2px rgba(0, 0, 0, 0.08)',
        'elevated' => '0 1px 2px rgba(0, 0, 0, 0.3), 0 1px 3px 1px rgba(0, 0, 0, 0.15)',
        'outlined' => '0 1px 2px 0 rgba(0, 0, 0, 0.05), 0 0 0 1px var(--bs-border-color)',
    ];

    // Bootstrap compiles these components with its default blue: bind them to the theme.
    // The calendar follows --bs-primary and --caelrik-on-primary through caelrik.css.
    private const BOOTSTRAP_BINDINGS = <<<'CSS'
        .btn-primary {
          --bs-btn-color: var(--caelrik-on-primary);
          --bs-btn-bg: var(--caelrik-primary);
          --bs-btn-border-color: var(--caelrik-primary);
          --bs-btn-hover-color: var(--caelrik-on-primary);
          --bs-btn-hover-bg: var(--caelrik-primary-hover);
          --bs-btn-hover-border-color: var(--caelrik-primary-hover);
          --bs-btn-active-color: var(--caelrik-on-primary);
          --bs-btn-active-bg: var(--caelrik-primary-hover);
          --bs-btn-active-border-color: var(--caelrik-primary-hover);
          --bs-btn-disabled-color: var(--caelrik-on-primary);
          --bs-btn-disabled-bg: var(--caelrik-primary);
          --bs-btn-disabled-border-color: var(--caelrik-primary);
          --bs-btn-focus-shadow-rgb: var(--bs-primary-rgb);
        }

        .btn-outline-primary {
          --bs-btn-color: var(--caelrik-primary);
          --bs-btn-border-color: var(--caelrik-primary);
          --bs-btn-hover-color: var(--caelrik-on-primary);
          --bs-btn-hover-bg: var(--caelrik-primary);
          --bs-btn-hover-border-color: var(--caelrik-primary);
          --bs-btn-active-color: var(--caelrik-on-primary);
          --bs-btn-active-bg: var(--caelrik-primary-hover);
          --bs-btn-active-border-color: var(--caelrik-primary-hover);
          --bs-btn-focus-shadow-rgb: var(--bs-primary-rgb);
        }

        .form-control,
        .form-select,
        .form-check-input {
          border-color: var(--caelrik-input-border);
        }

        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
          border-color: var(--caelrik-primary);
          box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }

        .form-check-input:checked {
          background-color: var(--caelrik-primary);
          border-color: var(--caelrik-primary);
        }

        .nav-pills {
          --bs-nav-pills-link-active-bg: var(--caelrik-primary);
          --bs-nav-pills-link-active-color: var(--caelrik-on-primary);
        }

        .pagination {
          --bs-pagination-active-bg: var(--caelrik-primary);
          --bs-pagination-active-border-color: var(--caelrik-primary);
          --bs-pagination-active-color: var(--caelrik-on-primary);
          --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }
        CSS;

    public static function render(Theme $theme): string
    {
        $css = sprintf("/* Caelrik UI theme \"%s\". Load it after caelrik.css. */\n", $theme->getName());

        $css .= self::block(":root,\n[data-bs-theme=light]", self::palette($theme, 'light'));
        $css .= "\n" . self::block('[data-bs-theme=dark]', self::palette($theme, 'dark'));

        $shape = [];
        if ($theme->getRadius() !== null) {
            $radius = self::number($theme->getRadius()) . 'rem';
            $shape['--bs-border-radius'] = $radius;
            $shape['--caelrik-radius'] = $radius;
        }
        if ($theme->getFontFamily() !== null) {
            $shape['--bs-body-font-family'] = $theme->getFontFamily();
        }
        if ($theme->getShadow() !== null) {
            $shape['--caelrik-surface-shadow'] = self::SHADOWS[$theme->getShadow()];
        }
        if ($shape !== []) {
            $css .= "\n" . self::block(':root', $shape);
        }

        return $css . "\n" . self::BOOTSTRAP_BINDINGS . "\n";
    }

    /** @return array<string, string> */
    private static function palette(Theme $theme, string $mode): array
    {
        $color = $theme->palette($mode);
        $rgb = static fn (string $token): string => $theme->rgb($mode, $token);

        return [
            '--bs-primary' => $color['primary'],
            '--bs-primary-rgb' => $rgb('primary'),
            '--bs-link-color' => $color['primary'],
            '--bs-link-color-rgb' => $rgb('primary'),
            '--bs-link-hover-color' => $color['primaryHover'],
            '--bs-link-hover-color-rgb' => $rgb('primaryHover'),
            '--bs-primary-text-emphasis' => $color['primaryHover'],
            '--bs-body-bg' => $color['bodyBg'],
            '--bs-body-bg-rgb' => $rgb('bodyBg'),
            '--bs-body-color' => $color['bodyColor'],
            '--bs-body-color-rgb' => $rgb('bodyColor'),
            '--bs-secondary-color' => $color['muted'],
            '--bs-tertiary-bg' => $color['surface'],
            '--bs-tertiary-bg-rgb' => $rgb('surface'),
            '--bs-border-color' => $color['border'],
            '--bs-focus-ring-color' => sprintf('rgba(%s, 0.25)', $rgb('primary')),
            '--bs-form-invalid-color' => $color['danger'],
            '--bs-form-invalid-border-color' => $color['danger'],
            '--bs-danger-text-emphasis' => $color['danger'],
            '--caelrik-primary' => $color['primary'],
            '--caelrik-on-primary' => $color['onPrimary'],
            '--caelrik-primary-hover' => $color['primaryHover'],
            '--caelrik-input-border' => $color['inputBorder'],
        ];
    }

    /** @param array<string, string> $properties */
    private static function block(string $selector, array $properties): string
    {
        $css = $selector . " {\n";
        foreach ($properties as $name => $value) {
            $css .= sprintf("  %s: %s;\n", $name, $value);
        }

        return $css . "}\n";
    }

    /** 0.5 => "0.5", 1.0 => "1", 0.375 => "0.375" (the same text as JavaScript's String(number)). */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
