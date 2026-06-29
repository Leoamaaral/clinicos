import type { PropsWithChildren, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ResponsiveTableProps = PropsWithChildren<{
    mobile: ReactNode;
    className?: string;
    tableClassName?: string;
    minWidth?: string;
}>;

export function ResponsiveTable({
    mobile,
    children,
    className,
    tableClassName,
    minWidth = '640px',
}: ResponsiveTableProps) {
    return (
        <>
            <div className="space-y-3 md:hidden">{mobile}</div>
            <div className={cn('hidden overflow-x-auto rounded-xl border md:block', className)}>
                <table
                    className={cn('w-full text-sm', tableClassName)}
                    style={{ minWidth }}
                >
                    {children}
                </table>
            </div>
        </>
    );
}
