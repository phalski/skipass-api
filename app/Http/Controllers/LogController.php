<?php

namespace App\Http\Controllers;

use App\Log;
use App\SkipassServiceInterface;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;

class LogController extends Controller
{
    private $skipassService;

    /**
     * Create a new controller instance.
     *
     * @param SkipassServiceInterface $skipassService
     * @return void
     */
    public function __construct(SkipassServiceInterface $skipassService)
    {
        $this->skipassService = $skipassService;
    }

    public function show(Request $request)
    {
        $data = $this->validate($request, [
            'project' => 'required|string|filled|max:32',
            'ticket' => [
                ['required_without', 'wtp'],
                'string',
                ['regex', '/^(\d{1,3})-(\d{1,3})-(\d{1,5})\z/']
            ],
            'wtp' => [
                ['required_without', 'ticket'],
                'string',
                ['regex', '/^([0-9A-Za-z]{8})-([0-9A-Za-z]{3})-([0-9A-Za-z]{3})\z/']
            ]
        ]);

        try {
            $this->skipassService->setProject($data['project']);
        } catch (\Exception $e) {
            return self::responseFor($e);
        }

        if (array_key_exists('ticket', $data)) {
            try {
                $this->skipassService->setTicket($data['ticket']);
            } catch (\Exception $e) {
                return self::responseFor($e);
            }
        } elseif (array_key_exists('wtp', $data)) {
            try {
                $this->skipassService->setWtp($data['wtp']);
            } catch (\Exception $e) {
                return self::responseFor($e);
            }
        }

        $this->skipassService->maybeUpdateLogs();

        return Log::with(['ticket', 'lift'])->where('ticket_id', $this->skipassService->getTicket()->id)->get();
    }

    private function maybeUpdateLogs() {
        $ticket = $this->skipassService->getTicket();
        if (is_null($ticket)) {
            // wtp mode -> always fetch all
            try {
                echo 'update logs';
                $this->skipassService->updateLogs(-1, 0);
            } catch (\Exception $e) {
                return self::responseFor($e);
            }
        } elseif (is_null($ticket->last_day_at) || $ticket->updated_at->diff($ticket->last_day_at)->m < 5 )  {
            // we have a last day set and the difference to the latest update is shorter than 6 months -> update count
            // and fetch only the missing logs

            try {
                echo 'update count';
                $this->skipassService->updateDayCount();
            } catch (\Exception $e) {
                return self::responseFor($e);
            }

            $ticket = $this->skipassService->getTicket();
            $offset = is_null($ticket->last_day_n) ? 0 : $ticket->last_day_n + 1;

            // only update when new days present
            if ($offset == 0 || $offset < $ticket->day_count) {
                try {
                    echo 'update logs';
                    $this->skipassService->updateLogs(-1, $offset);
                } catch (\Exception $e) {
                    return self::responseFor($e);
                }
            }
        }
    }


    /**
     * @param \Throwable $throwable
     * @return ResponseInterface
     */
    private static function responseFor($throwable): ResponseInterface
    {
        $code = $throwable->getCode() == 0 ? 500 : $throwable->getCode();
        return response()->json(['error' => $throwable->getMessage()], $code);
    }
}
