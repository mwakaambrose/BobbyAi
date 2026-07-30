import { Form, Head, usePage } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, TriangleAlert } from 'lucide-react';
import SocialAccountController from '@/actions/App/Http/Controllers/Agent/SocialAccountController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { AccountData, AccountStatus } from '@/types/agent';

const STATUS: Record<AccountStatus, { label: string; icon: typeof CheckCircle2; tone: string }> = {
    connected: { label: 'Connected', icon: CheckCircle2, tone: 'text-verified' },
    expired: { label: 'Session expired', icon: TriangleAlert, tone: 'text-gate' },
    disconnected: { label: 'Not connected', icon: CircleDashed, tone: 'text-muted-foreground' },
};

type Props = {
    accounts: AccountData[];
};

export default function AccountsIndex({ accounts }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Accounts" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Accounts"
                    description="The agent works in a real browser session. You sign in yourself, so no password is ever stored here."
                />

                {accounts.map((account) => {
                    const status = STATUS[account.status];
                    const Icon = status.icon;

                    return (
                        <Card key={account.id} className="p-5">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="space-y-1">
                                    <p className="font-medium">LinkedIn</p>
                                    <p className={`flex items-center gap-1.5 text-sm ${status.tone}`}>
                                        <Icon className="size-4" aria-hidden />
                                        {status.label}
                                        {account.lastVerifiedAt && (
                                            <span className="text-muted-foreground">
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
                                            <Button variant="outline" disabled={processing}>
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
                                            <Button disabled={processing}>Check connection</Button>
                                        )}
                                    </Form>
                                </div>
                            </div>

                            {account.status !== 'connected' && (
                                <ol className="mt-5 space-y-2 border-t pt-5 text-sm text-muted-foreground">
                                    <li className="flex gap-3">
                                        <span className="font-mono text-xs text-agent">01</span>
                                        Open the browser. A Chromium window appears on this machine.
                                    </li>
                                    <li className="flex gap-3">
                                        <span className="font-mono text-xs text-agent">02</span>
                                        Sign in to LinkedIn and clear two-factor yourself.
                                    </li>
                                    <li className="flex gap-3">
                                        <span className="font-mono text-xs text-agent">03</span>
                                        Come back and check the connection.
                                    </li>
                                </ol>
                            )}
                        </Card>
                    );
                })}
            </div>
        </>
    );
}
