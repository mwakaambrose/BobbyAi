import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

/** A hairline that runs the full width of the page, separating one movement from the next. */
function Rule() {
    return <div className="border-t border-border" aria-hidden />;
}

/**
 * A handful of posts caught mid-flight in the margins of the field: one being
 * drafted, two held for a decision, two already out. Deliberately placed
 * outside the text column so nothing ever sits behind the headline, and hidden
 * below `lg` where there is no margin to speak of.
 */
const marks = [
    { edge: 'left', offset: '7%', top: '18%', mark: '◆', tone: 'text-gate' },
    {
        edge: 'left',
        offset: '13%',
        top: '43%',
        mark: '✓',
        tone: 'text-verified',
    },
    { edge: 'left', offset: '5%', top: '62%', mark: '●', tone: 'text-agent' },
    { edge: 'left', offset: '11%', top: '80%', mark: '◆', tone: 'text-gate' },
    {
        edge: 'right',
        offset: '7%',
        top: '25%',
        mark: '✓',
        tone: 'text-verified',
    },
    { edge: 'right', offset: '12%', top: '48%', mark: '●', tone: 'text-agent' },
    { edge: 'right', offset: '6%', top: '72%', mark: '◆', tone: 'text-gate' },
] as const;

/**
 * The ground the hero sits on: an even field of marks, a few of them live. No
 * wash behind them — the field is the only ground. It dissolves before the page
 * reaches the band.
 */
function Motif() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-160 mask-[linear-gradient(to_bottom,black,black_60%,transparent)]"
        >
            <div
                className="absolute inset-0"
                style={{
                    backgroundImage:
                        'radial-gradient(color-mix(in oklch, var(--color-foreground) 16%, transparent) 1.5px, transparent 1.5px)',
                    backgroundSize: '22px 22px',
                }}
            />
            {marks.map((item) => (
                <span
                    key={`${item.edge}-${item.top}`}
                    style={{ [item.edge]: item.offset, top: item.top }}
                    className={`absolute hidden font-mono text-[15px] opacity-85 lg:block ${item.tone} ${item.mark === '●' ? 'animate-pulse' : ''}`}
                >
                    {item.mark}
                </span>
            ))}
        </div>
    );
}

function Nav() {
    const { auth, currentTeam, name } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <Link href="/" className="flex items-center gap-2">
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-5 fill-current" />
                </div>
                <span className="font-display text-base font-semibold tracking-[-0.015em]">
                    {name}
                </span>
            </Link>
            <nav className="flex items-center gap-3">
                {auth.user ? (
                    <Button variant="outline" asChild>
                        <Link href={dashboardUrl}>Dashboard</Link>
                    </Button>
                ) : (
                    <>
                        <Button variant="ghost" asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                        <Button asChild>
                            <Link href={register()}>Start writing</Link>
                        </Button>
                    </>
                )}
            </nav>
        </header>
    );
}

/** Three facts the hero has no room to argue, set as a rule-separated row beneath it. */
const attributes = [
    'One-line brief → three drafts',
    'Publishes itself',
    'Never without your approval',
] as const;

