import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    children?: ReactNode;
};

/**
 * The standard page introduction: a display-type title, a muted line of
 * supporting copy, and an optional right-aligned actions slot.
 */
export default function PageHeader({ title, description, children }: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="space-y-1">
                <h1 className="font-serif text-2xl font-medium tracking-[-0.01em]">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children && (
                <div className="flex items-center gap-2">{children}</div>
            )}
        </div>
    );
}
