import { LegalPage } from '@/components/legal-page';

export default function Disclaimer() {
    return (
        <LegalPage title="Disclaimer">
            <p>
                Traspaso is a simulation. Its businesses are fictional; their
                locations, finances and running are simulated. Real market data
                is used to set realistic ranges and distributions.
            </p>
            <h2>How the numbers are made</h2>
            <ul>
                <li>
                    Footfall is an estimate built from OpenStreetMap data (©
                    OpenStreetMap contributors, ODbL), not a pedestrian count.
                </li>
                <li>
                    The economy is calibrated so that, as INE and Hostelería de
                    España report, about a quarter of new cafés and bars close
                    in their first year and about half within five years.
                </li>
                <li>
                    Prices, rents and traspasos come from aggregated samples of
                    Zaragoza listings. Many other figures are still estimates.
                </li>
            </ul>
            <h2>What a viability check is, and isn't</h2>
            <p>
                It runs many possible futures of the café you describe and
                reports how they went. It can't know the things that often
                decide whether a real café works: the people, the service, the
                landlord, the books, the licences, or what happens next door. It
                is not financial, legal or tax advice. Before buying a business,
                check its accounts, lease and licences with a professional (a
                gestor, lawyer or accountant).
            </p>
        </LegalPage>
    );
}
