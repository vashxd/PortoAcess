<?php

namespace Tests\Feature;

use App\Enums\AccessStatus;
use App\Enums\InvoiceStatus;
use App\Models\AccessRecord;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->operador = User::where('email', 'operador@portoaccess.local')->first();
        $this->admin = User::where('email', 'admin@portoaccess.local')->first();
    }

    private function ids(): array
    {
        return [
            'retirada' => EntryType::where('name', 'Retirada de mercadoria')->first()->id,
            'balsa' => EntryType::where('name', 'Embarque na balsa')->first()->id,
            'visita' => EntryType::where('name', 'Visita')->first()->id,
            'carro' => VehicleCategory::where('name', 'Carro de passeio')->first()->id,
        ];
    }

    public function test_webhook_da_camera_cria_evento(): void
    {
        $res = $this->postJson('/api/camera/events', [
            'camera' => 'entrada',
            'plate' => 'abc-1d23',
            'color' => 'Prata',
            'model' => 'Onix',
            'confidence' => 97.5,
        ], ['X-Camera-Token' => config('portoaccess.camera_token')]);

        $res->assertCreated();
        $this->assertDatabaseHas('camera_events', ['plate' => 'ABC1D23', 'status' => 'pendente']);
    }

    public function test_webhook_rejeita_token_invalido(): void
    {
        $this->postJson('/api/camera/events', ['camera' => 'entrada'], ['X-Camera-Token' => 'errado'])
            ->assertUnauthorized();
    }

    public function test_fluxo_retirada_com_pagamento_misto_na_saida(): void
    {
        $ids = $this->ids();

        // Entrada (cobrança na saída — R$ 20 para carro de passeio)
        $this->actingAs($this->operador)
            ->post('/guarita/entrada', [
                'plate' => 'AAA1B22',
                'entry_type_id' => $ids['retirada'],
                'vehicle_category_id' => $ids['carro'],
                'manual_entry' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record = AccessRecord::latest('id')->first();
        $this->assertEquals(AccessStatus::NoPatio, $record->status);
        $this->assertEquals(20.00, (float) $record->amount_due);

        // Saída sem pagar deve falhar
        $this->actingAs($this->operador)
            ->post("/guarita/saida/{$record->id}", [])
            ->assertSessionHasErrors('payments');

        // Saída com pagamento misto (PIX + cartão) somando exatamente o valor
        $this->actingAs($this->operador)
            ->post("/guarita/saida/{$record->id}", [
                'payments' => [
                    ['method' => 'pix', 'amount' => 12.00],
                    ['method' => 'cartao_credito', 'amount' => 8.00, 'card_brand' => 'Visa'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertEquals(AccessStatus::Finalizado, $record->status);
        $this->assertEquals(0.0, $record->balanceDue());
        $this->assertCount(2, $record->payments);
    }

    public function test_pagamento_misto_com_soma_errada_e_rejeitado(): void
    {
        $ids = $this->ids();

        $this->actingAs($this->operador)->post('/guarita/entrada', [
            'plate' => 'BBB2C33',
            'entry_type_id' => $ids['retirada'],
            'vehicle_category_id' => $ids['carro'],
        ]);

        $record = AccessRecord::latest('id')->first();

        $this->actingAs($this->operador)
            ->post("/guarita/saida/{$record->id}", [
                'payments' => [
                    ['method' => 'pix', 'amount' => 10.00],
                    ['method' => 'dinheiro', 'amount' => 5.00],
                ],
            ])
            ->assertSessionHasErrors('payments');
    }

    public function test_balsa_exige_pagamento_na_entrada(): void
    {
        $ids = $this->ids();

        // Sem pagamento → bloqueado
        $this->actingAs($this->operador)
            ->post('/guarita/entrada', [
                'plate' => 'CCC3D44',
                'entry_type_id' => $ids['balsa'],
                'vehicle_category_id' => $ids['carro'],
            ])
            ->assertSessionHasErrors('payments');

        // Com pagamento → liberado (R$ 60 carro de passeio)
        $this->actingAs($this->operador)
            ->post('/guarita/entrada', [
                'plate' => 'CCC3D44',
                'entry_type_id' => $ids['balsa'],
                'vehicle_category_id' => $ids['carro'],
                'payments' => [['method' => 'pix', 'amount' => 60.00]],
            ])
            ->assertSessionHasNoErrors();

        $record = AccessRecord::latest('id')->first();
        $this->assertEquals(0.0, $record->balanceDue());
    }

    public function test_faturamento_de_empresa_conveniada_gera_fatura(): void
    {
        $ids = $this->ids();
        $company = Company::first(); // 10% de desconto no seeder

        // Empresa autoriza o veículo
        $vehicle = Vehicle::create(['plate' => 'DDD4E55', 'vehicle_category_id' => $ids['carro']]);
        $vehicle->authorizations()->create(['type' => 'empresa', 'company_id' => $company->id, 'active' => true]);

        // Entrada vinculada à empresa (retirada: R$ 20 − 10% = R$ 18)
        $this->actingAs($this->operador)->post('/guarita/entrada', [
            'plate' => 'DDD4E55',
            'entry_type_id' => $ids['retirada'],
            'vehicle_category_id' => $ids['carro'],
            'company_id' => $company->id,
        ])->assertSessionHasNoErrors();

        $record = AccessRecord::latest('id')->first();
        $this->assertEquals(18.00, $record->balanceDue());

        // Saída faturada
        $this->actingAs($this->operador)->post("/guarita/saida/{$record->id}", [
            'payments' => [['method' => 'faturado', 'amount' => 18.00]],
        ])->assertSessionHasNoErrors();

        // Fechamento do período pelo admin
        $this->actingAs($this->admin)->post('/admin/faturas', [
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::first();
        $this->assertEquals(18.00, (float) $invoice->total);
        $this->assertEquals(InvoiceStatus::Aberta, $invoice->status);
        $this->assertCount(1, $invoice->items);

        // PDF da fatura
        $this->actingAs($this->admin)
            ->get("/admin/faturas/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Baixa
        $this->actingAs($this->admin)->post("/admin/faturas/{$invoice->id}/baixa");
        $this->assertEquals(InvoiceStatus::Paga, $invoice->fresh()->status);
    }

    public function test_saida_sem_entrada_exige_justificativa(): void
    {
        $ids = $this->ids();

        $this->actingAs($this->operador)
            ->post('/guarita/saida-sem-entrada', [
                'plate' => 'EEE5F66',
                'entry_type_id' => $ids['visita'],
                'vehicle_category_id' => $ids['carro'],
            ])
            ->assertSessionHasErrors('justification');

        $this->actingAs($this->operador)
            ->post('/guarita/saida-sem-entrada', [
                'plate' => 'EEE5F66',
                'entry_type_id' => $ids['visita'],
                'vehicle_category_id' => $ids['carro'],
                'justification' => 'Veículo entrou antes da implantação do sistema',
            ])
            ->assertSessionHasNoErrors();

        $record = AccessRecord::latest('id')->first();
        $this->assertTrue($record->exit_without_entry);
        $this->assertEquals(AccessStatus::Finalizado, $record->status);
    }

    public function test_cancelamento_solicitado_pelo_operador_e_aprovado_pelo_admin(): void
    {
        $ids = $this->ids();

        $this->actingAs($this->operador)->post('/guarita/entrada', [
            'plate' => 'FFF6G77',
            'entry_type_id' => $ids['visita'],
            'vehicle_category_id' => $ids['carro'],
            'visitor_name' => 'João',
            'visitor_document' => '123',
        ]);

        $record = AccessRecord::latest('id')->first();

        $this->actingAs($this->operador)
            ->post("/guarita/registros/{$record->id}/solicitar-cancelamento", ['reason' => 'Registro duplicado'])
            ->assertSessionHasNoErrors();

        // Operador NÃO pode aprovar
        $this->actingAs($this->operador)
            ->post("/admin/cancelamentos/{$record->id}/aprovar")
            ->assertForbidden();

        // Admin aprova
        $this->actingAs($this->admin)->post("/admin/cancelamentos/{$record->id}/aprovar");
        $this->assertEquals(AccessStatus::Cancelado, $record->fresh()->status);
    }

    public function test_rbac_bloqueia_perfis_sem_permissao(): void
    {
        $financeiro = User::where('email', 'financeiro@portoaccess.local')->first();
        $auditor = User::where('email', 'auditor@portoaccess.local')->first();

        // Financeiro não acessa a guarita nem cadastros
        $this->actingAs($financeiro)->get('/guarita')->assertForbidden();
        $this->actingAs($financeiro)->get('/admin/usuarios')->assertForbidden();
        // ...mas acessa faturas e dashboard
        $this->actingAs($financeiro)->get('/admin/faturas')->assertOk();
        $this->actingAs($financeiro)->get('/admin/dashboard')->assertOk();

        // Auditor só leitura: dashboard e auditoria ok, faturas não
        $this->actingAs($auditor)->get('/admin/auditoria')->assertOk();
        $this->actingAs($auditor)->get('/admin/faturas')->assertForbidden();
        $this->actingAs($auditor)->get('/guarita')->assertForbidden();

        // Operador não acessa administração
        $this->actingAs($this->operador)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->operador)->get('/guarita')->assertOk();
    }

    public function test_auditoria_registra_alteracao_de_preco(): void
    {
        $this->actingAs($this->admin)->post('/admin/precos', [
            'entry_type_id' => $this->ids()['retirada'],
            'vehicle_category_id' => $this->ids()['carro'],
            'amount' => 25.00,
            'valid_from' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['entity' => 'Price', 'action' => 'created']);
    }

    public function test_paginas_principais_renderizam(): void
    {
        foreach (['/guarita', '/guarita/patio', '/guarita/consulta?placa=AAA1B22'] as $url) {
            $this->actingAs($this->operador)->get($url)->assertOk();
        }

        foreach ([
            '/admin/dashboard', '/admin/categorias', '/admin/tipos-entrada', '/admin/precos',
            '/admin/empresas', '/admin/autorizados', '/admin/usuarios', '/admin/faturas',
            '/admin/relatorios', '/admin/auditoria', '/admin/cancelamentos',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }
}
