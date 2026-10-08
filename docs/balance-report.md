# Balancing pass (milestones 9–11, 17–22)

How the game plays out on the real Zaragoza data, and what was changed to get
there. Reproduce with:

```bash
php artisan market:balance --games=1000          # 1,000 games per strategy (about 70 s)
php artisan market:balance --games=200 --json=out.json   # 1,000 games in all, every outcome saved
```

The command plays whole games without the database, the way the app does:
generate the market from a seed, buy, pick rivals (nearest listings, topped up
from real cafés and bars), play 12 months, then value what's left. Each game
draws its starting capital (€20k–€100k, whole thousands) and its starting month
from the seed. Net worth = cash + the landlord's deposit + the business's value;
cash below zero ends the game with cash + deposit.

## Public sources (milestone 22)

Placeholders that public sources settle, cited next to each number in
`config/market/zaragoza_cafe.php`. Outside sites can't be opened from the
build environment, so these come from search summaries, cross-checked
between at least two sources; each says so and should be checked against the
official text once.

| Section | Was | Now | Source |
|---|---|---|---|
| `staff.gross_per_payment_cents` | €1,300 (guess) | **€1,375** | Zaragoza hostelería agreement 2023–2025 (BOPZ 84, 13/04/2024, in force by ultraactividad), Grupo II (camarero, cocinero), 2025 table |
| `staff.employer_social_security_rate` | 31.5% | **32.15%** | Orden PJC/297/2026: 23.60 + 5.50 + 0.20 + 0.60 + MEI 0.75, plus AT/EP for CNAE 56, 1.50 |
| `staff.full_time_hours_per_week` | 40 | **34.15** | the agreement's 1,776 hours a year ÷ 52: holidays and leave included, so a full-timer covers fewer opening hours, and part-time cover costs more an hour |
| `cuota_autonomo` | 6 rough bands | **the 15 RETA bands** (€200–590) | RDL 13/2022 schedule, 2025 cuotas (unchanged in 2026 apart from MEI) |
| `iva` | 10% | 10% (sourced) | LIVA art. 91.Uno.2.2º |
| `income_tax` | 20% | 20% (sourced) | RIRPF art. 110, modelo 130 |
| `licence_day_parts` / night | to 03:00 | **to 02:00** | Zaragoza ordinance: 06:00–01:30, an hour later on Fridays, Saturdays and holiday eves |

