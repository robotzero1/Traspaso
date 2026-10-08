# Business Simulator — MVP Spec

> Working title. A web-based economic strategy game: take over a small business in a real Spanish city and try to make it work.

## 1. Premise

> Stage two (§11) makes the game real time (one game day per real day, no fixed end) and adds a standalone viability check. This section describes stage one.

The player starts with a fixed amount of capital (€20k–€100k, chosen at setup) in **Zaragoza**, browses businesses available for *traspaso*, buys one, and runs it month by month for **12 months**. The goal is to finish with more net worth than they started with, measured as cash plus business value minus debts.

The businesses are **fictional**. Their starting parameters are generated from **statistical distributions based on real market research**. They sit on a **real map** built from open data.

> *Business locations, financial data and operating characteristics are simulated. Real-world market data is used to establish realistic ranges and distributions.*

## 2. MVP scope

**In scope**

- One city: Zaragoza
- One business category: café / café-bar
- 100–200 generated businesses for sale
- Starting capital chosen at setup
- 12-month game, advanced one month per turn
- Monthly decisions: prices, opening hours (which day parts to open for, and days per week), staffing level, marketing spend, quality tier
- Simple monthly P&L and running cash balance
- Random events (equipment failure, inspection, staff quits, heatwave, local festival…)
- AI competitors nearby, with simple behaviour
- Map view (Leaflet + OSM tiles) showing businesses and points of interest
- Game over if cash goes below zero and can't be covered; option to sell the business at month 12

**Out of scope for the MVP**

- Multiplayer or shared markets
- Other cities or business types (the engine should not prevent them, though)
- Loans beyond a single simple start-up loan option
- Accounts/auth beyond Laravel's starter kit
- 3D or animated graphics
- Any data from Idealista, Fotocasa, Milanuncios or Google Places stored in the database

## 3. Stack

- **Laravel 13** (PHP 8.3+)
- **Inertia + React + TypeScript** (Laravel React starter kit)
- **Tailwind**
- **Leaflet** with OpenStreetMap tiles for the map, with the required OSM attribution shown
- **Recharts** (or similar) for cash-flow and P&L charts
- **SQLite** for development and tests, MySQL/Postgres in production
- **Pest** for tests

## 4. Architecture

```
app/
  Simulation/            ← pure PHP; no Eloquent, no DB, no facades
    Engine.php           ← simulateMonth(BusinessState, Decisions, MarketContext, Rng): MonthResult
    Demand/              ← footfall → customers → covers
    Costs/               ← staff, COGS, rent, utilities, taxes
    Events/              ← random event definitions + resolver
    Competitors/         ← competitor behaviour
    Valuation/           ← business value for sale at end
    Rng/SeededRng.php    ← deterministic RNG; every game has a seed
    Data/                ← readonly DTOs (BusinessState, Decisions, MonthResult, …)
  Generation/
    BusinessGenerator.php  ← draws businesses from distributions in config/market/
  Models/                ← Eloquent: persistence only
  Http/Controllers/      ← thin; map models ↔ DTOs, call the engine
config/market/
  zaragoza_cafe.php      ← parameter sheet (section 7)
database/seeders/
  geo/                   ← committed OSM/Catastro extracts (GeoJSON/CSV)
```

**Rules**

1. `app/Simulation` never touches the database or Laravel facades. It takes DTOs in and returns DTOs out.
2. All randomness goes through `SeededRng`. The same seed and the same decisions must always produce the same results, and tests depend on this.
3. Every tunable number lives in `config/market/*.php`, not hard-coded in the engine.
4. All money is stored as integer cents.

## 5. Data model

Each field is tagged by where its value comes from:

- **[real]**: from open data (OSM, Catastro, INE, Zaragoza open data)
- **[research]**: drawn from distributions built on manual market research
- **[sim]**: generated or evolved by the game

### neighbourhoods
| field | source | notes |
|---|---|---|
| name, geometry | [real] | Zaragoza open data / OSM boundaries |
| population | [real] | INE / municipal padrón |
| student_index 0–10 | [real→derived] | distance to university campuses, density of student housing |
| tourist_index 0–10 | [real→derived] | proximity to the Pilar, the old town and OSM tourism POIs |
| office_index 0–10 | [real→derived] | OSM office/commercial density |
| transport_index 0–10 | [real→derived] | tram/bus stops within 300 m |
| competition_density | [real→derived] | OSM `amenity=cafe|bar` count per built-up km² (grid cells with a city's worth of streets, so farmland inside a district boundary doesn't dilute it) |

