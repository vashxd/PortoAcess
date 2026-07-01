<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Price;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\Vessel;
use App\Models\VesselSchedule;
use App\Services\VesselService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuários iniciais (um por perfil)
        User::firstOrCreate(['email' => 'admin@portoaccess.local'], [
            'name' => 'Administrador',
            'password' => 'password',
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        User::firstOrCreate(['email' => 'operador@portoaccess.local'], [
            'name' => 'Operador Guarita',
            'password' => 'password',
            'role' => UserRole::Operador,
            'active' => true,
        ]);

        User::firstOrCreate(['email' => 'financeiro@portoaccess.local'], [
            'name' => 'Setor Financeiro',
            'password' => 'password',
            'role' => UserRole::Financeiro,
            'active' => true,
        ]);

        User::firstOrCreate(['email' => 'auditor@portoaccess.local'], [
            'name' => 'Auditor',
            'password' => 'password',
            'role' => UserRole::Auditor,
            'active' => true,
        ]);

        // Categorias de veículo (seção 3.2 da documentação)
        $categories = [
            'Motocicleta',
            'Carro de passeio',
            'SUV',
            'Caminhonete (pickup)',
            'Van / Utilitário',
            'Caminhão toco (2 eixos)',
            'Caminhão truck (3 eixos)',
            'Carreta / Bitrem',
            'Ônibus / Micro-ônibus',
        ];

        foreach ($categories as $name) {
            VehicleCategory::firstOrCreate(['name' => $name]);
        }

        // Tipos de entrada (seção 3.1)
        $funcionario = EntryType::firstOrCreate(['name' => 'Funcionário'], [
            'is_paid' => false,
            'requires_visitor_info' => false,
        ]);

        $visita = EntryType::firstOrCreate(['name' => 'Visita'], [
            'is_paid' => false,
            'max_stay_minutes' => 120,
            'requires_visitor_info' => true,
        ]);

        $retirada = EntryType::firstOrCreate(['name' => 'Retirada de mercadoria'], [
            'is_paid' => true,
            'charge_moment' => 'saida',
            'vessel_selection' => 'optional',
            'requires_visitor_info' => false,
        ]);

        $balsa = EntryType::firstOrCreate(['name' => 'Embarque na balsa'], [
            'is_paid' => true,
            'charge_moment' => 'entrada',
            'vessel_selection' => 'required',
            'requires_visitor_info' => false,
        ]);

        // Garante o novo campo em bancos já existentes (idempotente)
        $retirada->update(['vessel_selection' => 'optional']);
        $balsa->update(['vessel_selection' => 'required']);

        // Tabela de preços ilustrativa (seção 3.3)
        $tabela = [
            'Motocicleta' => [10.00, 25.00],
            'Carro de passeio' => [20.00, 60.00],
            'SUV' => [25.00, 70.00],
            'Caminhonete (pickup)' => [30.00, 90.00],
            'Van / Utilitário' => [35.00, 110.00],
            'Caminhão toco (2 eixos)' => [50.00, 180.00],
            'Caminhão truck (3 eixos)' => [65.00, 250.00],
            'Carreta / Bitrem' => [80.00, 350.00],
            'Ônibus / Micro-ônibus' => [60.00, 200.00],
        ];

        foreach ($tabela as $catName => [$retiradaPreco, $balsaPreco]) {
            $cat = VehicleCategory::where('name', $catName)->first();

            Price::firstOrCreate(
                ['entry_type_id' => $retirada->id, 'vehicle_category_id' => $cat->id, 'valid_from' => '2026-01-01'],
                ['amount' => $retiradaPreco],
            );
            Price::firstOrCreate(
                ['entry_type_id' => $balsa->id, 'vehicle_category_id' => $cat->id, 'valid_from' => '2026-01-01'],
                ['amount' => $balsaPreco],
            );
        }

        // Empresa conveniada de exemplo
        Company::firstOrCreate(['name' => 'Transportes Rio Negro Ltda'], [
            'cnpj' => '12.345.678/0001-90',
            'contact' => 'Maria Souza',
            'email' => 'financeiro@rionegro.example',
            'phone' => '(92) 99999-0000',
            'billing_cycle' => 'mensal',
            'credit_limit' => 10000.00,
            'discount_percent' => 10.00,
        ]);

        // Balsas e embarcações + grade de horários de exemplo
        $saoJorge = Vessel::firstOrCreate(['name' => 'Balsa São Jorge'], [
            'type' => 'balsa',
            'registration' => 'AM-2201',
            'operator' => 'Navegação Rio Negro',
            'default_destination' => 'Careiro da Várzea',
            'capacity_vehicles' => 20,
            'active' => true,
        ]);

        $novaEra = Vessel::firstOrCreate(['name' => 'Balsa Nova Era'], [
            'type' => 'balsa',
            'registration' => 'AM-3390',
            'operator' => 'Amazonas Ferry',
            'default_destination' => 'Manacapuru',
            'capacity_vehicles' => 15,
            'active' => true,
        ]);

        Vessel::firstOrCreate(['name' => 'Lancha Expresso'], [
            'type' => 'lancha',
            'operator' => 'Amazonas Ferry',
            'default_destination' => 'Iranduba',
            'active' => true,
        ]);

        // Grade: São Jorge seg-sex 08/12/16h; Nova Era todo dia 10/15h
        foreach (['08:00', '12:00', '16:00'] as $hora) {
            VesselSchedule::firstOrCreate(
                ['vessel_id' => $saoJorge->id, 'departure_time' => $hora],
                ['days_of_week' => [1, 2, 3, 4, 5], 'destination' => 'Careiro da Várzea', 'active' => true],
            );
        }
        foreach (['10:00', '15:00'] as $hora) {
            VesselSchedule::firstOrCreate(
                ['vessel_id' => $novaEra->id, 'departure_time' => $hora],
                ['days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'destination' => 'Manacapuru', 'active' => true],
            );
        }

        // Gera as viagens dos próximos 14 dias a partir da grade
        app(VesselService::class)->generateDepartures(Carbon::today(), Carbon::today()->addDays(14));

        // Massa de demonstração: empresas faturadas + acessos avulsos pagantes.
        $this->call(DemoDataSeeder::class);
    }
}
