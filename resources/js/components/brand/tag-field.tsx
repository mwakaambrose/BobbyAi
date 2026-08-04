import { X } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    placeholder?: string;
    hint?: string;
    /** Mirrors the server's array limit, so the ceiling is visible before submit. */
    max: number;
    /** Guardrails read as blocked rather than neutral. */
    tone?: 'neutral' | 'blocked';
    error?: string;
};

/**
 * A list of short terms, entered one at a time. Committing on Enter or comma
 * turns an invisible delimiter convention into something you can see and
 * remove, which matters when the terms are rules the agent has to obey.
 */
export default function TagField({
    id,
    label,
    values,
    onChange,
    placeholder,
    hint,
    max,
    tone = 'neutral',
    error,
}: Props) {
    const [draft, setDraft] = useState('');
    const input = useRef<HTMLInputElement>(null);

    const full = values.length >= max;

    const commit = (raw: string) => {
        const term = raw.trim();

        setDraft('');

        if (term === '' || full) {
            return;
        }

        const exists = values.some(
            (value) => value.toLowerCase() === term.toLowerCase(),
        );

        if (!exists) {
            onChange([...values, term]);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            commit(draft);

            return;
        }

        if (event.key === 'Backspace' && draft === '' && values.length > 0) {
            onChange(values.slice(0, -1));
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-baseline justify-between gap-3">
                <Label htmlFor={id}>{label}</Label>
                {full && (
                    <span className="text-label text-muted-foreground">
                        {max} is the limit
                    </span>
                )}
            </div>

            {/* The box is only a larger hit area for the input inside it, which
                carries the label, the focus ring, and the keyboard handling. */}
            <div
                onClick={() => input.current?.focus()}
                className="flex min-h-9 w-full flex-wrap items-center gap-1.5 rounded-lg border border-input bg-card px-2 py-1.5 shadow-xs transition-[color,box-shadow,border-color] focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/40"
            >
                {values.map((value) => (
                    <span
                        key={value}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-label',
                            tone === 'blocked'
                                ? 'border-gate/25 bg-gate/12 text-gate'
                                : 'border-border bg-secondary text-secondary-foreground',
                        )}
                    >
                        {value}
                        <button
                            type="button"
                            onClick={() =>
                                onChange(values.filter((v) => v !== value))
                            }
                            aria-label={`Remove ${value}`}
                            className="-mr-0.5 rounded-sm opacity-60 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                        >
                            <X className="size-3" aria-hidden />
                        </button>
                    </span>
                ))}

                <input
                    id={id}
                    ref={input}
                    value={draft}
                    disabled={full}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={onKeyDown}
                    onBlur={() => commit(draft)}
                    placeholder={values.length === 0 ? placeholder : undefined}
                    className="h-6 min-w-32 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed"
                />
            </div>

            {hint && <p className="text-label text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