### points_of_interest
`id, type (university|school|station|park|office|competitor_seed…), name, lat, lng, osm_id` — [real], imported from OSM extracts.

### footfall_points
A precomputed footfall surface, sampled at points along streets (about every 25 m), [real→derived]:
`id, lat, lng, osm_way_id, neighbourhood_id, poi_score, centrality_score, catchment_score, transport_score, footfall 0–10, footfall_<day part> 0–10 (one per day part), commercial (bool)`.
`commercial` marks points with shops or hospitality on the street nearby; businesses are only placed on those, so only commercial points are stored.

### businesses
| field | source |
|---|---|
| id, fictional_name | [sim] |
| neighbourhood_id, lat, lng | [real] location: a commercial street point from `footfall_points` |
| category (`cafe`, `cafe_bar`) | [research] |
| floor_area_m2 | [research] |
| indoor_seats, terrace_seats | [research] |
| rent_month_cents | [research], adjusted by neighbourhood |
| traspaso_cents | [research], adjusted by footfall and condition |
| licence (`cafe`, `cafe_bar`, `bar_musical`) | [research] |
| kitchen (`none`, `basic`, `full`) | [research] |
| condition 1–10, equipment_age_years | [sim] |
| footfall 0–10, footfall per day part | [real→derived] read from `footfall_points` at the business's location (see §8 Footfall estimate). Until milestone 8: neighbourhood indices + street type |
| base_reputation 0–100 | [sim] |
| status (`for_sale`, `owned_by_player`, `competitor`) | [sim] |

### games
`id, user_id, seed, starting_capital_cents, current_month (1–12), start_date, cash_cents, status (active|bankrupt|finished), business_id nullable`

### game_business_states
A snapshot per game per month: `reputation, staff_count, staff_morale, equipment_health, stock_quality, price_level, open_day_parts, open_days_per_week, marketing_spend_cents, …`

### month_results
`game_id, month, customers, revenue_cents, cogs_cents, staff_cents, rent_cents, utilities_cents, marketing_cents, other_cents, taxes_cents, profit_cents, cash_after_cents, events_json`

### events
`game_id, month, type, payload_json, choice nullable` — events can offer a choice, e.g. repair now for €1,400 or limp on with reduced capacity.

## 6. Engine — monthly loop

```
simulateMonth(state, decisions, context, rng) -> MonthResult

1. Seasonality & weather   month → demand multiplier; terrace usable-days
2. Potential customers     for each open day part:
                             footfall for that day part × seasonality
                             (until milestone 8: footfall × neighbourhood indices
                             weighted by that day part's demand mix)
3. Capture rate            f(reputation, price vs. local average, quality, marketing,
                             competitor attractiveness)
4. Covers                  per day part: min(potential × capture,
                             capacity × turnover × open_days)
5. Revenue                 Σ day parts: covers × that day part's average ticket
                             (adjusted by price level)
6. Costs                   COGS % (by quality tier), staff and utilities (scaled by
                             hours open), rent, cuota de autónomo, marketing,
                             insurance, maintenance; then the owner's own pay
                             leaves the cash (not a business cost)
7. Events                  roll each event's probability; apply its effects
8. State evolution         reputation moves toward (quality − price gap + service);
                             equipment wears; staff morale reacts to workload;
                             the spot's custom drifts (a lasting random walk)
9. Competitors             each nearby competitor adjusts price/quality
10. Return MonthResult      full breakdown for the UI
```

Each step is its own small class with its own unit tests. `Engine` only orchestrates them.

### Day parts

Opening hours are a choice of **which parts of the day** to open for, not a number of hours. Each day part draws a different crowd, so the right hours depend on the location:

