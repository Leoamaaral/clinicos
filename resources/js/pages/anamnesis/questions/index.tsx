import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors
    
    
} from '@dnd-kit/core';
import type {DragEndEvent, DragStartEvent} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import {
    QuestionRowPreview,
    SortableQuestionRow,
    questionListGridClass,
} from '@/components/sortable-question-row';
import { Button } from '@/components/ui/button';
import { routes } from '@/lib/clinic';
import { cn } from '@/lib/utils';
import type { AnamnesisQuestion } from '@/types/clinic';

type Props = { questions: AnamnesisQuestion[] };

export default function AnamnesisQuestionsIndex({ questions }: Props) {
    const [items, setItems] = useState(questions);
    const [activeQuestion, setActiveQuestion] = useState<AnamnesisQuestion | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 8 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    useEffect(() => {
        setItems(questions);
    }, [questions]);

    function saveOrder(ordered: AnamnesisQuestion[]) {
        setIsSaving(true);
        router.put(
            '/anamnesis-questions/reorder',
            { questions: ordered.map((q) => q.id) },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    }

    function handleDragStart(event: DragStartEvent) {
        const question = items.find((item) => item.id === event.active.id);
        setActiveQuestion(question ?? null);
    }

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;
        setActiveQuestion(null);

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);
        const reordered = arrayMove(items, oldIndex, newIndex);

        setItems(reordered);
        saveOrder(reordered);
    }

    function handleDragCancel() {
        setActiveQuestion(null);
    }

    const activeIndex = activeQuestion
        ? items.findIndex((item) => item.id === activeQuestion.id)
        : -1;

    return (
        <>
            <Head title="Perguntas da anamnese" />
            <div className="page-container">
                <PageHeader
                    title="Perguntas da anamnese"
                    description="Arraste as perguntas para definir a ordem de exibição na anamnese"
                    action={{
                        label: 'Nova pergunta',
                        href: routes.anamnesisQuestions.create(),
                    }}
                />

                {isSaving && (
                    <p className="text-muted-foreground text-sm">Salvando ordem...</p>
                )}

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragStart={handleDragStart}
                    onDragEnd={handleDragEnd}
                    onDragCancel={handleDragCancel}
                >
                    <div className="min-w-0 overflow-hidden rounded-xl border">
                        <div
                            className={cn(
                                questionListGridClass,
                                'bg-muted/50 text-muted-foreground hidden border-b py-3 text-sm font-medium sm:grid',
                            )}
                        >
                            <span aria-hidden />
                            <span>Pergunta</span>
                            <span>Tipo</span>
                            <span>Status</span>
                            <span className="text-right">Ações</span>
                        </div>

                        {items.length === 0 ? (
                            <div className="text-muted-foreground px-4 py-12 text-center text-sm">
                                <p>Nenhuma pergunta cadastrada.</p>
                                <Button className="mt-4" asChild>
                                    <Link href={routes.anamnesisQuestions.create()}>
                                        <Plus className="size-4" />
                                        Criar pergunta
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <SortableContext
                                items={items.map((item) => item.id)}
                                strategy={verticalListSortingStrategy}
                            >
                                {items.map((question, index) => (
                                    <SortableQuestionRow
                                        key={question.id}
                                        question={question}
                                        index={index}
                                        onDelete={() => {
                                            if (confirm('Remover esta pergunta?')) {
                                                router.delete(
                                                    `/anamnesis-questions/${question.id}`,
                                                );
                                            }
                                        }}
                                    />
                                ))}
                            </SortableContext>
                        )}
                    </div>

                    <DragOverlay dropAnimation={{ duration: 200, easing: 'ease' }}>
                        {activeQuestion ? (
                            <QuestionRowPreview
                                question={activeQuestion}
                                index={activeIndex}
                            />
                        ) : null}
                    </DragOverlay>
                </DndContext>
            </div>
        </>
    );
}

AnamnesisQuestionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Anamnese', href: routes.anamnesisQuestions.index() },
    ],
};
