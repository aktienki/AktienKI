<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
public function up():void{
Schema::create('instrument_fundamentals',function(Blueprint $table){
$table->id();
$table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
$table->date('snapshot_date')->index();
$table->json('data');
$table->string('source',32)->nullable();
$table->timestampsTz();
$table->unique(['instrument_id','snapshot_date']);
});}
public function down():void{Schema::dropIfExists('instrument_fundamentals');}
};