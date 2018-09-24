<?php

namespace App\Http\Controllers;

use App\Log;
use App\SkipassServiceInterface;
use Illuminate\Http\Request;

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


        $this->skipassService->setProject($data['project']);

        if (array_key_exists('ticket', $data)) {
            $this->skipassService->setTicket($data['ticket']);
        } elseif (array_key_exists('wtp', $data)) {
            $this->skipassService->setWtp($data['wtp']);

        }

        try {
            $this->skipassService->maybeUpdateLogs();
        } catch (\Exception $e) {
            return self::responseFor($e);
        }

        return Log::where('ticket_id', $this->skipassService->getTicket()->id)->get();
    }

    /**
     * @param \Throwable $throwable
     * @return mixed
     */
    private static function responseFor($throwable)
    {
        $code = $throwable->getCode() == 0 ? 500 : $throwable->getCode();
        return response()->json(['error' => $throwable->getMessage()], $code);
    }
}
