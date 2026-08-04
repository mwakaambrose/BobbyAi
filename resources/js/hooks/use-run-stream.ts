import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import echo from '@/echo';
import type { StepData } from '@/types/agent';

const RELOAD_PROPS = ['run', 'steps', 'post'];

/**
 * Renders the run as it happens.
 *
 * The stream is enhancement only. Every step is persisted before it is
 * broadcast, so a page whose socket never connects still fills in from the
 * fallback reload — which is the polling this replaced, only rarer.
 */
export function useRunStream(
    postId: number,
    active: boolean,
    serverSteps: StepData[],
): StepData[] {
    const [streamed, setStreamed] = useState<StepData[]>([]);

    useEffect(() => {
        if (!active) {
            return;
        }

        const channel = echo().private(`posts.${postId}`);

        channel.listen(
            '.run.step',
            (event: { runStatus: string; step: StepData }) => {
                setStreamed((current) => [...current, event.step]);

                // A terminal status is the one thing the stream cannot carry:
                // the post's own status and published URL change with it.
                if (
                    event.runStatus !== 'running' &&
                    event.runStatus !== 'queued'
                ) {
                    router.reload({ only: RELOAD_PROPS });
                }
            },
        );

        const fallback = window.setInterval(() => {
            router.reload({ only: RELOAD_PROPS });
        }, 8000);

        return () => {
            window.clearInterval(fallback);
            channel.stopListening('.run.step');
        };
    }, [postId, active]);

    const byId = new Map<number, StepData>();

    for (const step of [...serverSteps, ...streamed]) {
        byId.set(step.id, step);
    }

    return [...byId.values()].sort((a, b) => a.index - b.index);
}
