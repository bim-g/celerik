<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Theme;

use Caelrik\Ui\Theme\Console;
use Caelrik\Ui\Theme\Theme;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/caelrik-console-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    /**
     * @param list<string> $argv
     * @return array{int, string, string}
     */
    private function console(array $argv): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertNotFalse($out);
        self::assertNotFalse($err);
        $code = (new Console($out, $err))->run($argv);
        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    private function themeFile(Theme $theme): string
    {
        file_put_contents($file = $this->dir . '/theme.json', $theme->toJson());

        return $file;
    }

    public function testPresetPrintsThemeJson(): void
    {
        [$code, $out] = $this->console(['preset', 'bootstrap']);

        self::assertSame(Console::OK, $code);
        self::assertEquals(Theme::preset('bootstrap'), Theme::fromJson($out));
    }

    public function testCheckPrintsTheReportAndPassesForAGoodTheme(): void
    {
        [$code, $out] = $this->console(['check', $this->themeFile(Theme::preset('bootstrap'))]);

        self::assertSame(Console::OK, $code);
        self::assertStringContainsString('PASS light: Body text on #ffffff', $out);
        self::assertStringContainsString('PASS dark: Form field borders', $out);
        self::assertStringEndsWith("Bootstrap: every pair meets its minimum.\n", $out);
    }

    public function testCheckFailsOnAContrastProblemAndQuietPrintsOnlyFailures(): void
    {
        $file = $this->themeFile(Theme::preset('bootstrap')->color('light', 'danger', '#dd3545'));

        [$code, $out] = $this->console(['check', $file, '--quiet']);

        self::assertSame(Console::CONTRAST_FAILED, $code);
        self::assertSame(
            "FAIL light: Error messages on #ffffff = 4.49:1 (needs 4.5:1, WCAG 1.4.3)\n"
            . "Bootstrap: 1 pair below the minimum.\n",
            $out,
        );
    }

    public function testBuildWritesTheStylesheet(): void
    {
        $output = $this->dir . '/acme.css';
        [$code, $out] = $this->console(['build', $this->themeFile(Theme::preset('bootstrap')->name('Acme')), $output]);

        self::assertSame(Console::OK, $code);
        self::assertSame(sprintf("Wrote %s.\n", $output), $out);
        self::assertSame(Theme::preset('bootstrap')->name('Acme')->toCss(), file_get_contents($output));
    }

    public function testBuildRefusesAFailingThemeUnlessAllowed(): void
    {
        $file = $this->themeFile(Theme::preset('bootstrap')->color('dark', 'inputBorder', '#495057'));
        $output = $this->dir . '/acme.css';

        [$code, , $err] = $this->console(['build', $file, $output]);
        self::assertSame(Console::CONTRAST_FAILED, $code);
        self::assertStringContainsString('FAIL dark: Form field borders', $err);
        self::assertStringContainsString('Not written', $err);
        self::assertFileDoesNotExist($output);

        [$code] = $this->console(['build', $file, $output, '--allow-contrast-failures']);
        self::assertSame(Console::CONTRAST_FAILED, $code, 'Still reported, for CI.');
        self::assertFileExists($output);
    }

    public function testInvalidInputExitsWithTwo(): void
    {
        file_put_contents($bad = $this->dir . '/bad.json', '{"name": "X", "base": "bulma"}');

        self::assertSame(Console::INVALID, $this->console(['check', $bad])[0]);
        self::assertStringContainsString('Invalid theme', $this->console(['check', $bad])[2]);
        self::assertSame(Console::INVALID, $this->console(['check', $this->dir . '/missing.json'])[0]);
        self::assertSame(Console::INVALID, $this->console(['build', $bad])[0]);
        self::assertSame(Console::INVALID, $this->console(['preset', 'bulma'])[0]);
        // A removed look says why.
        [$code, , $err] = $this->console(['preset', 'material']);
        self::assertSame(Console::INVALID, $code);
        self::assertStringContainsString('The Material look was removed in Caelrik UI 0.2.0', $err);
        self::assertSame(Console::INVALID, $this->console(['deploy'])[0]);
        self::assertSame(Console::INVALID, $this->console(['check', $bad, '--force'])[0]);
        self::assertSame(
            Console::INVALID,
            $this->console(['build', $this->themeFile(Theme::preset('bootstrap')), $this->dir . '/no/such/dir/a.css'])[0],
        );
    }

    public function testHelp(): void
    {
        [$code, $out] = $this->console([]);

        self::assertSame(Console::OK, $code);
        self::assertStringStartsWith('Usage:', $out);
        self::assertSame($out, $this->console(['--help'])[1]);
    }

    public function testTheBinaryRunsAndReturnsTheExitCode(): void
    {
        $bin = dirname(__DIR__, 3) . '/bin/caelrik-theme';
        $file = $this->themeFile(Theme::preset('bootstrap')->color('light', 'danger', '#dd3545'));
        $command = sprintf('%s %s check %s --quiet', escapeshellarg(PHP_BINARY), escapeshellarg($bin), escapeshellarg($file));

        exec($command . ' 2>&1', $output, $code);

        self::assertSame(Console::CONTRAST_FAILED, $code);
        self::assertSame('Bootstrap: 1 pair below the minimum.', end($output));
    }
}
