import { Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    body: string;
    onChange: (body: string) => void;
    disabled: boolean;
    onAsk: (
        before: string,
        selection: string,
        after: string,
        instruction: string,
    ) => void;
};

/**
 * The draft as an ordinary textarea, plus a prompt bar that appears while a
 * fragment is selected. The buffer is split here rather than sent as offsets:
 * the browser counts UTF-16 code units and the server counts codepoints, so an
 * emoji anywhere in the post would make the two disagree.
 */
export function DraftEditor({ body, onChange, disabled, onAsk }: Props) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    const [span, setSpan] = useState<{ start: number; end: number } | null>(
        null,
    );
    const [instruction, setInstruction] = useState('');

    const capture = () => {
        const element = textarea.current;

        if (!element) {
            return;
        }

        const { selectionStart: start, selectionEnd: end } = element;

        setSpan(end > start ? { start, end } : null);
    };

    const ask = () => {
        if (!span || instruction.trim().length < 2) {
            return;
        }

        onAsk(
            body.slice(0, span.start),
            body.slice(span.start, span.end),
            body.slice(span.end),
            instruction.trim(),
        );

        setInstruction('');
        setSpan(null);
    };

    return (
        <div className="space-y-2">
            <textarea
                ref={textarea}
                name="body"
                rows={8}
                value={body}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                onSelect={capture}
                aria-label="The exact text to publish"
                className="min-h-40 w-full resize-none rounded-xl border border-input bg-transparent px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-60"
            />

            {span && !disabled && (
                <div className="space-y-2 rounded-xl border border-agent/40 bg-accent/40 px-3 py-2">
                    <p className="text-label text-muted-foreground">
                        <Sparkles className="mr-1 inline size-3" aria-hidden />“
                        {body.slice(span.start, span.end)}”
                    </p>

                    <div className="flex gap-2">
                        <input
                            value={instruction}
                            onChange={(event) =>
                                setInstruction(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    ask();
                                }
                            }}
                            placeholder="What is wrong with this?"
                            aria-label="What is wrong with the selected text"
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <Button
                            type="button"
                            size="sm"
                            disabled={instruction.trim().length < 2}
                            onClick={ask}
                        >
                            Ask
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
