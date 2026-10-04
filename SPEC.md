# Business Simulator — MVP Spec

> Working title. A web-based economic strategy game: take over a small business in a real Spanish city and try to make it work.

## 1. Premise

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
| competition_density | [real→derived] | OSM `amenity=cafe|bar` count per km² |

### points_of_interest
`id, type (university|school|station|park|office|competitor_seed…), name, lat, lng, osm_id` — [real], imported from OSM extracts.

### footfall_points
A precomputed footfall surface, sampled at points along streets (about every 25 m), [real→derived]:
`id, lat, lng, osm_way_id, neighbourhood_id, poi_score, centrality_score, catchment_score, transport_score, footfall 0–10, footfall_<day part> 0–10 (one per day part), commercial (bool)`.
`commercial` marks points with shops or hospitality on the street nearby; businesses are only placed on those.

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
                             insurance, maintenance
7. Events                  roll each event's probability; apply its effects
8. State evolution         reputation moves toward (quality − price gap + service);
                             equipment wears; staff morale reacts to workload
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
- Each day part's hour span (for staff and utility costs), demand mix and average ticket live in `config/market/*.php`.
- Opening for a day part with little local demand should cost more in wages and utilities than it brings in.
- Which day parts a licence allows is a rule the engine enforces, not something the DTO checks.

**Balance tests** (Pest, using fixed seeds):

- An average business, played with average decisions, ends year 1 between −10% and +25%.
- A great location with bad management loses money.
- A mediocre location with good management survives.
- Raising prices 50% above the local average makes revenue fall within three months.
- No single random event bankrupts a player with more than €5k of cash on hand.

## 7. Parameter sheet — `config/market/zaragoza_cafe.php`

> ⚠️ **Every value below is a PLACEHOLDER to be replaced with researched numbers.** Mark each with its source once verified.

| parameter | placeholder | to verify against |
|---|---|---|
| Rent €/month: P10 / P25 / median / P75 / P90 | 500 / 600 / 725 / 900 / 1,150 | manual sample of ~100 listings |
| Traspaso €: P10 / P25 / median / P75 / P90 | 8k / 12k / 18k / 27k / 45k | same sample |
| Floor area m² | 35–90, median 55 | same sample |
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

There is no open pedestrian-count data for Zaragoza, so footfall is estimated from open-data proxies, calibrated against a small set of manual counts. It is computed once, offline, by a script run locally (e.g. Python with `osmnx`/`networkx`), and the output is committed to `database/seeders/geo/`. The app never computes it at runtime.

For each street point, four component scores are worked out, each normalised to 0–1 across the city:

| component | what it measures | source |
|---|---|---|
| `poi_score` | shops, cafés, bars, banks, pharmacies, schools, offices… nearby, weighted by type and fading with distance (≈150–300 m) | OSM |
| `centrality_score` | how many short walking routes pass along the street (betweenness on the pedestrian network within ≈800 m); separates main streets from side streets | OSM street network |
| `catchment_score` | residents and workers within a 5–10 minute walk | INE census sections, Catastro / OSM offices |
| `transport_score` | tram and bus stops nearby, tram stops and interchanges weighted higher | OSM, Zaragoza open data (stop boardings, if published) |

- `footfall` is a weighted sum of the components, rescaled to 0–10 so the city's percentiles match the parameter sheet.
- **Per day part:** each POI type carries a timing profile (offices → morning and lunch; schools → morning and afternoon; bars → evening and night; universities → afternoon), so `poi_score`, and therefore footfall, is also computed per day part. This replaces the neighbourhood-level demand mix.
- **Calibration:** count pedestrians for 10 minutes at 15–20 varied spots, at two or three times of day. Tune the weights so the model ranks those spots the same way the counts do (check with a rank correlation), and commit the counts with the script.
- All weights, radii and timing profiles live in `config/market/*.php` and in the script's committed settings, marked as placeholders until calibrated.
- **Licence:** the surface is an OSM-derived database under the ODbL. Keep it in its own files and table, attribute OpenStreetMap wherever it appears, and expect to share it under the ODbL if distributed.

## 9. Milestones

Each one is sized to be a single cloud session.

1. **Scaffold**: Laravel + React starter kit, Pest, SQLite, folder structure from §4, `SeededRng`, and the DTOs.
2. **Market config + generator**: `config/market/zaragoza_cafe.php` and a `BusinessGenerator` that draws from percentiles, with tests that the output distributions match the config.
3. **Engine core**: steps 1–6 and 8 of §6, with unit tests per step and the first balance tests.
4. **Events + competitors**: steps 7 and 9; an event library of about 15 events; events that offer choices.
5. **Game flow**: create a game, browse businesses, buy one, set decisions, advance the month, end of game, bankruptcy.
6. **UI**: business browser with filters, monthly decisions screen, P&L, cash-flow chart.
7. **Map**: Leaflet map, business markers, POI layer, neighbourhood overlays, OSM attribution.
8. **Real geo data and footfall**: the offline footfall script (§8 Footfall estimate) and its calibration counts; import the committed OSM/INE extracts and the footfall surface; derive the neighbourhood indices; place generated businesses on commercial street points and read their footfall (overall and per day part) from the surface, replacing the street-type bonus and noise.
9. **Balancing pass**: run 1,000 simulated games with scripted strategies and report the outcome distributions.

## 10. Open questions

- Single-player only, or a shared market (players competing for the same listings) later?
- Should the player be able to own more than one business in the MVP?
- Is there a start-up loan option, and on what terms?
- How long is a game in real time: one sitting, or a turn per day?
