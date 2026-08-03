import { PostCard } from '@/components/agent/post-card';
import type { BoardPost } from '@/types/agent';

type Props = {
    label: string;
    status: string;
    posts: BoardPost[];
    teamSlug: string;
};

export function BoardColumn({ label, status, posts, teamSlug }: Props) {
    const live = status === 'publishing' && posts.length > 0;

    return (
        <section className="flex w-72 shrink-0 flex-col gap-3">
            <header className="flex items-center gap-2 px-1">
                <h2 className="font-serif text-sm font-medium tracking-[-0.01em]">
                    {label}
                </h2>
                <span
                    className={`rounded-full px-1.5 py-0.5 font-mono text-[11px] tabular-nums ${
                        live
                            ? 'animate-pulse bg-agent-muted text-agent'
                            : 'bg-muted text-muted-foreground'
                    }`}
                >
                    {posts.length}
                </span>
            </header>

            <div
                className={`flex max-h-[calc(100dvh-14rem)] min-h-32 flex-col gap-2 overflow-y-auto rounded-xl border p-2 transition-colors ${
                    live
                        ? 'border-agent/30 bg-agent-muted'
                        : 'border-border bg-muted/40'
                }`}
            >
                {posts.length === 0 ? (
                    <p className="px-2 py-6 text-center font-serif text-sm text-balance text-muted-foreground italic">
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
