<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gps extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gps';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['latitude', 'longitude', 'imei', 'speed', 'direction', 'gps_time'];
}