# Data requests (milestones 24 and 25)

What would replace the remaining guesses in `config/market/zaragoza_cafe.php`,
and how to gather it. Everything goes in as **aggregates**: ranges, tiers,
medians or percentiles. The repo never stores single listings, streets,
businesses or prices (see CLAUDE.md, Data rules).

For every figure, note:

- **the date** (month and year) it was gathered;
- **how many** listings, places or people it's based on (n);
- **the kind of source**: portal (looked at by hand), agency, gestoría,
  lawyer, landlord, café owner, your own count. No names needed.

A rough figure with an n of 10 beats a guess. Say when something is a
hunch rather than seen.

---

## Milestone 24: selling, buying and leases

### 1. How long café and bar traspasos stay listed

- **What:** days from a listing first appearing to it going (sold or
  withdrawn), for café and bar traspasos in Zaragoza.
- **Form:** median, and the 25th and 75th percentiles, or tiers ("a third
  go within 2 months, half within 4, a fifth still up after a year").
  Split by price tier (low / mid / prime, as in the balance report) if you
  can.
- **How:** note the listings you see on one day (count only, plus a private
  note of which they are, kept off the repo). Look again every two weeks for
  two or three months and count how many have gone. An agency can also give a
  typical time to sell.
- **Replaces:** `sale.buyers_per_month` (private 0.4, agency 1.0) and
  `sale.price_sensitivity` (3.0). Today a café asked at its value gets a first
  offer after ~53 days privately and ~21 through an agency.

### 2. How many new listings appear, and how many are up at once

- **What:** new café and bar traspaso listings in Zaragoza per week or month,
  and the total listed on one day.
- **Form:** two numbers (e.g. "about 40 listed; 8–12 new a month"), and the
  same for the low / mid / prime tiers if easy.
- **How:** the same two-weekly look as above: count the new ones each time.
- **Replaces:** `market_churn.new_per_week` (12) and `taken_per_week` (8%),
  and checks `business_count` (100–200 listings in the game's market).

### 3. Asking price against agreed price

- **What:** how far below the asking price traspasos actually sell.
- **Form:** a typical discount and a range ("usually 10–20% off; overpriced
  ones 30%+"), by tier if possible.
- **How:** agencies and gestorías that handle traspasos; owners who have sold.
- **Replaces:** `sale.opening_discount` (buyers open 10% below their limit),
  `sale.buyer_value_sd` (15%) and `sale.min_offer_share` (60%); and checks
  the valuation's resale target (a typical café sells for 85–100% of its
  traspaso after a year).

### 4. Agency fees

- **What:** what an agency charges to sell a traspaso, and who pays it.
- **Form:** share of the price and any minimum ("5% + IVA, minimum €3,000,
  paid by the seller"); some charge the buyer too.
- **How:** ask two or three agencies, or read their published terms.
- **Replaces:** `sale.agency_commission_share` (8%) and
  `agency_commission_min_cents` (€3,000).

### 5. Lease terms for cafés and bars

- **What:** in typical Zaragoza commercial leases (local de negocio):
  - **notice or break penalty** if the tenant leaves early;
  - **extra guarantee** on top of the legal 2-month deposit (months of rent,
    cash or bank aval);
  - **rent rise** when the lease passes to a traspaso buyer (LAU art. 32
    allows 20%), and how often landlords use it or sign a new lease instead;
  - typical **length** and yearly update (IPC).
- **Form:** the usual case and the range ("notice 2–3 months; most ask 2
  extra months as an aval").
- **How:** a lawyer, gestoría or agency; a few lease ads that state terms.
- **Replaces:** `closure.notice_months_of_rent` (2) and
  `purchase.guarantee_months_of_rent` (2); the rent rise isn't modelled yet.

### 6. The costs of buying

- **What:** the buyer's lawyer or gestoría for a traspaso, and the técnico's
  report for the licence's change of holder.
- **Form:** typical fee and range ("€600–1,200 flat"; "report €300–600").
- **How:** quotes from two or three gestorías or lawyers.
- **Replaces:** `purchase.legal_fees` (€800 + 1% of the traspaso) and
  `purchase.licence_change.technical_report_cents` (€400).

### 7. Getting out without a buyer

- **What:** (a) what used café equipment fetches when a place closes; (b)
  what a café in trouble sells for if the owner needs out fast.
- **Form:** (a) a share of what the fit-out cost, or euros for a typical
  small café; (b) a share of the asking price ("half, or the value of the
  equipment").
- **How:** second-hand hostelería equipment dealers; agencies.
- **Replaces:** `closure.scrap_share_of_fixtures` (20%) and
  `quick_sale.share_of_value` (50%).

---

## Milestone 25: prices and how busy cafés are

Gathered at the same time if convenient; used in milestone 25.

### 8. What cafés charge

- **What:** prices at ordinary neighbourhood cafés and bars against
  speciality or upmarket cafés: café solo, café con leche, a breakfast
  (coffee + tostada or croissant), a caña, a lunchtime menú if they do one.
- **Form:** a range per kind of café ("ordinary: café con leche €1.40–1.70;
  speciality: €2.50–3.20"), with n.
- **How:** menus on the wall or online, a walk round two or three areas.
- **Replaces:** `average_ticket_cents`, `ticket_position.tier`, and the
  premium-quality settings.

### 9. How busy they are

- **What:** for the same two kinds of café, how full they are at a few
  times (weekday 9:00, 13:30, 17:30; Saturday midday).
- **Form:** rough occupancy ("ordinary: half the tables at 9:00; speciality:
  a queue at 9:00, a third full at 17:30"), with n.
- **How:** walking past; your own observation is fine, say so.
- **Replaces:** `capture.price_elasticity` (0.7) and `capture.quality`: how
  many customers a dearer, better café loses or wins.

---

## Milestone 26: pedestrian counts

Use the counting page (**Pedestrian counts** in the sidebar, `/counts`) on
your phone: pin the spot (or "Use my location"), start the 10-minute timer
and tap once for every person who walks past. Aim for 15–20 varied spots on
shopping streets (busy and quiet, centre and neighbourhoods), each at two or
three times of day (morning, lunch, afternoon). When you're done, run
`php artisan geo:counts-export`, commit
`database/seeders/geo/zaragoza/sources/pedestrian_counts.csv`, and tell me:
I'll run `geo:calibrate --fit` and tune the footfall weights.

## Template

Copy, fill in what you have, leave the rest blank, and paste it back.

```
Gathered: <month year>

1. Time listed (n=__, source: __): median __ days; p25 __; p75 __
   by tier (optional): low __ / mid __ / prime __
2. Listed on one day: __ (n/a); new per month: __ (over __ weeks)
3. Agreed vs asking (n=__, source: __): typical __% below; range __–__%
4. Agency fee (n=__): __% (+IVA?), minimum €__, paid by __
5. Lease: notice/break __ months; extra guarantee __ months (cash/aval);
   rent rise on traspaso: __; length __ years
6. Buying: lawyer/gestoría €__–__; técnico report €__–__
7. Equipment resale: __% of fit-out (or €__ for a small café);
   fast sale: __% of asking
8. Prices (n=__ ordinary, __ speciality):
   ordinary: solo __, con leche __, breakfast __, caña __, menú __
   speciality: solo __, con leche __, breakfast __, caña __, menú __
9. Busyness (n=__): ordinary __ ; speciality __
```
