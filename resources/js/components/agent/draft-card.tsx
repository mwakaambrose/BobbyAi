import { Check } from 'lucide-react';
import type { DraftData } from '@/types/agent';

type Props = {
    draft: DraftData;
    selected: boolean;
    onSelect: (draft: DraftData) => void;
};

export function DraftCard({ draft, selected, onSelect }: Props) {
    return (
        <button
            type="button"
            onClick={() => onSelect(draft)}
            aria-pressed={selected}
            className={`w-full rounded-xl border bg-card p-3 text-left shadow-xs transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none ${
                selected
                    ? 'border-agent bg-agent-muted'
                    : 'hover:border-agent/40 hover:bg-accent/40'
            }`}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-[11px] tabular-nums text-muted-foreground uppercase">
                    draft {draft.variant} · {draft.character_count} chars
                </span>
                {selected && <Check className="size-4 text-agent" aria-hidden />}
            </div>

            <p className="mt-2 text-sm leading-relaxed">{draft.body}</p>

            {draft.rationale && (
                <p className="mt-2 text-xs text-muted-foreground">{draft.rationale}</p>
            )}
        </button>
    );
}
