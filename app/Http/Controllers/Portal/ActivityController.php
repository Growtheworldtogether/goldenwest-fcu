<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\ActivityQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    private const PER_PAGE = 25;
    private const EXPORT_LIMIT = 5000;

    public function index(Request $request)
    {
        return $this->show($request, null, 'portal.activity');
    }

    public function account(Request $request, Account $account)
    {
        Gate::authorize('view', $account);

        return $this->show($request, $account, 'portal.account');
    }

    public function export(Request $request, ?Account $account = null): StreamedResponse
    {
        if ($account) {
            Gate::authorize('view', $account);
        }

        $activity = new ActivityQuery($request->user(), $account);
        $rows = $activity->rows($activity->query($request)->limit(self::EXPORT_LIMIT)->get());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Account', 'Description', 'Type', 'Status', 'Amount', 'Balance after', 'Reference']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['tx']->effective_date->toDateString(),
                    $this->safe($r['account']->nickname.' '.$r['account']->masked()),
                    $this->safe($r['title']),
                    $r['tx']->type->value,
                    $r['tx']->status->value,
                    number_format($r['cents'] / 100, 2, '.', ''),
                    $r['balance_after'] ? number_format($r['balance_after']->cents / 100, 2, '.', '') : '',
                    $r['tx']->reference,
                ]);
            }
            fclose($out);
        }, 'golden-west-activity-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function show(Request $request, ?Account $account, string $view)
    {
        $activity = new ActivityQuery($request->user(), $account);
        $page = $activity->query($request)->paginate(self::PER_PAGE)->withQueryString();

        return view($view, [
            'account' => $account ? $activity->accounts[$account->id] : null,
            'accounts' => $activity->accounts,
            'rows' => $activity->rows($page),
            'page' => $page,
            'groups' => ActivityQuery::GROUPS,
            'filters' => $request->only(['q', 'type', 'direction', 'status', 'from', 'to', 'account']),
        ]);
    }

    /** Stop spreadsheet programs from running text that starts like a formula. */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
