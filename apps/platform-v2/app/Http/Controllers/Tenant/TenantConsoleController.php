<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TenantConsoleController extends Controller
{
    public function dashboard(): View
    {
        return view('tenant.dashboard', ['organization' => app('current-organization'), 'admin' => request()->user('tenant')]);
    }
}
