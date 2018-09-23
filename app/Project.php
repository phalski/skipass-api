<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    public function tickets()
    {
        return $this->hasMany('App\Ticket');
    }

    public function lifts()
    {
        return $this->hasMany('App\Lifts');
    }
}
