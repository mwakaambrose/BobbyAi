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
        <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-border bg-muted/40 px-6 py-16 text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-agent-muted text-agent">
                <Icon className="size-5" aria-hidden />
            </div>
            <div className="space-y-1.5">
                <p className="font-serif text-lg font-medium tracking-[-0.01em]">
                    {title}
                </p>
                <p className="max-w-sm font-serif text-sm text-balance text-muted-foreground italic">
                    {description}
                </p>
            </div>
            {children}
        </div>
    );
}
