<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Modules\Installer\Http\InteractsWithInstallerLocale;
use App\Modules\Installer\Services\Requirements;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RequirementsController extends Controller
{
    use InteractsWithInstallerLocale;

    public function __construct(private Requirements $requirements) {}

    public function index(Request $request)
    {
        $lang = $this->resolveWizardLocale($request);
        $evaluation = $this->requirements->evaluate();

        return view('installer::requirements', [
            'checks' => $evaluation['checks'],
            'ready' => $evaluation['ready'],
            'step' => 1,
            'lang' => $lang,
        ]);
    }
}
