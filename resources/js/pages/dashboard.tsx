import { Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
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
 * One cell of the figure strip. The number is the content, so it carries the
 * display face and everything else steps back from it.
 */
function Figure({
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
            className="group flex flex-col gap-1.5 px-5 py-8 transition-colors outline-none first:pl-0 hover:bg-accent/50 focus-visible:bg-accent/50"
        >
            <span
                className={cn(
                    'figure text-[clamp(2.5rem,4.5vw,3.5rem)] leading-none',
                    emphasis && 'text-agent',
                )}
            >
                {value}
            </span>
            <span className="text-sm font-medium group-hover:underline group-hover:decoration-foreground/25 group-hover:underline-offset-4">
                {label}
            </span>
            <span className="text-meta text-muted-foreground">{hint}</span>
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

                <section>
                    {/* Rules run between the cells, not around them, so the
                        strip reads as one ruled band rather than four boxes. */}
                    <div className="grid grid-cols-2 border-b border-rule sm:grid-cols-4 sm:[&>*:not(:first-child)]:border-l sm:[&>*:not(:first-child)]:border-rule [&>*:nth-child(n+3)]:border-t [&>*:nth-child(n+3)]:border-rule sm:[&>*:nth-child(n+3)]:border-t-0">
                        <Figure
                            value={String(overview.awaitingApproval)}
                            label="Awaiting publish"
                            hint="Approved, not yet live"
                            href={studio(teamSlug).url}
                        />
                        <Figure
                            value={String(overview.published)}
                            label="Published"
                            hint="Live on LinkedIn"
                            href={studio(teamSlug).url}
                        />
                        <Figure
                            value={String(activeRuns)}
                            label="Active runs"
                            hint="Agent working right now"
                            href={runsIndex(teamSlug).url}
                            emphasis={activeRuns > 0}
                        />
                        <Figure
                            value={`${overview.accounts.connected}/${overview.accounts.total}`}
                            label="Accounts"
                            hint="Connected sessions"
                            href={accountsIndex(teamSlug).url}
                        />
                    </div>
                </section>
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
