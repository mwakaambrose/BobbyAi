import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Width = 'narrow' | 'default' | 'wide';

const widths: Record<Width, string> = {
    /* A single column of settings or forms, held near a comfortable measure. */
    narrow: 'max-w-2xl',
    /* The working width for most pages: wide enough for a two-up split. */
    default: 'max-w-5xl',
    /* Tables and boards that genuinely need the room. */
    wide: 'max-w-7xl',
};

type Props = {
    children: ReactNode;
    width?: Width;
    className?: string;
};

/**
 * Every page sits in the same measure with the same margins, so the eye lands
 * in the same place on every navigation.
 */
export default function PageContainer({
    children,
    width = 'default',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'mx-auto w-full flex-1 px-6 py-10 md:px-10 md:py-12',
                widths[width],
                className,
            )}
        >
            {children}
        </div>
    );
}
