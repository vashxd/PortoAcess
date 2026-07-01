<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Price;
use App\Models\User;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

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
            'requires_visitor_info' => false,
        ]);

        $balsa = EntryType::firstOrCreate(['name' => 'Embarque na balsa'], [
            'is_paid' => true,
            'charge_moment' => 'entrada',
            'requires_visitor_info' => false,
        ]);

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

        // Massa de demonstração: empresas faturadas + acessos avulsos pagantes.
        $this->call(DemoDataSeeder::class);
    }
}
