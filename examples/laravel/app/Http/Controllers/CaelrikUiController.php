<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Modal;
use Caelrik\Ui\Components\Notification;
use Caelrik\Ui\Components\Tabs;
use Caelrik\Ui\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tabs, a modal with a form, and flash messages shown as notifications.
 */
final class CaelrikUiController extends Controller
{
    public function show(Request $request): View
    {
        $errors = $request->session()->get('errors')?->getBag('default')->toArray() ?? [];

        $form = Form::make('newsletter')
            ->action(route('caelrik.ui.subscribe'))
            ->csrf(csrf_token())
            ->add(Field::email('email', 'E-mail address')->required()->help('One e-mail per month, no spam.'))
            ->errors($errors)
            ->values($request->old())
            ->submit(null);

        return view('caelrik.ui', [
            'tabs' => Tabs::make('features')
                ->label('Features')
                ->tab('grid', 'Grid', 'Server-side paging, sorting, search and export.')
                ->tab('forms', 'Forms', Html::raw(
                    '<p class="mb-0">Validation, uploads and a <strong>rich text editor</strong>.</p>',
                ))
                ->tab('calendar', 'Calendar', 'Month, week, day and list views.')
                ->rememberInHash(),
            'modal' => Modal::make('newsletter-modal')
                ->title('Subscribe to the newsletter')
                ->body($form)
                ->cancelButton()
                ->button('Subscribe', 'primary', attributes: ['type' => 'submit', 'form' => 'newsletter'])
                ->staticBackdrop()
                // Reopen the modal when the server rejected the form.
                ->open($errors !== []),
            'notification' => Notification::make('flash')
                ->fromFlash($request->session()->only(['success', 'status', 'error', 'warning', 'info'])),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Messages are shown as text: user data in them is safe.
        return redirect()->route('caelrik.ui')->with('success', $data['email'] . ' is now subscribed.');
    }
}