function Hero() {
    const { name } = usePage().props;

    return (
        <section className="px-6 pt-24 pb-24 text-center sm:pt-32 sm:pb-28">
            <p className="font-mono text-[11px] tracking-[0.18em] text-muted-foreground uppercase">
                An AI agent for LinkedIn
            </p>
            <h1 className="mx-auto mt-6 max-w-3xl font-display text-5xl leading-[1.04] font-semibold tracking-[-0.03em] text-balance sm:text-6xl">
                Show up on LinkedIn every week. Without writing every week.
            </h1>
            <p className="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-balance text-muted-foreground">
                {name} learns your voice, drafts from a one-line brief, and
                publishes the moment you approve. Nothing goes out unread.
            </p>
            <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
                <Button size="lg" asChild>
                    <Link href={register()}>Start writing</Link>
                </Button>
                <Button size="lg" variant="outline" asChild>
                    <Link href={login()}>Log in</Link>
                </Button>
            </div>
            <ul className="mx-auto mt-12 flex max-w-3xl flex-col items-center justify-center gap-3 font-mono text-[11px] tracking-[0.14em] whitespace-nowrap text-muted-foreground uppercase sm:flex-row sm:gap-0">
                {attributes.map((attribute) => (
                    <li
                        key={attribute}
                        className="px-5 sm:border-l sm:border-border sm:first:border-l-0"
                    >
                        {attribute}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * A wide, quiet band between the pitch and the mechanics. Photo by RDNE Stock
 * project on Pexels (pexels.com/photo/10376030), used under the Pexels licence
 * — free for commercial use, attribution not required. It is
 * desaturated and washed at the edges so it joins the warm ground of the page
 * rather than sitting on top of it as a rectangle.
 */
function Band() {
    return (
        <section className="relative isolate overflow-hidden">
            <img
                src="/images/hero-desk.jpg"
                alt="Someone reading a feed on their phone beside an open laptop at a wooden desk."
                width={2000}
                height={1333}
                loading="lazy"
                decoding="async"
                className="h-56 w-full object-cover object-center saturate-[0.88] sm:h-72 lg:h-80 dark:opacity-70 dark:saturate-[0.6]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-linear-to-b from-background/45 via-transparent to-background/55 dark:from-background/80 dark:via-background/25 dark:to-background/85"
            />
        </section>
    );
}

/**
 * The three states the console uses, taught on the way in: work in progress,
 * a decision held for you, a publish that actually happened.
 */
const beats = [
    {
        number: '01',
        title: 'Write',
        body: 'Give it a topic, a link, or a half-formed thought. It comes back with three drafts in your voice, not one take you have to argue with.',
        chip: { label: 'drafting', mark: '●', tone: 'text-agent' },
    },
    {
        number: '02',
        title: 'Push back',
        body: 'Tell it the second line is too much and it rewrites that line, in place. Everything you already approved stays untouched.',
        chip: { label: 'waiting for you', mark: '◆', tone: 'text-gate' },
    },
    {
        number: '03',
        title: 'Watch',
        body: 'Say yes and a real browser session publishes it, step by step, with screenshots. You can watch it happen. You can stop it mid-run.',
        chip: { label: 'published', mark: '✓', tone: 'text-verified' },
    },
] as const;

function Beats() {
    return (
        <section className="mx-auto max-w-5xl px-6 py-20">
            <h2 className="text-center font-display text-2xl font-semibold tracking-[-0.02em]">
                Three moves, and one of them is yours
            </h2>
            <div className="mt-14 grid divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                {beats.map((beat) => (
                    <div
                        key={beat.number}
                        className="flex flex-col px-0 py-8 sm:px-7 sm:py-0 sm:first:pl-0 sm:last:pr-0"
                    >
                        <p className="font-mono text-[11px] tracking-[0.18em] text-muted-foreground uppercase">
                            {beat.number} / {beat.title}
                        </p>
                        <p className="mt-4 text-[15px] leading-relaxed text-pretty text-muted-foreground">
                            {beat.body}
                        </p>
                        <p
                            className={`mt-auto flex items-center gap-2 pt-6 font-mono text-[11px] tracking-[0.14em] uppercase ${beat.chip.tone}`}
                        >
                            <span
                                aria-hidden
                                className={
                                    beat.chip.mark === '●'
                                        ? 'animate-pulse'
                                        : undefined
                                }
                            >
                                {beat.chip.mark}
                            </span>
                            {beat.chip.label}
                        </p>
                    </div>
                ))}
            </div>
        </section>
    );
}

function Closing() {
    return (
        <section className="mx-auto max-w-3xl px-6 py-20 text-center">
            <p className="font-display text-3xl leading-[1.15] font-semibold tracking-tight text-balance sm:text-4xl">
                No autopilot. Nothing on your feed you didn’t read first.
            </p>
            <div className="mt-8">
                <Button size="lg" asChild>
                    <Link href={register()}>Start writing</Link>
                </Button>
            </div>
        </section>
    );
}

function Footer() {
    const { name } = usePage().props;

    return (
        <footer className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-8">
            <p className="text-sm text-muted-foreground">
                &copy; {new Date().getFullYear()} {name}
            </p>
            <div className="flex aspect-square size-6 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <AppLogoIcon className="size-3.5 fill-current" />
            </div>
        </footer>
    );
}

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="relative isolate flex min-h-screen flex-col bg-background text-foreground">
                <Motif />
                <Nav />
                <main className="flex-1">
                    <Hero />
                    <Rule />
                    <Band />
                    <Rule />
                    <Beats />
                    <Rule />
                    <Closing />
                </main>
                <Rule />
                <Footer />
            </div>
        </>
    );
}
