<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthorizedVehicleController;
use App\Http\Controllers\Admin\CancellationController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EntryTypeController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleCategoryController;
use App\Http\Controllers\Admin\VesselController;
use App\Http\Controllers\Admin\VesselDepartureController;
use App\Http\Controllers\Admin\VesselScheduleController;
use App\Http\Controllers\Guarita\AccessController;
use App\Http\Controllers\Guarita\GateController;
use App\Http\Controllers\Guarita\GuaritaController;
use App\Http\Controllers\Guarita\PatioController;
use App\Http\Controllers\Guarita\PaymentController;
use App\Http\Controllers\Guarita\PlateLookupController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    return $user
        ? redirect()->route($user->homeRoute())
        : redirect()->route('login');
});

Route::get('/dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Guarita (Operador + Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:operador,admin'])->prefix('guarita')->name('guarita.')->group(function () {
    Route::get('/', [GuaritaController::class, 'painel'])->name('painel');
    Route::get('/eventos', [GuaritaController::class, 'eventos'])->name('eventos'); // polling JSON
    Route::post('/eventos/{cameraEvent}/descartar', [GuaritaController::class, 'descartarEvento'])->name('eventos.descartar');

    Route::get('/lookup', [PlateLookupController::class, 'lookup'])->name('lookup'); // JSON p/ pré-preencher formulário
    Route::get('/consulta', [PlateLookupController::class, 'index'])->name('consulta');

    Route::post('/entrada', [AccessController::class, 'storeEntry'])->name('entrada');
    Route::post('/saida/{record}', [AccessController::class, 'storeExit'])->name('saida');
    Route::post('/saida-sem-entrada', [AccessController::class, 'storeExitWithoutEntry'])->name('saida-sem-entrada');
    Route::post('/registros/{record}/solicitar-cancelamento', [AccessController::class, 'requestCancel'])->name('solicitar-cancelamento');

    Route::post('/registros/{record}/pagamentos', [PaymentController::class, 'store'])->name('pagamentos');

    Route::get('/patio', [PatioController::class, 'index'])->name('patio');

    Route::post('/cancela/{camera}', [GateController::class, 'open'])
        ->where('camera', 'entrada|saida')
        ->name('cancela');
});

/*
|--------------------------------------------------------------------------
| Administração
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Dashboard: admin, financeiro e auditor (leitura)
    Route::middleware('role:admin,financeiro,auditor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/relatorios', [ReportController::class, 'index'])->name('relatorios');
        Route::get('/relatorios/export', [ReportController::class, 'export'])->name('relatorios.export');
    });

    // Quadro de viagens das balsas — visível a todos os perfis; gestão é só admin
    Route::middleware('role:operador,admin,financeiro,auditor')->group(function () {
        Route::get('/viagens', [VesselDepartureController::class, 'index'])->name('viagens.index');
    });

    // Cadastros: somente admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('categorias', VehicleCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['categorias' => 'category']);
        Route::resource('tipos-entrada', EntryTypeController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['tipos-entrada' => 'entryType']);
        Route::resource('precos', PriceController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['precos' => 'price']);
        Route::resource('autorizados', AuthorizedVehicleController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['autorizados' => 'authorizedVehicle']);
        Route::resource('usuarios', UserController::class)
            ->only(['index', 'store', 'update'])->parameters(['usuarios' => 'user']);

        // Balsas e embarcações
        Route::resource('embarcacoes', VesselController::class)
            ->only(['index', 'store', 'update', 'destroy'])->parameters(['embarcacoes' => 'vessel']);
        Route::post('/horarios', [VesselScheduleController::class, 'store'])->name('horarios.store');
        Route::put('/horarios/{schedule}', [VesselScheduleController::class, 'update'])->name('horarios.update');
        Route::delete('/horarios/{schedule}', [VesselScheduleController::class, 'destroy'])->name('horarios.destroy');
        Route::post('/viagens/gerar', [VesselDepartureController::class, 'generate'])->name('viagens.gerar');
        Route::post('/viagens', [VesselDepartureController::class, 'store'])->name('viagens.store');
        Route::put('/viagens/{departure}', [VesselDepartureController::class, 'update'])->name('viagens.update');
        Route::delete('/viagens/{departure}', [VesselDepartureController::class, 'destroy'])->name('viagens.destroy');

        Route::get('/cancelamentos', [CancellationController::class, 'index'])->name('cancelamentos');
        Route::post('/cancelamentos/{record}/aprovar', [CancellationController::class, 'approve'])->name('cancelamentos.aprovar');
        Route::post('/cancelamentos/{record}/rejeitar', [CancellationController::class, 'reject'])->name('cancelamentos.rejeitar');
    });

    // Empresas e faturas: admin + financeiro
    Route::middleware('role:admin,financeiro')->group(function () {
        Route::resource('empresas', CompanyController::class)
            ->only(['index', 'show', 'store', 'update'])->parameters(['empresas' => 'company']);
        Route::get('/faturas', [InvoiceController::class, 'index'])->name('faturas.index');
        Route::post('/faturas', [InvoiceController::class, 'store'])->name('faturas.store');
        Route::get('/faturas/{invoice}', [InvoiceController::class, 'show'])->name('faturas.show');
        Route::get('/faturas/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('faturas.pdf');
        Route::post('/faturas/{invoice}/baixa', [InvoiceController::class, 'markPaid'])->name('faturas.baixa');
    });

    // Auditoria: admin + auditor
    Route::middleware('role:admin,auditor')->group(function () {
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('auditoria');
    });
});

require __DIR__.'/auth.php';
