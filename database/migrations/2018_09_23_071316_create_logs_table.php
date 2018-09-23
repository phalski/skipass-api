<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id', false, true);
            $table->integer('ticket_id', false, true);
            $table->integer('lift_id', false, true);
            $table->timestamp('logged_at');
            $table->mediumInteger('day_n', false, true);
            $table->mediumInteger('ride_n', false, true);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('ticket_id')->references('id')->on('tickets');
            $table->foreign('lift_id')->references('id')->on('lifts');
            $table->unique(['project_id', 'ticket_id', 'lift_id', 'day_n', 'ride_n']);
            $table->index(['project_id', 'ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('logs');
    }
}
