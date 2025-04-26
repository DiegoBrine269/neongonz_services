<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CentresController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\VehiclesController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'is_active'])->group(function () {
    Route::resource('centres', CentresController::class)->except(['create', 'edit']);
    Route::resource('vehicles', VehiclesController::class)->except(['create', 'edit']);
    Route::resource('services', ServicesController::class)->except(['create', 'edit']);
    Route::resource('projects', ProjectsController::class)->except(['create', 'edit']);
    
    Route::post('/projects/{id}/add-vehicle', [ProjectsController::class, 'addVehicle']);
    Route::post('/projects/{id}/remove-vehicle', [ProjectsController::class, 'removeVehicle']);

    Route::get('/vehicles-types', [VehiclesController::class, 'types']);
});

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::post('/projects/{id}/close', [ProjectsController::class, 'close']);

});


