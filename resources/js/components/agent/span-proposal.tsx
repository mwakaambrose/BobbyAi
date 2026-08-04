import { Button } from '@/components/ui/button';
import type { SpanProposal } from '@/types/agent';

type Props = {
    proposal: SpanProposal;
    onAccept: () => void;
    onReject: () => void;
};

/**
 * The rewrite in place. Only the fragment changes; the text around it is
 * rendered exactly where it already was, so nothing on screen jumps while the
 * writer decides.
 */
export function SpanProposalView({ proposal, onAccept, onReject }: Props) {
    return (
        <div className="space-y-3">
            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {proposal.before}
                <del className="text-muted-foreground decoration-gate">
                    {proposal.selection}
                </del>
                <ins className="bg-agent-muted text-foreground no-underline">
                    {proposal.replacement}
                    {!proposal.settled && (
                        <span
                            className="ml-0.5 inline-block h-4 w-px animate-pulse bg-agent align-middle"
                            aria-hidden
                        />
                    )}
                </ins>
                {proposal.after}
            </p>

            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    disabled={!proposal.settled}
                    onClick={onAccept}
                >
                    Accept
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onReject}
                >
                    Reject
                </Button>
            </div>
        </div>
    );
}
