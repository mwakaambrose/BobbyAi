import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    children?: ReactNode;
};

/**
 * An empty screen is an invitation to act, so it is set like the opening of a
 * section: a rule, the instruction in display type, and the action right
 * under it. The icon sits small and inline rather than in a decorative badge.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    children,
}: Props) {
    return (
        <div className="border-t border-rule-strong py-10">
            <div className="max-w-md space-y-4">
                <Icon className="size-5 text-agent" aria-hidden />
                <div className="space-y-2">
                    <p className="display text-2xl">{title}</p>
                    <p className="font-serif text-[0.9375rem] leading-relaxed text-muted-foreground italic">
                        {description}
                    </p>
                </div>
                {children && <div className="flex gap-2 pt-1">{children}</div>}
            </div>
        </div>
    );
}
