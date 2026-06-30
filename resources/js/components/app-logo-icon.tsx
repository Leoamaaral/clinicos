import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
    alt = 'Clinic OS',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/clinic-os-icon.png"
            alt={alt}
            className={cn('object-contain', className)}
            {...props}
        />
    );
}
