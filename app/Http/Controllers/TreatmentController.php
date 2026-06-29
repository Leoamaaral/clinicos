<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTreatmentRequest;
use App\Http\Requests\UpdateTreatmentRequest;
use App\Models\Treatment;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TreatmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('treatments/index', [
            'treatments' => Treatment::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('treatments/create');
    }

    public function store(StoreTreatmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('treatments', 'public');
        }

        unset($data['image']);

        Treatment::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamento cadastrado com sucesso.']);

        return to_route('treatments.index');
    }

    public function edit(Treatment $treatment): Response
    {
        return Inertia::render('treatments/edit', [
            'treatment' => $treatment,
        ]);
    }

    public function update(UpdateTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            if ($treatment->image_path) {
                Storage::disk('public')->delete($treatment->image_path);
            }
            $data['image_path'] = $request->file('image')->store('treatments', 'public');
        }

        unset($data['image']);

        $treatment->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamento atualizado com sucesso.']);

        return to_route('treatments.index');
    }

    public function destroy(Treatment $treatment): RedirectResponse
    {
        if ($treatment->purchaseItems()->exists()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Não é possível excluir este tratamento pois existem compras de clientes vinculadas.',
            ]);

            return to_route('treatments.index');
        }

        try {
            $imagePath = $treatment->image_path;
            $treatment->delete();

            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        } catch (QueryException) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Não é possível excluir este tratamento pois existem registros vinculados.',
            ]);

            return to_route('treatments.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamento removido com sucesso.']);

        return to_route('treatments.index');
    }
}
