<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pass extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'ticket', 'ticket_project', 'ticket_pos', 'ticket_serial', 'wtp', 'wtp_chip_id', 'wtp_chip_id_crc', 'wtp_accept_id', 'seen_at'
    ];
}
