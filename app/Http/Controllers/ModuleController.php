<?php

namespace App\Http\Controllers;

use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index(CurrentOutlet $currentOutlet)
    {
        return view('modules.index', ['outlet' => $currentOutlet->get()]);
    }

    public function select(Request $request)
    {
        $data = $request->validate(['module' => ['required', Rule::in(['liquor', 'food'])]]);
        $request->session()->put('active_module', $data['module']);

        return redirect()->route($data['module'] === 'food' ? 'food.dashboard' : 'dashboard');
    }
}
