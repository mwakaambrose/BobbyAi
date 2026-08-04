import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';
import PostMessageController from '@/actions/App/Http/Controllers/Agent/PostMessageController';
import { ThreadMessage } from '@/components/agent/thread-message';
import { Button } from '@/components/ui/button';
import type {
    DraftData,
    PostSummary,
    ThreadMessage as ThreadMessageData,
} from '@/types/agent';

type Props = {
    teamSlug: string;
    post: PostSummary;
    messages: ThreadMessageData[];
    drafts: DraftData[];
    isDrafting: boolean;
};

/**
 * Fixed follow-ups rather than generated ones. They submit ordinary revision
 * requests, so they need no endpoint of their own. Approve is deliberately not
 * here - it belongs on the canvas, next to the thing being approved.
 */
const FOLLOW_UPS = [
    'Make it shorter',
    'Change the tone',
    'Add a stronger hook',
] as const;

export function ThreadColumn({
    teamSlug,
    post,
    messages,
    drafts,
    isDrafting,
}: Props) {
    return (
        <section className="flex min-h-0 flex-col gap-6 overflow-y-auto">
            {messages.length === 0 && (
                <div className="space-y-2">
                    <p className="eyebrow">brief</p>
                    <p className="rounded-lg bg-muted px-3 py-2 text-sm leading-relaxed">
                        {post.brief}
                    </p>
                </div>
            )}

            {messages.map((message) => (
                <ThreadMessage key={message.id} message={message}>
                    {message.kind === 'error' && (
                        <p className="text-meta text-destructive">
                            {message.payload?.reason as string}
                        </p>
                    )}

                    {message.kind === 'edit' && (
                        <div className="space-y-1 rounded-lg border border-border px-3 py-2 text-sm leading-relaxed">
                            <p className="text-muted-foreground line-through">
                                {message.payload?.before as string}
                            </p>
                            <p>{message.payload?.after as string}</p>
                        </div>
                    )}
                </ThreadMessage>
            ))}

            {isDrafting && (
                <p className="text-meta text-muted-foreground">
                    <span className="mr-2 inline-block size-1.5 animate-pulse rounded-full bg-agent align-middle" />
                    Bobby is writing…
                </p>
            )}

            {post.status === 'draft' && (
                <div className="mt-auto space-y-3 border-t border-border pt-5">
                    {!isDrafting && drafts.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {FOLLOW_UPS.map((text) => (
                                <Form
                                    key={text}
                                    {...PostMessageController.store.form({
                                        current_team: teamSlug,
                                        post: post.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    <input
                                        type="hidden"
                                        name="body"
                                        value={text}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                    >
                                        {text}
                                    </Button>
                                </Form>
                            ))}
                        </div>
                    )}

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
                </div>
            )}
        </section>
    );
}
