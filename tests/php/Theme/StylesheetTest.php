<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Theme;

use Caelrik\Ui\Theme\Stylesheet;
use Caelrik\Ui\Theme\Theme;
use PHPUnit\Framework\TestCase;

final class StylesheetTest extends TestCase
{
    public function testWritesBothPalettesWithBootstrapsDerivedProperties(): void
    {
        $css = Theme::preset('bootstrap')->name('Acme')->color('light', 'primary', '#0b5394')->shadow('elevated')->toCss();

        self::assertStringStartsWith(
            "/* Caelrik UI theme \"Acme\". Load it after caelrik.css. */\n"
            . ":root,\n[data-bs-theme=light] {\n  --bs-primary: #0b5394;\n  --bs-primary-rgb: 11, 83, 148;\n",
            $css,
        );
        self::assertStringContainsString("[data-bs-theme=dark] {\n  --bs-primary: #6ea8fe;\n", $css);
        self::assertStringContainsString('  --bs-link-color: #0b5394;', $css);
        self::assertStringContainsString('  --bs-focus-ring-color: rgba(11, 83, 148, 0.25);', $css);
        self::assertStringContainsString('  --bs-form-invalid-color: #dc3545;', $css);
        self::assertStringContainsString('  --caelrik-input-border: #6c757d;', $css);
        self::assertStringContainsString("  --caelrik-radius: 0.375rem;\n", $css);
        self::assertStringContainsString('  --caelrik-surface-shadow: ' . Stylesheet::SHADOWS['elevated'] . ';', $css);
        self::assertStringNotContainsString('--bs-body-font-family', $css);
        self::assertStringNotContainsString('--caelrik-focus-ring', $css);
    }

    public function testTheBootstrapBaseBindsComponentsCompiledWithBootstrapsBlue(): void
    {
        $css = Theme::preset('bootstrap')->toCss();

        self::assertStringContainsString('Load it after caelrik.css. */', $css);
        self::assertStringContainsString(".btn-primary {\n  --bs-btn-color: var(--caelrik-on-primary);", $css);
        self::assertStringContainsString('.pagination {', $css);
        self::assertStringContainsString(".form-check-input:checked {\n  background-color: var(--caelrik-primary);", $css);
        // The calendar's flat toolbar and its events follow --bs-primary and
        // --caelrik-on-primary through caelrik.css: the theme does not fill its buttons.
        self::assertStringNotContainsString('--fc-button', $css);
        self::assertStringContainsString("  --caelrik-on-primary: #ffffff;\n", $css);
        self::assertStringEndsWith("}\n", $css);
    }

    public function testAFontStackIsWrittenAsItIs(): void
    {
        $css = Theme::preset('bootstrap')->fontFamily("ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji'")->toCss();

        self::assertStringContainsString(
            "  --bs-body-font-family: ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji';",
            $css,
        );
    }

    public function testShapeTokensAreOptionalAndNumbersAreWrittenLikeJavaScript(): void
    {
        $theme = Theme::preset('bootstrap')->radius(null)->shadow(null);
        self::assertStringNotContainsString(":root {\n", $theme->toCss());

        self::assertStringContainsString('--caelrik-radius: 1rem;', $theme->radius(1.0)->toCss());
        self::assertStringContainsString('--caelrik-radius: 0rem;', $theme->radius(0.0)->toCss());
        self::assertStringContainsString('--caelrik-radius: 0.125rem;', $theme->radius(0.125)->toCss());
        self::assertStringContainsString('--caelrik-radius: 1.5rem;', $theme->radius(1.50)->toCss());
    }

    public function testEveryDeclarationHoldsOnlyValidatedValues(): void
    {
        // Theme rejects anything else (see ThemeTest); here the output's shape is checked.
        foreach (Theme::PRESETS as $preset) {
            $css = Theme::preset($preset)->name('Acme 2.0')->toCss();
            $body = substr($css, strpos($css, '*/') + 2);

            self::assertSame(1, substr_count($css, '*/'), 'The name cannot close the comment.');
            self::assertStringNotContainsString('<', $css);
            self::assertStringNotContainsString('@', $body);
            self::assertSame(substr_count($body, '{'), substr_count($body, '}'));
        }
    }
}
