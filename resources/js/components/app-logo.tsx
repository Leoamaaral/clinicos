import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props as { name?: string };

    return (
        <>
            <AppLogoIcon className="size-8" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name ?? 'Clínica'}
                </span>
            </div>
        </>
    );
}
