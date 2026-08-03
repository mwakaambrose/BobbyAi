import { Deferred, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StepRow, StepSkeleton } from '@/components/agent/step-row';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { screenshot } from '@/routes/runs';
import type { RunStatus, StepData } from '@/types/agent';

type Props = {
    run: {
        id: number;
        status: string;
        failureReason: string | null;
        contract: Record<string, unknown>;
        postId: number;
    };
    steps?: StepData[];
};

const STATUS_VARIANT: Record<RunStatus, 'gate' | 'verified' | 'live'> = {
    queued: 'live',
    running: 'live',
    published: 'verified',
    failed: 'gate',
    aborted: 'gate',
};

function Replay({ runId, steps }: { runId: number; steps: StepData[] }) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';
    const [cursor, setCursor] = useState(0);
    const current = steps[cursor];

    return (
        <div className="grid gap-6 lg:grid-cols-[19rem_1fr]">
            <ol
                className="h-fit rounded-xl border bg-card px-3 py-3 shadow-xs"
                onMouseLeave={() => setCursor(0)}
            >
                {steps.map((step, index) => (
                    <div key={step.id} onMouseEnter={() => setCursor(index)} onFocus={() => setCursor(index)}>
                        <StepRow step={step} last={index === steps.length - 1} />
                    </div>
                ))}
            </ol>

            {current && (
                <figure className="space-y-3">
                    <img
                        src={screenshot([teamSlug, runId, current.id]).url}
                        alt={`What the agent saw at step ${current.index}`}
                        className="w-full rounded-xl border"
                    />
                    <figcaption className="font-mono text-xs text-muted-foreground">
                        step {String(current.index).padStart(2, '0')} · {current.phase}
                        {current.reasoning && ` · “${current.reasoning}”`}
                    </figcaption>
                </figure>
            )}
        </div>
    );
}

export default function RunShow({ run, steps }: Props) {
    const variant = STATUS_VARIANT[run.status as RunStatus] ?? 'outline';

    return (
        <>
            <Head title={`Run ${run.id}`} />

            <div className="space-y-6 p-4">
                <PageHeader
                    title={`Run ${String(run.id).padStart(3, '0')}`}
                    description={run.failureReason ?? `Finished as ${run.status}.`}
                >
                    <Badge variant={variant}>{run.status}</Badge>
                </PageHeader>

                <Deferred
                    data="steps"
                    fallback={
                        <ol className="max-w-xs rounded-xl border bg-card px-3 py-3 shadow-xs">
                            <StepSkeleton />
                            <StepSkeleton />
                            <StepSkeleton />
                        </ol>
                    }
                >
                    <Replay runId={run.id} steps={steps ?? []} />
                </Deferred>
            </div>
        </>
    );
}
