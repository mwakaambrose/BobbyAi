import { Form } from '@inertiajs/react';
import RunController from '@/actions/App/Http/Controllers/Agent/RunController';
import { StepRow } from '@/components/agent/step-row';
import { Button } from '@/components/ui/button';
import type { RunData, StepData } from '@/types/agent';

const TONE: Record<RunData['status'], string> = {
    queued: 'text-muted-foreground',
    running: 'text-agent',
    published: 'text-verified',
    failed: 'text-destructive',
    aborted: 'text-gate',
};

type Props = {
    run: RunData;
    steps: StepData[];
    teamSlug: string;
};

export function RunTimeline({ run, steps, teamSlug }: Props) {
    const active = run.status === 'queued' || run.status === 'running';

    return (
        <div className="space-y-4">
            <header className="flex items-center justify-between gap-2">
                <p className="font-mono text-xs">
                    <span className="text-muted-foreground">run {run.id} · </span>
                    <span className={TONE[run.status]}>{run.status}</span>
                    <span className="text-muted-foreground"> · {run.step_count} steps</span>
                </p>

                {active && (
                    <Form
                        {...RunController.abort.form({ current_team: teamSlug, run: run.id })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button size="sm" variant="ghost" disabled={processing}>
                                Stop the agent
                            </Button>
                        )}
                    </Form>
                )}
            </header>

            <ol className="rounded-xl border bg-card px-3 py-3">
                {steps.map((step, index) => (
                    <StepRow key={step.id} step={step} last={index === steps.length - 1} />
                ))}

                {steps.length === 0 && (
                    <li className="py-1.5 pl-8 font-mono text-xs text-muted-foreground">
                        waiting for the first observation…
                    </li>
                )}
            </ol>

            {run.failure_reason && (
                <p className="rounded-lg bg-destructive/10 px-3 py-2 font-mono text-xs text-destructive">
                    {run.failure_reason}
                </p>
            )}
        </div>
    );
}
