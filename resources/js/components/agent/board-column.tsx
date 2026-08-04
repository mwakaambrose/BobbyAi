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
            <header
                className={cn(
                    'flex items-baseline justify-between gap-2 border-b pb-2',
                    live ? 'border-agent' : 'border-rule-strong',
                )}
            >
                <h2 className="eyebrow flex items-center gap-1.5 text-foreground">
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
                        'figure text-sm',
                        live ? 'text-agent' : 'text-muted-foreground',
                    )}
                >
                    {posts.length}
                </span>
            </header>

            <div className="flex max-h-[calc(100dvh-17rem)] min-h-32 flex-col gap-2 overflow-y-auto pt-3">
                {posts.length === 0 ? (
                    <p className="py-3 font-serif text-sm text-muted-foreground italic">
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
