<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'project_name', 'name', 'project', 'pos', 'serial', 'day_count', 'first_lift_id', 'first_day_at', 'first_day_n', 'last_day_at', 'last_day_n', 'last_logs_update'
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'last_day_at' => 'datetime',
        'last_logs_update' => 'datetime'
    ];

    public function project()
    {
        return $this->belongsTo('App\Project');
    }

    public function logs()
    {
        return $this->hasMany('App\Log');
    }

    public function firstLift()
    {
        return $this->hasOne('App\Lift');
    }
}