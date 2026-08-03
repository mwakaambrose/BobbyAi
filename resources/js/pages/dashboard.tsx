import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Plug, Send, Sparkles } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

function Stat({
    icon: Icon,
    label,
    value,
    hint,
    href,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    hint: string;
    href: string;
}) {
    return (
        <Card className="gap-0 py-0 transition-[box-shadow,border-color] hover:border-agent/40 hover:shadow-sm">
            <Link href={href} className="block p-5 focus-visible:outline-none">
                <CardContent className="flex items-start gap-4 p-0">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-agent-muted text-agent">
                        <Icon className="size-4" aria-hidden />
                    </div>
                    <div className="space-y-0.5">
                        <p className="font-mono text-2xl leading-none tabular-nums">
                            {value}
                        </p>
                        <p className="text-sm font-medium">{label}</p>
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    </div>
                </CardContent>
            </Link>
        </Card>
    );
}

export default function Dashboard({
    pendingInvitations = [],
    overview,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const { teamSlug } = overview;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Overview"
                    description="Where your posts stand, and what the agent is doing about them."
                >
                    <Button asChild>
                        <Link href={studio(teamSlug)}>
                            <Sparkles /> Open studio
                        </Link>
                    </Button>
                </PageHeader>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        icon={CheckCircle2}
                        label="Awaiting publish"
                        value={String(overview.awaitingApproval)}
                        hint="Approved, not yet live"
                        href={studio(teamSlug).url}
                    />
                    <Stat
                        icon={Send}
                        label="Published"
                        value={String(overview.published)}
                        hint="Live on LinkedIn"
                        href={studio(teamSlug).url}
                    />
                    <Stat
                        icon={Sparkles}
                        label="Active runs"
                        value={String(overview.activeRuns)}
                        hint="Agent working right now"
                        href={runsIndex(teamSlug).url}
                    />
                    <Stat
                        icon={Plug}
                        label="Accounts"
                        value={`${overview.accounts.connected}/${overview.accounts.total}`}
                        hint="Connected sessions"
                        href={accountsIndex(teamSlug).url}
                    />
                </div>
            </div>
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
