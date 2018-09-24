<?php

namespace App\Http\Controllers;

use App\Log;
use App\Project;
use App\SkipassServiceInterface;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;

class ProjectController extends Controller
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

    public function index() {
        return Project::all();
    }

    public function show($name)
    {
        $project = Project::where('name', $name)->first();
        if (!is_null($project)) {
            return $project;
        }

        try {
            $this->skipassService->setProject($name);
        } catch (\Exception $e) {
            return self::responseFor($e);
        }

        return $this->skipassService->getProject();
    }

    /**
     * @param \Throwable $throwable
     * @return ResponseInterface
     */
    private static function responseFor($throwable)
    {
        $code = $throwable->getCode() == 0 ? 500 : $throwable->getCode();
        return response()->json(['error' => $throwable->getMessage()], $code);
    }
}