Still open from this pass: one source adds a fixed October payment (€1,359)
to the 14 payments, which would raise wage costs ~7%; the BOPZ text
settles it. The terrace fee (Ordenanza Fiscal 25: a basic tariff × a street
category factor, 40% off through the cafés' association) has no euro figure
in any summary. Cost of goods, utilities and insurance have no public
benchmark that could be found.

Costs rose (wages +6%, cover hours +17% dearer), so typical owners failed
more: day by day 26% in year 1 and thoughtful players 13%, just outside the
bands. `capture.base_rate` **0.091 → 0.095** brings them back:

| 1,000 games | Monthly | Daily | 5 years |
|---|---|---|---|
| Typical owner, failed in year 1 (20–25%) | 22% | 22% | 22% |
| Typical owner, open after 5 years (45–50%) | | | 50% |
| Thoughtful, failed in year 1 (≤ 12%) | 8% | 11% | 8% |

Every other target passes too (resale 93%, round trip −10%, flipping +52% vs
+89% holding).

**Follow-up, with the user's figures** (the same agreement and sources):

- The agreement's **October payment** (fixed €1,359.07) is on top of the 14:
  `staff.payments_per_year` 14 → **14.988** (14 + 1,359.07 ÷ 1,375). Wages
  +7%; severance follows.
- The **flat-rate cuota** for new self-employed owners (€80 a month for the
  first 12 months, LETA art. 38 ter): `cuota_autonomo.flat_rate_cents` and
  `flat_rate_months`. The game assumes the player qualifies; the second café
  of a career doesn't get it.
- Not modelled (about 2–3% of wages for a café open every day): €12.61 per
  Sunday and €25 per holiday worked, +25% after midnight.
- One discrepancy left as it is: the user's summary gives a minimum RETA base
  of €735.29 (cuota ~€225–230); the 2025 schedule, which other sources say
  is frozen for 2026, starts at €653.59 (€200). The TGSS table settles it.

With these, typical owners failed 20% (the bottom of the band), so
`capture.base_rate` **0.095 → 0.093**:

| 1,000 games | Monthly | Daily | 5 years |
|---|---|---|---|
| Typical owner, failed in year 1 (20–25%) | 22% | 22% | 22% |
| Typical owner, open after 5 years (45–50%) | | | 46% |
| Thoughtful, failed in year 1 (≤ 12%) | 8% | 11% | 8% |

Resale 95%, round trip −8%, flipping +58% vs +73% holding: all pass.

## Selling, flipping and failed cafés (milestone 21)

Three changes to `market:balance`, all at 1,000 games:

1. **A café closed for not paying its owner now gets out the way a real
   owner would:** the better of a quick sale (half its value, after costs and
   tax) and closing down (notice, severance, scrap). Before, it was valued as
   a private sale at its full value, which a failing café wouldn't fetch
   quickly. Typical owners' worst outcomes fall: p10 −36% → **−47%** after a
   year; the median doesn't move (+26%).
2. **Round trip:** a typical owner who sells after a year, without having
   improved the café, gets back **11%** less than the traspaso after the
   gestoría and tax (13% day by day). Target: a 5–25% loss. ✓
3. **Flipping:** a flipper runs a café for a year, sells it, and buys another
   with everything, for 5 years. At first, with selling and buying free and
   instant, flipping beat holding (typical owner +86% vs +67%, 300 games): it
   was a money machine, from trading up with the profits at no cost. Real
   frictions, all **guesses** (`changing_cafe` in the sheet), fix that: the
   sale is haggled (buyers open 10% below their limit; a counter wins half of
   it back), the owner lives off savings for **2 months** between cafés, and
   buying costs **€1,800** (the buyer's gestoría and the licence's change of
   holder).

| 5 years, median net worth | Holding one café | Flipping every year |
|---|---|---|
| Typical owner | +85% | **+46%** ✓ |
| Thoughtful | +173% | +192% |

The thoughtful player still gains a little by flipping: they pick cafés
priced at under a year's profit, and selling each year cashes in that bargain.
That's an information edge a skilled buyer has, not a loophole, so it's
reported, not targeted.

**The viability report** now ends with what the café would sell for after 5
years (median and 8-in-10 range, before and after costs and tax, for the
futures where it's still open), and the owner's total return over every
future: net worth at the end or at closing, against the money put in, after
paying themselves.

## Closing down and quick sale (milestone 19)

Two ways out without waiting for a buyer, both on the month's last day:

- **Closing down:** two months' rent for the lease's notice or break penalty
  (a guess: commercial leases set their own, LAU art. 4); severance of 20
  days' gross pay per year worked for each employee (ET art. 53.1.b; closing
  is an objective dismissal), counting 2 years' seniority inherited with the
  traspaso (ET art. 44; the 2 years are a guess); the equipment sells for
  20% of the fixtures' value (a guess). The traspaso is lost; the deposit
  comes back.
- **Quick sale:** a buyer of last resort pays half the café's value (a guess),
  never less than the scrap value, with the usual gestoría and tax.

For a typical small café closing after a few months, closing costs more
than the scrap brings in; a quick sale usually leaves more, which is the
point of it. Since milestone 21, `market:balance` takes the better of the two for a café
closed for not paying its owner.

## Selling (milestone 18)

A listed café gets buyers at random (`config/market/zaragoza_cafe.php`,
`sale`). Each buyer's limit is the valuation give or take 15%; they open 10%
below it and never above asking. **All of these are guesses**: there's no
data yet on how long Zaragoza traspasos stay listed or how far agreed prices
fall below asking. With them, a café asked at a given multiple of its value
gets its first offer after (2,000 simulated listings, from April):

| Asking ÷ value | Privately: first offer (median, p25–p75) | Agency | Offer ÷ asking |
|---|---|---|---|
| 1.00 | 53 days (21–107) | 21 days (9–43) | 0.90 |
| 1.15 | 81 days (34–189) | 34 days (14–68) | 0.78 |
| 1.30 | 153 days (54–299) | 56 days (22–113) | 0.70 |

The handover then takes 30 days and completes at that month's end, so a
sale at a fair price takes about three to five months from listing, longer
when overpriced or in August. Costs: an agency takes 8% (minimum €3,000), the
gestoría €800, and the gain over the traspaso paid is taxed at the IRPF
savings scale (19–30%; LIRPF art. 76). Selling a whole going business isn't
subject to IVA (LIVA 7.1).

Net worth now counts the café at what a private sale at its value would
leave after these costs and the tax, in the game and in `market:balance`.
Medians fall 1–3 points; every target still passes at 1,000 games (monthly,
daily and 5 years).

## Resale value (milestone 17)

Stage three lets owners sell, so what a café would sell for
(`BusinessValuation`) has to be realistic. A buyer pays for the premises
(location and licence, plus the fit-out worn down with the equipment) and for
goodwill (the last 12 months' profit after the owner's pay, weighted by
reputation). The listed traspasos the market is calibrated to are typical
owners' asking prices, so the check is: **a café run as a typical owner runs it
should, a year on, sell for a little under its traspaso** (buyers agree below
asking; 0–15% is a guess until there's data on agreed prices).

`market:balance` now values every café at the end of each year it reaches and
prints the value ÷ the traspaso paid. With the old placeholders (location share
0.5, goodwill 0.5 years of profit) a typical owner's café was worth 1.08× its
traspaso after a year: more than they paid, for running it as the seller did.
Fitted values: **location share 0.4, goodwill 0.4 years** (fixtures share 0.3
unchanged).

| 1,000 games, monthly, 5 years | Year 1 | Year 3 | Year 5 |
|---|---|---|---|
| Typical owner (default) | 0.93 (0.64–1.53) | 1.00 (0.66–1.70) | 1.01 (0.65–1.76) |
| Thoughtful | 1.24 (0.68–2.16) | 1.22 (0.67–2.18) | 1.20 (0.66–2.19) |

Median (p10–p90) of the cafés that reached that year end, closing or not.
Day by day the typical owner's year-1 figure is 0.89. A café that doesn't pay
its owner sells for the premises alone, about 0.55–0.7 of its traspaso (a
guess). Thoughtful players buy cafés priced at under a year's profit, so theirs
are worth more than they paid.

| Target | Monthly | Daily | |
|---|---|---|---|
| Typical owner: after a year the café sells for 85–100% of its traspaso (median) | 93% | 89% | pass |
| Typical owner: 20–25% fail in year 1 | 23% | 23% | pass |
| Typical owner: 45–50% still open after 5 years | 49% | | pass |
| Thoughtful: 12% or fewer fail in year 1 | 9% | 11% | pass |

The closure targets don't depend on the valuation (they count cash and the
owner's pay), but net worth medians fall a few points. Note: runs of the
default 200 games can land a point outside the closure bands (26% failing, or
thoughtful 15% day by day); the targets are checked at 1,000 games.

Still guesses: the asking-to-agreed discount, the premises-only value, and
the reputation weight. Agreed prices (from agencies or gestorías), even as
tiers, would replace them.

## Day by day (milestone 11, current)

```bash
php artisan market:balance --games=1000 --daily      # the day-by-day engine the game uses (about 10 minutes per strategy)
```

The game now trades one day at a time (`DayEngine`). A month's trade is
shared out between its days, per day part, by:

- **weekday** (`day_of_week`): Friday and Saturday busiest, Monday the
  quietest, Sunday strong at lunch; evenings and nights swing the most
  (Saturday night is about five times a Monday night). **A guess**, shaped
  on Spanish card-spending by weekday; to check against card-spend data or
  counts.
- **public holidays** (`holidays`): national, Aragón (San Jorge) and
  Zaragoza (San Valero, Cincomarzada), plus Holy Thursday and Good Friday
  from the date of Easter. They trade like a Sunday.
- **the Pilar** (`pilar`): the nine days ending on the first Sunday on or
  after 12 October (2023: 7–15, 2024: 5–13, 2025: 4–12, 2026: 10–18).
  October's seasonality (1.15) was mostly the Pilar; with the fiesta
  weights (×1.3 mornings to ×2 nights, **a guess**) the rest of October
  trades like an ordinary month.
- **weather** (`weather`): rain days are AEMET's normals for Zaragoza
  Aeropuerto, 1981–2010 (days with ≥ 1 mm: 4.0, 3.9, 3.7, 5.7, 6.4, 4.0,
  2.6, 2.3, 3.2, 5.4, 5.1, 4.8 from January), read from a search summary
  because aemet.es is blocked here: **check them on the AEMET page**. Hot
  days (≥ 35 °C: 2 in June, 8 in July, 6 in August, 1 in September) and the
  effects (rain: −5% mornings to −15% evenings; heat: afternoons −25%,
  evenings +15%) are **guesses**. Rain closes the terrace; dry days open it
  often enough to give `terrace_usable_days`.
- **day-to-day noise** (`daily.noise_sd` 0.10, **a guess**) on top of the
  month's.

Each day part's weights are divided by their average over the month, and
weather effects by the month's expected weather, so the days of a month
add up to what the monthly engine gives: **seasonality, calibrated
before, stays as it was.**

Takings and the stock sold come in daily; rent, wages, utilities,
marketing, the cuota, the owner's pay and the other monthly costs are
settled on the month's last day, and bankruptcy is checked then. Events
roll daily with their monthly odds spread over the month
(1 − (1 − p)^(1/days)), once per type per month; their lasting effects
start the next day and are counted in days. Reputation and morale move on
open days at the daily rate that adds up to the monthly one; equipment
wears a share each day. Rivals, the street's drift and equipment age move
at month end, with the same random streams as the monthly engine.

**Which days a café closes.** Open fewer than seven days, a café closes
its quietest weekdays for the day parts it opens (Monday for a daytime
café), and its open days are a little busier than average. The monthly
engine now counts this too (`TradingCalendar::weekdayFactor`), so both
engines agree. It moved the monthly results slightly (1,000 games per
strategy, same seeds):

| Strategy | Before: median | Failed in year 1 | After: median | Failed in year 1 |
|---|---|---|---|---|
| thoughtful | +54% | 11% | +58% | 9% |
| default (typical new owner) | +32% | 23% | +34% | 23% |
| cheapest | −32% | 88% | −31% | 87% |
| premium | +162% | 3% | +171% | 3% |

Typical owners are unchanged, so `capture.base_rate` stays at 0.091. Five
years, monthly: 77% of typical owners open after year 1 and **49% after
5** (target 45–50%), thoughtful 63%.

### Do the days add up to the month?

With the same seeds, over every month of a year (`DayEngineTest`):

- **Demand matches within 1–2%.** What's left is the real calendar: a
  month with five Mondays has one day fewer open for a café closed on
  Mondays than the monthly engine's average, and holidays move trade
  to Sunday-like days.
- **Covers and revenue come out 1–2% lower** for a typical café (about
  0.5% with plenty of seats and staff). Each day is capped by its own seats
  and staff, so a busy Saturday or a Pilar evening turns people away even
  when the month as a whole wouldn't. Measured on the test café: day-to-day
  noise accounts for about 0.7 points, the weekly rhythm 0.5, the calendar
  0.6; weather hardly any. This is the more realistic of the two, so the
  monthly engine wasn't changed to match it.

### Targets on the daily engine (1,000 games per strategy)

| Strategy | Median net worth | Ahead | Bankrupt | **Failed in year 1** |
|---|---|---|---|---|
| thoughtful | +52% | 85% | 1.2% | **11%** |
| default (typical new owner) | +30% | 70% | 3.8% | **23%** |

(400 games of each of the other strategies: cheapest 89% failed, premium
2%, careless 100%.)

| Target | Actual | |
|---|---|---|
| Typical new owner (default settings): 20–25% fail in year 1 | 23% | pass |
| Thoughtful player: 12% or fewer fail in year 1 | 11% | pass |
| Thoughtful player: median net worth doesn't fall | +52% | pass |
| Thoughtful player: no district (10+ games) where over 30% fail | 27% (Casco Histórico) | pass |
| Thoughtful beats default settings by 5+ points (median net worth) | +22 points | pass |
| Careless player: 90% or more fail | 100% | pass |

Five years on the daily engine (600 games each, `--years=5 --daily`, about
40 minutes):

| Strategy | Year 1 | Year 2 | Year 3 | Year 4 | Year 5 |
|---|---|---|---|---|---|
| thoughtful | 88% | 80% | 73% | 66% | 61% |
| **default (typical new owner)** | **76%** | 67% | 59% | 54% | **47%** |

Typical owners: 24% fail in year 1, 47% still open after 5 years: both
targets pass. **One target is borderline on the daily engine:** the worst
district for the thoughtful player. Casco Histórico, the most crowded
district, lost 27% of 41 games in year 1 in the 1,000-game run (pass) and
33% of 21 in the 600-game run (fail, the limit is 30%). The samples are
small (about ±10 points), and the monthly engine gives 20%: the daily
engine's busy-day limits bite hardest where competition already squeezes
margins. Left as it is; worth watching when real counts for the old town
arrive.

The SPEC §6 balance tests now run on both engines.

### Still guesses

The weekday weights, the Pilar weights, hot days, weather effects and the
daily noise are guesses (`php artisan market:placeholders`). They only move
trade between days, so they can't shift the yearly calibration, but they
decide how lumpy a month is and so how many busy-day customers a café
turns away. Rain days need checking on aemet.es.

## Five years (milestone 10)

```bash
php artisan market:balance --games=1000 --years=5    # about 100 s
```

Games now run for any number of years. A café **closes** when its cash runs
out, or at the end of the first year (counted from purchase) in which it
didn't earn enough to pay its owner. The target is the INE/DIRCE figure for
food and drink businesses: 50–55% close within 5 years, so **45–50% of typical
new owners must still be open after 5 years**, as well as 75–80% after one.

What changed:

1. **Typical owners repair broken equipment** when they can afford it
   (keeping three months of their pay in hand), as do thoughtful and premium
   players. Never answering the breakdown event let a café wear out over the
   years; only 36% lasted 5 years. With repairs, 63% did: equipment matters
   a lot over five years.
2. **The street's fortunes drift** (`demand.local_trend`, new): a lasting
   random walk in each spot's custom, so over the years some streets gain
   offices, shops and residents and others lose them. Without it, a café that
   survived year one almost never closed later, since its income barely
   varied from year to year. Its spread, **24.5% a year**, is calibrated to the
   5-year survival figure, so it also stands in for closures the model doesn't
   simulate (the owner's health, family or burnout, rent rises at lease
   renewal). It is stored with each game's state.
3. **Rival turnover** was tested at higher rates (4–6% a month) and moved
   survival by only a couple of points, so it was left as it was.

### Still open at the end of each year (1,000 games per strategy)

| Strategy | Year 1 | Year 2 | Year 3 | Year 4 | Year 5 |
|---|---|---|---|---|---|
| thoughtful | 89% | 80% | 74% | 66% | 60% |
| **default (typical new owner)** | **77%** | 66% | 59% | 53% | **47%** |
| premium | 97% | 91% | 84% | 79% | 72% |
| cheapest | 12% | 5% | 3% | 2% | 1% |
| careless | 0% | 0% | 0% | 0% | 0% |

Typical owners close fastest in year one (23%), then at 10–14% a year, which is
the shape of real survival curves.

| Target | Actual | |
|---|---|---|
| Typical new owner (default settings): 20–25% fail in year 1 | 23% | pass |
| Typical new owner: 45–50% still open after 5 years | 47% | pass |
| Thoughtful player: more still open after 5 years than typical owners | 60% | pass |
| Thoughtful player: 12% or fewer fail in year 1 | 11% | pass |
| Thoughtful player: no district (10+ games) where over 30% fail | 20% | pass |
| Careless player: 90% or more fail | 100% | pass |

The 1-year targets still hold in 1-year runs (typical owners 23% fail,
thoughtful 11%). Net worth after five years is large for survivors (typical
owner median +86%), since a café that lasts keeps earning; the premium
strategy remains too strong (see below).

## Calibrated to real closure rates and listing prices

The game aims to be a realistic simulation, so it is calibrated to real data
rather than to invented targets:

- **1-year mortality:** about 20–25% of new cafés and bars close within their
  first 12 months (INE via DIRCE; Hostelería de España).
- **Density:** Zaragoza has about 3,000–3,500 hospitality establishments
  (IAEST; Asociación Café Bares de Zaragoza). OpenStreetMap maps 2,152 of them,
  about 65%.
- **Listing prices:** a sample of Zaragoza café and bar traspaso listings
  (October 2026) with the market tiers around it. Only the resulting
  distributions are stored, never the listings themselves:

  | Tier | Traspaso | Rent a month | Typical premises |
  |---|---|---|---|
  | Low | €10–30k | €400–700 | 25–50 m², neighbourhood bars, coffee takeaways |
  | Mid | €40–80k | €800–1,800 | established cafés in active districts |
  | Prime | €90–250k+ | €2,500 and up | prime pedestrian streets |

  A kitchen with a smoke outlet (salida de humos, often impossible to add in a
  residential building) and a terrace permit raise the asking price.

What changed:

1. **The owner takes €1,200 a month to live on** (`owner.pay_month_cents`),
   about the minimum wage after tax. It isn't a business cost (profit and
   taxes are before it), but it leaves the cash every month. The P&L shows
   "Your pay" and "Left after your pay"; goodwill in the business valuation
   counts only profit after the owner's pay.
2. **Failure counts what the statistics count:** a café fails in year 1 if its
   cash runs out *or* over the year it doesn't earn enough to pay its owner
   (a real owner would close or sell up). The end-of-game screen says whether
   the café paid its way.
3. **Competition density is corrected for OpenStreetMap's coverage**
   (`geo.osm_coverage.hospitality` 0.65): densities are scaled up by 1/0.65.
4. **Prices from the listings:** traspaso median €18k → **€40k** (0–100th
   percentile €6k–€250k), rent median €725 → **€900** (€350–€8,000), floor area
   35–90 m² → **25–220 m²**. The asking price follows location (0.85), then
   condition, size, a full kitchen and a terrace.
5. **Demand recalibrated:** `capture.base_rate` 0.040 → **0.091**, so that a
   typical new owner (a random affordable café on default settings) fails
   20–25% of the time. With the owner paid and the old demand, 81% failed.

### Results: 1,000 games per strategy

| Strategy | Median net worth | Ahead | Bankrupt | **Failed in year 1** | Profit/month before your pay | Traspaso paid | Traspaso ÷ a year's profit after pay |
|---|---|---|---|---|---|---|---|
| thoughtful | +45% | 84% | 1.0% | **11%** | €2,966 | €16,500 | 0.7 |
| default (typical new owner) | +24% | 68% | 3.8% | **23%** | €2,496 | €21,000 | 1.2 |
| cheapest | −32% | 8% | 10.0% | **90%** | −€258 | €6,500 | — |
| premium | +128% | 94% | 1.3% | **3%** | €6,083 | €38,500 | 0.6 |
| careless | −106% | 0% | 99.0% | **100%** | — | — | — |

(Medians. Players start with €20k–€100k, so most buy below the market's
median traspaso.)

| Target | Actual | |
|---|---|---|
| Typical new owner (default settings): 20–25% fail in year 1 | 23% | pass |
| Thoughtful player: 12% or fewer fail in year 1 | 11% | pass |
| Thoughtful player: median net worth doesn't fall | +45% | pass |
| Thoughtful player: no district (10+ games) where over 30% fail | 24% (worst: Casco Histórico) | pass |
| Thoughtful beats default settings by 5+ points (median net worth) | +20 points | pass |
| Careless player: 90% or more fail | 100% | pass |

A typical owner's traspaso comes to about 1.2 years of profit after their pay,
in line with the usual Spanish rule of thumb of 1–2 years.

### Still unrealistic: the premium strategy

Buying the busiest affordable spot and running it upmarket (premium quality,
prices 15% above average) almost never fails and gains +128%. Busy spots now
cost much more, but the premium settings themselves are too generous: the
better product and higher prices raise revenue per customer by about 30% while
the 15% price rise loses only a few percent of customers. How customers in
Zaragoza respond to quality and price (`capture.price_elasticity`,
`capture.quality`, `ticket_position.tier`) needs real data, such as what
speciality and upmarket cafés charge and how busy they are next to ordinary
ones.

## History: the first balancing pass

Everything below is the first pass, before the owner's pay and the closure-rate
calibration. Its figures are superseded by the section above, but the changes
it made still stand.

## Strategies

| Key | Player |
|---|---|
| `thoughtful` | Keeps 20% of capital as working capital. Buys one of the 5 affordable spots with the most footfall for the money (footfall^0.8 × seats ÷ (rent + traspaso/48)). Opens for its 3 busiest day parts. Each month closes a day part that costs more than it brings in, hires when turning >10% of customers away, lets one go when the floor is under 40% busy. |
| `default` | Random affordable café, the game's default settings all year. |
| `cheapest` | Cheapest traspaso, default settings with one employee. |
| `premium` | Keeps 10%. The busiest affordable spot, premium quality, prices 15% above average, 3 staff, €300 a month on marketing, its 4 busiest day parts. |
| `careless` | Random café; prices 50% above average, budget stock, 6 staff, open all hours 7 days, €2,000 a month on ads. |

## Results: 1,000 games per strategy

Net worth change over the year, as a share of starting capital.

| Strategy | p10 | p25 | Median | p75 | p90 | Ahead | Bankrupt | Profit/month (median) |
|---|---|---|---|---|---|---|---|---|
| thoughtful | −18% | −3% | **+20%** | +51% | +88% | 72% | 1.1% | €923 |
| default | −43% | −22% | **−5%** | +17% | +40% | 44% | 4.2% | €184 |
| cheapest | −85% | −55% | **−36%** | −25% | −15% | 2% | 8.4% | −€1,801 |
| premium | −46% | −16% | **+27%** | +84% | +143% | 66% | 7.5% | €1,574 |
| careless | −119% | −112% | **−107%** | −102% | −99% | 0% | 100% | −€10,074 |

### Targets

| Target | Actual | |
|---|---|---|
| Thoughtful player: median year between +5% and +25% | +20% | pass |
| Thoughtful player: at least 60% end up ahead | 72% | pass |
| Thoughtful player: under 5% go bankrupt | 1.1% | pass |
| Thoughtful player: no district (10+ games) with a median below −10% | 0% (worst) | pass |
| Default settings: median year between −10% and +10% | −5% | pass |
| Thoughtful beats default settings by 5+ points (median) | +25 points | pass |
| Careless player: median year below −20% | −107% | pass |

The command prints the same table each run. A second set of seeds (`--seed=10001`)
gave the same picture within sampling noise (±5 points on a 200-game median).

### Thoughtful player by district

| District | Games | Median | Ahead | Bankrupt |
|---|---|---|---|---|
| Universidad | 114 | +37% | 89% | 0% |
| Las Fuentes | 39 | +41% | 77% | 2.6% |
| Actur-Rey Fernando | 74 | +29% | 70% | 1.4% |
| San José | 115 | +23% | 73% | 0.9% |
| Sur | 20 | +20% | 75% | 0% |
| Centro | 115 | +17% | 68% | 0.9% |
| Delicias | 252 | +17% | 73% | 0.8% |
| Torrero-La Paz | 59 | +16% | 69% | 0% |
| La Almozara | 26 | +14% | 65% | 11.5% |
| El Rabal | 47 | +11% | 74% | 0% |
| Oliver-Valdefierro | 14 | −1% | 50% | 0% |
| Casco Histórico | 118 | +1% | 53% | 1.7% |

(Miralbueno and Santa Isabel had under 10 games.)

### Thoughtful player by footfall at the spot

| Footfall | Games | Median | Ahead |
|---|---|---|---|
| 2–4 | 184 | +3% | 55% |
| 4–6 | 391 | +19% | 69% |
| 6–8 | 330 | +24% | 78% |
| 8–10 | 93 | +29% | 88% |

### Thoughtful player by starting capital

| Capital | Median | Ahead |
|---|---|---|
| €20–40k | +36% | 66% |
| €40–60k | +24% | 75% |
| €60–80k | +16% | 69% |
| €80–100k | +13% | 73% |

Monthly profit doesn't grow much with capital (a careful player buys a similar
café either way), so the same profit is a bigger share of a smaller stake.

