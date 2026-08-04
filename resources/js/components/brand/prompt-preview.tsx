import type { BrandProfileData } from '@/types/agent';

type Props = {
    brand: BrandProfileData;
};

/**
 * The brand profile as the model receives it. The keys and their order match
 * App\Agent\Copywriter::prompt exactly — this is the page's whole point, so it
 * shows the real thing rather than a friendly paraphrase of it.
 */
export default function PromptPreview({ brand }: Props) {
    const lines: { key: string; value: string | null }[] = [
        { key: 'VOICE', value: brand.voice?.trim() || null },
        { key: 'AUDIENCE', value: brand.audience?.trim() || null },
        { key: 'TOPICS', value: brand.topics.join(', ') || null },
        { key: 'NEVER USE', value: brand.bannedTerms.join(', ') || null },
        {
            key: 'EXAMPLES OF OUR VOICE',
            value: brand.examples.filter((example) => example.trim() !== '')
                .length
                ? brand.examples.filter((example) => example.trim() !== '')[0]
                : null,
        },
    ];

    const exampleCount = brand.examples.filter(
        (example) => example.trim() !== '',
    ).length;

    return (
        <aside className="lg:sticky lg:top-8">
            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                <div className="border-b border-border px-4 py-3">
                    <h2 className="text-sm font-medium">
                        What the agent reads
                    </h2>
                    <p className="mt-0.5 text-label text-muted-foreground">
                        Sent ahead of every brief, word for word.
                    </p>
                </div>

                <dl className="space-y-3 px-4 py-4 font-mono text-label">
                    <div>
                        <dt className="text-muted-foreground">BRIEF:</dt>
                        <dd className="mt-0.5 text-muted-foreground italic">
                            whatever you ask for that day
                        </dd>
                    </div>

                    {lines.map(({ key, value }) => (
                        <div key={key}>
                            <dt className="text-muted-foreground">{key}:</dt>
                            <dd className="mt-0.5 leading-relaxed break-words">
                                {value === null ? (
                                    <span className="text-muted-foreground/70">
                                        not set
                                    </span>
                                ) : key === 'EXAMPLES OF OUR VOICE' ? (
                                    <>
                                        <span className="line-clamp-2">
                                            {value}
                                        </span>
                                        {exampleCount > 1 && (
                                            <span className="text-muted-foreground">
                                                + {exampleCount - 1} more
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    value
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </aside>
    );
}
