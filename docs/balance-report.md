# Balancing pass (milestone 9)

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

Data: the committed Zaragoza build (15 districts, 2020 padrón populations,
7,013 commercial street points, 1,129 cafés and bars). Code and config as of
this pass.

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
| thoughtful | −19% | −4% | **+20%** | +50% | +87% | 71% | 1.1% | €900 |
| default | −43% | −22% | **−5%** | +17% | +39% | 43% | 4.3% | €161 |
| cheapest | −85% | −56% | **−36%** | −26% | −15% | 2% | 8.6% | −€1,813 |
| premium | −60% | −20% | **+22%** | +77% | +135% | 62% | 9.9% | €1,403 |
| careless | −118% | −112% | **−107%** | −102% | −99% | 0% | 100% | −€9,990 |

### Targets

| Target | Actual | |
|---|---|---|
| Thoughtful player: median year between +5% and +25% | +20% | pass |
| Thoughtful player: at least 60% end up ahead | 71% | pass |
| Thoughtful player: under 5% go bankrupt | 1.1% | pass |
| Thoughtful player: no district (10+ games) with a median below −10% | −1% (worst) | pass |
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
