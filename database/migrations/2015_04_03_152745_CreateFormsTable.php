<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFormsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('forms', function(Blueprint $table)
		{
			$table->increments('fid');
			$table->integer('pid')->unsigned();
            $table->integer('adminGID')->unsigned();
			$table->string('name');
			$table->string('slug')->unique();
			$table->string('description');
            $table->string('layout');
            $table->boolean('preset');
            $table->boolean('public_metadata');
			$table->timestamps();

            $table->foreign('pid')->references('pid')->on('projects')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('forms');
	}

}
