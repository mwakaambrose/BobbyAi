import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Sparkles } from 'lucide-react';
import { useState } from 'react';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, studio } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as runsIndex } from '@/routes/runs';
import type { DashboardInvitation } from '@/types';

type Overview = {
    awaitingApproval: number;
    published: number;
    activeRuns: number;
    accounts: { connected: number; total: number };
    teamSlug: string;
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    overview: Overview;
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
            className="group rounded-lg border border-border bg-card p-4 shadow-xs transition-[border-color,box-shadow] outline-none hover:border-foreground/20 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring"
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-label text-muted-foreground">
                    {label}
                </span>
                <ArrowUpRight className="size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
            </div>
            <p
                className={cn(
                    'figure mt-2 text-[1.75rem] leading-none',
                    emphasis && 'text-agent',
                )}
            >
                {value}
            </p>
            <p className="mt-1.5 text-meta text-muted-foreground">{hint}</p>
        </Link>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    overview,
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

            <PageContainer>
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
