import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    children?: ReactNode;
};

/**
 * An empty screen is an invitation to act. It sits in a dashed well so it
 * reads as a placeholder rather than a permanent surface, with the action
 * directly under the instruction.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    children,
}: Props) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border px-6 py-14 text-center">
            <div className="flex size-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-xs">
                <Icon className="size-4" aria-hidden />
            </div>
            <p className="display mt-4 text-sm">{title}</p>
            <p className="mt-1.5 max-w-sm text-meta text-muted-foreground">
                {description}
            </p>
            {children && (
                <div className="mt-5 flex items-center gap-2">{children}</div>
            )}
        </div>
    );
}
