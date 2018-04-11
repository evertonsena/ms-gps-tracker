<?php
namespace App\Models;

use Illuminate\Support\Facades\Log;

use App\Models\Gps;

class DataTk103b
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var integer
     */
    private $imei;

    /**
     * @var string
     */
    private $alarm;

    /**
     * @var string
     */
    private $gps_time;

    /**
     * @var string
     */
    private $latitude;

    /**
     * @var string
     */
    private $longitude;

    /**
     * @var string
     */
    private $speed_in_mph;

    /**
     * @var string
     */
    private $bearing;

    public function setData($data)
    {
        $this->data = $data;
        $this->convertData();
        return $this;
    }

    public function save()
    {
        if(!$this->data)
        {
            Log::error('Dados para o rastreador TK103b não enviado');
            return false;
        }

        if(!$this->saveDataGps()) {
            Log::error('Não foi possível persisti os dados no database' .
                'para o rastreador TK103b... ');
            return false;
        }
        return true;
    }

    private function saveDataGps()
    {
        return Gps::create([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'imei' => $this->imei,
            'speed' => $this->speed_in_mph,
            'direction' => $this->bearing,
            'gps_time' => $this->gps_time
        ]);
    }

    private function convertData()
    {
        $this->imei = substr($this->data[0], 5);
        $this->alarm = $this->data[1];
        $this->gps_time = $this->nmeaToMysqlTime($this->data[2]);
        $this->latitude = $this->degreeToDecimal($this->data[7], $this->data[8]);
        $this->longitude = $this->degreeToDecimal($this->data[9], $this->data[10]);
        $speed_in_knots = $this->data[11];
        $this->speed_in_mph = 1.15078 * $speed_in_knots;
        $this->bearing = $this->data[12];
    }

    public function getImei()
    {
        return $this->imei;
    }

    public function getAlarm()
    {
        return $this->alarm;
    }

    private function nmeaToMysqlTime($date_time){
        $year = substr($date_time,0,2);
        $month = substr($date_time,2,2);
        $day = substr($date_time,4,2);
        $hour = substr($date_time,6,2);
        $minute = substr($date_time,8,2);
        $second = substr($date_time,10,2);
        return date("Y-m-d H:i:s", mktime($hour,$minute,$second,$month,$day,$year));
    }

    private function degreeToDecimal($coordinates_in_degrees, $direction){
        $degrees = (int)($coordinates_in_degrees / 100);
        $minutes = $coordinates_in_degrees - ($degrees * 100);
        $seconds = $minutes / 60;
        $coordinates_in_decimal = $degrees + $seconds;
        if (($direction == "S") || ($direction == "W")) {
            $coordinates_in_decimal = $coordinates_in_decimal * (-1);
        }
        return number_format($coordinates_in_decimal, 6,'.','');
    }

}