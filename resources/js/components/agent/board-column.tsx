import { PostCard } from '@/components/agent/post-card';
import { cn } from '@/lib/utils';
import type { BoardPost } from '@/types/agent';

type Props = {
    label: string;
    status: string;
    posts: BoardPost[];
    teamSlug: string;
};

/**
 * A column is a ruled section of the board, not a tinted tray. The rule under
 * the heading is what separates one stage from the next, and live work is
 * marked by colouring that rule rather than washing the whole column.
 */
export function BoardColumn({ label, status, posts, teamSlug }: Props) {
    const live = status === 'publishing' && posts.length > 0;

    return (
        <section className="flex w-72 shrink-0 flex-col">
            <header className="flex items-center justify-between gap-2 px-0.5 pb-2.5">
                <h2 className="flex items-center gap-1.5 text-[0.8125rem] font-medium">
                    {live && (
                        <span
                            className="size-1.5 shrink-0 animate-pulse rounded-full bg-agent"
                            aria-hidden
                        />
                    )}
                    {label}
                </h2>
                <span
                    className={cn(
                        'rounded-md px-1.5 py-0.5 text-label tabular-nums',
                        live
                            ? 'bg-agent-muted text-agent'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {posts.length}
                </span>
            </header>

            <div className="flex max-h-[calc(100dvh-17rem)] min-h-32 flex-col gap-2 overflow-y-auto rounded-lg bg-muted/50 p-2">
                {posts.length === 0 ? (
                    <p className="px-1 py-6 text-center text-meta text-muted-foreground">
                        Nothing here yet.
                    </p>
                ) : (
                    posts.map((post) => (
                        <PostCard
                            key={post.id}
                            post={post}
                            teamSlug={teamSlug}
                        />
                    ))
                )}
            </div>
        </section>
    );
}