| day part | typical trade | main demand drivers |
|---|---|---|
| `morning` | desayuno, almuerzo | office, transport, student |
| `lunch` | menú del día, vermut | office, tourist |
| `afternoon` | merienda, coffee after lunch | student, resident population |
| `evening` | cañas, tapas, cena | tourist, resident population |
| `night` | copas after midnight | tourist, student; needs a `cafe_bar` or `bar_musical` licence |

- The player picks any non-empty set of day parts, with gaps allowed (a split shift), plus open days per week.
- Each day part's hour span (for staff and utility costs), demand mix, average ticket and stop factor (how readily passers-by stop in: high for a quick coffee, lower for a meal) live in `config/market/*.php`.
- Opening for a day part with little local demand should cost more in wages and utilities than it brings in. Every open hour needs at least `staff.min_on_shift` people on the floor; hours the owner and staff can't cover are paid as part-time cover at the hourly staff cost.
- Which day parts a licence allows is a rule the engine enforces, not something the DTO checks.

**Balance tests** (Pest, using fixed seeds; rivals at the distances measured on the real surface, see `docs/balance-report.md`):

- An average business, played with average decisions, pays its owner and survives year 1 (calibrated to real closure rates: 20–25% of new cafés and bars close within 12 months, INE/DIRCE).
- A great location with bad management loses money.
- A mediocre location with good management survives.
- Raising prices 50% above the local average makes revenue fall within three months.
- No single random event bankrupts a player with more than €5k of cash on hand.

## 7. Parameter sheet — `config/market/zaragoza_cafe.php`

> ⚠️ **Every value below is a PLACEHOLDER to be replaced with researched numbers.** Mark each with its source once verified.

| parameter | placeholder | to verify against |
|---|---|---|
| Rent €/month: P10 / P25 / median / P75 / P90 | 450 / 600 / 900 / 1,500 / 2,500 | Zaragoza listings sample and market tiers, Oct 2026 (aggregated) |
| Traspaso €: P10 / P25 / median / P75 / P90 | 12k / 20k / 40k / 70k / 110k | same sample |
| Floor area m² | 25–220, median 60 | Zaragoza listings sample, Oct 2026 (aggregated) |
| Seats per m² (indoor) | ~0.5 | same sample |
| Average ticket € (café) | 3.50–5.00 | own observation / menus |
| Average ticket € (café-bar, evenings) | 6–10 | own observation |
| Day part hour spans | morning 7–12, lunch 12–16, afternoon 16–20, evening 20–24, night 0–3 | typical Zaragoza trading hours |
| Demand mix per day part (weight of each neighbourhood index) | see §6 Day parts | own observation / footfall counts |
| Footfall model: component weights, POI type weights, decay radius | see §8 Footfall estimate | manual pedestrian counts |
| Average ticket by day part | morning ≈ café ticket, evening/night ≈ café-bar ticket | own observation / menus |
| Day parts allowed per licence | `night` only with `cafe_bar` / `bar_musical` | Zaragoza licensing ordinance (opening-hours rules) |
| COGS % of revenue | 28–35% | hospitality benchmarks |
| IVA (hostelería) | 10% | AEAT |
| Employee gross/month (full-time, 14 pays) | ~SMI–€1,500 | current SMI / hostelería collective agreement for Zaragoza |
| Employer social security | ~31–32% of gross | Seguridad Social |
| Cuota de autónomo | income-linked bands | Seguridad Social tables |
| Utilities €/month | 250–500 | supplier estimates |
| Insurance €/month | 30–60 | quotes |
| Terrace fee (tasa de veladores) | per table/season | Zaragoza ordenanza fiscal |
| Seasonality multipliers (Jan…Dec) | e.g. 0.85 … 1.15 | Zaragoza climate + Pilar festival in October |
| Terrace usable-days by month | from climate normals | AEMET |

## 8. Data sources & licensing

| source | use | licence / condition |
|---|---|---|
| OpenStreetMap (Geofabrik extract / Overpass) | streets, POIs, competitors, tiles | ODbL; show attribution; keep OSM-derived data in its own tables |
| Catastro | building and parcel characteristics | free reuse with attribution; **no owner data** |
| INE / Zaragoza open data | population, demographics, neighbourhoods | open licences; attribute |
| AEMET | climate normals | open data; attribute |
| Property portals | **manual research only**, turned into aggregate distributions | no scraping, no copying text/photos, nothing stored per listing |
| Google Maps / Places | not used in the MVP | if added later: runtime display only, store only Place IDs |
| Manual pedestrian counts | calibrating the footfall model | own research; store only location, time, day part and count |

