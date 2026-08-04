import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';

function Nav() {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <Link href="/" className="flex items-center gap-2">
                <AppLogoIcon className="size-6 fill-current text-foreground" />
                <span className="font-serif text-lg font-medium tracking-[-0.01em]">
                    {usePage().props.name}
                </span>
            </Link>
            <nav className="flex items-center gap-3">
                {auth.user ? (
                    <Button size="lg" variant="outline" asChild>
                        <Link href={dashboardUrl}>Dashboard</Link>
                    </Button>
                ) : (
                    <>
                        <Button size="lg" variant="outline" asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                        <Button size="lg" asChild>
                            <Link href={register()}>Register</Link>
                        </Button>
                    </>
                )}
            </nav>
        </header>
    );
}

function Hero() {
    const { name } = usePage().props;

    return (
        <section className="mx-auto max-w-3xl px-6 pt-20 pb-16 text-center">
            <p className="font-mono text-[11px] tracking-widest text-muted-foreground uppercase">
                LinkedIn, on your terms
            </p>
            <h1 className="mt-4 font-serif text-5xl leading-[1.05] font-medium tracking-[-0.02em] text-balance sm:text-6xl">
                An agent that writes your posts, and never publishes one
                without you.
            </h1>
            <p className="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-balance text-muted-foreground">
                {name} drafts in your voice, shows you exactly what it plans
                to say, and waits. You approve the words. It handles the
                rest.
            </p>
            <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Button size="lg" asChild>
                    <Link href={register()}>Start writing</Link>
                </Button>
                <Button size="lg" variant="outline" asChild>
                    <Link href={login()}>Log in</Link>
                </Button>
            </div>
        </section>
    );
}

const steps = [
    {
        number: '01',
        title: 'Brief',
        body: 'Tell the agent what you want to say — a topic, a link, a rough idea. That’s the whole brief.',
    },
    {
        number: '02',
        title: 'Draft',
        body: 'It comes back with three variants in your voice, ready to compare side by side.',
    },
    {
        number: '03',
        title: 'Approve',
        body: 'Nothing goes out until you sign off on the exact words. Then a real browser session publishes it to LinkedIn.',
    },
] as const;

function HowItWorks() {
    return (
        <section className="mx-auto max-w-5xl px-6 py-16">
            <h2 className="text-center font-serif text-3xl font-medium tracking-[-0.01em]">
                How it works
            </h2>
            <div className="mt-10 grid gap-6 sm:grid-cols-3">
                {steps.map((step) => (
                    <Card key={step.number}>
                        <CardHeader>
                            <span className="font-mono text-xs text-muted-foreground">
                                {step.number}
                            </span>
                            <CardTitle className="text-xl">
                                {step.title}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {step.body}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}

function Footer() {
    const { name } = usePage().props;

    return (
        <footer className="mx-auto flex w-full max-w-5xl items-center justify-between border-t border-border px-6 py-8">
            <p className="text-sm text-muted-foreground">
                &copy; {new Date().getFullYear()} {name}
            </p>
            <div className="flex items-center gap-2">
                <AppLogoIcon className="size-4 fill-current text-muted-foreground" />
            </div>
        </footer>
    );
}

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <Nav />
                <main className="flex-1">
                    <Hero />
                    <HowItWorks />
                </main>
                <Footer />
            </div>
        </>
    );
}
