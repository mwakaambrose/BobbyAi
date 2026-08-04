import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AutopilotController from '@/actions/App/Http/Controllers/Agent/AutopilotController';
import PostIdeaController from '@/actions/App/Http/Controllers/Agent/PostIdeaController';
import IdeaRow from '@/components/autopilot/idea-row';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    AccountData,
    DraftedIdeaData,
    IdeaKind,
    PostIdeaData,
    PostingScheduleData,
} from '@/types/agent';

type Props = {
    schedule: PostingScheduleData;
    ideas: PostIdeaData[];
    drafted: DraftedIdeaData[];
    accounts: Pick<AccountData, 'id' | 'handle'>[];
    kinds: { value: IdeaKind; label: string }[];
};

const DAYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

export default function AutopilotIndex({
    schedule,
    ideas,
    drafted,
    accounts,
    kinds,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    const [isActive, setIsActive] = useState(schedule.isActive);
    const [days, setDays] = useState<string[]>(schedule.days);
    const [order, setOrder] = useState<PostIdeaData[]>(ideas);

    const toggleDay = (day: string) =>
        setDays((current) =>
            current.includes(day)
                ? current.filter((value) => value !== day)
                : [...current, day],
        );

    /* Reordering is optimistic: the list moves under the cursor and the new
       order is sent behind it, because waiting for a round trip to see a row
       move makes arranging a backlog feel broken. */
    const move = (index: number, direction: -1 | 1) => {
        const next = [...order];
        const target = index + direction;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];
        setOrder(next);

        router.put(
            PostIdeaController.reorder.url({ current_team: teamSlug }),
            { ids: next.map((idea) => idea.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Autopilot" />

            <PageContainer>
                <PageHeader
                    eyebrow="What Bobby writes, and when"
                    title="Autopilot"
                    description="Bobby drafts on a rhythm you set and leaves every post waiting for you. Nothing is published until you approve it."
                />

                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Idea backlog</CardTitle>
                                <CardDescription>
                                    Bobby takes the next one that keeps the feed
                                    varied. When the backlog runs dry it falls
                                    back to a brand pillar you have not written
                                    about lately.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                <Form
                                    {...PostIdeaController.store.form({
                                        current_team: teamSlug,
                                    })}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="flex gap-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="flex-1 space-y-1">
                                                <Input
                                                    name="body"
                                                    placeholder="Something worth writing about…"
                                                    aria-label="New idea"
                                                />
                                                <InputError
                                                    message={errors.body}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Add
                                            </Button>
                                        </>
                                    )}
                                </Form>

                                {order.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-border/60 p-6 text-center text-sm text-muted-foreground">
                                        Nothing queued. Bobby will fall back to
                                        your brand pillars.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {order.map((idea, index) => (
                                            <IdeaRow
                                                key={idea.id}
                                                idea={idea}
                                                teamSlug={teamSlug}
                                                kinds={kinds}
                                                isFirst={index === 0}
                                                isLast={
                                                    index === order.length - 1
                                                }
                                                onMove={(direction) =>
                                                    move(index, direction)
                                                }
                                            />
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {drafted.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Already drafted</CardTitle>
                                    <CardDescription>
                                        Ideas Bobby has written up.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-2 text-sm">
                                        {drafted.map((idea) => (
                                            <li
                                                key={idea.id}
                                                className="flex items-center justify-between gap-3 border-b border-border/40 pb-2 last:border-0"
                                            >
                                                <span className="min-w-0 truncate text-muted-foreground">
                                                    {idea.body}
                                                </span>
                                                {idea.post && (
                                                    <Badge variant="secondary">
                                                        {idea.post.status}
                                                    </Badge>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="lg:sticky lg:top-6">
                        <CardHeader>
                            <CardTitle>Cadence</CardTitle>
                            <CardDescription>
                                {schedule.lastRanAt
                                    ? `Last drafted ${schedule.lastRanAt}.`
                                    : 'Not run yet.'}
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...AutopilotController.update.form({
                                    current_team: teamSlug,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-5"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="flex items-center justify-between gap-3">
                                            <Label htmlFor="is_active">
                                                Autopilot on
                                            </Label>
                                            <Checkbox
                                                id="is_active"
                                                checked={isActive}
                                                onCheckedChange={(checked) =>
                                                    setIsActive(
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value={isActive ? '1' : '0'}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Days</Label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {DAYS.map((day) => (
                                                    <button
                                                        key={day}
                                                        type="button"
                                                        onClick={() =>
                                                            toggleDay(day)
                                                        }
                                                        aria-pressed={days.includes(
                                                            day,
                                                        )}
                                                    >
                                                        <Badge
                                                            variant={
                                                                days.includes(
                                                                    day,
                                                                )
                                                                    ? 'verified'
                                                                    : 'secondary'
                                                            }
                                                            className="capitalize"
                                                        >
                                                            {day.slice(0, 3)}
                                                        </Badge>
                                                    </button>
                                                ))}
                                            </div>
                                            {days.map((day) => (
                                                <input
                                                    key={day}
                                                    type="hidden"
                                                    name="days[]"
                                                    value={day}
                                                />
                                            ))}
                                            <InputError message={errors.days} />
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="space-y-1">
                                                <Label htmlFor="time_of_day">
                                                    Time
                                                </Label>
                                                <Input
                                                    id="time_of_day"
                                                    name="time_of_day"
                                                    type="time"
                                                    defaultValue={
                                                        schedule.timeOfDay
                                                    }
                                                />
                                                <InputError
                                                    message={errors.time_of_day}
                                                />
                                            </div>

                                            <div className="space-y-1">
                                                <Label htmlFor="posts_per_run">
                                                    Posts each time
                                                </Label>
                                                <Input
                                                    id="posts_per_run"
                                                    name="posts_per_run"
                                                    type="number"
                                                    min={1}
                                                    max={5}
                                                    defaultValue={
                                                        schedule.postsPerRun
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.posts_per_run
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="timezone">
                                                Timezone
                                            </Label>
                                            <Input
                                                id="timezone"
                                                name="timezone"
                                                defaultValue={schedule.timezone}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                The time above is read in this
                                                zone.
                                            </p>
                                            <InputError
                                                message={errors.timezone}
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="social_account_id">
                                                Account
                                            </Label>
                                            <select
                                                id="social_account_id"
                                                name="social_account_id"
                                                defaultValue={
                                                    schedule.socialAccountId ??
                                                    ''
                                                }
                                                className="h-9 w-full rounded-lg border border-input bg-card px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
                                            >
                                                <option value="">
                                                    Choose an account
                                                </option>
                                                {accounts.map((account) => (
                                                    <option
                                                        key={account.id}
                                                        value={account.id}
                                                    >
                                                        {account.handle ??
                                                            'LinkedIn'}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.social_account_id
                                                }
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="notify_emails">
                                                Tell these addresses
                                            </Label>
                                            {schedule.notifyEmails.map(
                                                (email, index) => (
                                                    <Input
                                                        key={email}
                                                        id={
                                                            index === 0
                                                                ? 'notify_emails'
                                                                : undefined
                                                        }
                                                        name="notify_emails[]"
                                                        type="email"
                                                        defaultValue={email}
                                                    />
                                                ),
                                            )}
                                            <Input
                                                name="notify_emails[]"
                                                type="email"
                                                placeholder="Add another address"
                                            />
                                            <InputError
                                                message={errors.notify_emails}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Save cadence
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            </PageContainer>
        </>
    );
}
