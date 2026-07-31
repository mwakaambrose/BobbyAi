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
        <section className="flex min-w-44 flex-1 basis-0 flex-col gap-3">
            <header className="flex items-center gap-2 px-1">
                <h2 className="text-sm font-medium">{label}</h2>
                <span
                    className={`rounded-full px-1.5 py-0.5 font-mono text-[11px] tabular-nums ${
                        live ? 'animate-pulse bg-agent-muted text-agent' : 'bg-muted text-muted-foreground'
                    }`}
                >
                    {posts.length}
                </span>
            </header>

            <div
                className={`flex min-h-28 flex-col gap-2 rounded-xl border border-dashed p-2 transition-colors ${
                    live
                        ? 'border-agent/40 bg-agent-muted/30'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                }`}
            >
                {posts.map((post) => (
                    <PostCard key={post.id} post={post} teamSlug={teamSlug} />
                ))}
            </div>
        </section>
    );
}
