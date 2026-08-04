import { Form, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Sparkles } from 'lucide-react';
import { useState } from 'react';
import PostAlternativeController from '@/actions/App/Http/Controllers/Agent/PostAlternativeController';
import PostApprovalController from '@/actions/App/Http/Controllers/Agent/PostApprovalController';
import PostRefinementController from '@/actions/App/Http/Controllers/Agent/PostRefinementController';
import { DraftEditor } from '@/components/agent/draft-editor';
import { SpanProposalView } from '@/components/agent/span-proposal';
import { Button } from '@/components/ui/button';
import { useSpanRefinement } from '@/hooks/use-span-refinement';
import type { DraftData, PostSummary } from '@/types/agent';

type Props = {
    teamSlug: string;
    post: PostSummary;
    drafts: DraftData[];
    streamed: string;
    failed: string | null;
    isDrafting: boolean;
};

/**
 * The post as it will be published, pinned beside the conversation so it is
 * never more than a glance away, with approval on the thing being approved.
 */
export function DraftCanvas({
    teamSlug,
    post,
    drafts,
    streamed,
    failed,
    isDrafting,
}: Props) {
    const [index, setIndex] = useState(0);
    const [body, setBody] = useState(
        post.approved_body ?? drafts[0]?.body ?? '',
    );

    const draft = drafts[index] ?? null;

    // A new draft arriving replaces what the editor holds, unless the post is
    // already approved, in which case the approved text is the record. Adjusted
    // during render rather than in an effect so there is no flash of the old
    // draft, and no cascading second render.
    const [shownDraftId, setShownDraftId] = useState(draft?.id ?? null);

    if (draft !== null && draft.id !== shownDraftId) {
        setShownDraftId(draft.id);

        if (post.approved_body === null) {
            setBody(draft.body);
        }
    }

    const streaming = isDrafting && streamed !== '';
    const shown = streaming ? streamed : body;

    const {
        proposal,
        failed: refineFailed,
        request,
        reject,
    } = useSpanRefinement(post.id, teamSlug);

    /**
     * The splice happens locally; the post only records that it happened, so a
     * later request arrives with the edit history behind it.
     */
    const accept = () => {
        if (!proposal) {
            return;
        }

        setBody(proposal.before + proposal.replacement + proposal.after);

        router.post(
            PostRefinementController.accept.url({
                current_team: teamSlug,
                post: post.id,
            }),
            {
                instruction: proposal.instruction,
                before: proposal.selection,
                after: proposal.replacement,
            },
            { preserveScroll: true },
        );

        reject();
    };

    return (
        <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
            <header className="flex items-center justify-between gap-2">
                <span className="text-label text-muted-foreground">
                    {drafts.length > 1
                        ? `Angle ${index + 1} of ${drafts.length}`
                        : 'Draft'}
                </span>
                {drafts.length > 1 && (
                    <span className="flex items-center gap-1">
                        <Button
                            size="icon"
                            variant="ghost"
                            disabled={index === 0}
                            onClick={() => setIndex(index - 1)}
                            aria-label="Previous angle"
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            disabled={index === drafts.length - 1}
                            onClick={() => setIndex(index + 1)}
                            aria-label="Next angle"
                        >
                            <ChevronRight />
                        </Button>
                    </span>
                )}
            </header>

            {failed && <p className="text-meta text-destructive">{failed}</p>}

            {refineFailed && (
                <p className="text-meta text-destructive">{refineFailed}</p>
            )}

            {streaming ? (
                <p className="text-sm leading-relaxed whitespace-pre-wrap">
                    {shown}
                    <span
                        className="ml-0.5 inline-block h-4 w-px animate-pulse bg-agent align-middle"
                        aria-hidden
                    />
                </p>
            ) : (
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
                            {proposal ? (
                                <SpanProposalView
                                    proposal={proposal}
                                    onAccept={accept}
                                    onReject={reject}
                                />
                            ) : (
                                <DraftEditor
                                    body={body}
                                    onChange={setBody}
                                    disabled={isDrafting}
                                    onAsk={request}
                                />
                            )}

                            <p className="font-mono text-label text-muted-foreground tabular-nums">
                                {body.length} chars · the agent may type these
                                words and nothing else
                            </p>

                            {errors.social_account_id && (
                                <p className="text-label text-destructive">
                                    {errors.social_account_id}
                                </p>
                            )}

                            {post.status === 'draft' && (
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        proposal !== null ||
                                        body.trim().length < 2
                                    }
                                    className="w-full"
                                >
                                    Approve and publish
                                </Button>
                            )}
                        </>
                    )}
                </Form>
            )}

            {drafts.length <= 1 && !isDrafting && post.status === 'draft' && (
                <Form
                    {...PostAlternativeController.store.form({
                        current_team: teamSlug,
                        post: post.id,
                    })}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={processing}
                            className="justify-start"
                        >
                            <Sparkles /> Show me other angles
                        </Button>
                    )}
                </Form>
            )}
        </div>
    );
}
