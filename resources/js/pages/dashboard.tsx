import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    History,
    PenLine,
    Sparkles,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, studio } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { show as postShow } from '@/routes/posts';
import { index as runsIndex, show as runShow } from '@/routes/runs';
import type { DashboardInvitation } from '@/types';

type Overview = {
    awaitingApproval: number;
    published: number;
    activeRuns: number;
    accounts: { connected: number; total: number };
    teamSlug: string;
};

type RecentPost = {
    id: number;
    brief: string;
    status: string;
    updatedAt: string;
};

type RecentRun = {
    id: number;
    status: string;
    stepCount: number;
    brief: string;
    startedAt: string | null;
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    overview: Overview;
    recentPosts: RecentPost[];
    recentRuns: RecentRun[];
};

const POST_BADGE: Record<string, 'secondary' | 'gate' | 'verified' | 'live'> = {
    draft: 'secondary',
    approved: 'gate',
    publishing: 'live',
    published: 'verified',
    failed: 'gate',
};

const RUN_BADGE: Record<string, 'secondary' | 'gate' | 'verified' | 'live'> = {
    queued: 'live',
    running: 'live',
    published: 'verified',
    failed: 'gate',
    aborted: 'secondary',
};

/**
 * A metric tile. The label leads so the row scans as a list of things being
 * measured, and the number sits under it at the size that carries the page.
 */
function Metric({
    value,
    label,
    hint,
    href,
    emphasis = false,
}: {
    value: string;
    label: string;
    hint: string;
    href: string;
    emphasis?: boolean;
}) {
    return (
        <Link
            href={href}
            className="group rounded-xl border border-border bg-card p-4 shadow-xs transition-[border-color,box-shadow,transform] outline-none hover:-translate-y-0.5 hover:border-foreground/15 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring"
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-label text-muted-foreground">
                    {label}
                </span>
                <ArrowUpRight className="size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
            </div>
            <p
                className={cn(
                    'figure mt-2.5 text-[1.75rem] leading-none',
                    emphasis && 'text-agent',
                )}
            >
                {value}
            </p>
            <p className="mt-2 text-meta text-muted-foreground">{hint}</p>
        </Link>
    );
}

/**
 * A titled panel with its own "see everything" link. Sections share this so
 * the two halves of the page read as one pair rather than two experiments.
 */
function Panel({
    title,
    icon: Icon,
    action,
    actionLabel,
    children,
}: {
    title: string;
    icon: LucideIcon;
    action: string;
    actionLabel: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <header className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    <Icon
                        className="size-4 text-muted-foreground"
                        aria-hidden
                    />
                    {title}
                </h2>
                <Link
                    href={action}
                    className="flex items-center gap-1 rounded-md text-label text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {actionLabel}
                    <ArrowRight className="size-3" aria-hidden />
                </Link>
            </header>
            {children}
        </section>
    );
}

function EmptyRow({ children }: { children: React.ReactNode }) {
    return (
        <p className="px-4 py-8 text-center text-meta text-muted-foreground">
            {children}
        </p>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    overview,
    recentPosts,
    recentRuns,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const { teamSlug, activeRuns } = overview;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <PageContainer className="space-y-6">
                <PageHeader
                    eyebrow={
                        activeRuns > 0
                            ? `${activeRuns} ${activeRuns === 1 ? 'run' : 'runs'} in progress`
                            : 'Agent idle'
                    }
                    title="Overview"
                    description="Where your posts stand, and what the agent is doing about them."
                >
                    <Button asChild>
                        <Link href={studio(teamSlug)}>
                            <Sparkles /> Open studio
                        </Link>
                    </Button>
                </PageHeader>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        value={String(overview.awaitingApproval)}
                        label="Awaiting publish"
                        hint="Approved, not yet live"
                        href={studio(teamSlug).url}
                    />
                    <Metric
                        value={String(overview.published)}
                        label="Published"
                        hint="Live on LinkedIn"
                        href={studio(teamSlug).url}
                    />
                    <Metric
                        value={String(activeRuns)}
                        label="Active runs"
                        hint="Agent working right now"
                        href={runsIndex(teamSlug).url}
                        emphasis={activeRuns > 0}
                    />
                    <Metric
                        value={`${overview.accounts.connected}/${overview.accounts.total}`}
                        label="Accounts"
                        hint="Connected sessions"
                        href={accountsIndex(teamSlug).url}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Recent posts"
                        icon={PenLine}
                        action={studio(teamSlug).url}
                        actionLabel="All posts"
                    >
                        {recentPosts.length === 0 ? (
                            <EmptyRow>
                                Nothing written yet. Start with a brief in the
                                studio.
                            </EmptyRow>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recentPosts.map((post) => (
                                    <li key={post.id}>
                                        <Link
                                            href={postShow([teamSlug, post.id])}
                                            className="flex items-center gap-3 px-4 py-3 transition-colors outline-none hover:bg-accent/60 focus-visible:bg-accent/60"
                                        >
                                            <span className="line-clamp-1 flex-1 text-sm">
                                                {post.brief}
                                            </span>
                                            <Badge
                                                variant={
                                                    POST_BADGE[post.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {post.status}
                                            </Badge>
                                            <span className="w-10 shrink-0 text-right text-label text-muted-foreground tabular-nums">
                                                {post.updatedAt}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Recent runs"
                        icon={History}
                        action={runsIndex(teamSlug).url}
                        actionLabel="All runs"
                    >
                        {recentRuns.length === 0 ? (
                            <EmptyRow>
                                No publishing attempts yet. Approve a post and
                                every step lands here.
                            </EmptyRow>
                        ) : (
                            <ul className="divide-y divide-border">
                                {recentRuns.map((run) => (
                                    <li key={run.id}>
                                        <Link
                                            href={runShow([teamSlug, run.id])}
                                            className="flex items-center gap-3 px-4 py-3 transition-colors outline-none hover:bg-accent/60 focus-visible:bg-accent/60"
                                        >
                                            <span className="shrink-0 font-mono text-label text-muted-foreground tabular-nums">
                                                {String(run.id).padStart(
                                                    3,
                                                    '0',
                                                )}
                                            </span>
                                            <span className="line-clamp-1 flex-1 text-sm">
                                                {run.brief}
                                            </span>
                                            <span className="shrink-0 text-label text-muted-foreground tabular-nums">
                                                {run.stepCount} steps
                                            </span>
                                            <Badge
                                                variant={
                                                    RUN_BADGE[run.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {run.status}
                                            </Badge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </PageContainer>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
