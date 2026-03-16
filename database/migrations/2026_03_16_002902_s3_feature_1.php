<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('file_transfers', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('user_id');
            $table->string('s3_key');
            $table->string('file_path');
            $table->string('direction');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('server_id');
            $table->index(['server_id', 'direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_transfers');
    }
};