**Geo data is fetched locally and committed** to `database/seeders/geo/`. Cloud environments may not have network access to Overpass or Catastro.

### Footfall estimate

There is no open pedestrian-count data for Zaragoza, so footfall is estimated from open-data proxies, calibrated against a small set of manual counts. It is computed once, offline, by artisan commands run locally (`geo:fetch`, `geo:build`, `geo:calibrate`; settings in `config/geo.php`), and the output is committed to `database/seeders/geo/zaragoza/`. The app never computes it at runtime.

For each street point, four component scores are worked out, each normalised to 0–1 across the city:

| component | what it measures | source |
|---|---|---|
| `poi_score` | shops, cafés, bars, banks, pharmacies, schools, offices… nearby, weighted by type and fading with distance (≈150–300 m) | OSM |
| `centrality_score` | how many short walking routes pass along the street (betweenness on the pedestrian network within ≈800 m); separates main streets from side streets | OSM street network |
| `catchment_score` | residents and workers within a 5–10 minute walk | district population density (INE census sections later), OSM offices |
| `transport_score` | tram and bus stops nearby, tram stops and interchanges weighted higher | OSM, Zaragoza open data (stop boardings, if published) |

- `footfall` is a weighted sum of the components, rescaled to 0–10 so the city's percentiles match the parameter sheet.
- **Per day part:** each POI type carries a timing profile (offices → morning and lunch; schools → morning and afternoon; cafés → morning; restaurants → lunch and evening; bars, pubs and nightclubs → evening and night; shops → morning and afternoon, closed at lunch and night; universities → daytime), so `poi_score`, and therefore footfall, is also computed per day part. Each day part also weights the components its own way: what's open nearby counts for more later in the day (70% at night) and transport more in the morning. This replaces the neighbourhood-level demand mix.
- **Calibration:** count pedestrians for 10 minutes at 15–20 varied spots, at two or three times of day. Tune the weights so the model ranks those spots the same way the counts do (check with a rank correlation), and commit the counts with the script.
- All weights, radii and timing profiles live in `config/market/*.php` and in the script's committed settings, marked as placeholders until calibrated.
- **Licence:** the surface is an OSM-derived database under the ODbL. Keep it in its own files and table, attribute OpenStreetMap wherever it appears, and expect to share it under the ODbL if distributed.

## 9. Milestones (stage one, done)

Each one is sized to be a single cloud session.

1. **Scaffold**: Laravel + React starter kit, Pest, SQLite, folder structure from §4, `SeededRng`, and the DTOs.
2. **Market config + generator**: `config/market/zaragoza_cafe.php` and a `BusinessGenerator` that draws from percentiles, with tests that the output distributions match the config.
3. **Engine core**: steps 1–6 and 8 of §6, with unit tests per step and the first balance tests.
4. **Events + competitors**: steps 7 and 9; an event library of about 15 events; events that offer choices.
5. **Game flow**: create a game, browse businesses, buy one, set decisions, advance the month, end of game, bankruptcy.
6. **UI**: business browser with filters, monthly decisions screen, P&L, cash-flow chart.
7. **Map**: Leaflet map, business markers, POI layer, neighbourhood overlays, OSM attribution.
8. **Real geo data and footfall**: the offline footfall script (§8 Footfall estimate) and its calibration counts; import the committed OSM/INE extracts and the footfall surface; derive the neighbourhood indices; place generated businesses on commercial street points and read their footfall (overall and per day part) from the surface, replacing the street-type bonus and noise.
9. **Balancing pass**: run 1,000 simulated games with scripted strategies and report the outcome distributions (`php artisan market:balance`; results in `docs/balance-report.md`).

## 10. Open questions

- Single-player only, or a shared market (players competing for the same listings) later?
- Should the player be able to own more than one business at once? (Stage two: one at a time; selling and buying again comes later.)
- Is there a start-up loan option, and on what terms?
- ~~How long is a game in real time?~~ Settled in §11: real time, one game day per real day, no fixed end.
- Paid capital tiers: exact amounts and prices above the free €30k.
- Later: Spain-wide viability for other cities and business types (the engine and geo pipeline allow it; each needs its own researched parameter sheet).

