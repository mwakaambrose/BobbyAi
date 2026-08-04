import { Link } from '@inertiajs/react';
import { show } from '@/routes/posts';
import type { BoardPost } from '@/types/agent';

/**
 * How far through its step budget a live run has travelled.
 */
function ProgressRing({ steps, budget }: { steps: number; budget: number }) {
    const circumference = 2 * Math.PI * 15;
    const fraction = Math.min(steps / Math.max(budget, 1), 1);

    return (
        <svg viewBox="0 0 36 36" className="size-5 -rotate-90" aria-hidden>
            <circle
                cx="18"
                cy="18"
                r="15"
                fill="none"
                strokeWidth="5"
                className="stroke-muted"
            />
            <circle
                cx="18"
                cy="18"
                r="15"
                fill="none"
                strokeWidth="5"
                strokeLinecap="round"
                className="stroke-agent transition-[stroke-dashoffset] duration-700 ease-out"
                strokeDasharray={circumference}
                strokeDashoffset={circumference * (1 - fraction)}
            />
        </svg>
    );
}

type Props = {
    post: BoardPost;
    teamSlug: string;
};

export function PostCard({ post, teamSlug }: Props) {
    return (
        <Link
            href={show([teamSlug, post.id])}
            className="group block rounded-xl border border-border bg-card p-3 shadow-xs transition-[border-color,box-shadow,transform] hover:-translate-y-0.5 hover:border-foreground/15 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <p className="line-clamp-4 text-sm leading-relaxed text-card-foreground">
                {post.body ?? post.brief}
            </p>

            <div className="mt-3 flex items-center justify-between font-mono text-[11px] text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    {post.byBobby && (
                        <span className="rounded bg-agent/12 px-1.5 py-0.5 text-agent">
                            Bobby
                        </span>
                    )}
                    {post.updatedAt}
                </span>

                {post.progress && (
                    <span className="flex items-center gap-1.5 text-agent">
                        <ProgressRing {...post.progress} />
                        {post.progress.steps}/{post.progress.budget}
                    </span>
                )}
            </div>
        </Link>
    );
}
