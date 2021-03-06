<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDocumentsfieldsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('documents_fields', function(Blueprint $table)
		{
			$table->increments('id');

			$table->integer('rid')->unsigned();
			$table->integer('flid')->unsigned();
			$table->mediumText('documents');
			$table->timestamps();

			$table->foreign('rid')->references('rid')->on('records')->onDelete('cascade');
			$table->foreign('flid')->references('flid')->on('fields')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('documents_fields');
	}

}
