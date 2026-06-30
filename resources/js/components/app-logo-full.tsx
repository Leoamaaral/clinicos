import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type AppLogoFullProps = ImgHTMLAttributes<HTMLImageElement> & {
    variant?: 'full' | 'compact';
};

export default function AppLogoFull({
    className,
    alt = 'clinicOS',
    variant = 'full',
    ...props
}: AppLogoFullProps) {
    const src =
        variant === 'compact'
            ? '/clinic-os-logo-compact.png'
            : '/clinic-os-logo.png';

    return (
        <img
            src={src}
            alt={alt}
            className={cn('object-contain', className)}
            {...props}
        />
    );
}
