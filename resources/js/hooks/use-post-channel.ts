import { useEffect } from 'react';
import echo from '@/echo';

/**
 * Owns the lifetime of the post's private channel.
 *
 * Drafting, refinement and the run theatre all listen on `posts.{id}`. Each of
 * them adds and removes its own listeners; leaving the channel belongs here, at
 * the page, or one feature unmounting would disconnect the others.
 */
export function usePostChannel(postId: number) {
    useEffect(() => {
        echo().private(`posts.${postId}`);

        return () => {
            echo().leave(`posts.${postId}`);
        };
    }, [postId]);
}
