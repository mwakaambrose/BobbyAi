import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    /** Live context for the page, shown as a pill beside the title. */
    eyebrow?: string;
    children?: ReactNode;
};

/**
 * The page title block. Actions sit on the title's line so the row reads as
 * one control strip, and the supporting copy stays out of their way.
 */
export default function PageHeader({
    title,
    description,
    eyebrow,
    children,
}: Props) {
    return (
        <header className="mb-6">
            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                <div className="flex items-center gap-2.5">
                    <h1 className="text-title">{title}</h1>
                    {eyebrow && (
                        <span className="eyebrow rounded-md bg-muted px-1.5 py-0.5">
                            {eyebrow}
                        </span>
                    )}
                </div>
                {children && (
                    <div className="flex items-center gap-2">{children}</div>
                )}
            </div>
            {description && (
                <p className="mt-1.5 max-w-[62ch] text-meta text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
