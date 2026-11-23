<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Caelrik\Ui\Components\Chart;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Tabs;
use Caelrik\Ui\Components\ThemeBuilder;
use Caelrik\Ui\Theme\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Theme builder: a development tool, only available when APP_ENV is "local". Saving
 * writes storage/app/caelrik-theme.json (keep it in git) and public/css/caelrik-theme.css,
 * which the layout loads (see layout.blade.php). In CI, rebuild the stylesheet with
 * vendor/bin/caelrik-theme build storage/app/caelrik-theme.json public/css/caelrik-theme.css.
 */
final class CaelrikThemeController extends Controller
{
    public function show(): View
    {
        abort_unless(app()->isLocal(), 404);

        return view('caelrik.theme-builder', [
            'builder' => ThemeBuilder::make('builder')
                ->theme(self::saved() ?? Theme::preset('bootstrap'))
                ->saveUrl(route('caelrik.theme.save'))
                ->csrf(csrf_token())
                ->preview(...self::previewComponents()),
        ]);
    }

    // The CSRF token is checked by Laravel's middleware (X-CSRF-TOKEN header, or the _token field).
    public function save(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        try {
            $theme = ThemeBuilder::fromRequest($request->request->all(), $request->getContent());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        file_put_contents(storage_path('app/caelrik-theme.json'), $theme->toJson());
        if (!is_dir(public_path('css'))) {
            mkdir(public_path('css'), 0755, true);
        }
        file_put_contents(public_path('css/caelrik-theme.css'), $theme->toCss());

        return $request->expectsJson()
            ? response()->json(['data' => $theme->toArray(), 'meta' => []])
            : redirect()->route('caelrik.theme');
    }

    /** The saved theme, used by the layout too. */
    public static function saved(): ?Theme
    {
        $file = storage_path('app/caelrik-theme.json');

        return is_file($file) ? Theme::fromJson((string) file_get_contents($file)) : null;
    }

    /** @return list<Form|Grid|Tabs|Chart> */
    private static function previewComponents(): array
    {
        return [
            Form::make('preview-form')
                ->add(
                    Field::text('name')->required(),
                    Field::email('email')->required()->help('We never share your e-mail.'),
                    Field::checkbox('terms', 'I accept the terms'),
                )
                ->errors(['email' => 'This e-mail is already registered.'])
                ->submit('Create account'),
            Grid::make('preview-grid')
                ->caption('Users')
                ->column('name')
                ->column('email', 'E-mail')
                ->pageSize(2)
                ->rows([
                    ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
                    ['name' => 'Alan Turing', 'email' => 'alan@example.com'],
                    ['name' => 'Grace Hopper', 'email' => 'grace@example.com'],
                ]),
            Tabs::make('preview-tabs')
                ->label('Settings')
                ->tab('profile', 'Profile', 'Your name and e-mail.')
                ->tab('security', 'Security', 'Change your password.'),
            Chart::make('preview-chart')
                ->type('line')
                ->title('Revenue')
                ->labels(['Jan', 'Feb', 'Mar'])
                ->dataset('North', [12, 19, 15])
                ->dataset('South', [9, 11, 17])
                ->height(200),
        ];
    }
}