## 11. Stage two: real time, the app and the viability check

Stage one built a turn-based 12-month game. Stage two turns it into a café that runs in real time on the player's phone, and adds a standalone, paid viability check. The realism rules stay: every number in a parameter sheet, calibrated against real data (`docs/balance-report.md`), checked with `market:balance`.

### Real time

- **One game day per real day.** A game starts on today's date and the café trades every day it is open. The calendar is the real one, so seasonality, August and the Pilar arrive when they really do.
- **No fixed end.** The café runs until the player goes bankrupt or (in a later stage) sells it. Net worth is shown at all times. Selling and buying another business comes later.
- **The nightly run, 23:00 Europe/Madrid.** The server simulates the day for every active café, stores the result and sends one push notification with the day's numbers and anything notable (an event, a rival's move, a record day).
- **Decisions apply from the next day.** Prices, opening hours and marketing change overnight. Some changes have lead times set in the parameter sheet: hiring and letting staff go (notice), a terrace permit (weeks).
- **Month end.** Rent, wages, the owner's pay and the other monthly costs go out on the last day of the month, with a monthly P&L. Bankruptcy is checked then: cash below zero after the month's bills ends the game.
- **Unanswered events** take their default choice after a deadline (a few days, per event). A player who doesn't open the app still has a café that trades on its current settings.
- **Catch-up.** If a nightly run is missed, the next one plays the missing days in order. Runs are idempotent: a day is simulated once.

### Daily simulation

- The monthly engine is split into days. Each day uses the same steps with that day's share of the month, plus a **day-of-week pattern** (busier Fridays and Saturdays, quieter Mondays, by day part) and **day-to-day weather** (rain empties terraces, heat changes the afternoon), from the parameter sheet.
- **Monthly totals stay calibrated:** over a month, the days add up to what the monthly engine produced, so stage one's calibration carries over. Tests check this with fixed seeds.
- Events roll daily with their monthly odds spread over the days. State (reputation, morale, equipment) moves daily in smaller steps.

### Capital and payment

- A new game starts with **up to €30,000 free**: enough for most low-tier and some mid-tier traspasos.
- **More is the player's savings, and it is paid for:** paid tiers raise the starting capital above €30k (amounts and prices to be decided), opening up bigger premises, kitchens, terraces and busier streets. In the game it is simply the owner's savings.
- In a future shared market, paid tiers would need separate leagues.
- Payments go through the web (Stripe Checkout), with IVA on digital services, receipts, and the 14-day withdrawal rules for digital content.

### The app

- A **PWA**: installable, works on phones first, opens on the latest day's results.
- **Web push** for the nightly summary and for events that need an answer. Players choose what they're notified about. On iPhone, push needs the app added to the home screen; the app explains this.

### The viability check (standalone, paid)

A separate product from the game: no game account needed.

- **Input:** the location picked on the map (no address search: geocoding would fetch geo data at runtime, which §8 rules out), plus the listing's traspaso, rent, floor area, seats, kitchen and terrace, and how the owner would run it.
- **Simulation:** about 1,000 five-year futures of that café on the calibrated engine, with real rivals and footfall at that spot.
- **Report:** the chance it is still open after 1, 3 and 5 years; the owner's income each year (median and range); when the traspaso is earned back; the main risks; how it compares with the district. Assumptions shown; clearly labelled a simulation, not financial advice.
- **Free preview** (the 1-year survival chance); the full 5-year report is paid.
- **Calibration:** a typical new owner must survive 5 years 45–50% of the time (INE/DIRCE; Hostelería de España: 50–55% of new food and drink businesses close within 5 years), as well as 1 year 75–80% of the time.

### Data model additions

- `games`: `started_on`, `last_simulated_on` (date), no fixed `months`.
- `day_results`: `game_id, date, customers, revenue_cents, day_parts (json), weather, events (json)`.
- `month_results` stays, written at month end.
- `push_subscriptions`: per user and device.
- `purchases` / `entitlements`: what each account has paid for (capital tier, reports).
- `viability_reports`: inputs, status, results, and whether it's paid.

