<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLiftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lifts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id', false, true);
            $table->string('name', 32)->unique();
            $table->float('lower_elevation_meters')->nullable();
            $table->float('upper_elevation_meters')->nullable();
            $table->time('ride_duration')->nullable();

            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects');
            $table->unique(['project_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lifts');
    }
}
