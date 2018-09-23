<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'ticket_id', 'lift_id', 'logged_at', 'day_n', 'ride_n'
    ];
}