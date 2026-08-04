import { Deferred, Form, Head, usePage, usePoll } from '@inertiajs/react';
import { ExternalLink, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import PostApprovalController from '@/actions/App/Http/Controllers/Agent/PostApprovalController';
import PostMessageController from '@/actions/App/Http/Controllers/Agent/PostMessageController';
import { DraftCard } from '@/components/agent/draft-card';
import { PostPreview } from '@/components/agent/post-preview';
import { RunTimeline } from '@/components/agent/run-timeline';
import { StepSkeleton } from '@/components/agent/step-row';
import { ThreadMessage } from '@/components/agent/thread-message';
import { Button } from '@/components/ui/button';
import { studio } from '@/routes';
import { show } from '@/routes/posts';
import type {
    DraftData,
    PostSummary,
    RunData,
    StepData,
    ThreadMessage as ThreadMessageData,
} from '@/types/agent';

type Props = {
    post: PostSummary;
    drafts: DraftData[];
    messages: ThreadMessageData[];
    run: RunData | null;
    steps?: StepData[];
};

export default function PostWorkspace({
    post,
    drafts,
    messages,
    run,
    steps,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    const [selected, setSelected] = useState<DraftData | null>(
        drafts[0] ?? null,
    );
    const [body, setBody] = useState(
        post.approved_body ?? drafts[0]?.body ?? '',
    );

    const active = run?.status === 'queued' || run?.status === 'running';

    // A zero interval polls as fast as the event loop allows, so the poll is
    // always created with a real interval and started only while a run is live.
    const { start, stop } = usePoll(
        1500,
        { only: ['run', 'steps', 'post'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (active) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [active, start, stop]);

    const selectDraft = (draft: DraftData) => {
        setSelected(draft);
        setBody(draft.body);
    };

    return (
        <>
            <Head title={truncateBrief(post.brief)} />

            <div className="grid h-full grid-cols-1 gap-6 px-6 py-8 md:px-10 lg:grid-cols-2">
                <section className="flex min-h-0 flex-col gap-6 overflow-y-auto rounded-md border bg-card p-5">
                    {messages.length === 0 && (
                        <div className="space-y-2">
                            <p className="eyebrow">brief</p>
                            <p className="rounded-lg bg-muted px-3 py-2 text-sm leading-relaxed">
                                {post.brief}
                            </p>
                        </div>
                    )}

                    {post.approved_body && (
                        <div className="space-y-2">
                            <p className="eyebrow">approved</p>
                            <p className="rounded-lg border border-agent/40 bg-agent-muted px-3 py-2 text-sm leading-relaxed">
                                {post.approved_body}
                            </p>
                        </div>
                    )}

                    {messages.map((message) => (
                        <ThreadMessage key={message.id} message={message}>
                            {message.kind === 'drafts' && (
                                <div className="space-y-2">
                                    {drafts.map((draft) => (
                                        <DraftCard
                                            key={draft.id}
                                            draft={draft}
                                            selected={selected?.id === draft.id}
                                            onSelect={selectDraft}
                                        />
                                    ))}
                                </div>
                            )}
                        </ThreadMessage>
                    ))}

                    {post.status === 'draft' && (
                        <div className="mt-auto space-y-5 border-t pt-5">
                            <Form
                                {...PostMessageController.store.form({
                                    current_team: teamSlug,
                                    post: post.id,
                                })}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex gap-2"
                            >
                                {({ processing }) => (
                                    <>
                                        <input
                                            name="body"
                                            placeholder="Shorter. Lead with the member story."
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                        />
                                        <Button
                                            size="icon"
                                            disabled={processing}
                                            aria-label="Ask for a revision"
                                        >
                                            <Send />
                                        </Button>
                                    </>
                                )}
                            </Form>

                            <Form
                                {...PostApprovalController.store.form({
                                    current_team: teamSlug,
                                    post: post.id,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <textarea
                                            name="body"
                                            rows={5}
                                            value={body}
                                            onChange={(event) =>
                                                setBody(event.target.value)
                                            }
                                            aria-label="The exact text to publish"
                                            className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                        />

                                        <p className="font-mono text-[11px] text-muted-foreground">
                                            {body.length} chars · the agent may
                                            type these words and nothing else
                                        </p>

                                        {errors.social_account_id && (
                                            <p className="text-xs text-destructive">
                                                {errors.social_account_id}
                                            </p>
                                        )}

                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                body.trim().length < 2
                                            }
                                            className="w-full"
                                        >
                                            Approve and publish
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    )}
                </section>

                <section className="min-h-0 space-y-4 overflow-y-auto rounded-md border bg-card p-5">
                    {run ? (
                        <Deferred
                            data="steps"
                            fallback={
                                <ol className="rounded-md border bg-card px-3 py-3">
                                    <StepSkeleton />
                                    <StepSkeleton />
                                    <StepSkeleton />
                                </ol>
                            }
                        >
                            <RunTimeline
                                run={run}
                                steps={steps ?? []}
                                teamSlug={teamSlug}
                            />
                        </Deferred>
                    ) : (
                        <PostPreview body={body || post.brief} handle={null} />
                    )}

                    {post.published_url && (
                        <a
                            href={post.published_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 text-sm text-verified underline-offset-4 hover:underline"
                        >
                            View it on LinkedIn{' '}
                            <ExternalLink className="size-3.5" aria-hidden />
                        </a>
                    )}
                </section>
            </div>
        </>
    );
}

/** Briefs are free text, so they get clipped before they reach a title bar. */
function truncateBrief(brief: string): string {
    return brief.length > 40 ? `${brief.slice(0, 40).trimEnd()}…` : brief;
}

PostWorkspace.layout = (props: {
    currentTeam?: { slug: string } | null;
    post: PostSummary;
}) => ({
    breadcrumbs: [
        {
            title: 'Studio',
            href: props.currentTeam ? studio(props.currentTeam.slug) : '/',
        },
        {
            title: truncateBrief(props.post.brief),
            href: props.currentTeam
                ? show([props.currentTeam.slug, props.post.id])
                : '#',
        },
    ],
});
