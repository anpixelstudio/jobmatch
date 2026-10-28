<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('department')->nullable();
            $table->text('description')->nullable();
            $table->text('criteria_text')->nullable();
            $table->json('required_skills')->nullable();
            $table->string('status')->default('open'); // open|closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('source'); // kaggle|upload
            $table->string('category')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->longText('raw_text')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('status')->default('ready'); // queued|parsing|ready|failed
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('job_opening_id')->nullable()->constrained('job_openings')->nullOnDelete();
            $table->string('review_status')->default('new'); // new|shortlist|interview|talent_pool|rejected
            $table->timestamps();

            $table->index(['source', 'status']);
        });

        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_opening_id')->nullable()->constrained('job_openings')->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_thread_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user|assistant|system
            $table->longText('content');
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('rank');
            $table->json('reasons_json')->nullable();
            $table->timestamps();

            $table->unique(['chat_message_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
    }
};
