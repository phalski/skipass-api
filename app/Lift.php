<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Lift extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'name', 'lower_elevation_meters', 'upper_elevation_meters', 'ride_duration'
    ];

    public function project()
    {
        return $this->belongsTo('App\Project');
    }

    public function logs()
    {
        return $this->hasMany('App\Log');
    }
}
