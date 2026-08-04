import { Deferred, Head, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { DraftCanvas } from '@/components/agent/draft-canvas';
import { RunTheatre } from '@/components/agent/run-theatre';
import { StepSkeleton } from '@/components/agent/step-row';
import { ThreadColumn } from '@/components/agent/thread-column';
import PageContainer from '@/components/page-container';
import { usePostChannel } from '@/hooks/use-post-channel';
import { usePostStream } from '@/hooks/use-post-stream';
import { useRunStream } from '@/hooks/use-run-stream';
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

    // The window between dispatching the drafting job and the agent's turn
    // being persisted: the last thing in the thread came from the user.
    const isDrafting =
        post.status === 'draft' && messages.at(-1)?.role === 'user';

    usePostChannel(post.id);

    const { streamed, failed } = usePostStream(post.id, isDrafting);

    const active = run?.status === 'queued' || run?.status === 'running';

    const liveSteps = useRunStream(post.id, active, steps ?? []);

    return (
        <>
            <Head title={truncateBrief(post.brief)} />

            <PageContainer className="flex h-full flex-col">
                <div className="grid min-h-0 flex-1 grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="min-h-0 rounded-md border bg-card p-5">
                        <ThreadColumn
                            teamSlug={teamSlug}
                            post={post}
                            messages={messages}
                            drafts={drafts}
                            isDrafting={isDrafting}
                        />
                    </div>

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
                                <RunTheatre
                                    run={run}
                                    steps={liveSteps}
                                    teamSlug={teamSlug}
                                />
                            </Deferred>
                        ) : (
                            <DraftCanvas
                                teamSlug={teamSlug}
                                post={post}
                                drafts={drafts}
                                streamed={streamed}
                                failed={failed}
                                isDrafting={isDrafting}
                            />
                        )}

                        {post.published_url && (
                            <a
                                href={post.published_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 text-sm text-verified underline-offset-4 hover:underline"
                            >
                                View it on LinkedIn{' '}
                                <ExternalLink
                                    className="size-3.5"
                                    aria-hidden
                                />
                            </a>
                        )}
                    </section>
                </div>
            </PageContainer>
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
