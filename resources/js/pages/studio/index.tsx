import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { PenLine, Plug, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import PostController from '@/actions/App/Http/Controllers/Agent/PostController';
import { BoardColumn } from '@/components/agent/board-column';
import { EmptyState } from '@/components/agent/empty-state';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as accountsIndex } from '@/routes/accounts';
import type { AccountData, BoardPost } from '@/types/agent';

type Column = {
    status: string;
    label: string;
    posts: BoardPost[];
};

type Props = {
    columns: Column[];
    totalPosts: number;
    awaitingReview: number;
    accounts: AccountData[];
};

export default function Studio({
    columns,
    totalPosts,
    awaitingReview,
    accounts,
}: Props) {
    const [composing, setComposing] = useState(false);
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    const publishing = columns.find((column) => column.status === 'publishing');
    const isLive = Boolean(publishing && publishing.posts.length > 0);

    // A zero interval polls as fast as the event loop allows, so the poll is
    // always created with a real interval and started only while a run is live.
    const { start, stop } = usePoll(3000, {}, { autoStart: false });

    useEffect(() => {
        if (isLive) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isLive, start, stop]);

    return (
        <>
            <Head title="Studio" />

            <PageContainer className="flex h-full flex-col">
                <PageHeader
                    eyebrow={
                        awaitingReview > 0
                            ? `${awaitingReview} waiting for you`
                            : totalPosts === 1
                              ? '1 post'
                              : `${totalPosts} posts`
                    }
                    title="Studio"
                    description="Every post, and exactly where the agent has taken it."
                >
                    <Dialog open={composing} onOpenChange={setComposing}>
                        <DialogTrigger asChild>
                            <Button disabled={accounts.length === 0}>
                                <PenLine /> New post
                            </Button>
                        </DialogTrigger>

                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    What should the post say?
                                </DialogTitle>
                                <DialogDescription>
                                    Describe the point, the tone, and anything
                                    that has to appear.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                {...PostController.store.form({
                                    current_team: teamSlug,
                                })}
                                onSuccess={() => setComposing(false)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="social_account_id"
                                            value={accounts[0]?.id ?? ''}
                                        />

                                        <div className="grid gap-2">
                                            <textarea
                                                name="brief"
                                                rows={5}
                                                autoFocus
                                                placeholder="Announce Growth Savings. Warm, no jargon, mention that members asked for it."
                                                className="min-h-32 w-full rounded-md border border-input bg-card px-3 py-2 text-sm leading-relaxed transition-[border-color,box-shadow] outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring/30"
                                            />
                                            <InputError
                                                message={errors.brief}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <Sparkles />
                                            {processing
                                                ? 'Writing drafts…'
                                                : 'Write drafts'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </PageHeader>

                {accounts.length === 0 ? (
                    <EmptyState
                        icon={Plug}
                        title="Connect LinkedIn to begin"
                        description="The agent publishes through a real browser session that you sign in to yourself. No password is stored here."
                    >
                        <Button variant="outline" asChild>
                            <a href={accountsIndex(teamSlug).url}>
                                Go to accounts
                            </a>
                        </Button>
                    </EmptyState>
                ) : totalPosts === 0 ? (
                    <EmptyState
                        icon={PenLine}
                        title="No posts yet"
                        description="Start with a brief. You get three drafts to choose from, and nothing reaches LinkedIn until you approve the exact words."
                    >
                        <Button onClick={() => setComposing(true)}>
                            <PenLine /> Write the first one
                        </Button>
                    </EmptyState>
                ) : (
                    /* The board scrolls past the page margins rather than
                       inside them, so a column is never clipped mid-card. The
                       bleed matches the container's own padding exactly. */
                    <div className="-mx-5 flex gap-6 overflow-x-auto px-5 pb-2 md:-mx-8 md:px-8">
                        {columns.map((column) => (
                            <BoardColumn
                                key={column.status}
                                teamSlug={teamSlug}
                                {...column}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </>
    );
}
