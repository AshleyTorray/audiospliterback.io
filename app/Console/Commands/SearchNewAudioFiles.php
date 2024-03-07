<?php

namespace App\Console\Commands;

use App\Models\ExcelAudioLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use App\Models\AudioFile;
use Spatie\Watcher\Watch;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Wav;
use FFMpeg\Format\Audio\Mp3;
use App\Imports\ExcelAudioLogImport;
use Maatwebsite\Excel\Facades\Excel;
use Exception;
use FFMpeg\Coordinate\TimeCode;

use DataTime;
class SearchNewAudioFiles extends Command
{
    /**
     * The name and signature of the console command.
     * created by the Ashley Torray at 2024_03_03
     * This code makes you helpful to search the new audio files added by the client
     */

    protected $signature = 'search:new-audio-files';

    protected $description = 'Command description';

    public function handle()
    {
        // $excelLogPath = env('EXCEL_LOG');
        // $excelPath = Storage::disk('public')->path($excelLogPath);
        $excelPath = env('EXCEL_LOG');

      
        $this->initExcelFileLog($excelPath);

        // $audioFilePath = env('AUDIO_PATH');
        // $audioPath = Storage::disk('public')->path($audioFilePath);
        $audioPath = env('AUDIO_PATH');

        $this->initAudioFiles($audioPath);
        $this->line("<info>!Note: Auto check the uploaded audio files is runnning </info>=============");
        
        Watch::path($audioPath)->onAnyChange(function (string $type, string $path) {
            $filename = basename($path);
            $existingFile = AudioFile::where('file_name', $filename)->first();
            if($type == Watch::EVENT_TYPE_FILE_CREATED)
            {
                if(!$existingFile)
                {
                    $this->line("new audio file has uploaded. inserting into database......");
                    $audioProperty = $this->getAudioProperty($path);
                    AudioFile::create([
                        'file_path' => $path,
                        'file_name' => $filename,
                        'duration' => $audioProperty['duration'],
                        'file_size' => $audioProperty['file_size'],
                        'format' => $audioProperty['format'],                       
                    ]);
                    $this->line("new <info>{$path}</info> file inserted into Database");
                    $this->splitAudioFile($path);
                }
                else
                {
                    $this->info("<info>{$filename}</info> has already splited.");
                }
            }
            if($type == Watch::EVENT_TYPE_FILE_DELETED)
            {
                if($existingFile)
                {
                    AudioFile::where('file_path', $path)->delete();
                }
                $this->line("<info> {$path} </info>file is delected.");
            }
            elseif ($type == Watch::EVENT_TYPE_DIRECTORY_CREATED) {
                // Check the contents of the directory to determine if we have multiple files or just one file
                $filesInDir = array_diff(scandir($path), array('.', '..')); // Exclude '.' and '..'
                
                if (count($filesInDir) > 1) {
                    $this->line("Directory with multiple files created at path: <info>{$path}</info>");
                    foreach ($filesInDir as $file) {
                        $filePath = $path . DIRECTORY_SEPARATOR . $file;
                        if (is_file($filePath)) {
                            $audioProperty = $this->getAudioProperty($filePath);
                            AudioFile::create([
                                'file_path' => $filePath,
                                'file_name' => basename($filePath),
                                'duration' => $audioProperty['duration'],
                                'file_size' => $audioProperty['file_size'],
                                'format' => $audioProperty['format'],                       
                            ]);
                            $this->line("new <info>{$filePath}</info> file inserted into Database");
                        }
                    }
                } elseif (count($filesInDir) === 1) {
                    $singleFilePath = realpath($path . DIRECTORY_SEPARATOR . array_pop($filesInDir));
                    if (is_file($singleFilePath)) {
                        $this->line("new audio file has uploaded. inserting into database......");
                            $audioProperty = $this->getAudioProperty($singleFilePath);
                            AudioFile::create([
                                'file_path' => $singleFilePath,
                                'file_name' => basename($singleFilePath),
                                'duration' => $audioProperty['duration'],
                                'file_size' => $audioProperty['file_size'],
                                'format' => $audioProperty['format'],                       
                            ]);
                            $this->line("Directory with a single file created. Processing file: <info>{$singleFilePath}</info>");
                            $this->splitAudioFile($singleFilePath);
                    }
                } else {
                    $this->line("Empty directory created at path: <info>{$path}</info>");
                }
            }
            if($type == Watch::EVENT_TYPE_DIRECTORY_DELETED)
            {
                $this->line("directory that includes <info> {$path} </info>file is delected.");

            }
        })->start();
    }

