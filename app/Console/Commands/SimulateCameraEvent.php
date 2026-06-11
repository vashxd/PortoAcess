<?php

namespace App\Console\Commands;

use App\Models\CameraEvent;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SimulateCameraEvent extends Command
{
    protected $signature = 'camera:simulate
        {camera=entrada : Câmera (entrada|saida)}
        {plate? : Placa (aleatória se omitida)}
        {--color= : Cor detectada}
        {--model= : Modelo detectado}
        {--known : Usa um veículo já cadastrado no banco}';

    protected $description = 'Simula um evento de leitura da câmera LPR (para testes sem hardware)';

    public function handle(): int
    {
        $camera = $this->argument('camera');

        if (! in_array($camera, ['entrada', 'saida'], true)) {
            $this->error('Câmera deve ser "entrada" ou "saida".');

            return self::FAILURE;
        }

        $plate = $this->argument('plate');
        $color = $this->option('color');
        $model = $this->option('model');

        if ($this->option('known')) {
            $vehicle = Vehicle::inRandomOrder()->first();
            if (! $vehicle) {
                $this->error('Nenhum veículo cadastrado. Rode php artisan db:seed primeiro.');

                return self::FAILURE;
            }
            $plate = $vehicle->plate;
            $color = $color ?? $vehicle->color;
            $model = $model ?? $vehicle->model;
        }

        $plate = $plate ? Vehicle::normalizePlate($plate) : $this->randomPlate();

        $event = CameraEvent::create([
            'camera' => $camera,
            'plate' => $plate,
            'color' => $color ?? collect(['Branco', 'Preto', 'Prata', 'Vermelho', 'Azul'])->random(),
            'model' => $model ?? collect(['Hilux', 'S10', 'Strada', 'Onix', 'HB20', 'Actros', 'FH 540'])->random(),
            'confidence' => round(mt_rand(880, 999) / 10, 1),
            'photo_path' => $this->placeholderPhoto($camera, $plate),
            'occurred_at' => now(),
        ]);

        $this->info("Evento #{$event->id} criado: câmera {$camera}, placa {$plate} ({$event->confidence}%).");
        $this->line('A leitura aparecerá no painel da guarita em alguns segundos (polling).');

        return self::SUCCESS;
    }

    private function randomPlate(): string
    {
        $letters = fn ($n) => collect(range('A', 'Z'))->random($n)->implode('');

        // Padrão Mercosul: LLLNLNN
        return $letters(3).mt_rand(0, 9).$letters(1).mt_rand(10, 99);
    }

    /** Gera uma imagem JPEG simples com a placa (requer ext-gd). */
    private function placeholderPhoto(string $camera, string $plate): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor(640, 360);
        $bg = imagecolorallocate($img, 30, 41, 59);
        $fg = imagecolorallocate($img, 255, 255, 255);
        $accent = imagecolorallocate($img, 56, 189, 248);
        imagefilledrectangle($img, 0, 0, 640, 360, $bg);
        imagefilledrectangle($img, 170, 140, 470, 220, $fg);
        imagestring($img, 5, 220, 170, $plate, $bg);
        imagestring($img, 4, 20, 20, strtoupper("CAMERA {$camera} - SIMULACAO"), $accent);
        imagestring($img, 3, 20, 320, now()->format('d/m/Y H:i:s'), $fg);

        $path = 'captures/'.now()->format('Y/m/d').'/'.uniqid("sim_{$camera}_").'.jpg';
        ob_start();
        imagejpeg($img, null, 85);
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($img);

        return $path;
    }
}
