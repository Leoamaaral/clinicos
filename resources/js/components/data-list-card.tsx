import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type DataListCardProps = PropsWithChildren<{
    className?: string;
}>;

export function DataListCard({ children, className }: DataListCardProps) {
    return (
        <div className={cn('bg-card rounded-xl border p-4 shadow-xs', className)}>
            {children}
        </div>
    );
}

type DataListCardRowProps = PropsWithChildren<{
    label: string;
    className?: string;
}>;

export function DataListCardRow({ label, children, className }: DataListCardRowProps) {
    return (
        <div className={cn('flex items-start justify-between gap-3 py-1', className)}>
            <span className="text-muted-foreground shrink-0 text-xs">{label}</span>
            <div className="min-w-0 text-right text-sm">{children}</div>
        </div>
    );
}

export function DataListCardActions({ children }: PropsWithChildren) {
    return <div className="mt-3 flex justify-end gap-1 border-t pt-3">{children}</div>;
}
