<?php

namespace Tests\Feature;

use App\Models\CameraEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o adapter do push ANPR da câmera Hikvision (HikvisionAnprController).
 * Garante que o parsing/normalização cria um CameraEvent sem depender da câmera física.
 */
class HikvisionAnprTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portoaccess.camera_token' => 'test-token']);
    }

    public function test_rejeita_token_invalido(): void
    {
        $this->postJson('/api/camera/hikvision', ['licensePlate' => 'ABC1D23'], [
            'X-Camera-Token' => 'errado',
        ])->assertStatus(401);

        $this->assertDatabaseCount('camera_events', 0);
    }

    public function test_cria_evento_a_partir_de_xml_anpr(): void
    {
        $xml = <<<'XML'
        <EventNotificationAlert>
            <ipAddress>192.168.1.50</ipAddress>
            <eventType>ANPR</eventType>
            <ANPR>
                <licensePlate>abc1d23</licensePlate>
                <vehicleType>car</vehicleType>
                <vehicleColor>prata</vehicleColor>
                <vehicleLogo>Toyota</vehicleLogo>
                <confidenceLevel>92</confidenceLevel>
            </ANPR>
        </EventNotificationAlert>
        XML;

        $response = $this->call(
            'POST',
            '/api/camera/hikvision?camera=saida&token=test-token',
            [], [], [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xml,
        );

        $response->assertStatus(201);

        $event = CameraEvent::sole();
        $this->assertSame('saida', $event->camera);
        $this->assertSame('ABC1D23', $event->plate); // normalizada (maiúscula, sem símbolos)
        $this->assertSame('prata', $event->color);
        $this->assertSame('car', $event->model);
        $this->assertSame('Toyota', $event->brand);
        $this->assertEquals(92.0, (float) $event->confidence);
    }

    public function test_cria_evento_a_partir_de_json(): void
    {
        $this->postJson('/api/camera/hikvision?camera=entrada', [
            'plateNumber' => 'XYZ9A88',
            'color' => 'preto',
        ], ['X-Camera-Token' => 'test-token'])->assertStatus(201);

        $event = CameraEvent::sole();
        $this->assertSame('entrada', $event->camera);
        $this->assertSame('XYZ9A88', $event->plate);
        $this->assertSame('preto', $event->color);
    }

    public function test_push_sem_placa_e_ignorado_sem_criar_evento(): void
    {
        $this->postJson('/api/camera/hikvision?camera=entrada', [
            'vehicleColor' => 'azul',
        ], ['X-Camera-Token' => 'test-token'])
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored']);

        $this->assertDatabaseCount('camera_events', 0);
    }
}
