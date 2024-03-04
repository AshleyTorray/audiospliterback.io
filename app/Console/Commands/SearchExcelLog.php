<?php

namespace App\Console\Commands;

use App\Imports\ExcelAudioLogImport;
use App\Models\ExcelAudioLog;
use Illuminate\Console\Command;
use Spatie\Watcher\Watch;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;


class SearchExcelLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:excel-log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $excelLogPath = env('EXCEL_LOG');
        // $excelPath = Storage::disk('public')->path($excelLogPath);
        $excelPath = $excelLogPath;
        $this->line("*******".$excelPath);

        
        $this->line("<info>!Note: Auto check the uploaded Excel log files is runnning </info>=============");
        $this->checkExistingExcelFile();
        Watch::path($excelPath)->onAnyChange(function (string $type, string $path) {
            $filename = basename($path);
            $existingFile = ExcelAudioLog::where('file_name', $filename)->first();
            if($type == Watch::EVENT_TYPE_FILE_CREATED)
            {
                if(!$existingFile)
                {
                    $this->line("new Excel log file has uploaded. inserting into database......");
                    Excel::import(new ExcelAudioLogImport($path), $path);
                    $this->line("new <info>{$path}</info> file inserted into Database");
                }
            }
            if($type == Watch::EVENT_TYPE_FILE_DELETED)
            {
                if($existingFile)
                {
                    ExcelAudioLog::where('file_path', $path)->delete();
                }
                $this->line("<info> {$path} </info>file is deleted.");
            }
        })->start();

    }

    public function checkExistingExcelFile(){
        
        // get the Excel log from the database
        $ExcelLogs = ExcelAudioLog::where('precek', '!=', 0)->orderBy('order_no', 'asc')->get();
        
        foreach($ExcelLogs as $ExcelLog)
        {
            
            $this->line("excel file log====={$ExcelLog->accounting_day}");
        }
        
    }
}
