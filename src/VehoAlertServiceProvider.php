<?php

namespace Veho\SystemAlert;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class VehoAlertServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/veho-alert.php',
            'veho-alert'
        );
    }

    public function boot()
    {
        Event::listen(ScheduledTaskFailed::class, function ($event) {
            try {
                $task = isset($event->task) ? $event->task : null;
                $name = 'scheduled job';
                if ($task && method_exists($task, 'getSummaryForDisplay')) {
                    $name = $task->getSummaryForDisplay();
                } elseif ($task && isset($task->command)) {
                    $name = $task->command;
                }
                $message = isset($event->exception) ? $event->exception->getMessage() : 'failed';
                VehoAlert::send('scheduled_job', $name . ': ' . $message);
            } catch (Throwable $e) {
            }
        });

        Event::listen(JobFailed::class, function ($event) {
            try {
                $name = 'queue job';
                if (isset($event->job) && method_exists($event->job, 'resolveName')) {
                    $name = $event->job->resolveName();
                }
                $message = isset($event->exception) ? $event->exception->getMessage() : 'failed';
                VehoAlert::send('queue_job', $name . ': ' . $message);
            } catch (Throwable $e) {
            }
        });
    }
}
