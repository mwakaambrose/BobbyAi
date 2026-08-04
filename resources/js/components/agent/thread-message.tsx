import type { ReactNode } from 'react';
import type { ThreadMessage as ThreadMessageData } from '@/types/agent';

type Props = {
    message: ThreadMessageData;
    children?: ReactNode;
};

export function ThreadMessage({ message, children }: Props) {
    const isUser = message.role === 'user';

    return (
        <div className="space-y-2">
            <p className="eyebrow">{isUser ? 'you' : 'bobby'}</p>

            {message.body && (
                <p
                    className={
                        isUser
                            ? 'rounded-lg bg-muted px-3 py-2 text-sm leading-relaxed'
                            : 'text-sm leading-relaxed'
                    }
                >
                    {message.body}
                </p>
            )}

            {children}
        </div>
    );
}
