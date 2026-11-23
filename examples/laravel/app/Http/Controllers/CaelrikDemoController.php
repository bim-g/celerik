<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Caelrik\Ui\Adapters\Laravel\EloquentDataSource;
use Caelrik\Ui\Components\Chart;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CaelrikDemoController extends Controller
{
    public function grid(): View
    {
        return view('caelrik.grid', ['grid' => $this->usersGrid()]);
    }

    /**
     * The same Grid definition renders the table and answers its requests,
     * so only declared columns can be sorted, searched or returned.
     */
    public function gridData(Request $request): JsonResponse
    {
        return response()->json(
            $this->usersGrid()->respond(new EloquentDataSource(User::query()), $request->query()),
        );
    }

    public function chart(): View
    {
        $perDay = User::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get(['created_at'])
            ->countBy(static fn (User $user): string => $user->created_at->format('Y-m-d'));

        $rows = [];
        foreach (range(13, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->format('Y-m-d');
            $rows[] = ['day' => $day, 'signups' => $perDay[$day] ?? 0];
        }

        $chart = Chart::make('signups')
            ->type('line')
            ->title('Sign-ups over the last 14 days')
            ->fromRows($rows, 'day', ['signups' => 'Sign-ups'])
            ->options(['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]])
            ->height(320);

        return view('caelrik.chart', ['chart' => $chart]);
    }

    public function form(Request $request): View
    {
        $form = Form::make('contact')
            ->action(route('caelrik.form.submit'))
            ->csrf(csrf_token())
            ->add(
                Field::text('name')->required()->maxLength(80),
                Field::email('email')->required(),
                Field::tel('phone')->mask('(999) 999-9999')->help('Optional.'),
                Field::select('topic', ['sales' => 'Sales', 'support' => 'Support'])
                    ->placeholder('Choose...')
                    ->required(),
                Field::textarea('message')->required()->minLength(10),
            )
            ->errors($request->session()->get('errors')?->getBag('default')->toArray() ?? [])
            ->values($request->old())
            ->submit('Send');

        return view('caelrik.form', ['form' => $form]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'topic' => ['required', 'in:sales,support'],
            'message' => ['required', 'string', 'min:10'],
        ]);

        return redirect()->route('caelrik.form')->with('status', 'Thanks, your message was sent.');
    }

    private function usersGrid(): Grid
    {
        return Grid::make('users')
            ->caption('Registered users')
            ->url(route('caelrik.grid.data'))
            ->addColumn(
                Column::make('id', 'ID')->searchable(false),
                Column::make('name'),
                Column::make('email', 'E-mail'),
                Column::make('created_at', 'Joined')->searchable(false),
            )
            ->pageSize(25)
            ->export('copy', 'csv', 'print')
            ->options(['order' => [[0, 'desc']]]);
    }
}
