<?php

namespace Database\Seeders;

use App\Enums\AccessStatus;
use App\Enums\PaymentMethod;
use App\Models\AccessRecord;
use App\Models\AuthorizedVehicle;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Transportes Rio Negro Ltda'],
            [
                'cnpj'            => '12.345.678/0001-90',
                'contact'         => 'Maria Souza',
                'email'           => 'financeiro@rionegro.example',
                'phone'           => '(92) 99999-0000',
                'billing_cycle'   => 'mensal',
                'credit_limit'    => 10000.00,
                'discount_percent'=> 10.00,
                'active'          => true,
            ]
        );

        $operador = User::where('role', 'operador')->first()
            ?? User::where('role', 'admin')->first();

        $retirada = EntryType::where('name', 'Retirada de mercadoria')->firstOrFail();
        $balsa    = EntryType::where('name', 'Embarque na balsa')->firstOrFail();

        $carro    = VehicleCategory::where('name', 'Carro de passeio')->firstOrFail();
        $caminhao = VehicleCategory::where('name', 'Caminhão toco (2 eixos)')->firstOrFail();
        $pickup   = VehicleCategory::where('name', 'Caminhonete (pickup)')->firstOrFail();
        $van      = VehicleCategory::where('name', 'Van / Utilitário')->firstOrFail();
        $moto     = VehicleCategory::where('name', 'Motocicleta')->firstOrFail();

        // ── Frota da empresa ────────────────────────────────────────
        $frota = [
            ['plate' => 'RIO0A01', 'category' => $carro,    'brand' => 'Toyota',    'model' => 'Corolla',   'color' => 'Prata',   'driver' => 'Carlos Menezes'],
            ['plate' => 'RIO0B02', 'category' => $pickup,   'brand' => 'Ford',      'model' => 'Ranger',    'color' => 'Branco',  'driver' => 'Ana Lima'],
            ['plate' => 'RIO0C03', 'category' => $caminhao, 'brand' => 'Mercedes',  'model' => 'Atego',     'color' => 'Azul',    'driver' => 'João Farias'],
            ['plate' => 'RIO0D04', 'category' => $van,      'brand' => 'Fiat',      'model' => 'Ducato',    'color' => 'Branco',  'driver' => 'Pedro Costa'],
            ['plate' => 'RIO0E05', 'category' => $moto,     'brand' => 'Honda',     'model' => 'CG 160',    'color' => 'Vermelho','driver' => 'Lucas Pinto'],
            ['plate' => 'RIO0F06', 'category' => $carro,    'brand' => 'Chevrolet', 'model' => 'Cruze',     'color' => 'Preto',   'driver' => 'Fernanda Rocha'],
        ];

        $vehicles = [];
        foreach ($frota as $v) {
            $veh = Vehicle::firstOrCreate(
                ['plate' => $v['plate']],
                [
                    'vehicle_category_id' => $v['category']->id,
                    'brand'      => $v['brand'],
                    'model'      => $v['model'],
                    'color'      => $v['color'],
                    'owner_name' => $v['driver'],
                ]
            );

            // Autorização de empresa (se ainda não existir)
            AuthorizedVehicle::firstOrCreate(
                ['vehicle_id' => $veh->id, 'type' => 'empresa'],
                [
                    'company_id'    => $company->id,
                    'employee_name' => $v['driver'],
                    'active'        => true,
                ]
            );

            $vehicles[] = ['vehicle' => $veh, 'category' => $v['category'], 'driver' => $v['driver']];
        }

        // ── Histórico de acessos faturados por mês ──────────────────
        // Cada entrada: [mes, dia, hora_entrada, horas_permanencia, tipo, veiculo_index]
        $roteiro = [
            // ABRIL
            ['2026-04-02', '08:00', 3, $balsa,    0],
            ['2026-04-03', '09:30', 2, $retirada, 1],
            ['2026-04-07', '07:45', 5, $balsa,    2],
            ['2026-04-10', '13:00', 1, $retirada, 3],
            ['2026-04-14', '10:00', 4, $balsa,    0],
            ['2026-04-17', '08:30', 2, $retirada, 4],
            ['2026-04-21', '11:00', 3, $balsa,    1],
            ['2026-04-24', '14:00', 2, $retirada, 5],
            ['2026-04-28', '09:00', 6, $balsa,    2],

            // MAIO
            ['2026-05-02', '07:30', 3, $balsa,    3],
            ['2026-05-05', '09:00', 2, $retirada, 0],
            ['2026-05-08', '10:30', 5, $balsa,    4],
            ['2026-05-12', '08:00', 2, $retirada, 1],
            ['2026-05-15', '13:30', 4, $balsa,    5],
            ['2026-05-19', '09:15', 1, $retirada, 2],
            ['2026-05-22', '07:45', 3, $balsa,    0],
            ['2026-05-26', '11:00', 2, $retirada, 3],
            ['2026-05-29', '08:30', 5, $balsa,    1],

            // JUNHO (até hoje)
            ['2026-06-02', '08:00', 3, $balsa,    5],
            ['2026-06-04', '09:30', 2, $retirada, 2],
            ['2026-06-06', '10:00', 4, $balsa,    4],
            ['2026-06-09', '08:45', 2, $retirada, 0],
            ['2026-06-11', '13:00', 3, $balsa,    3],
        ];

        $discount = $company->discount_percent / 100;

        foreach ($roteiro as [$date, $time, $hours, $entryType, $vIdx]) {
            $v    = $vehicles[$vIdx];
            $veh  = $v['vehicle'];
            $cat  = $v['category'];

            $enteredAt = Carbon::parse("{$date} {$time}");
            $exitedAt  = $enteredAt->copy()->addHours($hours);

            // Evita duplicar se já existe registro finalizado no mesmo dia para a mesma placa
            $exists = AccessRecord::where('vehicle_id', $veh->id)
                ->whereDate('entered_at', $enteredAt->toDateString())
                ->where('status', AccessStatus::Finalizado)
                ->exists();

            if ($exists) {
                continue;
            }

            // Busca o preço vigente
            $price = \App\Models\Price::where('entry_type_id', $entryType->id)
                ->where('vehicle_category_id', $cat->id)
                ->where('valid_from', '<=', $enteredAt)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $enteredAt))
                ->orderByDesc('valid_from')
                ->value('amount') ?? 0;

            $amountDue      = (float) $price;
            $discountAmount = round($amountDue * $discount, 2);
            $netAmount      = round($amountDue - $discountAmount, 2);

            $record = AccessRecord::create([
                'vehicle_id'          => $veh->id,
                'entry_type_id'       => $entryType->id,
                'vehicle_category_id' => $cat->id,
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
        }

        $this->command->info('✔ Histórico de testes criado: 6 veículos, 3 meses de acessos faturados.');
        $this->command->info("  Empresa: {$company->name} (desconto {$company->discount_percent}%)");
        $this->command->info('  Meses disponíveis: Abril, Maio e Junho/2026');
        $this->command->info('  Para gerar fatura: Admin → Faturas → Fechar período');
    }
}
