import { Head, Link, usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/agent/empty-state';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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

const STATUS_VARIANT: Record<RunStatus, 'gate' | 'verified' | 'live'> = {
    queued: 'live',
    running: 'live',
    published: 'verified',
    failed: 'gate',
    aborted: 'gate',
};

export default function RunsIndex({ runs }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Runs" />

            <PageContainer>
                <PageHeader
                    eyebrow={
                        runs.data.length === 1
                            ? '1 run'
                            : `${runs.data.length} runs`
                    }
                    title="Runs"
                    description="Every publishing attempt, kept in full."
                />

                {runs.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="No runs yet"
                        description="Approve a post and every step the agent takes is recorded here, screenshots included."
                    />
                ) : (
                    <ul className="divide-y divide-rule border-y border-rule">
                        {runs.data.map((run) => (
                            <li key={run.id}>
                                <Link
                                    href={show([teamSlug, run.id])}
                                    className="flex items-center gap-4 py-3.5 transition-colors hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none"
                                >
                                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                        {String(run.id).padStart(3, '0')}
                                    </span>
                                    <span className="line-clamp-1 flex-1 text-sm">
                                        {run.brief}
                                    </span>
                                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                        {run.stepCount} steps
                                    </span>
                                    <Badge
                                        variant={STATUS_VARIANT[run.status]}
                                        className="w-20 justify-center"
                                    >
                                        {run.status}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </PageContainer>
        </>
    );
}