    //if the excel files exist in the upload log directory,save them in the database
    public function initExcelFileLog(string $excelFileLogPath){

        $this->line("<info>!Note : initializing the Excel files......");
        $allFiles = File::allFiles($excelFileLogPath);
        foreach($allFiles as $file)
        {
            $existingFile = ExcelAudioLog::where('file_name', $file->getFilename())->first();
            // print_r($existingFile);
            
            if(!$existingFile)
            {
                Excel::import(new ExcelAudioLogImport($file->getPathname()), $file->getPathname());
                $this->line("new Excel-log file {$file->getFilename()} saved in database table <info>excel_audio_log</info>.");
            }
            else
            {
                $this->line("<info>========These Excel log file{$file->getFilename()} has been already updated===========</info>");
            }
        }
    }

    // if the audio files exist in the upload audio directory, save them in the database
    public function initAudioFiles($audioFilepath) {
        $this->line("<info>!Note : initializing the audio files......");
        $allFiles = File::allFiles($audioFilepath);
        foreach($allFiles as $file)
        {
            // $this->line("@@@@@@@@File in audio directory{$file}");

            $fileInfo = $this->getAudioFileInfo($file->getFilename());
            $newDateTime =  new \DateTime($fileInfo['date'].' '.$fileInfo['time']);
            $newDateTime = $newDateTime->format("Y-m-d H:i:s");
            $existingFile = AudioFile::where('file_name', $file->getFilename())->first();

            // check if the file exists in database audio_files
            if(!$existingFile)
            {
                $audioProperty = $this->getAudioProperty($file->getPathname());
                AudioFile::create([
                    'file_path' => $file->getPathname(),
                    'file_name' => $file->getFilename(),
                    'duration' => $audioProperty['duration'],
                    'file_size' => $audioProperty['file_size'],
                    'format' => $audioProperty['format']    
                ]);
                $this->line("======{$file->getPathname()} saved in database table aduio_files======");
                $this->splitAudioFile($file->getPathname());
            }
            else{
                
                $this->line("======These Aduio files has been already updated =======");
            }
            
        }
    }

    // get the duration of audio file
    public function getAudioProperty(string $filePath) : array
    {
        
        $ffprobe = \FFMpeg\FFProbe::create();
        $durationSeconds = $ffprobe->format($filePath)->get('duration');
        $sizeBits = $ffprobe->format($filePath)->get('size');
        $audioDuration = gmdate('H:i:s', (int) $durationSeconds);
        // $audioSize = "";
        // if($sizeBits/1024/1024 == 0)
        // {

        //     if($sizeBits/1024 == 0)
        //     {
        //         $audioSize = $sizeBits.'bytes';
        //     }
        //     else
        //     {
        //         $audioSize = $sizeBits/1024 .'.' + fmod($sizeBits, 1024).'Kbytes';
        //     }
        // }
        // else
        // {
        //     $audioSize = $sizeBits/1024/1024 . 'Mbytes';
        // }
        $audioSize = $sizeBits/1024/1024;
        
        $aduioProperty = [
            'duration' => $audioDuration,
            'file_size' => $audioSize,
            'format' => '*.wav'
        ];
        return $aduioProperty;
    }

