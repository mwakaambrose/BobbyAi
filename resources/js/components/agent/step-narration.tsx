import type { StepData } from '@/types/agent';

/**
 * One thing the agent did, as a sentence. The vertical rule runs through every
 * line; a denied step breaks it, so an interrupted run is legible at a glance.
 */
export function StepNarration({
    step,
    last = false,
}: {
    step: StepData;
    last?: boolean;
}) {
    const denied = step.gate_decision === 'denied';
    const errored = Boolean(step.error);

    const node = denied
        ? 'border-gate bg-background'
        : errored
          ? 'border-destructive bg-background'
          : step.phase === 'verify'
            ? 'border-verified bg-verified'
            : 'border-agent bg-agent';

    return (
        <li className="relative space-y-1 pb-3 pl-8">
            {!last && (
                <span
                    aria-hidden
                    className={`absolute top-4 bottom-0 left-[0.4375rem] w-px ${
                        denied
                            ? 'border-l border-dashed border-gate bg-transparent'
                            : 'bg-border'
                    }`}
                />
            )}

            <span
                aria-hidden
                className={`absolute top-2 left-1 size-2.5 rounded-full border-2 ${node}`}
            />

            <p className="text-sm leading-relaxed">
                {step.narration}
                {step.latency_ms !== null && (
                    <span className="ml-2 font-mono text-[11px] text-muted-foreground tabular-nums">
                        {step.latency_ms}ms
                    </span>
                )}
            </p>

            {step.reasoning && (
                <p className="text-meta text-muted-foreground">
                    “{step.reasoning}”
                </p>
            )}

            {denied && step.gate_reason && (
                <p className="text-meta text-gate">
                    blocked · {step.gate_reason}
                </p>
            )}

            {step.error && (
                <p className="text-meta text-destructive">{step.error}</p>
            )}
        </li>
    );
}
