import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import PostRefinementController from '@/actions/App/Http/Controllers/Agent/PostRefinementController';
import echo from '@/echo';
import type { SpanProposal } from '@/types/agent';

/**
 * Nothing about a refinement is persisted until it is accepted, so there is no
 * server state to fall back to. A stream that never arrives is given this long
 * before the editor comes back with the failure on it.
 */
const TIMEOUT_MS = 30_000;

export function useSpanRefinement(postId: number, teamSlug: string) {
    const [proposal, setProposal] = useState<SpanProposal | null>(null);
    const [failed, setFailed] = useState<string | null>(null);
    const timer = useRef<number | null>(null);

    const clearTimer = useCallback(() => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }
    }, []);

    useEffect(() => {
        const channel = echo().private(`posts.${postId}`);

        channel.listen('.refine.chunk', (event: { delta: string }) => {
            setProposal((current) =>
                current && !current.settled
                    ? {
                          ...current,
                          replacement: current.replacement + event.delta,
                      }
                    : current,
            );
        });

        // The settled replacement is trusted over the accumulated deltas, so a
        // dropped chunk cannot leave a hole in the text the writer accepts.
        channel.listen(
            '.refine.completed',
            (event: { replacement: string }) => {
                clearTimer();
                setProposal((current) =>
                    current
                        ? {
                              ...current,
                              replacement: event.replacement,
                              settled: true,
                          }
                        : current,
                );
            },
        );

        channel.listen('.refine.failed', (event: { reason: string }) => {
            clearTimer();
            setProposal(null);
            setFailed(event.reason);
        });

        return () => {
            clearTimer();
            channel.stopListening('.refine.chunk');
            channel.stopListening('.refine.completed');
            channel.stopListening('.refine.failed');
        };
    }, [postId, clearTimer]);

    const request = useCallback(
        (
            before: string,
            selection: string,
            after: string,
            instruction: string,
        ) => {
            setFailed(null);
            setProposal({
                before,
                selection,
                after,
                instruction,
                replacement: '',
                settled: false,
            });

            router.post(
                PostRefinementController.store.url({
                    current_team: teamSlug,
                    post: postId,
                }),
                { before, selection, after, instruction },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onError: () => {
                        clearTimer();
                        setProposal(null);
                        setFailed('That selection could not be sent.');
                    },
                },
            );

            clearTimer();
            timer.current = window.setTimeout(() => {
                setProposal(null);
                setFailed('Bobby did not answer. Try that again.');
            }, TIMEOUT_MS);
        },
        [postId, teamSlug, clearTimer],
    );

    const reject = useCallback(() => {
        clearTimer();
        setProposal(null);
    }, [clearTimer]);

    return { proposal, failed, request, reject };
}
