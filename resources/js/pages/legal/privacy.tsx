import { LegalPage } from '@/components/legal-page';

export default function Privacy() {
    return (
        <LegalPage title="Privacy">
            <p>
                [Legal name], NIF [number], [address] ([contact email]) is
                responsible for your data (GDPR and LOPDGDD).
            </p>
            <h2>What we keep, and why</h2>
            <ul>
                <li>
                    Your account: name, email and password (stored hashed), to
                    let you sign in. Basis: our contract with you.
                </li>
                <li>
                    Your games and their results, to run the game. Basis:
                    contract.
                </li>
                <li>
                    Viability checks: the location and figures you enter, and
                    the results. Anyone with a report's link can open it, so
                    keep the link private. Reports not paid for are deleted
                    after 90 days.
                </li>
                <li>
                    Push notification subscriptions for the devices you turn
                    them on for, until you turn them off. Basis: your consent.
                </li>
                <li>
                    Purchases: what you bought and when. Card details are
                    handled by Stripe, never by us. We keep purchase records as
                    long as tax law requires. Basis: legal obligation.
                </li>
            </ul>
            <h2>Who else sees it</h2>
            <ul>
                <li>Stripe, to take payments.</li>
                <li>
                    Your browser's push service (Google, Apple or Mozilla) to
                    deliver notifications; they see only the encrypted message.
                </li>
                <li>[Hosting provider], where the service runs.</li>
            </ul>
            <p>Map tiles load from OpenStreetMap in your browser.</p>
            <h2>Cookies</h2>
            <p>
                Only the ones needed to keep you signed in and remember your
                light or dark setting. No advertising or tracking cookies.
            </p>
            <h2>How long, and where</h2>
            <p>
                Your account and games until you delete your account; purchase
                records for as long as tax law requires (six years). Backups are
                kept for 14 days. The service is hosted in [EU country].
            </p>
            <h2>Your rights</h2>
            <p>
                You can ask to see, correct, export or delete your data, or
                object to its use, at [contact email]. Settings → Profile has a
                download of all your data, and deleting your account there
                removes it. You can complain to the Agencia Española de
                Protección de Datos (aepd.es).
            </p>
        </LegalPage>
    );
}
