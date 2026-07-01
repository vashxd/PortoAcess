<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Embarcações (balsas, lanchas, rebocadores e outros veículos aquáticos)
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 15)->default('balsa'); // balsa|lancha|rebocador|outro
            $table->string('registration')->nullable();    // nome/prefixo/identificação
            $table->string('operator')->nullable();         // empresa operadora
            $table->string('default_destination')->nullable();
            $table->unsignedInteger('capacity_vehicles')->nullable(); // informativo (fase 1)
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Grade de horários recorrente (por dia da semana)
        Schema::create('vessel_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->json('days_of_week');          // [0..6] 0=domingo (Carbon dayOfWeek)
            $table->string('departure_time', 5);    // HH:MM
            $table->string('destination')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Viagens (partidas datadas — geradas a partir da grade ou avulsas)
        Schema::create('vessel_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('departure_date');
            $table->string('departure_time', 5); // HH:MM
            $table->dateTime('departure_at');    // data+hora (ordenação/consulta)
            $table->string('destination')->nullable();
            $table->string('status', 12)->default('agendada'); // agendada|embarcando|encerrada|cancelada
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['vessel_id', 'departure_at']);
            $table->index(['departure_at', 'status']);
        });

        // Vínculo do acesso do veículo com a balsa/viagem escolhida
        Schema::table('access_records', function (Blueprint $table) {
            $table->unsignedBigInteger('vessel_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('vessel_departure_id')->nullable()->after('vessel_id');
            $table->index('vessel_departure_id');
        });

        // Como o tipo de entrada trata a escolha de balsa: none|optional|required
        Schema::table('entry_types', function (Blueprint $table) {
            $table->string('vessel_selection', 10)->default('none')->after('charge_moment');
        });
    }

    public function down(): void
    {
        Schema::table('entry_types', function (Blueprint $table) {
            $table->dropColumn('vessel_selection');
        });

        Schema::table('access_records', function (Blueprint $table) {
            $table->dropColumn(['vessel_id', 'vessel_departure_id']);
        });

        Schema::dropIfExists('vessel_departures');
        Schema::dropIfExists('vessel_schedules');
        Schema::dropIfExists('vessels');
    }
};
