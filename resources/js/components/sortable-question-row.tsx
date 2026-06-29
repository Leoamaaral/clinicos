import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link } from '@inertiajs/react';
import { GripVertical, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { routes } from '@/lib/clinic';
import { cn } from '@/lib/utils';
import type { AnamnesisQuestion } from '@/types/clinic';

const typeLabels: Record<string, string> = {
    text: 'Texto',
    select: 'Seleção',
    checkbox: 'Sim/Não',
    textarea: 'Texto',
    boolean: 'Sim/Não',
};

/** Grid compartilhado entre cabeçalho e linhas para alinhar colunas */
export const questionListGridClass =
    'grid grid-cols-[2.25rem_minmax(0,1fr)_6.5rem_5.5rem_5.5rem] items-center gap-x-4 px-4';

type SortableQuestionRowProps = {
    question: AnamnesisQuestion;
    index: number;
    onDelete: () => void;
};

export function SortableQuestionRow({
    question,
    index,
    onDelete,
}: SortableQuestionRowProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: question.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const dragHandle = (
        <button
            type="button"
            className={cn(
                'text-muted-foreground hover:text-foreground flex shrink-0 touch-none items-center justify-center p-1',
                isDragging ? 'cursor-grabbing' : 'cursor-grab',
            )}
            aria-label={`Arrastar pergunta ${index + 1}`}
            {...attributes}
            {...listeners}
        >
            <GripVertical className="size-5" />
        </button>
    );

    const actions = (
        <div className="flex gap-1">
            <Button variant="ghost" size="icon" asChild>
                <Link href={routes.anamnesisQuestions.edit(question.id)}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" onClick={onDelete}>
                <Trash2 className="text-destructive size-4" />
            </Button>
        </div>
    );

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={cn(
                'border-b text-sm last:border-b-0',
                isDragging && 'z-10 opacity-30',
            )}
        >
            <div className="flex gap-3 px-4 py-3 md:hidden">
                {dragHandle}
                <div className="min-w-0 flex-1">
                    <p className="leading-snug font-medium">
                        {question.question}
                        {question.is_required && (
                            <span className="text-destructive ml-1">*</span>
                        )}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-xs">
                            {typeLabels[question.type] ?? question.type}
                        </span>
                        <Badge variant={question.is_active ? 'default' : 'secondary'}>
                            {question.is_active ? 'Ativa' : 'Inativa'}
                        </Badge>
                        <div className="ml-auto">{actions}</div>
                    </div>
                </div>
            </div>

            <div
                className={cn(
                    questionListGridClass,
                    'hidden py-3 md:grid',
                )}
            >
                {dragHandle}
                <div className="min-w-0 truncate">
                    {question.question}
                    {question.is_required && (
                        <span className="text-destructive ml-1">*</span>
                    )}
                </div>
                <div className="text-muted-foreground">
                    {typeLabels[question.type] ?? question.type}
                </div>
                <div>
                    <Badge variant={question.is_active ? 'default' : 'secondary'}>
                        {question.is_active ? 'Ativa' : 'Inativa'}
                    </Badge>
                </div>
                <div className="flex justify-end">{actions}</div>
            </div>
        </div>
    );
}

export function QuestionRowPreview({
    question,
    index,
}: {
    question: AnamnesisQuestion;
    index: number;
}) {
    return (
        <div className="bg-card border-primary max-w-full rounded-lg border-2 p-4 text-sm shadow-lg ring-2 ring-primary/20">
            <div className="flex gap-3">
                <GripVertical className="text-primary size-5 shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="font-medium">
                        {question.question}
                        {question.is_required && (
                            <span className="text-destructive ml-1">*</span>
                        )}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <span className="text-muted-foreground text-xs">
                            {typeLabels[question.type] ?? question.type}
                        </span>
                        <Badge variant={question.is_active ? 'default' : 'secondary'}>
                            {question.is_active ? 'Ativa' : 'Inativa'}
                        </Badge>
                    </div>
                </div>
            </div>
        </div>
    );
}