## What was wrong, and what changed

The first run on the real data, before any change:

| Strategy | Median | Bankrupt |
|---|---|---|
| thoughtful | +48% | 1% |
| default | −28% | 21% |
| premium | +107% | 4% |

1. **Location wasn't priced in.** Rent and traspaso followed footfall only
   weakly (correlation 0.5 and 0.45), so the busiest streets rented for about
   €1,000 a month and buying the busiest affordable spot won almost every game.
   → `rent.correlation.location` 0.5 → **0.85**, `traspaso.correlation.location`
   0.45 → **0.8**.
2. **Two employees by default.** At about €2,000 each a month, two staff cost
   more than a typical café brings in, and one is enough to serve its
   customers. → `default_decisions.staff_count` and `takeover.staff_count`
   2 → **1**.
3. **Goodwill counted the year's profit twice.** The valuation added 0.75 years
   of profit on top of the cash it had already brought in, widening every gap.
   → `valuation.profit_multiple_years` 0.75 → **0.5**.
4. **Opening longer was always worth it.** Wages didn't depend on opening
   hours: staff were spread thinner, below one person on the floor, at no cost.
   Even a quiet café gained about €580 a month by opening evenings, against
   SPEC §6 ("a day part with little local demand should cost more in wages and
   utilities than it brings in"). → New rule: every open hour needs
   `staff.min_on_shift` people (1.0); hours the owner and staff can't cover are
   paid as part-time cover at the hourly staff cost (`Staffing`,
   `MonthlyCosts::coverCents`). The decisions screen shows this cost. An evening
   at a quiet café now roughly breaks even; at a busy one it still pays.
5. **Overall level.** → `capture.base_rate` 0.0455 → **0.040**, which puts the
   thoughtful player's median near +20%.
6. **Morning and afternoon tickets were too high** (€3.50–€5.00, a full
   breakfast). Most people just have a coffee at the bar, about €1.50 in
   Zaragoza. → `average_ticket_cents` morning **€1.80–€3.00**, afternoon
   **€2.00–€3.50**. A cheap coffee is an easy stop, so more passers-by come
   in: a new per-day-part `stop_factor` (morning **1.8**, afternoon **1.55**,
   others 1.0) multiplies potential customers, and morning seat turnover is
   **2.5** an hour (many drink standing at the bar). Morning revenue is about
   what it was, from roughly twice the customers each spending half as much,
   so the balance above holds.

The SPEC §6 balance tests were brought in line with the real game:

- Their rivals are now five cafés at the median distances measured on the
  Zaragoza surface (42–134 m around an average spot, 50–222 m around a quiet
  one, 40–91 m around a busy one), instead of three at 120–300 m.
- "Average decisions" are the game's defaults (one employee).
- "Good management" at a quiet spot opens through the afternoon. With one
  employee costing the same whatever the hours, closing after lunch only loses
  revenue.

All five SPEC tests pass: average business −10%…+25% (median about −5%, 90% of
years in range), great location with bad management loses money, mediocre
location with good management survives, +50% prices lose revenue by month 3,
and no single event bankrupts a player with over €5k.

## Observations

- **Location matters, but isn't everything.** Results rise steadily with
  footfall (+3% at 2–4, +29% at 8–10) without the busiest spots running away.
- **The historic centre is the hardest.** Casco Histórico has 105 cafés and
  bars per built-up km² and the most competitive streets; a careful player
  only breaks even there (median +1%). Centro does better (+17%).
- **Premium is high risk, high reward.** A similar median to the thoughtful
  player, but a much wider spread and 10% bankruptcies.
- **The cheapest cafés are traps.** The cheapest traspasos sit on near-empty
  streets (median footfall 0.7) and lose about €1,800 a month.
- **Careless play always fails.** Everyone goes bankrupt, half of them within 4–5 months.

## Still placeholders

Every number in `config/market/zaragoza_cafe.php` is still a placeholder (see
`php artisan market:placeholders`), so this balance holds for the current
guesses. When real figures arrive (rents, traspasos, wages, average tickets,
pedestrian counts), rerun `market:balance` and retune `capture.base_rate`
first: it moves every strategy together.

## Unclear in SPEC

- §6 asks that a quiet day part "cost more in wages and utilities than it
  brings in" but doesn't say how wages relate to opening hours. This pass
  reads it as a minimum of one person on the floor every open hour, with
  part-time cover for the gap.
- §1 measures success as net worth change on the starting capital. Since
  capital ranges 5× (€20k–€100k), the same café gives very different
  percentages. A capital-independent score (say, profit over the year) may be
  fairer to show on the end screen.
- §11 (daily simulation) doesn't say which days a café open fewer than
  seven days closes. Milestone 11 closes its quietest weekdays for the day
  parts it opens (public holidays count as Sundays); the player can't pick
  them yet.
- §11 asks that "the days add up to what the monthly engine produced".
  Demand does; covers can't exactly, because a day can be full when the
  month isn't. The tests allow for this (1–5% fewer covers, depending on
  how near capacity the café runs).
- §11 doesn't say when the stock is paid for. The daily engine pays it out
  of each day's takings (with one-off event costs), and everything else at
  month end.
- Until the real-time clock (milestone 12), events waiting for a choice are
  settled on the first day of the next month, as before.
