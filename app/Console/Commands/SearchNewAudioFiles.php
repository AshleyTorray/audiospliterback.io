<?php

namespace App\Console\Commands;

use App\Models\ExcelAudioLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use App\Models\AudioFile;
use Spatie\Watcher\Watch;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Wav;
use FFMpeg\Format\Audio\Mp3;
use Exception;
use FFMpeg\Coordinate\TimeCode;

use DataTime;
class SearchNewAudioFiles extends Command
{
    /**
     * The name and signature of the console command.
     * created by the Ashley Torray at 2024_10_55
     * Thiss code makes you helpful to search the new audio files added by the client
     * 
     */

    protected $signature = 'search:new-audio-files';

    protected $description = 'Command description';

    public function handle()
    {
        $audioFilePath = env('AUDIO_PATH');
        $audioPath = Storage::disk('public')->path($audioFilePath);
        

        $this->line("<info>!Note: Auto check the uploaded audio files is runnning </info>=============");
        
        Watch::path($audioPath)->onAnyChange(function (string $type, string $path) {
            $filename = basename($path);
            $existingFile = AudioFile::where('file_path', $path)->where('file_name', $filename)->first();
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
            if($type == Watch::EVENT_TYPE_DIRECTORY_CREATED)
            {
                $this->line("directory that includes <info> {$path} </info>file is created.");
            }
            if($type == Watch::EVENT_TYPE_DIRECTORY_DELETED)
            {
                $this->line("directory that includes <info> {$path} </info>file is delected.");

            }
        })->start();

        // $audioFileConvertPath = env('AUDIO_CONVERT_PATH');
        // $aduioConvertPath = Storage::disk('local')->files($audioFileConvertPath);

    }


    // get the duration of audio file

    public function getAudioProperty(string $filePath) : array{
        
        
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

    public function splitAudioFile(string $filePath){

        $audioFileInfo = $this->getAudioFileInfo(basename($filePath));
        $tillDateTime = new \DateTime($audioFileInfo['date'].''.$audioFileInfo['time']);
        $fromDateTime = $tillDateTime->format('H:i:s');
        $toDateTime = $tillDateTime->modify('+1 hour');
        $toDateTime = $toDateTime->format('H:i:s');

        $matchAudioInfos = ExcelAudioLog::select('order_no', 'precek', 'waiter', 'file_path')->where('accounting_day', '=', $audioFileInfo['date'])->where('precek', '>', $fromDateTime)->where('precek', '<', $toDateTime)->orderBy('precek', 'asc')->get();
        if($matchAudioInfos->isEmpty())
        {
            $this->line("file {$filePath} is failed to insert into database");
        }
        else
        {
            $precekArray = [];
            foreach($matchAudioInfos as $matchAudioInfo){
                array_push($precekArray, $matchAudioInfo->precek);
            }
            array_push($precekArray, $toDateTime);
            print_r($precekArray);
            $tempInterval = 0;
            $ffmpeg = FFMpeg::create();
            $audio = $ffmpeg->open($filePath);
            $format = new Wav();            
            $audioFileConvertPath = env('AUDIO_CONVERT_PATH');
            $aduioConvertPath = Storage::disk('public')->path($audioFileConvertPath). DIRECTORY_SEPARATOR .basename($filePath);
            $audio->addFilter(new \FFMpeg\Filters\Audio\SimpleFilter(['-af', 'anlmdn']));
            $count  = 0;
            foreach($precekArray as $precek)
            {
                $this->line("path is {$precek}");
                $tempTime = Carbon::createFromFormat('H:i:s', $precek);
                
                //add the last duration manually

                if($count == (count($precekArray) - 1))
                {
                    $precek_duration =  61*59 - $tempInterval;
                }
                else
                {
                    $precek_duration =  $tempTime->minute * 60 + $tempTime->second - $tempInterval;
                }

                //check if the precek time is same in ExcelLog

                if($precek_duration != 0)
                {
                    $audio->filters()->clip(TimeCode::fromSeconds($tempInterval), TimeCode::fromSeconds($precek_duration));
                    $outputFilePath = $aduioConvertPath.'to'.$tempTime->format('H-i-s').'.wav';
                    $audio->save($format, $outputFilePath);
                    $this->line("<info>{$filePath}</info> -> during <info>{$precek_duration} -> </info> splited </info>");
                    $this->zipToMp3file($outputFilePath);
                    unlink($outputFilePath);
                }
                $tempInterval = $tempTime->minute * 60 + $tempTime->second;
                $count++;
                
            }
            $this->mergeTwoFiles(basename($filePath), $aduioConvertPath);
        }
        //merge last and first 
        // $splitedFileInfo = [

        // ];
        // return $splitedFileInfo;  
    }

    public function mergeTwoFiles(string $originalFileName, $filePath) {
        
        $preAudioFile = AudioFile::where('file_name', '<', $originalFileName)->where('file_name', 'like', '%to%')->orderBy('file_name', 'desc')->limit(1)->first();
        if(!$preAudioFile)
        {
            return;
        }
        $preAudioPath = $preAudioFile->file_path;
        $aftAudioFile = AudioFile::where('file_name', 'like', $originalFileName.'to%')->orderBy('file_name', 'asc')->limit(1)->first();
        $aftAudioPath = $aftAudioFile->file_path;

        
        $ffmpeg = FFMpeg::create();
        $aftAudioAudio = $ffmpeg->open($aftAudioPath);
        // $outTempPath = $aftAudioPath;


        $outTempPath = pathinfo($filePath, PATHINFO_DIRNAME);
        // $outTempPath = $filePath['dirname'];
        // $this->info("==================={$outTempPath}");
        $outTempPath = $outTempPath."\\temp.mp3";
        // $preAudio->concat([$preAudioPath, $aftAudioPath])->saveFromSameCodecs($aftAudioPath, true);
        $aftAudioAudio->concat([$preAudioPath, $aftAudioPath])->saveFromSameCodecs($outTempPath, true);

        if(file_exists($aftAudioPath))
        {
            unlink($aftAudioPath);
        }
        if(file_exists($preAudioPath))
        {
            unlink($preAudioPath);
        }
        // unlink($preAudioPath);
        rename($outTempPath, $aftAudioPath);

        $this->line("merged two files : {$preAudioFile->file_name} and {$aftAudioFile->file_name}");
    }
    //convert to Mp3 files
    public function ZipToMp3file($filePath)
    {
        
        $lowerBitrate = 64000;
        $ffmpeg = FFMpeg::create();
        $ffprobe = \FFMpeg\FFProbe::create();
        $audio = $ffmpeg->open($filePath);


        //remove the noise from audio background sound
        $audio->filters()->custom("aecho=0.8:0.9:1000:0.3");
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
    public function getAudioFileInfo(string $fileName) : array {
        
        // Regular expression to match the expected format
        $pattern = '/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d*_.+\.wav$/';
        $audioFileInfo = [];
        if (preg_match($pattern, $fileName, $matches)) {
            // Construct the date string
            $date = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
            
            // Construct the time string
            $time = "{$matches[4]}:{$matches[5]}";
            $audioFileInfo =  [
                'date' => $date,
                'time' => $time
            ];
        }
        return $audioFileInfo;

    }
}