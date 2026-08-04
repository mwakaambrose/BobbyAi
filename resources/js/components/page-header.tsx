import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    /** A short label for what this page is part of, not where it sits. */
    eyebrow?: string;
    children?: ReactNode;
};

/**
 * The page's masthead: a tracked-out label, the title in display type, and a
 * rule that closes the block off from the content below it.
 */
export default function PageHeader({
    title,
    description,
    eyebrow,
    children,
}: Props) {
    return (
        <header className="mb-10 border-b border-rule pb-6">
            {eyebrow && <p className="eyebrow mb-3">{eyebrow}</p>}
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <h1 className="display text-display">{title}</h1>
                {children && (
                    <div className="flex items-center gap-2">{children}</div>
                )}
            </div>
            {description && (
                <p className="mt-3 max-w-[58ch] text-sm leading-relaxed text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
