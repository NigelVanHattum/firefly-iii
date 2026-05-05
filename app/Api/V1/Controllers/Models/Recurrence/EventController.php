<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Models\Recurrence;

use Carbon\Carbon;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\RecurrenceRepetition;
use FireflyIII\Repositories\Recurring\RecurringRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
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

    /**
     * @throws FireflyException
     */
    public function events(Request $request): JsonResponse
    {
        $occurrences                   = [];
        $return                        = [];
        $start                         = Carbon::createFromFormat('Y-m-d', $request->get('start'));
        $end                           = Carbon::createFromFormat('Y-m-d', $request->get('end'));
        $firstDate                     = Carbon::createFromFormat('Y-m-d', $request->get('first_date'));
        $endDate                       = '' !== (string) $request->get('end_date') ? Carbon::createFromFormat('Y-m-d', $request->get('end_date')) : null;
        $endsAt                        = (string) $request->get('ends');
        $repetitionType                = explode(',', (string) $request->get('type'))[0];
        $repetitions                   = (int) $request->get('reps');
        $weekend                       = (int) $request->get('weekend');
        $repetitionMoment              = '';
        $skip                          = (int) $request->get('skip');
        $skip                          = $skip < 0 || $skip > 31 ? 0 : $skip;
        $weekend                       = $weekend < 1 || $weekend > 4 ? 1 : $weekend;

        if (!$endDate instanceof Carbon) {
            $endDate = now()->addYear();
        }

        if (!$start instanceof Carbon || !$end instanceof Carbon || !$firstDate instanceof Carbon) {
            return response()->json();
        }

        $start->startOfDay();

        if ($firstDate->gt($end)) {
            return response()->json();
        }
        $actualStart                   = clone $firstDate;

        if ('weekly' === $repetitionType || 'monthly' === $repetitionType) {
            $repetitionMoment = explode(',', (string) $request->get('type'))[1] ?? '1';
        }
        if ('ndom' === $repetitionType) {
            $repetitionMoment = str_ireplace('ndom,', '', $request->get('type'));
        }
        if ('yearly' === $repetitionType) {
            $repetitionMoment = explode(',', (string) $request->get('type'))[1] ?? '2025-01-01';
        }
        $actualStart->startOfDay();
        $repetition                    = new RecurrenceRepetition();
        $repetition->repetition_type   = $repetitionType;
        $repetition->repetition_moment = $repetitionMoment;
        $repetition->repetition_skip   = $skip;
        $repetition->weekend           = $weekend;
        $actualEnd                     = clone $end;

        if ('until_date' === $endsAt) {
            $actualEnd   = $endDate;
            $occurrences = $this->recurring->getOccurrencesInRange($repetition, $actualStart, $actualEnd);
        }
        if ('times' === $endsAt) {
            $occurrences = $this->recurring->getXOccurrences($repetition, $actualStart, $repetitions);
        }
        if ('times' !== $endsAt && 'until_date' !== $endsAt) {
            $occurrences = $this->recurring->getOccurrencesInRange($repetition, $actualStart, $actualEnd);
        }

        /** @var Carbon $current */
        foreach ($occurrences as $current) {
            if ($current->gte($start)) {
                $event    = [
                    'id'        => $repetitionType.$firstDate->format('Ymd'),
                    'title'     => 'X',
                    'allDay'    => true,
                    'start'     => $current->format('Y-m-d'),
                    'end'       => $current->format('Y-m-d'),
                    'editable'  => false,
                    'rendering' => 'background',
                ];
                $return[] = $event;
            }
        }

        return response()->json($return);
    }
}
