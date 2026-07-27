<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
public function up():void{
Schema::create('news',function(Blueprint $table){
$table->id();
$table->foreignId('instrument_id')->nullable()->constrained()->nullOnDelete();
$table->string('headline');
$table->text('summary')->nullable();
$table->string('url')->nullable();
$table->string('source',64)->nullable();
$table->timestampTz('published_at')->nullable()->index();
$table->timestampsTz();
});
Schema::create('news_sentiments',function(Blueprint $table){
$table->id();
$table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
$table->decimal('sentiment',8,4);
$table->decimal('confidence',8,4)->nullable();
$table->timestampsTz();
});
}
public function down():void{
Schema::dropIfExists('news_sentiments');
Schema::dropIfExists('news');
}
};