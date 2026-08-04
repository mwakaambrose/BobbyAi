/**
 * The placeholder shown while a run's steps are still being fetched. The rows
 * themselves are narrated by StepNarration; only this skeleton remains here.
 */
export function StepSkeleton() {
    return (
        <li className="relative flex items-center gap-3 py-1.5 pl-8">
            <span
                aria-hidden
                className="absolute top-2 left-1 size-2.5 rounded-full bg-muted"
            />
            <span className="h-3 w-6 animate-pulse rounded bg-muted" />
            <span className="h-3 w-28 animate-pulse rounded bg-muted" />
        </li>
    );
}
