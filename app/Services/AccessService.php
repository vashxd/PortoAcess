<?php

namespace App\Services;

use App\Enums\AccessStatus;
use App\Enums\CameraEventStatus;
use App\Enums\ChargeMoment;
use App\Enums\PaymentMethod;
use App\Models\AccessRecord;
use App\Models\CameraEvent;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessService
{
    public function __construct(
        private PriceService $prices,
        private PaymentService $payments,
    ) {}

    /**
     * Registra a entrada de um veículo (a partir de evento da câmera ou manual).
     *
     * @param array $data plate, vehicle_category_id, entry_type_id, company_id?,
     *                    visitor_name?, visitor_document?, destination?,
     *                    brand?, model?, color?, owner_name?,
     *                    camera_event_id?, manual_entry?, payments? (tipos pagos na entrada),
     *                    exemption_reason?
     */
    public function registerEntry(array $data, int $operatorId): AccessRecord
    {
        return DB::transaction(function () use ($data, $operatorId) {
            $plate = Vehicle::normalizePlate($data['plate']);
            $entryType = EntryType::findOrFail($data['entry_type_id']);
            $company = ! empty($data['company_id']) ? Company::findOrFail($data['company_id']) : null;
            $event = ! empty($data['camera_event_id']) ? CameraEvent::find($data['camera_event_id']) : null;

            $vehicle = Vehicle::firstOrNew(['plate' => $plate]);

            // Veículo já no pátio não pode entrar de novo
            $open = AccessRecord::where('vehicle_id', $vehicle->id ?? 0)
                ->where('status', AccessStatus::NoPatio)->exists();
            if ($vehicle->exists && $open) {
                throw ValidationException::withMessages([
                    'plate' => "O veículo {$plate} já está no pátio (registro de entrada em aberto).",
                ]);
            }

            // Divergência cor/modelo × cadastro (possível placa clonada)
            $mismatch = false;
            if ($vehicle->exists && $event) {
                $mismatch = $this->detectMismatch($vehicle, $event->color, $event->model);
            }

            // Atualiza/completa o cadastro do veículo
            $vehicle->fill(array_filter([
                'vehicle_category_id' => $data['vehicle_category_id'] ?? null,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'color' => $data['color'] ?? null,
                'owner_name' => $data['owner_name'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
            $vehicle->save();

            $due = $this->prices->amountDue($entryType, (int) $data['vehicle_category_id'], $company);

            if (! empty($data['exemption_reason'])) {
                $due['discount'] = $due['amount']; // isenção pontual: desconto total, justificado
            }

            $record = AccessRecord::create([
                'vehicle_id' => $vehicle->id,
                'entry_type_id' => $entryType->id,
                'vehicle_category_id' => $data['vehicle_category_id'],
                'entered_at' => now(),
                'entry_photo' => $event?->photo_path,
                'detected_color' => $event?->color,
                'detected_model' => $event?->model,
                'plate_read_confidence' => $event?->confidence,
                'amount_due' => $due['amount'],
                'discount_amount' => $due['discount'],
                'status' => AccessStatus::NoPatio,
                'manual_entry' => (bool) ($data['manual_entry'] ?? $event === null),
                'color_model_mismatch' => $mismatch,
                'operator_in_id' => $operatorId,
                'company_id' => $company?->id,
                'visitor_name' => $data['visitor_name'] ?? null,
                'visitor_document' => $data['visitor_document'] ?? null,
                'destination' => $data['destination'] ?? null,
                'exemption_reason' => $data['exemption_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($event) {
                $event->update([
                    'access_record_id' => $record->id,
                    'status' => CameraEventStatus::Vinculado,
                ]);
            }

            // Tipo pago com cobrança na entrada (ex.: balsa): pagamento obrigatório antes da liberação
            if ($entryType->is_paid && $entryType->charge_moment === ChargeMoment::Entrada && $record->balanceDue() > 0) {
                if (empty($data['payments'])) {
                    throw ValidationException::withMessages([
                        'payments' => 'Este tipo de entrada exige pagamento antes da liberação.',
                    ]);
                }
                $this->payments->register($record, $data['payments'], $operatorId, $data['billing_justification'] ?? null);
            }

            return $record;
        });
    }

    /**
     * Registra a saída de um veículo que está no pátio.
     *
     * @param array $data payments?, camera_event_id?, billing_justification?, exemption_reason?
     */
    public function registerExit(AccessRecord $record, array $data, int $operatorId): AccessRecord
    {
        return DB::transaction(function () use ($record, $data, $operatorId) {
            if ($record->status !== AccessStatus::NoPatio) {
                throw ValidationException::withMessages([
                    'record' => 'Este registro não está em aberto.',
                ]);
            }

            $event = ! empty($data['camera_event_id']) ? CameraEvent::find($data['camera_event_id']) : null;

            if (! empty($data['exemption_reason'])) {
                $record->discount_amount = $record->amount_due;
                $record->exemption_reason = $data['exemption_reason'];
                $record->save();
            }

            if (! empty($data['payments'])) {
                $this->payments->register($record, $data['payments'], $operatorId, $data['billing_justification'] ?? null);
            }

            $record->refresh();
            if ($record->balanceDue() > 0.009) {
                throw ValidationException::withMessages([
                    'payments' => sprintf('Há valor pendente de R$ %.2f. Registre o pagamento antes de liberar a saída.', $record->balanceDue()),
                ]);
            }

            $record->update([
                'exited_at' => now(),
                'exit_photo' => $event?->photo_path,
                'status' => AccessStatus::Finalizado,
                'operator_out_id' => $operatorId,
            ]);

            if ($event) {
                $event->update([
                    'access_record_id' => $record->id,
                    'status' => CameraEventStatus::Vinculado,
                ]);
            }

            return $record;
        });
    }

    /**
     * Saída sem registro de entrada correspondente (RF09): registro manual auditado.
     */
    public function registerExitWithoutEntry(array $data, int $operatorId): AccessRecord
    {
        return DB::transaction(function () use ($data, $operatorId) {
            $plate = Vehicle::normalizePlate($data['plate']);
            $vehicle = Vehicle::firstOrCreate(
                ['plate' => $plate],
                ['vehicle_category_id' => $data['vehicle_category_id'] ?? null],
            );

            $event = ! empty($data['camera_event_id']) ? CameraEvent::find($data['camera_event_id']) : null;

            $record = AccessRecord::create([
                'vehicle_id' => $vehicle->id,
                'entry_type_id' => $data['entry_type_id'],
                'vehicle_category_id' => $data['vehicle_category_id'],
                'entered_at' => now(),
                'exited_at' => now(),
                'exit_photo' => $event?->photo_path,
                'status' => AccessStatus::Finalizado,
                'manual_entry' => true,
                'exit_without_entry' => true,
                'operator_out_id' => $operatorId,
                'notes' => $data['justification'],
            ]);

            if ($event) {
                $event->update(['access_record_id' => $record->id, 'status' => CameraEventStatus::Vinculado]);
            }

            return $record;
        });
    }

    private function detectMismatch(Vehicle $vehicle, ?string $color, ?string $model): bool
    {
        $colorMismatch = $vehicle->color && $color
            && mb_strtolower(trim($vehicle->color)) !== mb_strtolower(trim($color));

        $modelMismatch = $vehicle->model && $model
            && ! str_contains(mb_strtolower($model), mb_strtolower(trim($vehicle->model)))
            && ! str_contains(mb_strtolower($vehicle->model), mb_strtolower(trim($model)));

        return $colorMismatch || $modelMismatch;
    }
}
