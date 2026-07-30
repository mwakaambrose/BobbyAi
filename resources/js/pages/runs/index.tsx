import { Head, Link, usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/agent/empty-state';
import Heading from '@/components/heading';
import { show } from '@/routes/runs';
import type { RunStatus } from '@/types/agent';

type RunRow = {
    id: number;
    status: RunStatus;
    stepCount: number;
    failureReason: string | null;
    startedAt: string | null;
    brief: string;
};

type Props = {
    runs: { data: RunRow[] };
};

const TONE: Record<RunStatus, string> = {
    queued: 'text-muted-foreground',
    running: 'text-agent',
    published: 'text-verified',
    failed: 'text-destructive',
    aborted: 'text-gate',
};

export default function RunsIndex({ runs }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Runs" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4">
                <Heading title="Runs" description="Every publishing attempt, kept in full." />

                {runs.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="No runs yet"
                        description="Approve a post and every step the agent takes is recorded here, screenshots included."
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {runs.data.map((run) => (
                            <li key={run.id}>
                                <Link
                                    href={show([teamSlug, run.id])}
                                    className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                        {String(run.id).padStart(3, '0')}
                                    </span>
                                    <span className="line-clamp-1 flex-1 text-sm">{run.brief}</span>
                                    <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                        {run.stepCount} steps
                                    </span>
                                    <span className={`w-20 text-right font-mono text-xs ${TONE[run.status]}`}>
                                        {run.status}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