    //split new added audio file to refer the excellog file
    public function splitAudioFile(string $filePath)
    {

        $audioFileInfo = $this->getAudioFileInfo(basename($filePath));
        $tillDateTime = new \DateTime($audioFileInfo['date'].''.$audioFileInfo['time']);
        $minute  =$tillDateTime->format('i');
        $fromDateTime = $tillDateTime->format('H:i:s');
        if($minute != "00")
        {
            $fromDateTime = $tillDateTime->modify('-1 minute');
            $fromDateTime = $fromDateTime->format('H:i:s');
        }
        if($tillDateTime->format('H') == "23")
        {
            $toDateTime = $tillDateTime->format('H:59:59');    
        }
        else
        {
            $toDateTime = $tillDateTime->modify('+1 hour');
            $toDateTime = $toDateTime->format('H:00:00');
        }
        
        $this->line(" accounting_day---->".$audioFileInfo['date'] .", time------->".$audioFileInfo['time']. " : splited from {$fromDateTime} to {$toDateTime}");
        $matchAudioInfos = ExcelAudioLog::select('order_no', 'precek', 'waiter', 'file_path', 'time', 'closed')->where('accounting_day', '=', $audioFileInfo['date'])->where('precek', '>', $fromDateTime)->where('precek', '<', $toDateTime)->orderBy('precek', 'asc')->get();
        if($matchAudioInfos->isEmpty())
        {
            $this->line("file {$filePath} is failed to insert into database");
        }
        else
        {
            $splitStartArray = [];
            $splitEndArray = [];

            foreach($matchAudioInfos as $matchAudioInfo)
            {
                array_push($splitStartArray, $matchAudioInfo->time);
                array_push($splitEndArray, $matchAudioInfo->closed);
            }
            // $precekArray = [];
            // foreach($matchAudioInfos as $matchAudioInfo){
            //     array_push($precekArray, $matchAudioInfo->precek);
            // }
            // array_push($precekArray, $toDateTime);
            // print_r($precekArray);
            // $tempInterval = 0;
            $ffmpeg = FFMpeg::create();
            $audio = $ffmpeg->open($filePath);
            $format = new Wav();            
            $audioFileConvertPath = env('AUDIO_CONVERT_PATH');
            // $aduioConvertPath = Storage::disk('public')->path($audioFileConvertPath). DIRECTORY_SEPARATOR .basename($filePath);

            $aduioConvertPath = $audioFileConvertPath. DIRECTORY_SEPARATOR .basename($filePath);

            $audio->addFilter(new \FFMpeg\Filters\Audio\SimpleFilter(['-af', 'anlmdn']));

            $timeIntervals = [];
            
            
            $startTimeObject  = new \DateTime($fromDateTime);
            $startTime = $startTimeObject->format('H')*3600 + $startTimeObject->format('i')* 60 + $startTimeObject->format('s');
            for($i = 0; $i < count($splitStartArray); $i++)
            {
                $startDateTime = new \DateTime($splitStartArray[$i]);
                $tempDateTime = new \DateTime($startDateTime->format('Y-m-d')." ".$fromDateTime);
                $mergeTime1 = [];
                $mergeTime2 = [];
                if($startDateTime < $tempDateTime)
                {
                    $mergePreTime = new \DateTime($splitStartArray[$i]);
                    $startDateTime->setTime($tempDateTime->format('H'), 0, 0);
                    $mergeTime1 = [
                        'fromTime1' =>  $mergePreTime,
                        'toTime1' => $startDateTime
                    ];
                    $mergeAftTime = new \DateTime($splitEndArray[$i]);
                    $mergeTime2 = [
                        'fromTime2' => $startDateTime,
                        'toTime2' => $mergeAftTime
                    ];

                }
                else
                {
                    $startDateTime->modify('-1 minute');
                }
                
                $endDateTimeString = $startDateTime->format('Y-m-d') . ' ' . $splitEndArray[$i];
                $endDateTime = new \DateTime($endDateTimeString);
                $tempDateTime = new \DateTime($endDateTime->format('Y-m-d')." ".$toDateTime);
                
                if($endDateTime > $tempDateTime)
                {
                    $endDateTime->setTime($tempDateTime->format('H'), 0, 0);
                }
                else
                {
                    $endDateTime->modify('+1 minute');
                }
                
                if ($endDateTime > $startDateTime) {
                    $interval = $startDateTime->diff($endDateTime);
                } else {
                    $interval = $endDateTime->diff($startDateTime);
                }
                
                
                $splitStart  = $startDateTime->format('H') * 3600 + $startDateTime->format('i') * 60 + $startDateTime->format('s');
                if($splitStart > $startTime)
                {
                    $splitStart = $splitStart - $startTime;
                }
                else
                {
                    $splitStart = 0;
                }
                
                $splitsDuration = $interval->h * 3600 + $interval->i * 60 + $interval->s;
                
                if($splitsDuration != 0)
                {
                    $audio->filters()->clip(TimeCode::fromSeconds($splitStart), TimeCode::fromSeconds($splitsDuration));
                    $outputFilePath = $aduioConvertPath.'_from_'.$startDateTime->format('H-i-s').'_to_'.$endDateTime->format('H-i-s').'.wav';
                    $audio->save($format, $outputFilePath);
                    $this->zipToMp3file($outputFilePath);
                    unlink($outputFilePath);
                }
                if(count($mergeTime1) != 0 && count($mergeTime2) != 0)
                {
                    $this->mergeTwoFiles($filePath, $mergeTime1, $mergeTime2);
                }
                $startTime = $startDateTime->format('H') * 3600 + $startDateTime->format('i') * 60 + $startDateTime->format('s');
            }
        }
    }


