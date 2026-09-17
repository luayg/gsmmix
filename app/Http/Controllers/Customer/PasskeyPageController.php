<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class PasskeyPageController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('customer.passkeys',[
            'passkeys'=>$request->user()->passkeys()->latest()->get(),
        ]);
    }
}
