import { Form } from '@inertiajs/react';
import RunController from '@/actions/App/Http/Controllers/Agent/RunController';
import { StepNarration } from '@/components/agent/step-narration';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { screenshot } from '@/routes/runs';
import type { RunData, StepData } from '@/types/agent';

const STATUS_VARIANT: Record<RunData['status'], 'gate' | 'verified' | 'live'> =
    {
        queued: 'live',
        running: 'live',
        published: 'verified',
        failed: 'gate',
        aborted: 'gate',
    };

type Props = {
    run: RunData;
    steps: StepData[];
    teamSlug: string;
};

/**
 * What the agent is looking at, and what it is doing about it. The most recent
 * screenshot stays at the top so the page it is working on is never more than a
 * glance away, with the narration accumulating beneath it.
 */
export function RunTheatre({ run, steps, teamSlug }: Props) {
    const active = run.status === 'queued' || run.status === 'running';
    const latest = [...steps].reverse().find((step) => step.screenshot_path);

    return (
        <div className="space-y-4">
            <header className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                        run {run.id} · {run.step_count} steps
                    </span>
                    <Badge variant={STATUS_VARIANT[run.status]}>
                        {run.status}
                    </Badge>
                </div>

                {active && (
                    <Form
                        {...RunController.abort.form({
                            current_team: teamSlug,
                            run: run.id,
                        })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={processing}
                            >
                                Stop the agent
                            </Button>
                        )}
                    </Form>
                )}
            </header>

            {latest && (
                <img
                    src={screenshot([teamSlug, run.id, latest.id]).url}
                    alt={`What the agent saw at step ${latest.index}`}
                    className="w-full rounded-md border"
                />
            )}

            <ol>
                {steps.map((step, index) => (
                    <StepNarration
                        key={step.id}
                        step={step}
                        last={index === steps.length - 1}
                    />
                ))}

                {steps.length === 0 && (
                    <li className="text-meta text-muted-foreground">
                        <span className="mr-2 inline-block size-1.5 animate-pulse rounded-full bg-agent align-middle" />
                        Waiting for the first look at the page…
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