    public function mergeTwoFiles(string $filePath, array $mergeTime1, array $mergeTime2){


        $preSplitFile  = AudioFile::where('format', '*.wav')->where('file_name', '<' , basename($filePath))->orderBy('file_name', 'desc')->limit(1)->first();

        $ffmpeg = FFMpeg::create();         
        $audioFileConvertPath = env('AUDIO_CONVERT_PATH');
        // $aduioConvertPath = Storage::disk('public')->path($audioFileConvertPath). DIRECTORY_SEPARATOR .basename($filePath);
        

        $aduioConvertPath = $audioFileConvertPath. DIRECTORY_SEPARATOR .basename($filePath);

        if($preSplitFile)
        {
            $audio = $ffmpeg->open($preSplitFile->file_path);
            $format = new Wav();
            $splitStartTime = $mergeTime1['fromTime1']->format('i') * 60 + $mergeTime1['fromTime1']->format('s');
            $splitInterval = 61 * 59 - $splitStartTime;
            $audio->filters()->clip(TimeCode::fromSeconds($splitStartTime), TimeCode::fromSeconds($splitInterval));
            $preMergePath = $aduioConvertPath.'_from_'.$mergeTime1['fromTime1']->format("H-i-s").'_to_'.$mergeTime1['toTime1']->format("H-i-s").'.wav';
            $this->line($preMergePath);
            $audio->save($format, $preMergePath);
            
            $this->zipToMp3file($preMergePath);
            unlink($preMergePath);
            $preMergePath = $aduioConvertPath.'_from_'.$mergeTime1['fromTime1']->format("H-i-s").'_to_'.$mergeTime1['toTime1']->format("H-i-s").'.mp3';

            $afterMergeTime = new \DateTime($mergeTime2['toTime2']->format("H:i:s"));
            $afterMergeTime->modify('+1 minute');
            $afterMergePath = $aduioConvertPath.'_from_'.$mergeTime2['fromTime2']->format("H-i-s").'_to_'.$afterMergeTime->format('H-i-s').'.mp3';
            
            AudioFile::where('file_path', $afterMergePath)->delete();
            $outPutPath = $aduioConvertPath.'_from_'.$mergeTime1['fromTime1']->format("H-i-s").'_to_'.$afterMergeTime->format('H-i-s').'.wav';
            
            if(file_exists($afterMergePath))
            {
                $afterMergeAudio = $ffmpeg->open($afterMergePath);
                $afterMergeAudio->concat([$preMergePath, $afterMergePath])->saveFromSameCodecs($outPutPath, true);
                $this->line("merged two files to {$outPutPath}");
                $this->ZipToMp3file($outPutPath);
                unlink($outPutPath);
                unlink($afterMergePath);
            }
            unlink($preMergePath);
        }
    }
    //merge pre-last splited file and new-first splited file
    // public function mergeTwoFiles(string $originalFileName, $filePath) 
    // {
        
