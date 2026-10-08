<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Modules\Installer\Services\Requirements;
use Illuminate\Routing\Controller;

class RequirementsController extends Controller
{
    public function __construct(private Requirements $requirements) {}

    public function index()
    {
        $evaluation = $this->requirements->evaluate();

        return view('installer::requirements', [
            'checks' => $evaluation['checks'],
            'ready' => $evaluation['ready'],
            'step' => 1,
        ]);
    }
}
