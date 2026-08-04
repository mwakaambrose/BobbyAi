import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
};

/**
 * One measure for every page. Wide enough for a board or a table, and the same
 * on every screen the sidebar reaches, so the page never resizes underneath you
 * as you move between menu items. Where a form or a column of prose needs a
 * shorter line, it constrains itself inside this frame rather than narrowing
 * the frame — that keeps the left edge fixed on every navigation.
 */
export default function PageContainer({ children, className }: Props) {
    return (
        <div
            className={cn(
                'mx-auto w-full max-w-5xl flex-1 px-5 py-8 md:px-8 md:py-10',
                className,
            )}
        >
            {children}
        </div>
    );
}
