<?php

namespace App\Http\Controllers;

use App\Support\CurrentOutlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function switch(Request $request, CurrentOutlet $currentOutlet)
    {
        $data = $request->validate(['outlet_id' => ['required', 'integer']]);
        $outlet = $request->user()->outlets()->wherePivot('is_active', true)
            ->where('outlets.organisation_id', $request->user()->organisation_id)->findOrFail($data['outlet_id']);
        $request->session()->put('active_outlet_id', $outlet->id);
        $currentOutlet->forget();

        return redirect()->route('dashboard')->with('status', 'Switched to '.$outlet->name.'.');
    }
}
