<?php

use App\Jobs\CheckPaymentStatusesJob;
use App\Jobs\DailyPaymentsReportJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DailyPaymentsReportJob())
    ->dailyAt('09:00');

Schedule::job(new CheckPaymentStatusesJob())
    ->dailyAt('10:00');
