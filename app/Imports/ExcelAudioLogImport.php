<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use App\Models\ExcelAudioLog;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;


class ExcelAudioLogImport implements ToModel, WithStartRow
{
    /**
    * @param array $row
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    private $excelFilePath;
    private $excelFilename;

    public function __construct(string  $filePath){
        $this->excelFilePath = $filePath;
        $this->excelFilename = basename($filePath);
    }
    public function startRow() : int {
        return 5;
    }
    public function model(array $row)
    {
        $accountingDay = Date::excelToDateTimeObject($row[0]);
        $accountingDay = $accountingDay->format('Y-m-d');
        if($row[4] == null)
        {
            $row[4] = 0;
        }
        if($row[6] == null)
        {
            $row[6] = Carbon::createFromTime(0,0);
        }
        if($row[9] == null)
        {
            $row[9] = "";
        }
        return new ExcelAudioLog([
            'accounting_day' => $accountingDay,
            'order_no'       => $row[4],
            'precek'         => $row[6],
            'waiter'         => $row[9],
            'file_path'      => $this->excelFilePath,
            'file_name'      => $this->excelFilename
        ]);
    }
}

