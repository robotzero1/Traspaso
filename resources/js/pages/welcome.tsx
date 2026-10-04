import { Head, Link, usePage } from '@inertiajs/react';
import { login, register } from '@/routes';
import { index as gamesIndex } from '@/routes/games';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Take over a café in Zaragoza" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="flex justify-end gap-3 p-6 text-sm">
                    {auth.user ? (
                        <Link
                            href={gamesIndex()}
                            className="rounded-md border px-4 py-1.5 hover:bg-muted"
                        >
                            Your games
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className="rounded-md px-4 py-1.5 hover:bg-muted"
                            >
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                className="rounded-md border px-4 py-1.5 hover:bg-muted"
                            >
                                Register
                            </Link>
                        </>
                    )}
                </header>
                <main className="mx-auto flex max-w-2xl flex-1 flex-col justify-center gap-6 px-6 pb-16">
                    <h1 className="text-4xl font-semibold tracking-tight">
                        Traspaso
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        Take over a café in Zaragoza with €20,000–€100,000 of
                        your own money. Pick a business for sale, set your
                        prices, hours, staff and marketing, ride out the
                        surprises, and see whether you're better off after a
                        year.
                    </p>
                    <div>
                        <Link
                            href={auth.user ? gamesIndex() : register()}
                            className="inline-block rounded-md bg-primary px-5 py-2 text-primary-foreground hover:bg-primary/90"
                        >
                            {auth.user ? 'Play' : 'Start playing'}
                        </Link>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Business locations, financial data and operating
                        characteristics are simulated. Real-world market data is
                        used to establish realistic ranges and distributions.
                    </p>
                </main>
            </div>
        </>
    );
}
