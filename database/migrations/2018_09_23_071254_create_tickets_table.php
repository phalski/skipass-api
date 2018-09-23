<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('project_id', false, true);
            $table->string('project_name', 32); // also store name for faster lookup
            $table->string('name', 16)->unique();
            $table->smallInteger('project', false, true);
            $table->smallInteger('pos', false, true);
            $table->mediumInteger('serial', false, true);
            $table->mediumInteger('day_count', false, true)->nullable();
            $table->date('first_day_at')->nullable();
            $table->mediumInteger('first_day_n', false, true)->nullable();
            $table->date('last_day_at')->nullable();
            $table->mediumInteger('last_day_n', false, true)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects');
            $table->unique(['project_id', 'name']);
            $table->unique(['project_name', 'name']); // faster lookup
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tickets');
    }
}
