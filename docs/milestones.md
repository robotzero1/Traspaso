# Milestones log (from milestone 11)

A plain-language record of what each milestone built, when, and the key
numbers. The detail lives in `SPEC.md` (each milestone's "(done)" entry),
the commits, and `docs/balance-report.md` (every calibration). Milestones
1–10 are in `SPEC.md` §9 and §11.

## Stage two: real time, the app and the viability check

### 11. Daily simulation · 7 Oct 2026 · `db3c8c9`

The café trades day by day on the real calendar instead of a month at a
time: busier Fridays and Saturdays, public holidays (traded as Sundays), the
Pilar week, and day-to-day weather (rain days from AEMET normals; rain
closes the terrace). Takings and stock are daily, the other bills at month
end. Each day's share is normalised over its month, so the days add up to
the calibrated monthly totals and stage one's calibration carries over.
`market:balance --daily` checks the targets on the daily engine.

### 12. Real-time clock · 7 Oct 2026 · `7183711`

One game day per real day, no fixed end. Every night at 23:00 Madrid time a
job plays each café's day; missed nights catch up, and each day is played
once. Decisions apply from the next day; hiring takes 7 days and letting
someone go 15 days' notice (Estatuto de los Trabajadores). Events wait 3
days for an answer, then take their default. A café bought today trades from
tomorrow, and a part month pays its bills pro rata.

### 13. App and push notifications · 7 Oct 2026 · `0903954`

The game installs on a phone like an app (web app manifest, icons, service
worker) and opens on the latest day's results. After each nightly run, one
push notification per device: the day's customers and takings, the month's
profit at month end, and events waiting for a decision. Players choose what
they hear about; bankruptcy always gets through.

### 14. Viability check · 7 Oct 2026 · `8907541`

A standalone tool at `/viability`: pin a real café on the map, enter the
listing's figures, your money and how you'd run it. It plays 1,000
five-year futures of that café (about 30 seconds) against the real cafés
and bars nearby. Free preview: 1-year survival, the spot's footfall and
rivals. Full report: survival at 1, 3 and 5 years, yearly profit before the
owner's pay with an 8-in-10 range, months to earn back the traspaso, and
flagged risks.

### 15. Payments and accounts · 7 Oct 2026 · `0097788`

Stripe Checkout for the full viability report and for bigger starting
capital (€30k free; €60k and €100k of "savings" paid). IVA included, an
invoice from Stripe, and a box waiving the 14-day withdrawal right for
digital content (RDL 1/2007 art. 103 m). Draft terms, privacy and
disclaimer pages. Prices are placeholders.

### 16. Going live · 7 Oct 2026 · `f45f332`

A deployment guide for one EU server (`docs/deploy.md`, configs in
`deploy/`). Health checks behind `/up` (database, queue worker, failed
jobs, missed nightly runs), a heartbeat ping after each nightly run, nightly
SQLite backups kept 14 days, and GDPR basics: download your data, delete
your account, unpaid reports pruned after 90 days.

## Stage three: selling and buying

Planned 8 Oct 2026 (`b2b57e2`, decisions `95d1171`): selling through a
listing that takes months, an optional agency, income tax on the gain, and
resale value in the viability check.

### 17. Valuation calibration · 8 Oct 2026 · `c36e0d2`

What a buyer would pay for a café (premises, fit-out worn with the
equipment, and goodwill from the last year's profit after the owner's pay)
was fitted so that a café run the typical way sells, after a year, for
85–100% of its traspaso: listings are asking prices and buyers agree a
little below. Location share 0.5 → 0.4, goodwill 0.5 → 0.4 years of profit.
Result: 0.93 of the traspaso after a year, about 1.0 after 3 and 5 years.

### 18. Selling · 8 Oct 2026 · `32c18c4`

List the café at an asking price, privately or through an agency. Buyers
turn up at random (fewer when overpriced, in August and at Christmas), each
with a hidden top price around the café's value; they open about 10% below
it. Accept, reject or counter once; a buyer answers a counter overnight. An
accepted sale completes at the month end after a 30-day handover: the price
less agency commission, gestoría and IRPF on the gain (savings scale,
19–30%), plus the deposit back. Net worth now counts the café at what the
owner would walk away with. With the guessed rates, a café asked at its
value gets a first offer after about 7–8 weeks privately, 3 through an
agency.

### 19. Closing down and quick sale · 8 Oct 2026 · `dcc1088`

Two ways out without waiting for a buyer, both at the month end. Close down:
two months' rent as the lease's notice, staff severance of 20 days a year
(ET art. 53.1.b, with seniority inherited through the traspaso), the
equipment sold for scrap, the traspaso lost. Or sell to a buyer of last
resort at half the café's value.

### 20. Buying again · 8 Oct 2026 · `5d5d725`

A career: after a sale or closure, buy another café with the cash left (the
€30k cap applies only to a new career). Today's market, not the one from
the start: listings come and go week by week, and the café you sold stays on
the map as a possible rival. A career table lists every café you've run.

### 21. Balance for selling, and resale in the viability check · 8 Oct 2026 · `bb58b24`

The balance simulations gained a flipper (run a café a year, sell it, buy
another with everything). At first flipping beat holding one café, +86% vs
+67% over 5 years: a money machine. With realistic frictions (haggling, two
months between cafés, buying costs) it no longer does for a typical owner
(+46% vs +85%). A typical owner selling after a year loses about 11% of the
traspaso. Failing cafés now take the better of a quick sale and closing. The
viability report adds the year-5 resale value and the owner's total return.

## Stage four: real data and buying costs

Planned 8 Oct 2026 (`87dcbec`).

### 22. Public-source research pass · 8 Oct 2026 · `ea441c8`, `d5b2388`

Placeholders replaced with sourced figures:

| | Was | Now | Source |
|---|---|---|---|
| Waiter's wage | €1,300 × 14 (guess) | €1,375 × 14 + €1,359 October payment | Zaragoza hostelería agreement 2023–2025 |
| Employer Social Security | 31.5% | 32.15% | 2026 rates + 1.5% accident insurance (CNAE 56) |
| Full-time hours | 40 a week | 34.15 | 1,776 hours a year in the agreement |
| Self-employed fee | 6 rough bands | the 15 official bands, €200–590 | RDL 13/2022 |
| New owner's fee | — | €80 a month for 12 months | tarifa plana |
| Night trading | to 03:00 | to 02:00 | Zaragoza opening-hours ordinance |

IVA (10%) and the income-tax instalment (20%) were confirmed. Higher costs
nudged the demand setting `capture.base_rate` 0.091 → 0.093 to keep 20–25%
of typical new cafés failing in year 1, as INE/DIRCE report.

### 23. Buying costs · 8 Oct 2026 · `1221b94`

A traspaso costs more than its price: the legal deposit (2 months' rent,
LAU art. 36.1) and an extra guarantee (2 months, a guess), both returned on
leaving; the buyer's lawyer (€800 + 1%, a guess); the licence's change of
holder (€102.70 fee, a technical report, gestoría). Every listing shows
"cash needed": €45,400 for the median café, was €41,800.
`capture.base_rate` 0.093 → 0.094.

### 24. Market data intake · started 9 Oct 2026 · `5409ca4`

`docs/data-requests.md` lists what to gather, in aggregated form: how long
traspasos stay listed, new listings a month, agreed vs asking price, agency
fees, lease terms, buying and closing costs, café prices and busyness.
Waiting on the figures.

### 25. Price and quality response · not started

Waits on café prices and busyness (ordinary vs speciality) to recalibrate
how customers respond to price and quality.

### 26. Pedestrian counts · counting page 9 Oct 2026 · `4a88390`

A phone page (`/counts`): pin the spot, start a 10-minute timer, tap once per
passer-by, save. Only the spot, time and count are kept.
`geo:counts-export` adds them to the committed file that `geo:calibrate`
fits the footfall model to. Waiting on the counts.

### 27. Where every number stands · first pass 9 Oct 2026 · `f152eb4`, `1a012ca`, `05a3935`

Every section of the parameter sheet marked real (with source), fitted (to
which target), guess, or design. A sensitivity check moved each guess on its
own: three carry the calibration, each moving year-1 failure by 8–12 points
across a plausible range: what customers spend, how much nearby rivals take,
and how readily passers-by stop for a coffee. Those are exactly what
milestones 25 and 26 measure. Utilities, insurance and the terrace fee
barely matter. To repeat once the data lands.
