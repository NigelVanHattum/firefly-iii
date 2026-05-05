<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Models\Recurrence;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Repositories\Recurring\RecurringRepositoryInterface;
use FireflyIII\Support\Facades\Steam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SuggestController extends Controller
{
    private RecurringRepositoryInterface $recurring;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            $this->recurring = app(RecurringRepositoryInterface::class);
            $this->recurring->setUser(auth()->user());

            return $next($request);
        });
    }

    public function suggest(Request $request): JsonResponse
    {
        $string      = '' === (string) $request->get('date') ? Carbon::now()->format('Y-m-d') : (string) $request->get('date');
        $today       = today(config('app.timezone'))->startOfDay();

        try {
            $date = Carbon::createFromFormat('Y-m-d', $string, config('app.timezone'));
        } catch (InvalidFormatException) {
            $date = Carbon::today(config('app.timezone'));
        }
        if (!$date instanceof Carbon) {
            return response()->json();
        }
        $date->startOfDay();
        $preSelected = (string) $request->get('pre_select');
        $locale      = Steam::getLocale();

        Log::debug(sprintf('date = %s, today = %s. date > today? %s', $date->toAtomString(), $today->toAtomString(), var_export($date > $today, true)));
        Log::debug(sprintf('past = true? %s', var_export('true' === (string) $request->get('past'), true)));

        $result      = [];
        if ($date > $today || 'true' === (string) $request->get('past')) {
            Log::debug('Will fill dropdown.');
            $weekly     = sprintf('weekly,%s', $date->dayOfWeekIso);
            $monthly    = sprintf('monthly,%s', $date->day);
            $dayOfWeek  = (string) trans(sprintf('config.dow_%s', $date->dayOfWeekIso));
            $ndom       = sprintf('ndom,%s,%s', $date->weekOfMonth, $date->dayOfWeekIso);
            $yearly     = sprintf('yearly,%s', $date->format('Y-m-d'));
            $yearlyDate = $date->isoFormat((string) trans('config.month_and_day_no_year_js', [], $locale));
            $result     = [
                'daily'  => ['label' => (string) trans('firefly.recurring_daily'), 'selected' => str_starts_with($preSelected, 'daily')],
                $weekly  => [
                    'label'    => (string) trans('firefly.recurring_weekly', ['weekday' => $dayOfWeek]),
                    'selected' => str_starts_with($preSelected, 'weekly'),
                ],
                $monthly => [
                    'label'    => (string) trans('firefly.recurring_monthly', ['dayOfMonth' => $date->day]),
                    'selected' => str_starts_with($preSelected, 'monthly'),
                ],
                $ndom    => [
                    'label'    => (string) trans('firefly.recurring_ndom', ['weekday' => $dayOfWeek, 'dayOfMonth' => $date->weekOfMonth]),
                    'selected' => str_starts_with($preSelected, 'ndom'),
                ],
                $yearly  => [
                    'label'    => (string) trans('firefly.recurring_yearly', ['date' => $yearlyDate]),
                    'selected' => str_starts_with($preSelected, 'yearly'),
                ],
            ];
        }
        Log::debug('Dropdown is', $result);

        return response()->json($result);
    }
}
