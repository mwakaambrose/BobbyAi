import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import echo from '@/echo';

type ChunkPayload = { delta: string };
type FailedPayload = { reason: string };

const RELOAD_PROPS = ['drafts', 'messages', 'post'];

/**
 * Renders the draft as it is written.
 *
 * The stream is enhancement only. The job persists everything, so a page whose
 * socket never connects still gets its draft from the fallback reload, and the
 * completion reload always replaces streamed text with server state.
 */
export function usePostStream(postId: number, isDrafting: boolean) {
    const [streamed, setStreamed] = useState('');
    const [failed, setFailed] = useState<string | null>(null);

    useEffect(() => {
        if (!isDrafting) {
            return;
        }

        const channel = echo().private(`posts.${postId}`);

        channel.listen('.draft.chunk', (event: ChunkPayload) => {
            // A delta arriving means this attempt is alive, so any error left
            // over from the previous one no longer describes the canvas.
            setFailed(null);
            setStreamed((current) => current + event.delta);
        });

        channel.listen('.draft.completed', () => {
            setStreamed('');
            router.reload({ only: RELOAD_PROPS });
        });

        channel.listen('.draft.failed', (event: FailedPayload) => {
            setStreamed('');
            setFailed(event.reason);
            router.reload({ only: ['messages'] });
        });

        // If the socket never connects the draft still lands in the database,
        // so the page catches up on its own a few seconds later.
        const fallback = window.setInterval(() => {
            router.reload({ only: RELOAD_PROPS });
        }, 8000);

        // The listeners come off, but the channel stays: refinement and the run
        // theatre are on it too, and its lifetime belongs to usePostChannel.
        return () => {
            window.clearInterval(fallback);
            channel.stopListening('.draft.chunk');
            channel.stopListening('.draft.completed');
            channel.stopListening('.draft.failed');
        };
    }, [postId, isDrafting]);

    // Derived rather than cleared in the effect: once drafting is over the
    // partial text is meaningless, and server state has already replaced it.
    return { streamed: isDrafting ? streamed : '', failed };
}