    //     $preAudioFile = AudioFile::where('file_name', '<', $originalFileName)->where('file_name', 'like', '%to%')->orderBy('file_name', 'desc')->limit(1)->first();
    //     if(!$preAudioFile)
    //     {
    //         return;
    //     }
    //     $preAudioPath = $preAudioFile->file_path;
    //     $aftAudioFile = AudioFile::where('file_name', 'like', $originalFileName.'to%')->orderBy('file_name', 'asc')->limit(1)->first();
    //     $aftAudioPath = $aftAudioFile->file_path;

        
    //     $ffmpeg = FFMpeg::create();
    //     $aftAudioAudio = $ffmpeg->open($aftAudioPath);
    //     // $outTempPath = $aftAudioPath;


    //     $outTempPath = pathinfo($filePath, PATHINFO_DIRNAME);
    //     // $outTempPath = $filePath['dirname'];
    //     // $this->info("==================={$outTempPath}");
    //     $outTempPath = $outTempPath."\\temp.mp3";
    //     // $preAudio->concat([$preAudioPath, $aftAudioPath])->saveFromSameCodecs($aftAudioPath, true);
    //     $aftAudioAudio->concat([$preAudioPath, $aftAudioPath])->saveFromSameCodecs($outTempPath, true);

    //     if(file_exists($aftAudioPath))
    //     {
    //         unlink($aftAudioPath);
    //     }
    //     if(file_exists($preAudioPath))
    //     {
    //         unlink($preAudioPath);
    //     }
    //     // unlink($preAudioPath);
    //     rename($outTempPath, $aftAudioPath);

    //     $this->line("merged two files : {$preAudioFile->file_name} and {$aftAudioFile->file_name}");
    // }

    //convert to Mp3 files
    public function ZipToMp3file($filePath)
    {
        
        $lowerBitrate = 64000;
        $ffmpeg = FFMpeg::create();
        $ffprobe = \FFMpeg\FFProbe::create();
        $audio = $ffmpeg->open($filePath);


        //remove the noise from audio background sound
        // $audio->filters()->custom("aecho=0.8:0.9:1000:0.3");

        $audio->filters()->custom('afftdn=nr=22:tn=1:tr=0:om=o');
        $format = new Mp3();
        $format->setAudioKiloBitrate((int)($lowerBitrate / 1000));
        try {

            $lastWavPosition = strrpos($filePath, ".wav");
            if($lastWavPosition !== false)
            {
                $filePath = substr_replace($filePath,  ".mp3", $lastWavPosition, strlen(".wav"));
            }
            $audio->save($format, $filePath);
            $file_name = basename($filePath);
            $duration = $ffprobe->format($filePath)->get('duration');
            $formattedDuration = gmdate('H:i:s', (int) $duration);
            $file_size = $ffprobe->format($filePath)->get('size');
            $existFlag =  AudioFile::select('*')->where('file_path', $filePath)->where('file_name', $file_name)->get();

            //chec if the splited audio file with same name exist
            if($existFlag->count() == 0)
            {
                AudioFile::updateOrCreate([
                    'file_path' => $filePath,
                    'file_name' => $file_name,
                    'duration' => $formattedDuration,
                    'file_size' => $file_size,
                    'format' => '*.mp3'
                ]);
                $this->line(" and converted in <info>{$filePath}</info>");

            }
            
        } catch (Exception $e) {
            echo "An error occurred: " . $e->getMessage() . "\n";
        }
    }

    // get the date and time from audio file name
    public function getAudioFileInfo(string $fileName) : array 
    {        
        $pattern = '/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})_.*\.wav$/';
        $audioFileInfo = [];
        if (preg_match($pattern, $fileName, $matches)) {
            $date = "{$matches[1]}-{$matches[2]}-{$matches[3]}";

            $time = "{$matches[4]}:{$matches[5]}:{$matches[6]}";

            $audioFileInfo =  [
                'date' => $date,
                'time' => $time
            ];
        }
        return $audioFileInfo;
    }
}
