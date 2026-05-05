<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofencesController extends Controller
{
    public function index()
    {
        return JsonResource::collection(Geofence::all());
    }

    public function show($id)
    {
        $geofence = Geofence::findOrFail($id);
        return new JsonResource($geofence);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'coordinates' => 'required|array',
        ]);
        $geofence = Geofence::create($data);
        return response()->json($geofence, 201);
    }

    public function update(Request $request, $id)
    {
        $geofence = Geofence::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'coordinates' => 'sometimes|array',
        ]);
        $geofence->update($data);
        return response()->json($geofence);
    }

    public function destroy($id)
    {
        $geofence = Geofence::findOrFail($id);
        $geofence->delete();
        return response()->json(null, 204);
    }
}
