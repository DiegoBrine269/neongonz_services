<?php

namespace App\Http\Controllers;

use App\Models\ProjectVehiclesPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectVehiclesPhotosController extends Controller
{
    public function index(Request $request)
    {
        $images = ProjectVehiclesPhoto::orderBy('created_at', 'desc')
            ->paginate(20); // trae 20 a la vez

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
