import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type PageHeaderProps = {
    title: string;
    description?: string;
    action?: {
        label: string;
        href: string;
    };
};

export function PageHeader({ title, description, action }: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                {description && (
                    <p className="text-muted-foreground mt-1 text-sm">{description}</p>
                )}
            </div>
            {action && (
                <Button asChild className="w-full shrink-0 sm:w-auto">
                    <Link href={action.href}>
                        <Plus className="size-4" />
                        {action.label}
                    </Link>
                </Button>
            )}
        </div>
    );
}
