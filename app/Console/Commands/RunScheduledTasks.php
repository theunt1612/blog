<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Carbon\Carbon;

class RunScheduledTasks extends Command
{
    protected $signature = 'run-scheduled-tasks';
    protected $description = 'Run scheduled tasks from database';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Lấy các task chưa chạy hoặc đã lỗi lần trước
        $tasks = DB::table('scheduled_tasks')
                    ->where('status', 'pending')
                    ->orWhere('status', 'failed')
                    ->where('schedule_time', '<=', Carbon::now())
                    ->get();

        foreach ($tasks as $task) {
            try {
                // Gán trạng thái đang chạy
                DB::table('scheduled_tasks')->where('id', $task->id)->update(['status' => 'running']);

                // Thực thi lệnh
                $output = shell_exec($task->command);
                DB::table('task_logs')->insert([
                    'task_id' => $task->id,
                    'output' => $output,
                    'status' => 'success'
                ]);

                // Update trạng thái thành hoàn thành
                DB::table('scheduled_tasks')->where('id', $task->id)->update(['status' => 'completed']);
            } catch (\Exception $e) {
                // Ghi log lỗi
                DB::table('task_logs')->insert([
                    'task_id' => $task->id,
                    'output' => $e->getMessage(),
                    'status' => 'error'
                ]);

                // Update trạng thái thành lỗi
                DB::table('scheduled_tasks')->where('id', $task->id)->update(['status' => 'failed']);
            }
        }

        $this->info('Scheduled tasks executed.');
    }
}