<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan data D1 s.d. D6 pada DFD Level 1.
 * D1 (akun dan peran) ada di migrasi users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // D5: model dan data historis
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('algorithm', 80);
            $table->json('params');
            $table->decimal('macro_f1', 5, 4)->nullable();
            $table->decimal('recall_high', 5, 4)->nullable();
            $table->json('metrics')->nullable();
            $table->unsignedInteger('train_rows')->default(0);
            $table->unsignedInteger('test_rows')->default(0);
            $table->boolean('meets_target')->default(false);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });

        Schema::create('historical_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('row_count');
            $table->json('label_counts');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('historical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_dataset_id')->constrained()->cascadeOnDelete();
            $table->decimal('attendance', 5, 2);
            $table->decimal('ips', 4, 2);
            $table->decimal('failed_ratio', 5, 4);
            $table->decimal('ips_delta', 4, 2);
            $table->string('label', 10);
        });

        // D2: data akademik tersamar
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64)->index();
            $table->string('source_name', 120);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->json('semesters');
            $table->timestamps();
        });

        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64);
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('course_code', 20);
            $table->string('course_name', 120);
            $table->unsignedTinyInteger('credits');
            $table->decimal('grade_point', 3, 2);
            $table->string('grade_input', 12);
            $table->decimal('attendance', 5, 2);
            $table->timestamps();

            $table->unique(['student_ref', 'semester', 'course_code']);
            $table->index(['student_ref', 'semester']);
        });

        // D3: hasil dan riwayat risiko
        Schema::create('risk_results', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64);
            $table->unsignedTinyInteger('semester');
            $table->string('level', 10); // rendah | sedang | tinggi
            $table->decimal('score', 4, 2);
            $table->json('features');
            $table->json('factors');
            $table->json('suggestions');
            $table->date('data_as_of');
            $table->foreignId('model_version_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_ref', 'semester']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64)->index();
            $table->foreignId('risk_result_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level', 10);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // D4: persetujuan dan pengaturan
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64);
            $table->foreignId('dpa_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('granted')->default(false);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->unique(['student_ref', 'dpa_id']);
        });

        Schema::create('student_settings', function (Blueprint $table) {
            $table->id();
            $table->string('student_ref', 64)->unique();
            $table->boolean('remind_in_app')->default(true);
            $table->boolean('remind_email')->default(false);
            $table->timestamps();
        });

        // D6: log akses
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 16);
            $table->string('student_ref', 64)->index();
            $table->string('action', 40);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach ([
            'access_logs', 'student_settings', 'consents', 'reminders', 'risk_results',
            'academic_records', 'import_batches', 'historical_records',
            'historical_datasets', 'model_versions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
