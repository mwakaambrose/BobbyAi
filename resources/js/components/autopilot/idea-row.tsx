import { Form, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, X } from 'lucide-react';
import PostIdeaController from '@/actions/App/Http/Controllers/Agent/PostIdeaController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { IdeaKind, PostIdeaData } from '@/types/agent';

type Props = {
    idea: PostIdeaData;
    teamSlug: string;
    kinds: { value: IdeaKind; label: string }[];
    onMove: (direction: -1 | 1) => void;
    isFirst: boolean;
    isLast: boolean;
};

/**
 * One backlog row. The kind chip is editable because Bobby's guess only
 * affects ordering, and a correction should be cheap to make and permanent
 * once made.
 */
export default function IdeaRow({
    idea,
    teamSlug,
    kinds,
    onMove,
    isFirst,
    isLast,
}: Props) {
    const setKind = (kind: IdeaKind) => {
        router.put(
            PostIdeaController.update.url({ current_team: teamSlug, idea: idea.id }),
            { kind },
            { preserveScroll: true },
        );
    };

    return (
        <li className="flex items-start gap-3 rounded-lg border border-border/60 bg-card p-3">
            <div className="flex flex-col gap-0.5 pt-0.5">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-5"
                    disabled={isFirst}
                    onClick={() => onMove(-1)}
                    aria-label="Move up"
                >
                    <ChevronUp className="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-5"
                    disabled={isLast}
                    onClick={() => onMove(1)}
                    aria-label="Move down"
                >
                    <ChevronDown className="size-3.5" />
                </Button>
            </div>

            <div className="min-w-0 flex-1 space-y-2">
                <p className="text-sm leading-relaxed">{idea.body}</p>

                <div className="flex flex-wrap items-center gap-1.5">
                    {kinds.map((kind) => (
                        <button
                            key={kind.value}
                            type="button"
                            onClick={() => setKind(kind.value)}
                            aria-pressed={idea.kind === kind.value}
                        >
                            <Badge
                                variant={
                                    idea.kind === kind.value
                                        ? 'verified'
                                        : 'secondary'
                                }
                                className={
                                    idea.kind === kind.value
                                        ? ''
                                        : 'opacity-50 hover:opacity-100'
                                }
                            >
                                {kind.label}
                            </Badge>
                        </button>
                    ))}

                    {idea.kind === null && (
                        <span className="text-xs text-muted-foreground">
                            Bobby is still reading this one
                        </span>
                    )}
                </div>
            </div>

            <Form
                {...PostIdeaController.destroy.form({
                    current_team: teamSlug,
                    idea: idea.id,
                })}
                options={{ preserveScroll: true }}
            >
                <Button
                    variant="ghost"
                    size="icon"
                    type="submit"
                    className="size-7 text-muted-foreground"
                    aria-label="Remove idea"
                >
                    <X className="size-4" />
                </Button>
            </Form>
        </li>
    );
}
