type Props = {
    body: string;
    handle: string | null;
};

/**
 * Shows the approved copy the way it will read on the feed, so the thing being
 * approved looks like the thing that gets published.
 */
export function PostPreview({ body, handle }: Props) {
    return (
        <article className="rounded-md border bg-card p-5">
            <header className="flex items-center gap-3">
                <div aria-hidden className="size-10 rounded-full bg-muted" />
                <div>
                    <p className="text-sm font-medium">
                        {handle ?? 'Your LinkedIn account'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Preview · not yet published
                    </p>
                </div>
            </header>

            <p className="mt-4 text-sm leading-relaxed whitespace-pre-wrap">
                {body}
            </p>
        </article>
    );
}
