import { useState } from 'react';
import type { StepData } from '@/types/agent';

/**
 * One node on the run trace. The vertical rule runs through every row; a denied
 * step breaks it, so an interrupted run is legible at a glance.
 */
export function StepRow({ step, last = false }: { step: StepData; last?: boolean }) {
    const [open, setOpen] = useState(false);

    const denied = step.gate_decision === 'denied';
    const errored = Boolean(step.error);
    const verifying = step.phase === 'verify';

    const node = denied
        ? 'border-gate bg-background'
        : errored
          ? 'border-destructive bg-background'
          : verifying
            ? 'border-verified bg-verified'
            : 'border-agent bg-agent';

    return (
        <li className="relative pl-8">
            {!last && (
                <span
                    aria-hidden
                    className={`absolute top-4 bottom-0 left-[0.4375rem] w-px ${
                        denied ? 'border-l border-dashed border-gate bg-transparent' : 'bg-border'
                    }`}
                />
            )}

            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className="flex w-full items-baseline gap-3 rounded-md py-1.5 pr-2 text-left font-mono text-xs transition-colors hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span
                    aria-hidden
                    className={`absolute left-1 top-2 size-2.5 rounded-full border-2 ${node}`}
                />
                <span className="font-mono text-[11px] tabular-nums text-muted-foreground">
                    {String(step.index).padStart(2, '0')}
                </span>
                <span className="truncate">
                    {step.action
                        ? `${step.action.action}${step.action.ref ? ` #${step.action.ref}` : ''}`
                        : step.phase}
                </span>
                <span className="ml-auto shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground">
                    {step.latency_ms ? `${step.latency_ms}ms` : ''}
                </span>
            </button>

            {(denied || errored) && !open && (
                <p className="pb-2 pr-2 font-mono text-xs text-balance text-gate">
                    {step.gate_reason ?? step.error}
                </p>
            )}

            {open && (
                <div className="space-y-1 pb-2 pr-2 text-xs leading-relaxed">
                    {step.reasoning && (
                        <p className="text-muted-foreground">“{step.reasoning}”</p>
                    )}
                    {step.gate_reason && (
                        <p className="font-mono text-gate">blocked · {step.gate_reason}</p>
                    )}
                    {step.error && (
                        <p className="font-mono text-destructive">error · {step.error}</p>
                    )}
                </div>
            )}
        </li>
    );
}

export function StepSkeleton() {
    return (
        <li className="relative flex items-center gap-3 py-1.5 pl-8">
            <span aria-hidden className="absolute left-1 top-2 size-2.5 rounded-full bg-muted" />
            <span className="h-3 w-6 animate-pulse rounded bg-muted" />
            <span className="h-3 w-28 animate-pulse rounded bg-muted" />
        </li>
    );
}