### Milestones (stage two)

10. **Multi-year engine and 5-year calibration** (done): games and simulations beyond 12 months; `market:balance --years=5`; the 5-year survival target holds alongside the 1-year one. A café closes when its cash runs out or after a year that didn't pay its owner; each spot's custom drifts over the years (`demand.local_trend`). See `docs/balance-report.md`.
11. **Daily simulation** (done): `DayEngine` trades day by day on the real calendar, with day-of-week patterns per day part, public holidays (traded as Sundays), the Pilar week, day-to-day weather (AEMET rain days; rain closes the terrace) and daily noise; takings and stock daily, the other bills at month end; events daily with their monthly odds spread over the days. Each day's weights are normalised over its month, so the days add up to the monthly engine's demand; covers come out 1–2% lower because each day hits its own seat and staff limit. The turn-based game now plays each month day by day and stores `day_results`; `market:balance --daily` checks the targets on the daily engine. See `docs/balance-report.md`.
12. **Real-time clock** (done): `game:nightly` is scheduled at 23:00 Madrid (`config/game.php`) and queues a `SimulateGameDays` job per active café; `SimulateDays` plays every day not yet played (catch-up), one locked transaction per day (idempotent), settling the month on its last day. The café trades from the day after purchase; a first month traded in part pays its fixed bills and the owner's pay pro rata. Decisions apply from the next day; hiring waits `staff.hire_lead_days` (7), letting go `staff.notice_days` (15). Events wait `events.deadline_days` (3) for an answer, then take their default. `game.months` is null (no fixed end). Fast-forward to month end stays for trying the game out (`GAME_FAST_FORWARD`, off in production). No terrace decision exists yet, so its permit lead time waits for one.
13. **PWA and push** (done): web app manifest, icons and a service worker (push only, no offline cache); the installed app opens on `/games/latest`. Web Push with VAPID keys (`php artisan webpush:vapid`, `WEBPUSH_*` in `.env`; without them nothing is sent): after each nightly run that plays a day, one notification per device with the latest day's results, the month's profit at month end, and events waiting for a decision. Players choose daily results and/or events in Settings → Notifications (bankruptcy always gets through), turn push on per device, and get install help (iPhone needs the home screen). Expired devices are dropped. The game opens on a phone-sized "latest day" card.
14. **Viability check** (done): `/viability`, open to anyone. A map pin (snapped to the nearest commercial street point within 150 m) and the listing's figures, the user's money and how they'd run it; `RunViabilityCheck` plays 1,000 five-year futures of that café on the queue (about 30 s), with the user's plan held fixed, repairs when affordable, and the real cafés and bars nearby as rivals (one at the pin is taken to be the café itself). The report: free preview (1-year survival, the spot's footfall and rivals); full report (survival at 1, 3 and 5 years, yearly profit before the owner's pay with an 8-in-10 range, months to earn back the traspaso, flagged risks) once `paid_at` is set, which milestone 15's payments will do. `VIABILITY_UNLOCK_ALL=true` shows the full report for local testing.
15. **Payments and accounts** (done): one-off Stripe Checkout payments (`app/Payments`, behind a `PaymentGateway` interface) for the full viability report (no account needed) and for capital tiers (€60k and €100k of savings, tied to the account; up to €30k stays free). Each payment is a `purchases` row, fulfilled once (idempotent) by the `/stripe/webhook` or when the buyer returns from Checkout. Prices include IVA (an optional Stripe tax rate shows it on the receipt) and Stripe issues an invoice. Buyers must tick a box agreeing that the digital content starts at once and they lose the 14-day withdrawal right (RDL 1/2007 art. 103 m). Terms, privacy and disclaimer pages are drafts with [placeholders]. Prices in `config/payments.php` are placeholders.
16. **Going live** (done): `docs/deploy.md` sets up one EU VPS (nginx, PHP-FPM, SQLite in WAL mode, the database queue) with the configs in `deploy/` (nginx, supervisor for the queue worker, the scheduler's crontab, `deploy.sh`). Monitoring: `/up` and `app:health` fail when the database is down, a queued job waits over 30 minutes, a job failed in the last day, or a café missed a nightly run; an optional heartbeat URL is pinged after each nightly run. Backups: `app:backup` (nightly, 04:00 Madrid) keeps 14 days of compressed SQLite copies; copying them off the server is up to the host. GDPR: a JSON download of the user's data in Settings, account deletion (purchase records kept without the user, for tax law), unpaid reports pruned after 90 days and abandoned checkouts after 30. Production forces HTTPS, trusts proxies from `TRUSTED_PROXIES`, and seeds no test user.

## 12. Stage three: selling and buying

Stage two's café runs until it goes bankrupt. Stage three lets the owner get out the way real owners do, by selling the traspaso or closing, and then buy another café with what they have left. One game becomes an owner's career: a run of cafés, one at a time, with net worth across all of them. The realism rules stay. A sale takes months, costs money, and fetches what a buyer would really pay for the books the café shows.

### Selling: a listing, not a button

- **List the café** at an asking price. It keeps trading while it's listed: rent, staff and the nightly results all carry on. The player can change the price or withdraw the listing at any time.
- **Buyers look at the books.** Each buyer values the café with `BusinessValuation`: location and licence, fixtures worn with the equipment, and goodwill from the last 12 months' profit after the owner's pay and from reputation. A café run down before the sale is worth less.
- **Offers arrive over time.** Interested buyers arrive at random, at a rate that falls as the asking price rises above what buyers think it's worth, and slows in August and over Christmas. Each buyer offers below asking, around their own valuation. All of these rates come from the parameter sheet.
- **Answering an offer:** accept, reject, or counter once. An offer lapses after a few days, like an event. Offers come in as push notifications.
- **From acceptance to handover**, the café keeps trading for a handover period (a few weeks: gestoría, the landlord's paperwork). At completion the price arrives as cash, less the costs of the sale, and the deposit (fianza) comes back.
- **The landlord:** under LAU art. 32 a tenant can assign a business lease without the landlord's consent (the landlord may raise the rent by 20%), but most commercial leases agree their own terms (art. 4). The landlord's side only shows up in the time and costs of the sale. *To confirm.*
- **Costs of the sale:** an agency commission if the player chooses an agency (more buyers, and it costs a share of the price), gestoría fees, and income tax on the gain (IRPF savings scale on the sale price less what was paid, simplified). IVA: selling a whole going business is not subject to IVA (LIVA art. 7.1). Any other tax on the deal is *to confirm with a gestor*.

### Closing down

- **Close** instead of selling: stop trading, pay the lease's notice period (or the break penalty), sell the equipment at scrap value, and lose the traspaso. The deposit comes back, less any damage.
- **A quick sale** for a café in trouble: a lowball offer from a buyer of last resort, completed fast. It's the realistic way out before bankruptcy. Bankruptcy stays for cafés whose cash runs out first.

### Buying again

- **A career, not a new game.** After a sale or a closure the owner has cash and no café: no income and no owner's pay. They buy the next café in the same game, with what they have. The €30k free cap and paid tiers apply only when a game starts. Money made in the game is the owner's to use.
- **A living market.** Listings come and go in real time. New ones appear every week, drawn from the same calibrated distributions, and others sell to someone else and disappear. The market the player sees is today's, not the one from when the game started.
- **The café sold stays on the map**, now run by its new owner as a rival if the player buys nearby.
- **A career page:** each café the owner ran, with what they paid, how long they ran it, its profit, and what it sold for. Net worth over the whole career.

### Data needed (aggregated only, per §8)

- **Time to sell** for Zaragoza café and bar traspasos: how long listings stay up, even as rough tiers. Without data this is a *guess*.
- **Asking vs agreed price**: the usual discount. *Guess* without data.
- **Agency commission** for traspasos (a share or a flat fee), and the gestoría costs of the handover.
- **Lease notice and break terms** in typical Zaragoza commercial leases.
- **How many new café and bar listings appear a month** in Zaragoza, for the living market.
- **Valuation:** calibrate `BusinessValuation` (still a PLACEHOLDER) against the aggregated listings. A newly listed café should be worth about what it's listed for.

### Data model additions

- `businesses`: listing dates (`listed_on`, `delisted_on`) for the living market, and a status for cafés sold or closed by the player.
- `games`: `previous_game_id` chains the cafés of a career (one café per game, so results stay apart), `market_refreshed_on` for the living market.
- `sale_listings`: `game_id, business_id, asking_cents, with_agency, listed_on, withdrawn_on, accepted_on, completes_on`.
- `sale_offers`: `sale_listing_id, buyer, amount_cents, made_on, expires_on, status` (open, accepted, rejected, countered, lapsed), `counter_cents`.

### Milestones (stage three)

17. **Valuation calibration** (done): `market:balance` values every café at each year end and prints the value ÷ the traspaso paid, with a target: a typical owner's café sells after a year for 85–100% of its traspaso (listings are asking prices; buyers agree a little below). Fitted: location share 0.4, goodwill 0.4 years of profit after the owner's pay. Typical owner 0.93 at year 1, 1.0 at years 3 and 5; the closure targets still hold (`docs/balance-report.md`).
18. **Selling** (done): `app/Simulation/Sale/` (`BuyerMarket`, `SaleCosts`; parameters in the sheet's `sale` section, all guesses). The Sell tab lists the café at an asking price, privately or through an agency; the nightly run brings buyers, lapses offers after 5 days and has buyers answer counter-offers (taken if within their hidden limit, else they walk). An accepted sale completes on the last day of the month after a 30-day handover, after that month's bills: the price less commission, gestoría and IRPF on the gain, plus the deposit, become cash and the game ends (buying again is milestone 20). Offers and the completion come as push notifications. Net worth counts the café at what a private sale would leave after costs and tax. Tables `sale_listings`, `sale_offers`.
19. **Closing down and quick sale** (done): `Closure` in `app/Simulation/Sale/`, parameters in the sheet's `closure` and `quick_sale` sections. From the Sell tab, either takes effect on the current month's last day, after its bills, withdrawing any listing. **Close down** (can be called off until then): two months' rent for the lease notice (guess), severance of 20 days' pay per year worked (ET art. 53.1.b, with 2 years' inherited seniority, a guess; ET art. 44), the equipment sold for 20% of the fixtures' value (guess), the deposit back, the traspaso lost. **Quick sale**: a buyer of last resort pays half the café's value (guess), never less than scrap, agreed at once and taxed like any sale. Both end the game until milestone 20; both come as push notifications.
20. **Buying again** (done): a career is a chain of games, one café each (`games.previous_game_id`), which keeps each café's results apart without re-keying them. After a sale or closure, "Buy another café" starts the next game with the cash left (the free cap and paid tiers apply only to a new career), in a market generated for that day; the café sold on joins it as a `taken` business that can be picked as a rival. While the player is choosing, the market changes week by week (`market_churn`, guesses): each listing sells elsewhere with 8% chance a week (it stays as a possible rival), and about 12 new ones appear. A career table on the game page lists every café: traspaso, months, profit before the owner's pay, outcome, and net worth against the first starting capital.
21. **Balance and resale in the viability check** (done): `market:balance` closes a café that didn't pay its owner the better of a quick sale and closing down; adds a flipper (sell every year, buy again with everything, paying the haggle, 2 months between cafés and €1,800 buying costs, all guesses in `changing_cafe`); and two targets: a typical owner selling after a year loses 5–25% of the traspaso (11%), and flipping doesn't beat holding for a typical owner (+46% vs +85% over 5 years). A thoughtful flipper gains a little (+192% vs +173%) from buying underpriced cafés: reported, not targeted. The viability report adds the year-5 resale value (before and after costs and tax) and the owner's total return across all futures.

### Decisions (settled)

- **Selling is a listing that takes months**, not an instant sale at the valuation.
- **The agency is optional.** Listing through an agency costs commission and brings more buyers; selling privately is cheaper and slower.
- **Income tax on the gain is included.** It's paid at completion. Net worth counts the café at what the owner would walk away with: the valuation less typical sale costs and the tax a sale would trigger.
- **The viability check reports resale value:** what the café would likely sell for after 5 years (median and 8-in-10 range, after costs and tax), and the owner's total return including it. It's added in milestone 21, once the valuation is calibrated.
