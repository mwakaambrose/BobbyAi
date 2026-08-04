import { Form, Head, usePage } from '@inertiajs/react';
import SocialAccountController from '@/actions/App/Http/Controllers/Agent/SocialAccountController';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { AccountData, AccountStatus } from '@/types/agent';

const accountBadge = {
    connected: 'verified',
    expired: 'gate',
    disconnected: 'secondary',
} as const satisfies Record<AccountStatus, 'verified' | 'gate' | 'secondary'>;

const STATUS_LABEL: Record<AccountStatus, string> = {
    connected: 'Connected',
    expired: 'Session expired',
    disconnected: 'Not connected',
};

type Props = {
    accounts: AccountData[];
};

export default function AccountsIndex({ accounts }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Accounts" />

            <PageContainer className="space-y-4">
                <PageHeader
                    eyebrow="LinkedIn"
                    title="Accounts"
                    description="The agent works in a real browser session. You sign in yourself, so no password is ever stored here."
                />

                {accounts.map((account) => (
                    <Card key={account.id} className="p-5">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="space-y-1">
                                <p className="font-medium">LinkedIn</p>
                                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Badge
                                        variant={accountBadge[account.status]}
                                    >
                                        {STATUS_LABEL[account.status]}
                                    </Badge>
                                    {account.lastVerifiedAt && (
                                        <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                            · checked {account.lastVerifiedAt}
                                        </span>
                                    )}
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                <Form
                                    {...SocialAccountController.connect.form({
                                        current_team: teamSlug,
                                        account: account.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Open browser and sign in
                                        </Button>
                                    )}
                                </Form>

                                <Form
                                    {...SocialAccountController.verify.form({
                                        current_team: teamSlug,
                                        account: account.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button disabled={processing}>
                                            Check connection
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </div>

                        {account.status !== 'connected' && (
                            <ol className="mt-5 space-y-2 border-t border-border pt-5 text-sm text-muted-foreground">
                                <li className="flex gap-3">
                                    <span className="font-mono text-[11px] text-agent tabular-nums">
                                        01
                                    </span>
                                    Open the browser. A Chromium window appears
                                    on this machine.
                                </li>
                                <li className="flex gap-3">
                                    <span className="font-mono text-[11px] text-agent tabular-nums">
                                        02
                                    </span>
                                    Sign in to LinkedIn and clear two-factor
                                    yourself.
                                </li>
                                <li className="flex gap-3">
                                    <span className="font-mono text-[11px] text-agent tabular-nums">
                                        03
                                    </span>
                                    Come back and check the connection.
                                </li>
                            </ol>
                        )}
                    </Card>
                ))}
            </PageContainer>
        </>
    );
}
