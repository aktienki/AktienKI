<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
public function up():void{
Schema::create('dividends',function(Blueprint $table){
$table->id();
$table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
$table->date('ex_date')->index();
$table->decimal('amount',20,8);
$table->string('currency',3)->nullable();
$table->timestampsTz();
});}
public function down():void{Schema::dropIfExists('dividends');}
};