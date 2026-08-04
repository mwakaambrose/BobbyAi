import { Form, Head, Link } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useState } from 'react';
import PostController from '@/actions/App/Http/Controllers/Agent/PostController';
import PageContainer from '@/components/page-container';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard, studio } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as runsIndex } from '@/routes/runs';
import type { DashboardInvitation } from '@/types';
import type { HomeStatus, Suggestion } from '@/types/agent';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    suggestions: Suggestion[];
    status: HomeStatus;
};

/**
 * The greeting changes with the hour so returning to the app feels like being
 * met rather than logging in.
 */
function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 18 ? 'Good afternoon' : 'Good evening';
}

const CARD_CLASS =
    'flex flex-col rounded-xl border border-border bg-card p-3 text-left shadow-2xs transition-[border-color,box-shadow,transform] outline-none hover:-translate-y-0.5 hover:border-foreground/15 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring';

/**
 * A suggestion either navigates to work already in flight, or fills the
 * composer with a brief. It never does both.
 */
function SuggestionCard({
    suggestion,
    onPrompt,
}: {
    suggestion: Suggestion;
    onPrompt: (prompt: string) => void;
}) {
    const inner = (
        <>
            <span className="line-clamp-2 text-sm font-medium">
                {suggestion.label}
            </span>
            <span className="mt-1 line-clamp-1 text-label text-muted-foreground">
                {suggestion.hint}
            </span>
        </>
    );

    if (suggestion.href) {
        return (
            <Link href={suggestion.href} className={CARD_CLASS}>
                {inner}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={CARD_CLASS}
            onClick={() => onPrompt(suggestion.prompt ?? '')}
        >
            {inner}
        </button>
    );
}

export default function Home({
    pendingInvitations = [],
    suggestions,
    status,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [brief, setBrief] = useState('');

    const { teamSlug } = status;

    return (
        <>
            <Head title="Home" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <PageContainer className="flex flex-col justify-center py-16">
                <div className="mx-auto w-full max-w-2xl">
                    <h1 className="text-display">
                        {greeting()}.
                        <br />
                        <span className="text-muted-foreground">
                            What should we post today?
                        </span>
                    </h1>
                    <p className="mt-3 text-meta text-muted-foreground">
                        Bobby writes in your voice and waits for your approval
                        before anything goes live.
                    </p>

                    <Form
                        {...PostController.store.form({
                            current_team: teamSlug,
                        })}
                        className="mt-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex items-end gap-2 rounded-xl border border-input bg-card p-3 shadow-md focus-within:border-foreground/20">
                                    <textarea
                                        name="brief"
                                        rows={2}
                                        value={brief}
                                        onChange={(event) =>
                                            setBrief(event.target.value)
                                        }
                                        placeholder="Ask Bobby to write something…"
                                        className="w-full resize-none bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                                    />
                                    <Button
                                        size="icon"
                                        className="rounded-full"
                                        disabled={
                                            processing || brief.trim() === ''
                                        }
                                        aria-label="Send brief"
                                    >
                                        <ArrowUp />
                                    </Button>
                                </div>
                                {errors.brief && (
                                    <p className="mt-2 text-label text-destructive">
                                        {errors.brief}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>

                    <div className="mt-3 grid gap-2 sm:grid-cols-3">
                        {suggestions.map((suggestion) => (
                            <SuggestionCard
                                key={suggestion.label}
                                suggestion={suggestion}
                                onPrompt={setBrief}
                            />
                        ))}
                    </div>

                    <p className="mt-8 flex flex-wrap items-center gap-x-2 gap-y-1 text-label text-muted-foreground">
                        <Link
                            href={runsIndex(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.activeRuns} in progress
                        </Link>
                        <span aria-hidden>·</span>
                        <Link
                            href={studio(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.awaitingPublish} awaiting publish
                        </Link>
                        <span aria-hidden>·</span>
                        <Link
                            href={accountsIndex(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.accountsConnected} of {status.accountsTotal}{' '}
                            accounts connected
                        </Link>
                    </p>
                </div>
            </PageContainer>
        </>
    );
}

Home.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Home',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
