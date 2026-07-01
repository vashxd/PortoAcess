<?php

namespace Database\Seeders;

use App\Enums\AccessStatus;
use App\Enums\PaymentMethod;
use App\Models\AccessRecord;
use App\Models\AuthorizedVehicle;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Payment;
use App\Models\Price;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Massa de dados de demonstração:
 *   - Várias empresas conveniadas, cada uma com frota de categorias
 *     diferentes e acessos faturados (para fechar período e gerar faturas).
 *   - Acessos avulsos (gerais) pagantes — carros, motos, etc. — pagos em
 *     dinheiro/pix/cartão, em datas variadas.
 *
 * Idempotente: pode rodar mais de uma vez sem duplicar registros.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $operador = User::where('role', 'operador')->first()
            ?? User::where('role', 'admin')->first();

        $retirada = EntryType::where('name', 'Retirada de mercadoria')->firstOrFail();
        $balsa    = EntryType::where('name', 'Embarque na balsa')->firstOrFail();

        // Mapa de categorias por nome curto -> modelo
        $cats = VehicleCategory::pluck('id', 'name');
        $cat = fn (string $name) => $cats[$name];

        $entryByKey = ['retirada' => $retirada, 'balsa' => $balsa];

        // ── EMPRESAS CONVENIADAS ────────────────────────────────────────
        $companies = [
            ['name' => 'Logística Amazonas Express Ltda', 'cnpj' => '11.222.333/0001-44', 'contact' => 'Roberto Dias',    'email' => 'fin@amazonasexpress.example', 'phone' => '(92) 98111-0001', 'billing_cycle' => 'mensal',    'credit_limit' => 15000, 'discount_percent' => 8,  'prefix' => 'AEX'],
            ['name' => 'Pesca & Cia Distribuidora',       'cnpj' => '22.333.444/0001-55', 'contact' => 'Sandra Melo',     'email' => 'fin@pescacia.example',        'phone' => '(92) 98111-0002', 'billing_cycle' => 'quinzenal', 'credit_limit' => 8000,  'discount_percent' => 5,  'prefix' => 'PCD'],
            ['name' => 'Construtora Solimões S/A',         'cnpj' => '33.444.555/0001-66', 'contact' => 'Marcos Tavares',  'email' => 'fin@solimoes.example',        'phone' => '(92) 98111-0003', 'billing_cycle' => 'mensal',    'credit_limit' => 25000, 'discount_percent' => 12, 'prefix' => 'CSL'],
            ['name' => 'AgroNorte Comércio Ltda',          'cnpj' => '44.555.666/0001-77', 'contact' => 'Patrícia Nunes',  'email' => 'fin@agronorte.example',       'phone' => '(92) 98111-0004', 'billing_cycle' => 'mensal',    'credit_limit' => 12000, 'discount_percent' => 7,  'prefix' => 'AGN'],
            ['name' => 'Frigorífico Boa Vista Ltda',       'cnpj' => '55.666.777/0001-88', 'contact' => 'Eduardo Ramos',   'email' => 'fin@frigoboavista.example',   'phone' => '(92) 98111-0005', 'billing_cycle' => 'semanal',   'credit_limit' => 9000,  'discount_percent' => 6,  'prefix' => 'FBV'],
            ['name' => 'Distribuidora Negro & Branco',     'cnpj' => '66.777.888/0001-99', 'contact' => 'Juliana Castro',  'email' => 'fin@negrobranco.example',     'phone' => '(92) 98111-0006', 'billing_cycle' => 'quinzenal', 'credit_limit' => 14000, 'discount_percent' => 9,  'prefix' => 'DNB'],
            ['name' => 'Transportadora Manaus Sul',        'cnpj' => '77.888.999/0001-11', 'contact' => 'Felipe Andrade',  'email' => 'fin@manaussul.example',       'phone' => '(92) 98111-0007', 'billing_cycle' => 'mensal',    'credit_limit' => 20000, 'discount_percent' => 10, 'prefix' => 'TMS'],
            ['name' => 'Bebidas Tropical Ltda',            'cnpj' => '88.999.000/0001-22', 'contact' => 'Camila Freitas',  'email' => 'fin@bebidastropical.example', 'phone' => '(92) 98111-0008', 'billing_cycle' => 'mensal',    'credit_limit' => 11000, 'discount_percent' => 5,  'prefix' => 'BTR'],
        ];

        // Frota-padrão por empresa: variedade de categorias.
        $frotaModelos = [
            ['cat' => 'Carro de passeio',          'brand' => 'Toyota',     'model' => 'Corolla',  'color' => 'Prata',    'driver' => 'Carlos Menezes'],
            ['cat' => 'Caminhonete (pickup)',      'brand' => 'Ford',       'model' => 'Ranger',   'color' => 'Branco',   'driver' => 'Ana Lima'],
            ['cat' => 'Caminhão toco (2 eixos)',   'brand' => 'Mercedes',   'model' => 'Atego',    'color' => 'Azul',     'driver' => 'João Farias'],
            ['cat' => 'Van / Utilitário',          'brand' => 'Fiat',       'model' => 'Ducato',   'color' => 'Branco',   'driver' => 'Pedro Costa'],
            ['cat' => 'Motocicleta',               'brand' => 'Honda',      'model' => 'CG 160',   'color' => 'Vermelho', 'driver' => 'Lucas Pinto'],
            ['cat' => 'Caminhão truck (3 eixos)',  'brand' => 'Volvo',      'model' => 'VM 270',   'color' => 'Branco',   'driver' => 'Marcos Reis'],
            ['cat' => 'Carreta / Bitrem',          'brand' => 'Scania',     'model' => 'R450',     'color' => 'Cinza',    'driver' => 'Rafael Gomes'],
            ['cat' => 'SUV',                        'brand' => 'Jeep',       'model' => 'Compass',  'color' => 'Preto',    'driver' => 'Bianca Souza'],
        ];

        // Calendário de acessos faturados (dia útil, hora, horas, tipo, idx frota)
        // 6 acessos por empresa em datas variadas (abril–junho/2026) => 48 registros.
        $agendaEmpresa = [
            ['2026-04-04', '07:30', 3, 'balsa',    0],
            ['2026-04-11', '09:00', 2, 'retirada', 1],
            ['2026-05-06', '08:15', 4, 'balsa',    2],
            ['2026-05-20', '13:00', 2, 'retirada', 3],
            ['2026-06-03', '07:45', 5, 'balsa',    4],
            ['2026-06-12', '10:30', 2, 'retirada', 5],
        ];

        $totalEmpresa = 0;

        foreach ($companies as $ci => $c) {
            $company = Company::firstOrCreate(
                ['name' => $c['name']],
                [
                    'cnpj'             => $c['cnpj'],
                    'contact'          => $c['contact'],
                    'email'            => $c['email'],
                    'phone'            => $c['phone'],
                    'billing_cycle'    => $c['billing_cycle'],
                    'credit_limit'     => $c['credit_limit'],
                    'discount_percent' => $c['discount_percent'],
                    'active'           => true,
                ]
            );

            // Frota: 6 veículos por empresa, categorias variadas.
            $vehicles = [];
            foreach (array_slice($frotaModelos, 0, 6) as $fi => $fm) {
                $plate = sprintf('%s%d%02d', $c['prefix'], $ci, $fi); // ex.: AEX001
                $veh = Vehicle::firstOrCreate(
                    ['plate' => $plate],
                    [
                        'vehicle_category_id' => $cat($fm['cat']),
                        'brand'      => $fm['brand'],
                        'model'      => $fm['model'],
                        'color'      => $fm['color'],
                        'owner_name' => $fm['driver'],
                    ]
                );

                AuthorizedVehicle::firstOrCreate(
                    ['vehicle_id' => $veh->id, 'type' => 'empresa'],
                    [
                        'company_id'    => $company->id,
                        'employee_name' => $fm['driver'],
                        'active'        => true,
                    ]
                );

                $vehicles[] = ['veh' => $veh, 'cat' => $cat($fm['cat'])];
            }

            $discount = (float) $company->discount_percent / 100;

            foreach ($agendaEmpresa as [$date, $time, $hours, $typeKey, $vIdx]) {
                $entryType = $entryByKey[$typeKey];
                $v   = $vehicles[$vIdx];
                $veh = $v['veh'];
                $catId = $v['cat'];

                $enteredAt = Carbon::parse("{$date} {$time}");
                $exitedAt  = $enteredAt->copy()->addHours($hours);

                $exists = AccessRecord::where('vehicle_id', $veh->id)
                    ->whereDate('entered_at', $enteredAt->toDateString())
                    ->where('status', AccessStatus::Finalizado)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $amountDue = (float) $this->priceFor($entryType->id, $catId, $enteredAt);
                $discountAmount = round($amountDue * $discount, 2);
                $netAmount = round($amountDue - $discountAmount, 2);

                $record = AccessRecord::create([
                    'vehicle_id'          => $veh->id,
                    'entry_type_id'       => $entryType->id,
                    'vehicle_category_id' => $catId,
                    'company_id'          => $company->id,
                    'entered_at'          => $enteredAt,
                    'exited_at'           => $exitedAt,
                    'amount_due'          => $amountDue,
                    'discount_amount'     => $discountAmount,
                    'status'              => AccessStatus::Finalizado,
                    'manual_entry'        => true,
                    'operator_in_id'      => $operador?->id,
                    'operator_out_id'     => $operador?->id,
                ]);

                if ($netAmount > 0) {
                    Payment::create([
                        'access_record_id' => $record->id,
                        'method'           => PaymentMethod::Faturado->value,
                        'amount'           => $netAmount,
                        'paid_at'          => $exitedAt,
                        'user_id'          => $operador?->id,
                        'notes'            => "Faturado — {$company->name}",
                    ]);
                }

                $totalEmpresa++;
            }
        }

        // ── ACESSOS AVULSOS (GERAIS) PAGANTES ───────────────────────────
        // Veículos sem vínculo de empresa, pagos no ato (pix/dinheiro/cartão).
        $avulsos = [
            ['plate' => 'AVU1A23', 'cat' => 'Carro de passeio',         'brand' => 'Volkswagen', 'model' => 'Gol',        'color' => 'Branco',   'owner' => 'José Almeida'],
            ['plate' => 'AVU2B34', 'cat' => 'Motocicleta',              'brand' => 'Yamaha',     'model' => 'Fazer 250',  'color' => 'Azul',     'owner' => 'Mariana Dias'],
            ['plate' => 'AVU3C45', 'cat' => 'SUV',                      'brand' => 'Hyundai',    'model' => 'Creta',      'color' => 'Cinza',    'owner' => 'Ricardo Lopes'],
            ['plate' => 'AVU4D56', 'cat' => 'Caminhonete (pickup)',     'brand' => 'Chevrolet',  'model' => 'S10',        'color' => 'Preto',    'owner' => 'Tatiane Cruz'],
            ['plate' => 'AVU5E67', 'cat' => 'Van / Utilitário',         'brand' => 'Renault',    'model' => 'Master',     'color' => 'Branco',   'owner' => 'Gustavo Pires'],
            ['plate' => 'AVU6F78', 'cat' => 'Caminhão toco (2 eixos)',  'brand' => 'VW',         'model' => 'Delivery',   'color' => 'Vermelho', 'owner' => 'Henrique Sá'],
            ['plate' => 'AVU7G89', 'cat' => 'Motocicleta',              'brand' => 'Honda',      'model' => 'Biz 125',    'color' => 'Preto',    'owner' => 'Larissa Moura'],
            ['plate' => 'AVU8H90', 'cat' => 'Carro de passeio',         'brand' => 'Fiat',       'model' => 'Argo',       'color' => 'Vermelho', 'owner' => 'Diego Barros'],
            ['plate' => 'AVU9I01', 'cat' => 'Ônibus / Micro-ônibus',    'brand' => 'Marcopolo',  'model' => 'Volare',     'color' => 'Branco',   'owner' => 'Turismo Rio Mar'],
            ['plate' => 'AVU0J12', 'cat' => 'Carreta / Bitrem',         'brand' => 'DAF',        'model' => 'XF',         'color' => 'Azul',     'owner' => 'Sérgio Vale'],
            ['plate' => 'AVUAK23', 'cat' => 'Caminhão truck (3 eixos)', 'brand' => 'Iveco',      'model' => 'Tector',     'color' => 'Branco',   'owner' => 'Paulo Nogueira'],
            ['plate' => 'AVUBL34', 'cat' => 'SUV',                      'brand' => 'Jeep',       'model' => 'Renegade',   'color' => 'Verde',    'owner' => 'Aline Teixeira'],
        ];

        $avulsoVehicles = [];
        foreach ($avulsos as $a) {
            $avulsoVehicles[] = Vehicle::firstOrCreate(
                ['plate' => $a['plate']],
                [
                    'vehicle_category_id' => $cat($a['cat']),
                    'brand'      => $a['brand'],
                    'model'      => $a['model'],
                    'color'      => $a['color'],
                    'owner_name' => $a['owner'],
                ]
            );
        }

        $metodos = [
            PaymentMethod::Pix,
            PaymentMethod::Dinheiro,
            PaymentMethod::CartaoDebito,
            PaymentMethod::CartaoCredito,
        ];

        // 36 acessos avulsos em datas variadas (abril–junho/2026).
        $agendaAvulso = [
            ['2026-04-01', '08:10', 1, 'retirada'], ['2026-04-03', '09:40', 2, 'balsa'],
            ['2026-04-06', '11:20', 1, 'retirada'], ['2026-04-09', '14:05', 3, 'balsa'],
            ['2026-04-13', '07:55', 2, 'retirada'], ['2026-04-16', '10:30', 1, 'balsa'],
            ['2026-04-19', '15:00', 2, 'retirada'], ['2026-04-22', '08:45', 4, 'balsa'],
            ['2026-04-25', '13:15', 1, 'retirada'], ['2026-04-28', '09:05', 2, 'balsa'],
            ['2026-05-01', '08:00', 1, 'retirada'], ['2026-05-04', '12:30', 3, 'balsa'],
            ['2026-05-07', '07:40', 2, 'retirada'], ['2026-05-10', '16:10', 1, 'balsa'],
            ['2026-05-13', '09:20', 2, 'retirada'], ['2026-05-16', '11:00', 5, 'balsa'],
            ['2026-05-19', '14:45', 1, 'retirada'], ['2026-05-22', '08:25', 2, 'balsa'],
            ['2026-05-25', '10:15', 3, 'retirada'], ['2026-05-28', '13:50', 1, 'balsa'],
            ['2026-05-31', '07:35', 2, 'retirada'], ['2026-06-01', '09:10', 1, 'balsa'],
            ['2026-06-03', '12:00', 2, 'retirada'], ['2026-06-05', '15:30', 3, 'balsa'],
            ['2026-06-07', '08:05', 1, 'retirada'], ['2026-06-09', '10:40', 2, 'balsa'],
            ['2026-06-11', '14:20', 1, 'retirada'], ['2026-06-13', '07:50', 4, 'balsa'],
            ['2026-06-15', '11:25', 2, 'retirada'], ['2026-06-17', '13:05', 1, 'balsa'],
            ['2026-06-18', '09:35', 2, 'retirada'], ['2026-06-19', '16:00', 3, 'balsa'],
            ['2026-06-20', '08:20', 1, 'retirada'], ['2026-06-21', '10:55', 2, 'balsa'],
            ['2026-06-22', '12:40', 1, 'retirada'], ['2026-06-23', '14:10', 2, 'balsa'],
        ];

        $totalAvulso = 0;

        foreach ($agendaAvulso as $i => [$date, $time, $hours, $typeKey]) {
            $entryType = $entryByKey[$typeKey];
            $veh   = $avulsoVehicles[$i % count($avulsoVehicles)];
            $catId = $veh->vehicle_category_id;
            $method = $metodos[$i % count($metodos)];

            $enteredAt = Carbon::parse("{$date} {$time}");
            $exitedAt  = $enteredAt->copy()->addHours($hours);

            $exists = AccessRecord::where('vehicle_id', $veh->id)
                ->whereDate('entered_at', $enteredAt->toDateString())
                ->where('status', AccessStatus::Finalizado)
                ->exists();
            if ($exists) {
                continue;
            }

            $amountDue = (float) $this->priceFor($entryType->id, $catId, $enteredAt);

            $record = AccessRecord::create([
                'vehicle_id'          => $veh->id,
                'entry_type_id'       => $entryType->id,
                'vehicle_category_id' => $catId,
                'company_id'          => null,
                'entered_at'          => $enteredAt,
                'exited_at'           => $exitedAt,
                'amount_due'          => $amountDue,
                'discount_amount'     => 0,
                'status'              => AccessStatus::Finalizado,
                'manual_entry'        => true,
                'operator_in_id'      => $operador?->id,
                'operator_out_id'     => $operador?->id,
            ]);

            if ($amountDue > 0) {
                Payment::create([
                    'access_record_id' => $record->id,
                    'method'           => $method->value,
                    'amount'           => $amountDue,
                    'card_brand'       => in_array($method, [PaymentMethod::CartaoDebito, PaymentMethod::CartaoCredito])
                        ? ['Visa', 'Mastercard', 'Elo'][$i % 3]
                        : null,
                    'paid_at'          => $exitedAt,
                    'user_id'          => $operador?->id,
                    'notes'            => 'Pagamento avulso ('.$method->label().')',
                ]);
            }

            $totalAvulso++;
        }

        $this->command->info("✔ Demo: {$totalEmpresa} acessos faturados (empresas) e {$totalAvulso} acessos avulsos criados.");
        $this->command->info('  Empresas conveniadas: '.count($companies).' (+ existentes)');
        $this->command->info('  Para gerar faturas: Admin → Faturas → Fechar período por empresa.');
    }

    /** Busca o preço vigente para o tipo+categoria na data informada. */
    private function priceFor(int $entryTypeId, int $categoryId, Carbon $when): float
    {
        return (float) (Price::where('entry_type_id', $entryTypeId)
            ->where('vehicle_category_id', $categoryId)
            ->where('valid_from', '<=', $when)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $when))
            ->orderByDesc('valid_from')
            ->value('amount') ?? 0);
    }
}
