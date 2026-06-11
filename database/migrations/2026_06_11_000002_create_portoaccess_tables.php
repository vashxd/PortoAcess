<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('entry_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_paid')->default(false);
            $table->string('charge_moment', 10)->nullable(); // entrada|saida (apenas tipos pagos)
            $table->unsignedInteger('max_stay_minutes')->nullable(); // alerta de permanência (ex.: visita)
            $table->boolean('requires_visitor_info')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnpj', 18)->nullable();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('billing_cycle', 15)->default('mensal'); // semanal|quinzenal|mensal
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_type_id')->constrained();
            $table->foreignId('vehicle_category_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['entry_type_id', 'vehicle_category_id', 'valid_from']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 10)->unique();
            $table->foreignId('vehicle_category_id')->nullable()->constrained();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('owner_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('authorized_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->string('type', 15); // funcionario|empresa
            $table->string('employee_name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('access_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('entry_type_id')->constrained();
            $table->foreignId('vehicle_category_id')->constrained();
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->string('entry_photo')->nullable();
            $table->string('exit_photo')->nullable();
            $table->string('detected_color')->nullable();
            $table->string('detected_model')->nullable();
            $table->decimal('plate_read_confidence', 5, 2)->nullable();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('status', 15)->default('no_patio'); // no_patio|finalizado|cancelado
            $table->boolean('manual_entry')->default(false);
            $table->boolean('exit_without_entry')->default(false);
            $table->boolean('color_model_mismatch')->default(false);
            $table->foreignId('operator_in_id')->nullable()->constrained('users');
            $table->foreignId('operator_out_id')->nullable()->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_document')->nullable();
            $table->string('destination')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('cancel_requested_at')->nullable();
            $table->text('cancel_request_reason')->nullable();
            $table->foreignId('cancel_requested_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['status', 'entered_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_record_id')->constrained();
            $table->string('method', 20); // pix|cartao_debito|cartao_credito|dinheiro|faturado
            $table->decimal('amount', 10, 2);
            $table->string('card_brand')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('user_id')->constrained();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('company_id')->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->decimal('total', 12, 2);
            $table->string('status', 10)->default('aberta'); // aberta|paga|vencida
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_record_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('camera_events', function (Blueprint $table) {
            $table->id();
            $table->string('camera', 10); // entrada|saida
            $table->string('plate', 10)->nullable();
            $table->string('color')->nullable();
            $table->string('model')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('access_record_id')->nullable()->constrained();
            $table->string('status', 12)->default('pendente'); // pendente|vinculado|descartado
            $table->timestamps();
            $table->index(['status', 'occurred_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 30); // created|updated|deleted|login|gate_open|...
            $table->string('entity', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at');
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('camera_events');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('access_records');
        Schema::dropIfExists('authorized_vehicles');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('entry_types');
        Schema::dropIfExists('vehicle_categories');
    }
};
