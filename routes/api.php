<?php

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CentresController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\Auth\PasswordResetController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'is_active'])->group(function () {
    
    Route::get('/invoices/pending', [InvoicesController::class, 'pending']);

    Route::apiResource('centres', CentresController::class);
    Route::apiResource('vehicles', VehiclesController::class);
    Route::apiResource('services', ServicesController::class);
    Route::apiResource('projects', ProjectsController::class);


    //Extras de vehículos
    Route::get('/vehicles-types', [VehiclesController::class, 'types']);
    // Route::get('/vehicles', [VehiclesController::class, 'types']);


    //Extras de proyectos
    Route::post('/projects/{id}/duplicate', [ProjectsController::class, 'duplicate']);
    Route::post('/projects/{id}/add-vehicle', [ProjectsController::class, 'addVehicle']);
    Route::post('/projects/{id}/remove-vehicle', [ProjectsController::class, 'removeVehicle']);

    //Obtiene los proyectos abiertos relacionados al centro en cuestión
    Route::get('/centres/{id}/open-projects', [CentresController::class, 'showOpenProjects']);




    Route::put('/user/change-password', [UserController::class, 'changePasswordSave']);    
    Route::put('/user/{id}', [UserController::class, 'update'])->where('id', '[0-9]+');



});

// Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
// Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    
    Route::get('/users/{id}/performance', [UserController::class, 'performance']);



    Route::post('/projects/{id}/toggle-status', [ProjectsController::class, 'toggleStatus']);

    Route::resource('invoices', InvoicesController::class)->except(['create', 'edit']);


    Route::get('/invoices/{invoice}/pdf', [InvoicesController::class, 'downloadPdf']);
    Route::post('/invoices/create-custom', [InvoicesController::class, 'createCustom']);


    

});


