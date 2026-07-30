import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    children?: ReactNode;
};

export function EmptyState({ icon: Icon, title, description, children }: Props) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 px-6 py-16 text-center dark:border-sidebar-border">
            <Icon className="size-5 text-muted-foreground" aria-hidden />
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                <p className="max-w-sm text-sm text-balance text-muted-foreground">
                    {description}
                </p>
            </div>
            {children}
        </div>
    );
}
