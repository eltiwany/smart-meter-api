<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSensorValue extends Model
{
    use HasFactory;

    public function user_sensor()
    {
        return $this->belongsTo(UserSensor::class);
    }
}
