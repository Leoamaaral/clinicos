<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderAnamnesisQuestionsRequest;
use App\Http\Requests\StoreAnamnesisQuestionRequest;
use App\Http\Requests\UpdateAnamnesisQuestionRequest;
use App\Models\AnamnesisQuestion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnamnesisQuestionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('anamnesis/questions/index', [
            'questions' => AnamnesisQuestion::query()->orderBy('order')->orderBy('id')->get(),
        ]);
    }

    public function reorder(ReorderAnamnesisQuestionsRequest $request): RedirectResponse
    {
        foreach ($request->validated('questions') as $index => $id) {
            AnamnesisQuestion::whereKey($id)->update(['order' => $index]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ordem das perguntas atualizada.']);

        return back();
    }

    public function create(): Response
    {
        return Inertia::render('anamnesis/questions/create');
    }

    public function store(StoreAnamnesisQuestionRequest $request): RedirectResponse
    {
        AnamnesisQuestion::create([
            ...$request->validated(),
            'order' => (AnamnesisQuestion::max('order') ?? -1) + 1,
            'is_active' => $request->boolean('is_active', true),
            'is_required' => $request->boolean('is_required'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta criada com sucesso.']);

        return to_route('anamnesis-questions.index');
    }

    public function edit(AnamnesisQuestion $anamnesisQuestion): Response
    {
        return Inertia::render('anamnesis/questions/edit', [
            'question' => $anamnesisQuestion,
        ]);
    }

    public function update(UpdateAnamnesisQuestionRequest $request, AnamnesisQuestion $anamnesisQuestion): RedirectResponse
    {
        $anamnesisQuestion->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'is_required' => $request->boolean('is_required'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta atualizada com sucesso.']);

        return to_route('anamnesis-questions.index');
    }

    public function destroy(AnamnesisQuestion $anamnesisQuestion): RedirectResponse
    {
        $anamnesisQuestion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta removida com sucesso.']);

        return to_route('anamnesis-questions.index');
    }
}
