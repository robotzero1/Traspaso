import { LegalPage } from '@/components/legal-page';

export default function Terms() {
    return (
        <LegalPage title="Terms">
            <p>
                Traspaso is run by [legal name], NIF [number], [address], Spain
                ([contact email]). By using it or buying from it you accept
                these terms.
            </p>
            <h2>What Traspaso is</h2>
            <p>
                A simulation of running a café or bar in Zaragoza: a game, and a
                viability check that simulates possible futures of a café you
                describe. Both are simulations built on estimates; see the{' '}
                <a href="/disclaimer" className="underline">
                    disclaimer
                </a>
                . Nothing here is financial, legal or tax advice.
            </p>
            <h2>What you can buy</h2>
            <ul>
                <li>A full viability report, for the café it was run for.</li>
                <li>
                    More starting capital in the game (your savings), for every
                    game started from your account.
                </li>
            </ul>
            <p>
                Prices are in euros and include IVA. Payment is taken by Stripe
                when you buy; you get a receipt by email.
            </p>
            <h2>Right of withdrawal</h2>
            <p>
                These are digital content supplied straight away. When you buy,
                you ask for immediate delivery and accept that you then lose the
                14-day right of withdrawal (Real Decreto Legislativo 1/2007,
                art. 103 m). If something you bought doesn't work as described,
                contact us and we'll put it right or refund you.
            </p>
            <h2>Your account and use</h2>
            <p>
                Keep your login safe. Don't misuse the service, try to break it,
                or resell reports as your own. We may change or end features;
                anything you've paid for stays available while the service runs.
            </p>
            <h2>Liability</h2>
            <p>
                Within what the law allows, we aren't liable for decisions you
                make based on the simulation. This doesn't limit your rights as
                a consumer under Spanish law.
            </p>
            <h2>Law</h2>
            <p>
                Spanish law applies. Consumers may also use the EU's online
                dispute resolution platform.
            </p>
        </LegalPage>
    );
}
