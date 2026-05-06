<?php

namespace App\Http\Controllers;

use App\Models\ProjectVehiclesPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectVehiclesPhotosController extends Controller
{
    public function index(Request $request)
    {
        $images = ProjectVehiclesPhoto::with('projectVehicle.project.service', 'projectVehicle.project.centre', 'projectVehicle.vehicle')->orderBy('created_at', 'desc')
            ->paginate(3); // trae 3 a la vez

        return response()->json($images);
    }

    public function show(int $id)
    {
        $photo = ProjectVehiclesPhoto::findOrFail($id);

        return response()->json([
            'id' => $photo->id,
            'url' => url(Storage::temporaryUrl('projects/' . $photo->path, now()->addMinutes(30))),

        ]);
    }
}
