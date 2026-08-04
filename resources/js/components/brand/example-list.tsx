import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    values: string[];
    onChange: (values: string[]) => void;
    /** Mirrors the server's array limit. */
    max: number;
};

/**
 * Each example gets its own field. The alternative — one textarea split on
 * blank lines — hides the boundary the agent actually reads, so a pasted post
 * containing a paragraph break silently became two examples.
 */
export default function ExampleList({ values, onChange, max }: Props) {
    const replace = (index: number, value: string) => {
        onChange(values.map((current, at) => (at === index ? value : current)));
    };

    const remove = (index: number) => {
        onChange(values.filter((_, at) => at !== index));
    };

    if (values.length === 0) {
        return (
            <button
                type="button"
                onClick={() => onChange([''])}
                className="w-full rounded-lg border border-dashed border-input px-4 py-8 text-sm text-muted-foreground transition-colors hover:border-ring hover:text-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
            >
                Paste a post you were happy with
            </button>
        );
    }

    return (
        <div className="grid gap-3">
            {values.map((value, index) => (
                // Examples are unordered free text, so position is the only
                // key available; edits replace in place rather than reorder.
                <div key={index} className="grid gap-1.5">
                    <div className="flex items-baseline justify-between gap-3">
                        <Label
                            htmlFor={`example-${index}`}
                            className="text-muted-foreground"
                        >
                            Example
                        </Label>
                        <button
                            type="button"
                            onClick={() => remove(index)}
                            aria-label={`Remove example ${index + 1}`}
                            className="rounded-sm text-muted-foreground transition-colors hover:text-destructive focus-visible:text-destructive focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                        >
                            <Trash2 className="size-3.5" aria-hidden />
                        </button>
                    </div>
                    <textarea
                        id={`example-${index}`}
                        rows={4}
                        value={value}
                        onChange={(event) => replace(index, event.target.value)}
                        maxLength={3000}
                        placeholder="We built this for members who asked for it…"
                        className="w-full rounded-lg border border-input bg-card px-3 py-2 text-sm leading-relaxed shadow-xs transition-[color,box-shadow,border-color] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
                    />
                </div>
            ))}

            <div className="flex items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={values.length >= max}
                    onClick={() => onChange([...values, ''])}
                >
                    <Plus /> Add example
                </Button>
                <span className="text-label text-muted-foreground">
                    {values.length} of {max}
                </span>
            </div>
        </div>
    );
}
