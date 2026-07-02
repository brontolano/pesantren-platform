<?php

namespace Modules\Payroll\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payroll\Models\SlipGaji;

/** Dipancarkan saat slip gaji difinalisasi & dijurnal. */
class SlipGajiFinal
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SlipGaji $slip) {}
}
